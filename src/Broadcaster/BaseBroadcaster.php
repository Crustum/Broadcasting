<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Broadcaster;

use ArrayAccess;
use Cake\Datasource\EntityInterface;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Inflector;
use Closure;
use Crustum\Broadcasting\Channel\ChannelInterface;
use Crustum\Broadcasting\Exception\InvalidChannelException;
use Exception;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base Broadcaster
 *
 * Abstract base class providing common broadcasting functionality.
 * Handles channel authentication, user resolution, and parameter binding.
 * Following CakePHP conventions with explicit method names.
 *
 * @package Crustum\Broadcasting\Broadcaster
 */
abstract class BaseBroadcaster implements BroadcasterInterface
{
    use LocatorAwareTrait;

    /**
     * The callback to resolve the authenticated user information.
     *
     * @var \Closure|null
     */
    protected ?Closure $authenticatedUserCallback = null;

    /**
     * The registered channel authenticators.
     *
     * @var array<string, callable|string>
     */
    protected array $channels = [];

    /**
     * The registered channel options.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $channelOptions = [];

    /**
     * Broadcaster configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Broadcaster configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Resolve the authenticated user payload for the incoming connection request.
     * Following CakePHP convention for explicit resolver methods.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User payload or null if not authenticated
     */
    public function resolveAuthenticatedUser(ServerRequestInterface $request): ?array
    {
        if ($this->authenticatedUserCallback) {
            return ($this->authenticatedUserCallback)($request);
        }

        return null;
    }

    /**
     * Resolve authenticated user for user authentication.
     * Following CakePHP convention for explicit resolver methods.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User authentication data or null if not authenticated
     */
    public function resolveUserAuth(ServerRequestInterface $request): ?array
    {
        return $this->resolveAuthenticatedUser($request);
    }

    /**
     * Register the user retrieval callback used to authenticate connections.
     * Following CakePHP convention for explicit registration methods.
     *
     * @param \Closure $callback User resolution callback
     * @return $this
     */
    public function registerUserResolver(Closure $callback)
    {
        $this->authenticatedUserCallback = $callback;

        return $this;
    }

    /**
     * Register a channel authenticator.
     *
     * @param string $channel Channel pattern
     * @param callable|string $callback Authentication callback
     * @param array<string, mixed> $options Channel options
     * @return $this
     */
    public function registerChannel(string $channel, callable|string $callback, array $options = [])
    {
        $this->channels[$channel] = $callback;
        $this->channelOptions[$channel] = $options;

        return $this;
    }

    /**
     * Get all registered channels.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return array<string, callable|string>
     */
    public function getRegisteredChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get options for a specific channel.
     * Following CakePHP convention for explicit getter methods.
     *
     * @param string $channel Channel name
     * @return array{auth?: callable|string, guards?: array<string>, middleware?: array<string>, prefix?: string, domain?: string}
     */
    public function getChannelOptions(string $channel): array
    {
        foreach ($this->channelOptions as $pattern => $options) {
            if ($this->channelNameMatchesPattern($channel, $pattern)) {
                return $options;
            }
        }

        return [];
    }

    /**
     * Set broadcaster configuration.
     * Following CakePHP convention for explicit setter methods.
     *
     * @param array<string, mixed> $config Configuration array
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Get broadcaster configuration.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if broadcaster supports the given channel type.
     *
     * @param string $channelType Channel type
     * @return bool
     */
    public function supportsChannelType(string $channelType): bool
    {
        return true;
    }

    /**
     * Verify that a user can access a channel.
     * Following CakePHP convention for explicit verification methods.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param string $channel Channel name
     * @return mixed Authentication result
     * @throws \Crustum\Broadcasting\Exception\InvalidChannelException
     */
    protected function verifyUserCanAccessChannel(ServerRequestInterface $request, string $channel): mixed
    {
        foreach ($this->channels as $pattern => $callback) {
            if (!$this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            $user = $this->retrieveUserFromRequest($request, $channel);

            if (is_string($callback) && class_exists($callback)) {
                $channelHandler = new $callback();
                if ($channelHandler instanceof ChannelInterface) {
                    $model = $this->resolveModelFromChannel($pattern, $channel);
                    $result = $channelHandler->join($user, $model);
                } else {
                    throw new Exception("Channel class {$callback} must implement ChannelInterface");
                }
            } else {
                $handler = $this->normalizeChannelHandlerToCallable($callback);
                $channelKeys = $this->extractChannelKeys($pattern, $channel);

                $parameters = [];
                foreach ($channelKeys as $key => $value) {
                    if (!is_numeric($key)) {
                        $parameters[] = $value;
                    }
                }

                $result = $handler($user, ...$parameters);
            }

            if ($result === false) {
                throw InvalidChannelException::unauthorized($channel);
            }

            if ($result) {
                return $this->validAuthenticationResponse($request, $result);
            }
        }

        throw InvalidChannelException::unauthorized($channel);
    }

    /**
     * Resolve model entity from channel pattern and name.
     *
     * @param string $pattern Channel pattern (e.g., 'orders.{order}')
     * @param string $channel Channel name (e.g., 'orders.123')
     * @return \Cake\Datasource\EntityInterface Resolved model entity
     * @throws \Exception
     */
    protected function resolveModelFromChannel(string $pattern, string $channel): EntityInterface
    {
        $channelKeys = $this->extractChannelKeys($pattern, $channel);

        foreach ($channelKeys as $key => $value) {
            if (!is_numeric($key) && $key !== 'user') {
                return $this->resolveEntityFromKey($key, $value);
            }
        }

        throw new Exception("Channel class requires a model parameter in pattern (e.g., 'orders.{order}'), but pattern '{$pattern}' has none. Use closures for simple channels without parameters.");
    }

    /**
     * Resolve entity from parameter key and value.
     *
     * @param string $key Parameter key (e.g., 'order', 'post')
     * @param string $value Parameter value (e.g., '123')
     * @return \Cake\Datasource\EntityInterface Resolved entity
     * @throws \Exception
     */
    protected function resolveEntityFromKey(string $key, string $value): EntityInterface
    {
        $tableName = Inflector::camelize(Inflector::tableize($key));

        try {
            $table = $this->getTableLocator()->get($tableName);
            $entity = $table->get($value);

            return $entity;
        } catch (Exception $e) {
            throw new Exception("Failed to resolve {$key} with value {$value}: " . $e->getMessage());
        }
    }

    /**
     * Extract channel keys from channel name using pattern.
     *
     * @param string $pattern Channel pattern
     * @param string $channel Channel name
     * @return array<string> Extracted keys
     */
    protected function extractChannelKeys(string $pattern, string $channel): array
    {
        preg_match('/^' . preg_replace('/\{(.*?)\}/', '(?<$1>[^\.]+)', $pattern) . '/', $channel, $keys);

        return $keys;
    }

    /**
     * Normalize channel handler to callable.
     *
     * @param mixed $callback Channel handler
     * @return callable Normalized callable
     */
    protected function normalizeChannelHandlerToCallable(mixed $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        return function (...$args) use ($callback) {
            if (!class_exists($callback)) {
                throw new Exception("Class {$callback} not found");
            }
            $instance = new $callback();

            if (!$instance instanceof ChannelInterface) {
                throw new Exception("Class {$callback} must implement ChannelInterface");
            }

            return $instance->join(...$args);
        };
    }

    /**
     * Retrieve user from request.
     * Following CakePHP convention for explicit retrieval methods.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param string $channel Channel name
     * @return mixed User instance or null
     */
    protected function retrieveUserFromRequest(
        ServerRequestInterface $request,
        string $channel,
    ): mixed {
        if ($request instanceof ServerRequest) {
            return $this->retrieveUserFromCakeRequest($request);
        }

        return null;
    }

    /**
     * Retrieve user from CakePHP request.
     *
     * @param \Cake\Http\ServerRequest $request CakePHP request
     * @return mixed User instance or null
     */
    protected function retrieveUserFromCakeRequest(ServerRequest $request): mixed
    {
        if ($request->getAttribute('identity')) {
            return $request->getAttribute('identity')->getOriginalData();
        }

        return null;
    }

    /**
     * Check if channel name matches pattern.
     *
     * @param string $channel Channel name
     * @param string $pattern Channel pattern
     * @return bool True if matches
     */
    protected function channelNameMatchesPattern(string $channel, string $pattern): bool
    {
        $pattern = str_replace('.', '\.', $pattern);
        $regex = '/^' . preg_replace('/\{(.*?)\}/', '([^\.]+)', $pattern) . '$/';

        return preg_match($regex, $channel) === 1;
    }

    /**
     * Format channels array into strings.
     *
     * @param array<string> $channels Channel instances
     * @return array<string> String channel names
     */
    protected function formatChannels(array $channels): array
    {
        return array_map(function ($channel) {
            return (string)$channel;
        }, $channels);
    }

    /**
     * Get channel name from request.
     * Following CakePHP convention for explicit getter methods.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return string|null
     */
    protected function getChannelNameFromRequest(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return null;
        }

        return $body['channel_name'] ?? null;
    }

    /**
     * Format user data for presence channels.
     * Following CakePHP convention for explicit action methods.
     *
     * @param \Cake\Datasource\EntityInterface|\ArrayAccess<string, mixed>|array{id: string|int, username?: string, full_name?: string}|array<string, mixed> $user User data
     * @return array{id: string|int, user_info: array{id: string|int, name: string}} Formatted user data
     */
    protected function formatUserData(EntityInterface|ArrayAccess|array $user): array
    {
        return [
            'id' => $user['id'],
            'user_info' => [
                'id' => $user['id'],
                'name' => $user['full_name'] ?? $user['username'] ?? 'User',
            ],
        ];
    }

    /**
     * Format payload for broadcasting.
     * Following CakePHP convention for explicit action methods.
     *
     * @param array<string, mixed> $payload Payload data
     * @return array<string, mixed> Formatted payload
     */
    protected function formatPayload(array $payload): array
    {
        return array_merge($payload, [
            'time_ms' => (int)((float)microtime(true) * 1000.0),
        ]);
    }

    /**
     * Validate and format authentication response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param mixed $result Authentication result
     * @return mixed Formatted authentication response
     */
    public function validAuthenticationResponse(ServerRequestInterface $request, mixed $result): mixed
    {
        return $result;
    }

    /**
     * Set the registered channel authenticators.
     *
     * @param array<string, callable|string> $channels
     * @return void
     */
    public function setChannelCallbacks(array $channels): void
    {
        $this->channels = $channels;
    }

    /**
     * Set the registered channel options.
     *
     * @param array<string, array<string, mixed>> $options
     * @return void
     */
    public function setChannelOptions(array $options): void
    {
        $this->channelOptions = $options;
    }
}
