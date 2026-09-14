<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Mercure;

use Cake\Core\Configure;
use InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * Mercure Hub Factory
 *
 * Builds Mercure hub instances from connection config: validates
 * expirations and secrets, defaults the RFC 9068 claims, hardens the
 * cookie path, and falls back to the FrankenPHP built-in hub when no
 * `url` is configured.
 */
class MercureHubFactory
{
    /**
     * Build a hub instance from connection config.
     *
     * Expected keys: `url`, `public_url`, `secret` (or
     * `publish_secret`/`subscribe_secret`), `algorithm`,
     * `publish_algorithm`, `subscribe_algorithm`, `passphrase`,
     * `claims`, `subscribe_claims`, `subscribe_expiration` (minutes),
     * `publish_expiration` (minutes), `cookie_name`, `client_options`,
     * `encryption_key`, `topic_prefix`, `client_events`.
     *
     * @param array<string, mixed> $config Connection config
     * @return \Symfony\Component\Mercure\HubInterface Configured hub instance
     */
    public static function hub(array $config): HubInterface
    {
        if (empty($config['url'])) {
            return static::frankenPhpHub($config);
        }

        $publishExpiration = (int)(($config['publish_expiration'] ?? 0) * 60);
        if ($publishExpiration < 0 || ($publishExpiration === 0 && !empty($config['publish_expiration']))) {
            throw new InvalidArgumentException(
                'The Mercure "publish_expiration" configuration value must be a positive number of minutes, or 0 to use the default lifetime.',
            );
        }

        $publishTokenFactory = new DefaultClaimsTokenFactory(
            WebTokenFactory::fromSecret(
                static::secret($config, 'publish'),
                $config['publish_algorithm'] ?? $config['algorithm'] ?? 'HS256',
                $publishExpiration,
                $config['publish_passphrase'] ?? $config['passphrase'] ?? '',
            ),
            static::publishClaims($config),
        );

        return new Hub(
            $config['url'],
            new CachingTokenProvider(
                new FactoryTokenProvider($publishTokenFactory, [new Grant([Grant::ACTION_PUBLISH], ['*'])]),
            ),
            static::subscribeFactory($config),
            $config['public_url'] ?? null ?: null,
            empty($config['client_options']) ? null : HttpClient::create($config['client_options']),
            $config['cookie_name'] ?? null ?: null,
            ProtocolVersion::V1,
        );
    }

    /**
     * Subscriber-token lifetime in whole seconds.
     *
     * @param array<string, mixed> $config Connection config
     * @return int Lifetime in seconds
     */
    public static function subscribeExpiration(array $config): int
    {
        $expiration = (int)(($config['subscribe_expiration'] ?? 5) * 60);
        if ($expiration <= 0) {
            throw new InvalidArgumentException(
                'The Mercure "subscribe_expiration" configuration value must be a positive number of minutes.',
            );
        }

        return $expiration;
    }

    /**
     * Validate cookie / URL combination (secure-prefixed cookies need HTTPS).
     *
     * @param \Symfony\Component\Mercure\HubInterface $hub Hub to validate
     * @return void
     */
    public static function assertCookiePath(HubInterface $hub): void
    {
        if (
            str_starts_with($hub->getCookieName(), '__') &&
            parse_url($hub->getPublicUrl(), PHP_URL_SCHEME) === 'http'
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'The Mercure "%s" cookie requires an "https" hub "public_url" ("cookie_name"). ' .
                    'Use HTTPS, or configure a "cookie_name" without the "__Secure-" or "__Host-" prefix for plain-HTTP development.',
                    $hub->getCookieName(),
                ),
            );
        }
    }

    /**
     * Build the subscribe-side token factory, or null when no secret configured.
     *
     * @param array<string, mixed> $config Connection config
     * @return \Symfony\Component\Mercure\Jwt\TokenFactoryInterface|null Subscribe-side factory, null when no secret is configured
     */
    public static function subscribeFactory(array $config): ?TokenFactoryInterface
    {
        if (empty($config['subscribe_secret']) && empty($config['secret'])) {
            return null;
        }

        $claims = static::claims($config);
        $claims['sub'] = $claims['sub'] ?? null ?: 'anonymous';

        return new DefaultClaimsTokenFactory(
            WebTokenFactory::fromSecret(
                static::secret($config, 'subscribe'),
                $config['subscribe_algorithm'] ?? $config['algorithm'] ?? 'HS256',
                static::subscribeExpiration($config),
                $config['subscribe_passphrase'] ?? $config['passphrase'] ?? '',
            ),
            $claims,
        );
    }

    /**
     * Build the channel encrypter when `encryption_key` is configured.
     *
     * The key must be base64-encoded 32 bytes, with an optional `base64:` prefix.
     *
     * @param array<string, mixed> $config Connection config
     * @return \Crustum\Broadcasting\Mercure\ChannelEncrypter|null Encrypter, null when no key is configured
     */
    public static function channelEncrypter(array $config): ?ChannelEncrypter
    {
        if (empty($config['encryption_key'])) {
            return null;
        }

        $encodedKey = (string)$config['encryption_key'];
        if (str_starts_with($encodedKey, 'base64:')) {
            $encodedKey = substr($encodedKey, 7);
        }

        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException(
                'The Mercure "encryption_key" configuration value must be a base64-encoded 32-byte key. ' .
                'You may generate one with: php -r "echo base64_encode(random_bytes(32));"',
            );
        }

        return new ChannelEncrypter($key);
    }

    /**
     * Additional JWT claims for the publisher side.
     *
     * @param array<string, mixed> $config Connection config
     * @return array<string, mixed>
     */
    public static function publishClaims(array $config): array
    {
        $claims = static::claims($config);
        $claims['sub'] = $claims['sub'] ?? null ?: ($claims['client_id'] ?? null ?: $config['url']);

        return $claims;
    }

    /**
     * Resolve the publish/subscribe secret, enforcing HMAC minimum lengths.
     *
     * @param array<string, mixed> $config Connection config
     * @param string $side `publish` or `subscribe`
     * @return non-empty-string Resolved secret for the requested side
     */
    protected static function secret(array $config, string $side): string
    {
        $secret = $config[$side . '_secret'] ?? null ?: ($config['secret'] ?? null);
        if (empty($secret)) {
            throw new InvalidArgumentException(sprintf(
                'The Mercure broadcasting connection requires a "secret" (or "%s_secret") configuration value.',
                $side,
            ));
        }

        $algorithm = $config[$side . '_algorithm'] ?? $config['algorithm'] ?? 'HS256';
        $minimumLength = ['HS256' => 32, 'HS384' => 48, 'HS512' => 64][$algorithm] ?? 0;
        if (strlen((string)$secret) < $minimumLength) {
            throw new InvalidArgumentException(sprintf(
                'The Mercure "secret" (or "%s_secret") configuration value must be at least %d bytes long to sign %s tokens.',
                $side,
                $minimumLength,
                $algorithm,
            ));
        }

        return (string)$secret;
    }

    /**
     * Additional JWT claims with RFC 9068 defaults.
     *
     * @param array<string, mixed> $config Connection config
     * @return array<string, mixed>
     */
    protected static function claims(array $config): array
    {
        $claims = $config['claims'] ?? [];
        if (!is_array($claims)) {
            $claims = [];
        }

        $appUrl = Configure::read('App.fullBaseUrl');
        $url = $config['url'] ?? null ?: ($config['public_url'] ?? null ?: '/.well-known/mercure');

        if (empty($config['url']) && empty($config['public_url']) && $appUrl) {
            $origin = parse_url((string)$appUrl);
            if (isset($origin['scheme'], $origin['host'])) {
                $url = $origin['scheme'] . '://' . $origin['host']
                    . (isset($origin['port']) ? ':' . $origin['port'] : '') . $url;
            }
        }

        $claims['aud'] = $claims['aud'] ?? null ?: ($config['public_url'] ?? null ?: $url);
        $claims['iss'] = $claims['iss'] ?? null ?: ($appUrl ?: $url);
        $claims['client_id'] = $claims['client_id'] ?? null ?: $claims['iss'];

        return $claims;
    }

    /**
     * Hub backed by FrankenPHP's built-in hub.
     *
     * @param array<string, mixed> $config Connection config
     * @return \Symfony\Component\Mercure\HubInterface FrankenPHP-backed hub instance
     */
    protected static function frankenPhpHub(array $config): HubInterface
    {
        if (!function_exists('mercure_publish')) {
            throw new InvalidArgumentException(
                'The Mercure broadcasting connection requires a "url" configuration value, ' .
                'unless the application is served by FrankenPHP with its built-in Mercure hub enabled.',
            );
        }

        return new FrankenPhpHub(
            $config['public_url'] ?? null ?: '/.well-known/mercure',
            static::subscribeFactory($config),
            $config['cookie_name'] ?? null ?: null,
            ProtocolVersion::V1,
        );
    }
}
