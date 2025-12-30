<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Broadcaster;

use Cake\Log\Log;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Log Broadcaster
 *
 * A broadcaster implementation that logs broadcast messages using CakePHP's log system.
 * Useful for debugging and development environments.
 *
 * @package Crustum\Broadcasting\Broadcaster
 */
class LogBroadcaster extends BaseBroadcaster implements BroadcasterInterface
{
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
     * Authenticate a request for a channel.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return mixed Authentication result
     */
    public function auth(ServerRequestInterface $request): mixed
    {
        return ['auth' => true];
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
     * Broadcast a message to channels.
     *
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @return void
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $this->logBroadcast($channels, $event, $payload);

        $channelsString = implode(', ', $channels);
        $payloadJson = json_encode($payload, JSON_PRETTY_PRINT);

        Log::info("Broadcasting [{$event}] on channels [{$channelsString}] with payload: {$payloadJson}");
    }

    /**
     * Get the broadcaster name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'log';
    }

    /**
     * Set broadcaster configuration.
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
     * Resolve authenticated user for user authentication.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User authentication data or null if not authenticated
     */
    public function resolveUserAuth(ServerRequestInterface $request): ?array
    {
        return null;
    }

    /**
     * Resolve authenticated user for user authentication.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return array{id: string|int, name?: string, email?: string, roles?: array<string>, permissions?: array<string>}|null User authentication data or null if not authenticated
     */
    public function resolveAuthenticatedUser(ServerRequestInterface $request): ?array
    {
        return null;
    }

    /**
     * Set the registered channel authenticators.
     *
     * @param array<string, callable|string> $channels
     * @return void
     */
    public function setChannelCallbacks(array $channels): void
    {
    }

    /**
     * Set the registered channel options.
     *
     * @param array<string, array<string, mixed>> $options
     * @return void
     */
    public function setChannelOptions(array $options): void
    {
    }
}
