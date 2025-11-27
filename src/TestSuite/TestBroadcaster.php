<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite;

use Crustum\Broadcasting\Broadcaster\NullBroadcaster;
use Crustum\Broadcasting\Broadcasting;

/**
 * Test Broadcaster
 *
 * Captures broadcasts instead of sending them for testing purposes.
 * Similar to TestEmailTransport for email testing and TestNotificationSender for notification testing.
 *
 * Usage:
 * ```
 * // In test setup
 * TestBroadcaster::replaceAllBroadcasters();
 *
 * // Broadcast as normal
 * Broadcasting::to('orders')->event('OrderCreated')->send();
 *
 * // Make assertions
 * $broadcasts = TestBroadcaster::getBroadcasts();
 * ```
 */
class TestBroadcaster extends NullBroadcaster
{
    /**
     * Captured broadcasts
     *
     * @var array<array<string, mixed>>
     */
    protected static array $broadcasts = [];

    /**
     * Connection name for this broadcaster instance
     *
     * @var string
     */
    protected string $connectionName = 'default';

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Broadcaster configuration
     */
    public function __construct(array $config = [])
    {
        $this->connectionName = $config['connectionName'] ?? 'default';
        unset($config['connectionName']);

        parent::__construct($config);
    }

    /**
     * Broadcast a message by capturing it instead of actually sending
     *
     * Overrides parent to store broadcast data instead of sending.
     *
     * @param array<string> $channels Channel names
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @return void
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $socket = $payload['socket'] ?? null;

        static::$broadcasts[] = [
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload,
            'connection' => $this->connectionName,
            'socket' => $socket,
            'timestamp' => time(),
        ];
    }

    /**
     * Replace all broadcasters with test broadcaster
     *
     * Similar to TestNotificationSender::replaceAllSenders()
     *
     * @param array<string>|string|null $connections Connection names to replace, or null for all configured
     * @return void
     */
    public static function replaceAllBroadcasters(string|array|null $connections = null): void
    {
        if ($connections === null) {
            $connections = Broadcasting::configured();
        } elseif (is_string($connections)) {
            $connections = [$connections];
        }

        foreach ($connections as $connection) {
            $connectionName = (string)$connection;
            $config = Broadcasting::getConfig($connectionName);
            Broadcasting::drop($connectionName);

            if ($config) {
                Broadcasting::setConfig($connectionName, [
                    'className' => static::class,
                    'connectionName' => $connectionName,
                ] + $config);
            }
        }

        Broadcasting::getRegistry()->reset();
    }

    /**
     * Get all captured broadcasts
     *
     * @return array<array<string, mixed>>
     */
    public static function getBroadcasts(): array
    {
        return static::$broadcasts;
    }

    /**
     * Clear all captured broadcasts
     *
     * @return void
     */
    public static function clearBroadcasts(): void
    {
        static::$broadcasts = [];
    }

    /**
     * Get broadcasts to a specific channel
     *
     * @param string $channel Channel name
     * @return array<array<string, mixed>>
     */
    public static function getBroadcastsToChannel(string $channel): array
    {
        return array_values(array_filter(
            static::$broadcasts,
            fn($b) => in_array($channel, $b['channels']),
        ));
    }

    /**
     * Get broadcasts of a specific event
     *
     * @param string $event Event name
     * @return array<array<string, mixed>>
     */
    public static function getBroadcastsByEvent(string $event): array
    {
        return array_values(array_filter(
            static::$broadcasts,
            fn($b) => $b['event'] === $event,
        ));
    }

    /**
     * Get broadcasts via a specific connection
     *
     * @param string $connection Connection name
     * @return array<array<string, mixed>>
     */
    public static function getBroadcastsByConnection(string $connection): array
    {
        return array_values(array_filter(
            static::$broadcasts,
            fn($b) => $b['connection'] === $connection,
        ));
    }

    /**
     * Get broadcasts excluding a specific socket
     *
     * @param string $socket Socket ID
     * @return array<array<string, mixed>>
     */
    public static function getBroadcastsExcludingSocket(string $socket): array
    {
        return array_values(array_filter(
            static::$broadcasts,
            fn($b) => $b['socket'] === $socket,
        ));
    }

    /**
     * Get broadcast for specific channel and event
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @return array<array<string, mixed>>
     */
    public static function getBroadcastsToChannelWithEvent(string $channel, string $event): array
    {
        return array_values(array_filter(
            static::$broadcasts,
            fn($b) => in_array($channel, $b['channels']) && $b['event'] === $event,
        ));
    }
}
