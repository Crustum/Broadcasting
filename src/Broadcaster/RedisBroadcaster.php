<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Broadcaster;

use Cake\Log\Log;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Trait\PusherChannelConventionsTrait;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Redis;

/**
 * Redis Broadcaster
 *
 * Broadcasting implementation using Redis pub/sub.
 * Handles channel pattern matching and connection pooling.
 * Following CakePHP conventions with explicit method names.
 *
 * @package Crustum\Broadcasting\Broadcaster
 */
class RedisBroadcaster extends BaseBroadcaster
{
    use PusherChannelConventionsTrait;

    /**
     * Redis connection name.
     *
     * @var string
     */
    protected string $connection;

    /**
     * Redis client instance.
     *
     * @var mixed
     */
    protected mixed $redis;

    /**
     * Constructor.
     *
     * @param array{connection?: string, redis?: array{host?: string, port?: int, password?: string|null, database?: int}} $config Redis configuration
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->connection = $config['connection'] ?? 'default';
        $this->redis = $this->getRedisConnection();
    }

    /**
     * Authenticate a request for a channel.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return mixed Authentication result
     */
    public function auth(ServerRequestInterface $request): mixed
    {
        $channelName = $this->getChannelNameFromRequest($request);

        if (!$channelName) {
            throw new BroadcastingException('Missing channel name', 400);
        }

        $user = $this->resolveAuthenticatedUser($request);

        if ($this->isGuardedChannel($channelName)) {
            $normalizedChannel = $this->normalizeChannelName($channelName);

            if (str_starts_with($channelName, 'private-')) {
                return $this->authenticatePrivateChannel($normalizedChannel, $user);
            }

            if (str_starts_with($channelName, 'presence-')) {
                return $this->authenticatePresenceChannel($normalizedChannel, $user);
            }
        }

        return ['auth' => true];
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

        if (empty($channels)) {
            Log::debug('RedisBroadcaster: No channels to broadcast to');

            return;
        }

        $this->logBroadcast($channels, $event, $payload);

        $socket = null;
        if (isset($payload['socket'])) {
            $socket = $payload['socket'];
            unset($payload['socket']);
        }

        $payload = $this->formatPayload($payload);
        $message = json_encode([
            'event' => $event,
            'data' => $payload,
            'socket' => $socket,
        ]);

        if ($message === false) {
            throw new BroadcastingException('Failed to encode message to JSON', 500);
        }

        Log::debug('RedisBroadcaster: Broadcasting', [
            'channels' => $channels,
            'event' => $event,
            'message' => $message,
        ]);

        try {
            $this->broadcastToMultipleChannels($channels, $message);
            Log::debug('RedisBroadcaster: Broadcast successful to ' . count($channels) . ' channels');
        } catch (Exception $e) {
            Log::error('Redis broadcast failed', [
                'channels' => $channels,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            throw new BroadcastingException('Redis broadcast failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Broadcast to multiple channels using Lua script for efficiency.
     *
     * @param array<string> $channels Channel names
     * @param string $message JSON-encoded message
     * @return void
     */
    protected function broadcastToMultipleChannels(array $channels, string $message): void
    {
        $connection = $this->getRedisConnection();

        $args = array_merge([$message], $channels);

        Log::debug('RedisBroadcaster: Publishing via Lua script', [
            'channels' => $channels,
            'message_length' => strlen($message),
        ]);

        $result = $connection->eval(
            $this->getBroadcastMultipleChannelsScript(),
            $args,
            0,
        );

        Log::debug('RedisBroadcaster: Lua script result', ['result' => $result]);
    }

    /**
     * Get the Lua script for broadcasting to multiple channels.
     *
     * ARGV[1] - The payload (JSON message)
     * ARGV[2...] - The channel names
     *
     * @return string
     */
    protected function getBroadcastMultipleChannelsScript(): string
    {
        return <<<'LUA'
for i = 2, #ARGV do
  redis.call('publish', ARGV[i], ARGV[1])
end
LUA;
    }

    /**
     * Get the broadcaster name.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'redis';
    }

    /**
     * Check if broadcaster supports the given channel type.
     *
     * @param string $channelType Channel type
     * @return bool
     */
    public function supportsChannelType(string $channelType): bool
    {
        return in_array($channelType, ['public', 'private', 'presence']);
    }

    /**
     * Get Redis connection.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return mixed Redis connection
     */
    protected function getRedisConnection(): mixed
    {
        $config = $this->getConfig();
        $redisConfig = $config['redis'] ?? [];

        $host = $redisConfig['host'] ?? '127.0.0.1';
        $port = $redisConfig['port'] ?? 6379;
        $password = $redisConfig['password'] ?? null;
        $database = $redisConfig['database'] ?? 0;

        $redis = new Redis();
        $redis->connect($host, $port);

        if ($password) {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        return $redis;
    }

    /**
     * Authenticate private channel.
     * Following CakePHP convention for explicit action methods.
     *
     * @param string $channel Channel name
     * @param mixed $user User data
     * @return array{auth: bool} Authentication result
     */
    protected function authenticatePrivateChannel(string $channel, mixed $user): array
    {
        if (!$user) {
            throw new BroadcastingException('User not authenticated for private channel', 401);
        }

        return ['auth' => true];
    }

    /**
     * Authenticate presence channel.
     * Following CakePHP convention for explicit action methods.
     *
     * @param string $channel Channel name
     * @param mixed $user User data
     * @return array{auth: bool, channel_data: array{id: int|string, user_info: array{id: int|string, name: string}}|array{}} Authentication result
     */
    protected function authenticatePresenceChannel(string $channel, mixed $user): array
    {
        if (!$user) {
            throw new BroadcastingException('User not authenticated for presence channel', 401);
        }

        $userData = $this->formatUserData($user);

        return [
            'auth' => true,
            'channel_data' => $userData,
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
     * Publish message to Redis channel.
     * Following CakePHP convention for explicit action methods.
     *
     * @param string $channel Channel name
     * @param string $message Message to publish
     * @return void
     */
    protected function publishToChannel(string $channel, string $message): void
    {
        $this->redis->publish($channel, $message);
    }
}
