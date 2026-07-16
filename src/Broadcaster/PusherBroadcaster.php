<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Broadcaster;

use ArrayAccess;
use Authentication\IdentityInterface;
use Cake\Collection\Collection;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Polyfill\StringFunctions;
use Crustum\Broadcasting\Trait\PusherChannelConventionsTrait;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Pusher\Pusher;

/**
 * Pusher Broadcaster
 *
 * Broadcasting implementation for Pusher service using Pusher PHP SDK.
 * Controller handles HTTP response generation.
 *
 * @package Crustum\Broadcasting\Broadcaster
 */
class PusherBroadcaster extends BaseBroadcaster
{
    use PusherChannelConventionsTrait;

    /**
     * Pusher PHP SDK client.
     *
     * @var \Pusher\Pusher
     */
    protected Pusher $pusherClient;

    /**
     * Whether JSONP callbacks are allowed on authorization responses.
     *
     * @var bool
     */
    protected bool $allowJsonp = false;

    /**
     * Constructor.
     *
     * @param array{driver?: string, key?: string, secret?: string, app_id?: string, jsonp?: bool, options?: array<string, mixed>} $config Pusher configuration
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (!isset($config['key']) || empty($config['key'])) {
            throw new BroadcastingException("Pusher configuration 'key' is required.", 500);
        }

        if (!isset($config['secret']) || empty($config['secret'])) {
            throw new BroadcastingException("Pusher configuration 'secret' is required.", 500);
        }

        if (!isset($config['app_id']) || empty($config['app_id'])) {
            throw new BroadcastingException("Pusher configuration 'app_id' is required.", 500);
        }

        $this->allowJsonp = (bool)($config['jsonp'] ?? false);
        $this->pusherClient = $this->createPusherClient($config);
    }

    /**
     * Whether JSONP callbacks are allowed for authorization responses.
     *
     * @return bool
     */
    public function allowsJsonp(): bool
    {
        return $this->allowJsonp;
    }

    /**
     * Create Pusher client instance.
     * Protected method to allow testing with mocks.
     *
     * @param array{driver?: string, key: string, secret: string, app_id: string, options?: array<string, mixed>} $config Pusher configuration
     * @return \Pusher\Pusher
     */
    protected function createPusherClient(array $config): Pusher
    {
        return new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $config['options'] ?? [],
        );
    }

    /**
     * Authenticate a request for a channel.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array<string, mixed> Authentication result
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException
     * @throws \Crustum\Broadcasting\Exception\InvalidChannelException
     */
    public function auth(ServerRequestInterface $request): array|Response
    {
        $channelName = $this->getChannelNameFromRequest($request);
        $socketId = $this->getSocketIdFromRequest($request);

        $missingParams = [];
        if (!$socketId) {
            $missingParams[] = 'socket_id';
        }

        if (!$channelName) {
            $missingParams[] = 'channel_name';
        }

        if ($missingParams !== []) {
            $message = 'Missing required parameters: ' . implode(', ', $missingParams);
            throw new BroadcastingException($message, 400);
        }

        if ($channelName === null) {
            throw new BroadcastingException('Missing required parameters: channel_name', 400);
        }

        $result = $this->verifyUserCanAccessChannel($request, $channelName);
        if ($result instanceof Response) {
            return $result;
        }

        if (!is_array($result)) {
            throw new BroadcastingException('Invalid authentication response type', 500);
        }

        return $result;
    }

    /**
     * Resolve authenticated user for user authentication.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User authentication data or null if not authenticated
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException
     */
    public function resolveAuthenticatedUser(ServerRequestInterface $request): ?array
    {
        $socketId = $this->getSocketIdFromRequest($request);

        if (!$socketId) {
            throw new BroadcastingException('Missing required parameters: socket_id', 400);
        }

        $user = $this->resolveUserFromRequest($request);
        if ($user === null) {
            return null;
        }

        try {
            $userData = $this->getUserData($user);
            $response = $this->pusherClient->authenticateUser($socketId, $userData);

            return json_decode($response, true);
        } catch (Exception $exception) {
            throw new BroadcastingException('Failed to authenticate user: ' . $exception->getMessage(), 500, $exception);
        }
    }

    /**
     * Broadcast a message to channels.
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

        $this->logBroadcast($channels, $event, $payload);

        $socket = null;
        if (isset($payload['socket'])) {
            $socket = $payload['socket'];
            unset($payload['socket']);
        }

        $parameters = $socket !== null ? ['socket_id' => $socket] : [];

        $channelsCollection = new Collection($channels);
        $channelsCollection->chunk(100)->each(function ($channelChunk) use ($event, $payload, $parameters): void {
            $channelArray = is_array($channelChunk) ? $channelChunk : $channelChunk->toArray();
            $this->pusherClient->trigger($channelArray, $event, $payload, $parameters);
        });
    }

    /**
     * Get the broadcaster name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'pusher';
    }

    /**
     * Check if broadcaster supports the given channel type.
     *
     * @param string $channelType Channel type
     * @return bool
     */
    public function supportsChannelType(string $channelType): bool
    {
        return in_array($channelType, ['public', 'private', 'presence'], true);
    }

    /**
     * Get user data for user authentication.
     *
     * @param \Authentication\IdentityInterface|\Cake\Datasource\EntityInterface|\ArrayAccess<string, mixed>|array{id: string|int, username?: string, full_name?: string} $user User identity
     * @return array<string, mixed>
     */
    protected function getUserData(IdentityInterface|EntityInterface|ArrayAccess|array $user): array
    {
        $userData = $user instanceof IdentityInterface ? $user->getOriginalData() : $user;

        return $this->formatUserData($userData);
    }

    /**
     * Return the valid authentication response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param mixed $result Authentication result
     * @return \Cake\Http\Response|array<string, mixed>
     */
    public function validAuthenticationResponse(ServerRequestInterface $request, mixed $result): array|Response
    {
        $channelName = $this->getChannelNameFromRequest($request);
        $socketId = $this->getSocketIdFromRequest($request);

        if (empty($socketId)) {
            throw new BroadcastingException('Socket ID not found in request.', 400);
        }

        if ($channelName !== null && StringFunctions::startsWith($channelName, 'presence-')) {
            $user = $this->resolveUserFromRequest($request);
            if ($user === null) {
                throw new BroadcastingException('User not authenticated for presence channel.', 403);
            }

            $userData = $this->getUserData($user);

            if ($user instanceof EntityInterface) {
                $userId = $user->get('id');
            } else {
                /** @phpstan-ignore-next-line offsetAccess.notFound */
                $userId = $user['id'];
            }
            $authString = $this->pusherClient->authorizePresenceChannel(
                $channelName,
                $socketId,
                (string)$userId,
                $userData['user_info'],
            );

            return $this->decodePusherResponse($request, $authString);
        }

        if ($channelName === null) {
            throw new BroadcastingException('Channel name is required for authorization', 400);
        }

        $authString = $this->pusherClient->authorizeChannel($channelName, $socketId);

        return $this->decodePusherResponse($request, $authString);
    }

    /**
     * Decode a Pusher authorization response, optionally as JSONP when enabled.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param string $response Raw Pusher auth response
     * @return \Cake\Http\Response|array<string, mixed>
     * @throws \Crustum\Broadcasting\Exception\BroadcastingException
     */
    protected function decodePusherResponse(ServerRequestInterface $request, string $response): array|Response
    {
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BroadcastingException('JSON decode error: ' . json_last_error_msg(), 500);
        }

        $callback = $this->getCallbackFromRequest($request);
        if ($callback === null || $callback === '' || !$this->allowJsonp) {
            return $decoded;
        }

        $json = json_encode($decoded);
        if ($json === false) {
            throw new BroadcastingException('JSON encode error for JSONP response', 500);
        }

        return (new Response())
            ->withType('application/javascript')
            ->withStringBody($callback . '(' . $json . ');');
    }

    /**
     * Resolve a JSONP callback name from the request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return string|null
     */
    protected function getCallbackFromRequest(ServerRequestInterface $request): ?string
    {
        $callback = $request->getQueryParams()['callback'] ?? null;
        if (is_string($callback) && $callback !== '') {
            return $callback;
        }

        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['callback']) && is_string($body['callback'])) {
            return $body['callback'] !== '' ? $body['callback'] : null;
        }

        return null;
    }

    /**
     * Resolve the authenticated user from the request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return \Authentication\IdentityInterface|\Cake\Datasource\EntityInterface|null User identity or null
     */
    protected function resolveUserFromRequest(ServerRequestInterface $request): IdentityInterface|EntityInterface|null
    {
        if ($this->authenticatedUserCallback instanceof Closure) {
            $result = ($this->authenticatedUserCallback)($request);

            return $result instanceof IdentityInterface ? $result : null;
        }

        if ($request instanceof ServerRequest) {
            return $this->retrieveUserFromCakeRequest($request);
        }

        return null;
    }

    /**
     * Get socket ID from request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return string|null
     */
    protected function getSocketIdFromRequest(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return null;
        }

        return $body['socket_id'] ?? null;
    }

    /**
     * Get Pusher client.
     *
     * @return \Pusher\Pusher
     */
    public function getClient(): Pusher
    {
        return $this->pusherClient;
    }
}
