<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Broadcaster;

use Cake\Datasource\EntityInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidChannelException;
use Crustum\Broadcasting\Mercure\ChannelEncrypter;
use Crustum\Broadcasting\Mercure\MercureHubFactory;
use Crustum\Broadcasting\Trait\PusherChannelConventionsTrait;
use DateTimeImmutable;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Mercure Broadcaster
 *
 * Optional core driver for Server-Sent Events broadcasting through a Mercure hub.
 * Every joined topic is multiplexed over one EventSource guarded by a single
 * authorization cookie, so `auth()` mints one token covering all currently
 * joined channels and returns it as a cookie on a JSON response. Public
 * channels need no grant: delivery is gated by the update's `private` flag.
 * Requires `symfony/mercure`; end-to-end encrypted channels additionally
 * require `web-token/jwt-library`.
 *
 * Do not route the hub's authorization cookie through encrypted-cookie
 * middleware: encrypting the raw JWT produces a value the hub can never
 * verify.
 */
class MercureBroadcaster extends BaseBroadcaster
{
    use PusherChannelConventionsTrait;

    /**
     * @var \Symfony\Component\Mercure\HubInterface Mercure hub used for publishing and token minting
     */
    protected HubInterface $hub;

    /**
     * Create a new broadcaster instance.
     *
     * Resolves the Mercure hub from the connection config, validates the
     * cookie path, and stores the subscriber token lifetime, topic prefix,
     * client-event flag, and optional encryption key for later use.
     *
     * @param array<string, mixed> $config Connection config, see `MercureHubFactory::hub()`
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException When the Mercure
     *   library is missing or the hub cannot be built from the config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!interface_exists(HubInterface::class)) {
            throw new BroadcastingException(
                'symfony/mercure is required to use the Mercure broadcast driver. ' .
                'You may install it via: composer require symfony/mercure',
                500,
            );
        }

        $hub = MercureHubFactory::hub($config);
        MercureHubFactory::assertCookiePath($hub);

        if (!$hub->getFactory() instanceof TokenFactoryInterface) {
            throw new BroadcastingException(
                'The Mercure broadcasting connection requires a "secret" (or "subscribe_secret") configuration value.',
                500,
            );
        }

        $this->hub = $hub;
        $this->config['subscribe_expiration'] = $config['subscribe_expiration'] ?? 5;
        $this->config['topic_prefix'] = (string)($config['topic_prefix'] ?? null ?: 'https://crustum.cake/echo/');
        $this->config['client_events'] = (bool)($config['client_events'] ?? true);
        if (!array_key_exists('encryption_key', $this->config) && !empty($config['encryption_key'])) {
            $this->config['encryption_key'] = $config['encryption_key'];
        }
    }

    /**
     * Authenticate the incoming request for the requested channels.
     *
     * Reads a `channel_names` array and returns a JSON response carrying one
     * authorization cookie that covers every granted channel. Authorization
     * is per channel: a denied channel is flagged in the response body and
     * left out of the token grants, so revoking one channel mid-session
     * never takes down the others. Each authorized end-to-end encrypted
     * channel's entry carries the JSON Web Key decrypting its updates.
     * Every guarded channel also gets a whisper topic the subscriber may
     * publish to, keeping the channel topics server-only.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming HTTP request
     * @throws \Crustum\Broadcasting\Exception\InvalidChannelException When no channels
     *   are requested, a name is not a string, or more than 100 are requested
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException When an encrypted
     *   channel is used without an encryption key or the cookie cannot be minted
     * @return \Cake\Http\Response JSON response with the authorization cookie attached
     */
    public function auth(ServerRequestInterface $request): Response
    {
        $data = $request->getParsedBody();
        $raw = is_array($data) ? ($data['channel_names'] ?? []) : [];
        if (
            !is_array($raw) || $raw === [] ||
            count($raw) > 100 ||
            $raw !== array_filter($raw, is_string(...))
        ) {
            throw new InvalidChannelException('Access denied.', 403);
        }

        $channelNames = array_values(array_unique(array_filter($raw, fn($c): bool => $c !== '')));
        if ($channelNames === []) {
            throw new InvalidChannelException('Access denied.', 403);
        }

        [$entries, $grants, $user] = $this->authorizeChannels($request, $channelNames);
        $cookie = $this->makeAuthorizationCookie($request, $grants, $user);
        $body = json_encode([
            'channel_names' => $entries,
            'expires_in' => $this->expiration(),
            'topic_prefix' => $this->config['topic_prefix'],
            'client_events' => $this->config['client_events'],
        ], JSON_THROW_ON_ERROR);

        return (new Response())
            ->withType('application/json')
            ->withStringBody($body)
            ->withCookie($this->toCakeCookie($cookie));
    }

    /**
     * Broadcast a message to channels as Mercure updates.
     *
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @return void
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $channels = $this->formatChannels($channels);
        if ($channels === []) {
            return;
        }

        $socket = $payload['socket'] ?? null;
        unset($payload['socket']);

        $publicChannels = [];
        $guardedChannels = [];
        $encryptedChannels = [];

        foreach ($channels as $channel) {
            if ($this->isEncryptedChannel($channel)) {
                if (!$this->encrypter() instanceof ChannelEncrypter) {
                    throw new BroadcastingException(
                        sprintf(
                            'Mercure broadcasting requires an "encryption_key" configuration value to broadcast on the end-to-end encrypted channel [%s].',
                            $channel,
                        ),
                        500,
                    );
                }

                $encryptedChannels[] = $channel;
            } elseif ($this->isGuardedChannel($channel)) {
                $guardedChannels[] = $channel;
            } else {
                $publicChannels[] = $channel;
            }
        }

        try {
            if ($publicChannels !== []) {
                $this->hub->publish(new Update(
                    array_map($this->topic(...), $publicChannels),
                    $this->updateData($publicChannels, $event, $payload, $socket),
                    false,
                ));
            }

            foreach ($guardedChannels as $channel) {
                $this->hub->publish(new Update(
                    [$this->topic($channel)],
                    $this->updateData([$channel], $event, $payload, $socket),
                    true,
                ));
            }

            $plaintext = $encryptedChannels === []
                ? null
                : $this->updateData(null, $event, $payload, $socket);

            foreach ($encryptedChannels as $channel) {
                $encrypter = $this->encrypter();
                if (!$encrypter instanceof ChannelEncrypter) {
                    throw new BroadcastingException(
                        sprintf(
                            'Mercure broadcasting requires an "encryption_key" configuration value to broadcast on the end-to-end encrypted channel [%s].',
                            $channel,
                        ),
                        500,
                    );
                }

                $this->hub->publish(new Update(
                    [$this->topic($channel)],
                    json_encode([
                        'channels' => [$channel],
                        'data' => $encrypter->encrypt((string)$plaintext, $channel),
                    ], JSON_THROW_ON_ERROR),
                    true,
                ));
            }
        } catch (BroadcastingException $exception) {
            throw $exception;
        } catch (JsonException $exception) {
            throw new BroadcastingException(
                sprintf('Mercure error: %s.', $exception->getMessage()),
                500,
                $exception,
            );
        } catch (Throwable $throwable) {
            throw new BroadcastingException(
                sprintf(
                    'Mercure error: %s.',
                    $throwable->getPrevious()?->getMessage() ?? $throwable->getMessage(),
                ),
                500,
                $throwable,
            );
        }
    }

    /**
     * Broadcast multiple personalized messages, grouping items that share
     * the same payload into a single Mercure publish with multiple topics.
     *
     * The Mercure spec allows multiple `topic` parameters per POST, so items
     * with identical event + data + privacy are merged into one Update.
     * Encrypted channels are published individually (each needs its own JWE).
     *
     * @param array<mixed> $broadcasts Event objects or flat broadcast specs
     * @param int $chunkSize Max topics per publish request (default 100)
     * @return void
     */
    public function bulkBroadcast(array $broadcasts, int $chunkSize = 100): void
    {
        $normalized = $this->normalizeBulkBroadcasts($broadcasts);
        if ($normalized === []) {
            return;
        }

        $chunkSize = max(1, $chunkSize);

        $publicGroups = [];
        $privateGroups = [];
        $encryptedItems = [];

        foreach ($normalized as $item) {
            $channel = $item['channel'];
            $payload = $item['data'];

            if ($this->isEncryptedChannel($channel)) {
                $encryptedItems[] = $item;
                continue;
            }

            $groupKey = $item['event'] . "\0" . json_encode($payload, JSON_THROW_ON_ERROR);
            if ($this->isGuardedChannel($channel)) {
                $privateGroups[$groupKey][] = $channel;
            } else {
                $publicGroups[$groupKey][] = $channel;
            }
        }

        foreach ($publicGroups as $groupKey => $channels) {
            $envelope = $this->buildBulkEnvelope($groupKey, $channels);
            foreach (array_chunk($channels, $chunkSize) as $chunk) {
                $this->hub->publish(new Update(
                    array_map($this->topic(...), $chunk),
                    $envelope,
                    false,
                ));
            }
        }

        foreach ($privateGroups as $groupKey => $channels) {
            $envelope = $this->buildBulkEnvelope($groupKey, $channels);
            foreach (array_chunk($channels, $chunkSize) as $chunk) {
                $this->hub->publish(new Update(
                    array_map($this->topic(...), $chunk),
                    $envelope,
                    true,
                ));
            }
        }

        foreach ($encryptedItems as $item) {
            $this->broadcast([$item['channel']], $item['event'], $item['data']);
        }
    }

    /**
     * Build the update envelope from a bulk group key and channel list.
     *
     * @param string $groupKey event \0 payload_json \0 socket
     * @param array<string> $channels Channel names
     * @return string JSON-encoded update envelope
     */
    private function buildBulkEnvelope(string $groupKey, array $channels): string
    {
        $nullPos = strpos($groupKey, "\0");
        assert($nullPos !== false);
        $event = substr($groupKey, 0, $nullPos);
        $payload = json_decode(substr($groupKey, $nullPos + 1), true, 512, JSON_THROW_ON_ERROR);

        return $this->updateData($channels, $event, $payload, null);
    }

    /**
     * Get the broadcaster name used in config and logs.
     *
     * @return string Broadcaster name
     */
    public function getName(): string
    {
        return 'mercure';
    }

    /**
     * Check whether the given channel type can be served.
     *
     * @param string $channelType Channel type (`public`, `private`, or `presence`)
     * @return bool True when the type is supported
     */
    public function supportsChannelType(string $channelType): bool
    {
        return in_array($channelType, ['public', 'private', 'presence'], true);
    }

    /**
     * Get the underlying Symfony hub instance.
     *
     * @return \Symfony\Component\Mercure\HubInterface Hub instance used for publishing and token minting
     */
    public function getHub(): HubInterface
    {
        return $this->hub;
    }

    /**
     * Namespaced hub topic for a channel.
     *
     * Channel names are RFC 3986 encoded into a single path segment, so a
     * name can never escape its namespace.
     *
     * @param string $channel Channel name
     * @return string Namespaced hub topic for the channel
     */
    public function topic(string $channel): string
    {
        return $this->config['topic_prefix'] . 'channel/' . rawurlencode($channel);
    }

    /**
     * Whisper topic of a channel: the only topic its subscribers may
     * publish to, keeping the channel topic server-only.
     *
     * @param string $channel Channel name
     * @return string Whisper topic confined to client publishes for the channel
     */
    public function whisperTopic(string $channel): string
    {
        return $this->config['topic_prefix'] . 'whisper/' . rawurlencode($channel);
    }

    /**
     * Encode the data of an update targeting the given channels.
     *
     * @param array<string>|null $channels Channels (null for encrypted plaintext)
     * @param string $event Event name
     * @param array<string, mixed> $payload Payload
     * @param string|null $socket Socket ID excluded from the broadcast, if any
     * @return string JSON-encoded update envelope
     */
    protected function updateData(?array $channels, string $event, array $payload, mixed $socket): string
    {
        return json_encode(array_filter([
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload,
            'socket' => $socket,
        ], fn($value): bool => $value !== null), JSON_THROW_ON_ERROR);
    }

    /**
     * Check whether a channel carries end-to-end encrypted updates.
     *
     * @param string $channel Channel name
     * @return bool True for `private-encrypted-` channels
     */
    protected function isEncryptedChannel(string $channel): bool
    {
        return str_starts_with($channel, 'private-encrypted-');
    }

    /**
     * Build the channel encrypter from the configured encryption key.
     *
     * @throws \InvalidArgumentException When the configured key is malformed
     * @return \Crustum\Broadcasting\Mercure\ChannelEncrypter|null Encrypter, or null when no key is configured
     */
    protected function encrypter(): ?ChannelEncrypter
    {
        return MercureHubFactory::channelEncrypter($this->config);
    }

    /**
     * Authorize every requested channel and collect token grants.
     *
     * Public channels need no grant and stay out of the token. Guarded
     * channels resolve the user and run the registered callbacks; failures
     * are flagged with `denied` instead of failing the whole batch.
     * Presence channels additionally get a subscription grant carrying the
     * member payload, encrypted channels expose their JWK, and every
     * guarded channel contributes a whisper topic when client events are on.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming HTTP request
     * @param array<string> $channelNames Requested channel names, deduplicated
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException When an encrypted
     *   channel is authorized without an encryption key configured
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, \Symfony\Component\Mercure\Jwt\Grant>, 2: mixed} Response entries, token grants, and the first resolved user (if any)
     */
    protected function authorizeChannels(ServerRequestInterface $request, array $channelNames): array
    {
        $entries = [];
        $privateTopics = [];
        $presenceGrants = [];
        $whisperTopics = [];
        $user = null;

        foreach ($channelNames as $channelName) {
            $entry = ['name' => $channelName];

            if (!$this->isGuardedChannel($channelName)) {
                $entries[] = $entry;

                continue;
            }

            $normalized = $this->normalizeChannelName($channelName);
            $channelUser = $this->retrieveUserFromRequest($request, $normalized);
            $result = $channelUser === null ? false : $this->channelAuthResult($request, $channelName);

            if ($result === false || $result === null) {
                $entry['denied'] = true;
                $entries[] = $entry;

                continue;
            }

            $user ??= $channelUser;

            if ($this->isEncryptedChannel($channelName)) {
                if (!$this->encrypter() instanceof ChannelEncrypter) {
                    throw new BroadcastingException(
                        sprintf(
                            'Mercure broadcasting requires an "encryption_key" configuration value to authorize the end-to-end encrypted channel [%s].',
                            $channelName,
                        ),
                        500,
                    );
                }

                $entry['jwk'] = $this->encrypter()->channelJwk($channelName);
                $privateTopics[] = $this->topic($channelName);
            } elseif (str_starts_with($channelName, 'presence-')) {
                $presenceGrants[] = new Grant(
                    [Grant::ACTION_SUBSCRIBE],
                    [
                        'exact' => [$this->topic($channelName)],
                        'urlpattern' => [$this->subscriptionPattern($channelName)],
                    ],
                    [
                        'user_id' => $this->userIdentifier($channelUser),
                        'user_info' => $result,
                    ],
                );
            } else {
                $privateTopics[] = $this->topic($channelName);
            }

            if ($this->config['client_events']) {
                $whisperTopics[] = $this->whisperTopic($channelName);
                $entry['whisper_topic'] = $this->whisperTopic($channelName);
            }

            $entries[] = $entry;
        }

        $grants = $presenceGrants;
        if ($privateTopics !== []) {
            array_unshift($grants, new Grant([Grant::ACTION_SUBSCRIBE], array_values(array_unique($privateTopics))));
        }

        if ($this->config['client_events'] && $whisperTopics !== []) {
            $grants[] = new Grant(
                [Grant::ACTION_SUBSCRIBE, Grant::ACTION_PUBLISH],
                array_values(array_unique($whisperTopics)),
            );
        }

        return [$entries, $grants, $user];
    }

    /**
     * Mint the subscriber authorization cookie for the granted topics.
     *
     * Only the per-request `sub` claim is contributed here; the static
     * RFC 9068 claims come from the hub's token factory. The cookie lifetime
     * matches the subscriber token lifetime.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming HTTP request, used to resolve the cookie domain
     * @param array<int, \Symfony\Component\Mercure\Jwt\Grant> $grants Token grants for the authorized channels
     * @param mixed $user First resolved user payload, if any channel required authentication
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException When the hub and the
     *   application do not share a registrable domain for the cookie
     * @return \Symfony\Component\HttpFoundation\Cookie Authorization cookie carrying the subscriber JWT
     */
    protected function makeAuthorizationCookie(
        ServerRequestInterface $request,
        array $grants,
        mixed $user = null,
    ): SymfonyCookie {
        $claims = [];
        if ($user !== null) {
            $claims['sub'] = $this->userIdentifier($user);
        }

        $hubRequest = SfRequest::create((string)$request->getUri(), 'POST');

        try {
            return (new Authorization(new HubRegistry($this->hub), $this->expiration()))
                ->createCookie($hubRequest, $grants, null, $claims);
        } catch (MercureRuntimeException $mercureRuntimeException) {
            throw new BroadcastingException(
                sprintf(
                    'Mercure error: %s. Adjust the Mercure "public_url" configuration value so the hub [%s] shares a registrable domain with the application host [%s].',
                    rtrim($mercureRuntimeException->getMessage(), '.'),
                    $this->hub->getPublicUrl(),
                    $hubRequest->getHost(),
                ),
                500,
                $mercureRuntimeException,
            );
        }
    }

    /**
     * Convert the Symfony cookie minted by the authorization helper into a Cake cookie.
     *
     * The bridge has to live somewhere: the Mercure helper only speaks
     * HttpFoundation, while the auth response is a Cake response. Keeping it
     * a small protected method (instead of inline code) makes the mapping
     * overridable and unit-testable.
     *
     * @param \Symfony\Component\HttpFoundation\Cookie $cookie Cookie minted by the Mercure authorization helper
     * @return \Cake\Http\Cookie\Cookie Equivalent Cake cookie
     */
    protected function toCakeCookie(SymfonyCookie $cookie): Cookie
    {
        $sameSite = match (strtolower((string)$cookie->getSameSite())) {
            'lax' => Cookie::SAMESITE_LAX,
            'none' => Cookie::SAMESITE_NONE,
            default => Cookie::SAMESITE_STRICT,
        };

        return new Cookie(
            $cookie->getName(),
            (string)$cookie->getValue(),
            (new DateTimeImmutable())->setTimestamp($cookie->getExpiresTime()),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            $sameSite,
        );
    }

    /**
     * Subscriber-token (and cookie) lifetime in whole seconds.
     *
     * @throws \InvalidArgumentException When `subscribe_expiration` is not a positive number of minutes
     * @return int Lifetime in seconds
     */
    protected function expiration(): int
    {
        return MercureHubFactory::subscribeExpiration($this->config);
    }

    /**
     * Run the registered channel callbacks for a single channel.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming HTTP request
     * @param string $channel Channel name to check
     * @return bool True when a callback authorizes the user for the channel
     */
    protected function canAccess(ServerRequestInterface $request, string $channel): bool
    {
        $result = $this->channelAuthResult($request, $channel);

        return $result !== false && $result !== null;
    }

    /**
     * Run the registered channel callbacks and return the raw result.
     *
     * Presence callbacks may return member data; private callbacks booleans.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming HTTP request
     * @param string $channel Channel name to check
     * @return mixed Raw callback result, `false` when no callback matches
     */
    protected function channelAuthResult(ServerRequestInterface $request, string $channel): mixed
    {
        foreach ($this->channels as $pattern => $callback) {
            if (!$this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            $user = $this->retrieveUserFromRequest($request, $channel);
            $keys = $this->extractChannelKeys($pattern, $channel);
            $params = [];
            foreach ($keys as $key => $value) {
                if (!is_numeric($key)) {
                    $params[] = $value;
                }
            }

            $handler = $this->normalizeChannelHandlerToCallable($callback);

            return $handler($user, ...$params);
        }

        return false;
    }

    /**
     * Subscription-API topic matcher for a presence channel.
     *
     * Matches both the snapshot listing and per-subscriber events.
     * `{/:subscriber}?` is an optional URLPattern group covering the
     * trailing segment and its slash.
     *
     * @param string $channelName Presence channel name
     * @return string URLPattern matcher for the channel's subscription topics
     */
    protected function subscriptionPattern(string $channelName): string
    {
        return sprintf(
            '%s/subscriptions/:match_type/%s{/:subscriber}?',
            rtrim($this->hub->getPublicUrl(), '/'),
            rawurlencode($this->topic($channelName)),
        );
    }

    /**
     * Broadcasting identifier of a user payload.
     *
     * Same pattern as notification routing (`routeNotificationFor...`):
     * a specific method on the entity wins, otherwise the default `id`
     * applies. Plain payloads carry the identifier under the `id` key
     * (see `PusherBroadcaster` presence auth and `BaseBroadcaster::formatUserData()`).
     *
     * @param \Cake\Datasource\EntityInterface|array<string, mixed> $user Authenticated user payload
     * @return string Broadcasting identifier
     */
    protected function userIdentifier(EntityInterface|array $user): string
    {
        if (is_array($user)) {
            return (string)$user['id'];
        }

        if (method_exists($user, 'getBroadcastIdentifier')) {
            return (string)$user->getBroadcastIdentifier();
        }

        return (string)$user->get('id');
    }

    /**
     * Return the valid authentication response for the request.
     *
     * The Mercure driver answers auth fully inside `auth()` (JSON body plus
     * authorization cookie), so a precomputed result passes through here.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming HTTP request
     * @param mixed $result Precomputed authentication result
     * @return mixed The same result, unchanged
     */
    public function validAuthenticationResponse(ServerRequestInterface $request, mixed $result): mixed
    {
        return $result;
    }

    /**
     * Format channels (unwrap Channel objects).
     *
     * @param array<int|string, mixed> $channels Channel names or channel objects
     * @return array<string> Plain channel names
     */
    protected function formatChannels(array $channels): array
    {
        return array_map(
            fn($channel): string => $channel instanceof Channel ? $channel->getName() : (string)$channel,
            array_values($channels),
        );
    }
}
