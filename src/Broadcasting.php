<?php
declare(strict_types=1);

namespace Crustum\Broadcasting;

use Cake\Core\Configure;
use Cake\Core\StaticConfigTrait;
use Cake\Log\Log;
use Crustum\Broadcasting\Broadcaster\BroadcasterInterface;
use Crustum\Broadcasting\Broadcaster\NullBroadcaster;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Channel\PresenceChannel;
use Crustum\Broadcasting\Channel\PrivateChannel;
use Crustum\Broadcasting\Event\BroadcastableInterface;
use Crustum\Broadcasting\Event\ConditionalInterface;
use Crustum\Broadcasting\Event\QueueableInterface;
use Crustum\Broadcasting\Exception\InvalidBroadcasterException;
use Crustum\Broadcasting\Job\BroadcastJob;
use Crustum\Broadcasting\Queue\CakeQueueAdapter;
use Crustum\Broadcasting\Queue\QueueAdapterInterface;
use Crustum\Broadcasting\Registry\BroadcasterRegistry;
use Exception;

/**
 * Broadcasting provides a consistent interface to Broadcasting in your application. It allows you
 * to use several different Broadcasting engines, without coupling your application to a specific
 * implementation. It also allows you to change out broadcaster storage or configuration without effecting
 * the rest of your application.
 *
 * ### Configuring Broadcasting engines
 *
 * You can configure Broadcasting engines in your application's `Config/broadcasting.php` file.
 * A sample configuration would be:
 *
 * ```
 * Broadcasting::config('pusher', [
 *    'className' => Crustum\Broadcasting\Broadcaster\PusherBroadcaster::class,
 *    'key' => 'your-app-key',
 *    'secret' => 'your-app-secret',
 *    'app_id' => 'your-app-id'
 * ]);
 * ```
 *
 * This would configure a Pusher broadcaster engine to the 'pusher' alias. You could then broadcast
 * to that broadcaster by using it for the `$config` parameter in the various Broadcasting methods.
 *
 * There are 4 built-in broadcasting engines:
 *
 * - `NullBroadcaster` - Discards all broadcast events, useful for development and testing.
 * - `LogBroadcaster` - Writes broadcast events to the application log for debugging.
 * - `PusherBroadcaster` - Uses Pusher service for real-time WebSocket connections.
 * - `RedisBroadcaster` - Uses Redis for self-hosted broadcasting services.
 *
 * See Broadcaster engine documentation for expected configuration keys.
 *
 * @see config/broadcasting.php for configuration settings
 */
class Broadcasting
{
    use StaticConfigTrait;

    /**
     * Flag for tracking whether broadcasting is enabled.
     *
     * @var bool
     */
    protected static bool $_enabled = true;

    /**
     * Broadcasting Registry used for creating and using broadcaster adapters.
     *
     * @var \Crustum\Broadcasting\Registry\BroadcasterRegistry
     */
    protected static BroadcasterRegistry $_registry;

    /**
     * Queue adapter instance
     *
     * @var \Crustum\Broadcasting\Queue\QueueAdapterInterface
     */
    protected static QueueAdapterInterface $_queueAdapter;

    /**
     * Whether channel callbacks have been loaded
     *
     * @var bool
     */
    protected static bool $_channelsLoaded = false;

    /**
     * Returns the Broadcasting Registry instance used for creating and using broadcaster adapters.
     *
     * @return \Crustum\Broadcasting\Registry\BroadcasterRegistry
     */
    public static function getRegistry(): BroadcasterRegistry
    {
        return static::$_registry ??= new BroadcasterRegistry();
    }

    /**
     * Sets the Broadcasting Registry instance used for creating and using broadcaster adapters.
     *
     * Also allows for injecting of a new registry instance.
     *
     * @param \Crustum\Broadcasting\Registry\BroadcasterRegistry $registry Injectable registry object.
     * @return void
     */
    public static function setRegistry(BroadcasterRegistry $registry): void
    {
        static::$_registry = $registry;
    }

    /**
     * Sets the queue adapter instance.
     *
     * @param \Crustum\Broadcasting\Queue\QueueAdapterInterface $queueAdapter Queue adapter instance
     * @return void
     */
    public static function setQueueAdapter(QueueAdapterInterface $queueAdapter): void
    {
        static::$_queueAdapter = $queueAdapter;
    }

    /**
     * Gets the queue adapter instance.
     *
     * @return \Crustum\Broadcasting\Queue\QueueAdapterInterface
     */
    public static function getQueueAdapter(): QueueAdapterInterface
    {
        if (!isset(static::$_queueAdapter)) {
            static::$_queueAdapter = new CakeQueueAdapter();
        }

        return static::$_queueAdapter;
    }

    /**
     * Finds and builds the instance of the required broadcaster class.
     *
     * @param string $name Name of the config array that needs a broadcaster instance built
     * @throws \Crustum\Broadcasting\Exception\InvalidBroadcasterException When a broadcaster cannot be created.
     * @throws \RuntimeException If loading of the broadcaster failed.
     * @return void
     */
    protected static function _buildBroadcaster(string $name): void
    {
        $registry = static::getRegistry();

        if (empty(static::$_config[$name]['className'])) {
            throw new InvalidBroadcasterException(
                sprintf('The `%s` broadcasting configuration does not exist.', $name),
            );
        }

        $config = static::$_config[$name];

        try {
            $registry->load($name, $config);
        } catch (Exception $e) {
            if (!array_key_exists('fallback', $config)) {
                $registry->set($name, new NullBroadcaster());
                trigger_error($e->getMessage(), E_USER_WARNING);

                return;
            }

            if ($config['fallback'] === false) {
                throw $e;
            }

            if ($config['fallback'] === $name) {
                throw new InvalidBroadcasterException(sprintf(
                    '`%s` broadcasting configuration cannot fallback to itself.',
                    $name,
                ), 0, $e);
            }

            $fallbackBroadcaster = clone static::get($config['fallback']);
            $registry->set($name, $fallbackBroadcaster);
        }
    }

    /**
     * Begin broadcasting to the given channels.
     *
     * @param \Crustum\Broadcasting\Channel\Channel|array<string|\Crustum\Broadcasting\Channel\Channel>|string $channels Channels
     * @return \Crustum\Broadcasting\PendingBroadcast
     */
    public static function to(Channel|array|string $channels): PendingBroadcast
    {
        return new PendingBroadcast($channels);
    }

    /**
     * Begin broadcasting to a private channel.
     *
     * @param string $channel Channel name
     * @return \Crustum\Broadcasting\PendingBroadcast
     */
    public static function private(string $channel): PendingBroadcast
    {
        return new PendingBroadcast(new PrivateChannel($channel));
    }

    /**
     * Begin broadcasting to a presence channel.
     *
     * @param string $channel Channel name
     * @return \Crustum\Broadcasting\PendingBroadcast
     */
    public static function presence(string $channel): PendingBroadcast
    {
        return new PendingBroadcast(new PresenceChannel($channel));
    }

    /**
     * Begin broadcasting an event object.
     *
     * @param \Crustum\Broadcasting\Event\BroadcastableInterface $event Event object
     * @return \Crustum\Broadcasting\PendingBroadcast
     */
    public static function event(BroadcastableInterface $event): PendingBroadcast
    {
        $channels = $event->broadcastChannel();
        $pending = new PendingBroadcast($channels);

        $eventName = $event->broadcastEvent();
        if ($eventName) {
            $pending->event($eventName);
        }

        $payload = $event->broadcastData();
        if ($payload) {
            $pending->data($payload);
        }

        if ($event->broadcastSocket()) {
            $pending->setSocket($event->broadcastSocket());
        }

        if ($event instanceof ConditionalInterface) {
            if (!$event->broadcastWhen()) {
                $pending->skip();

                return $pending;
            }
        }

        if ($event instanceof QueueableInterface) {
            $queueName = $event->broadcastQueue();

            $delay = $event->broadcastDelay();
            if ($delay !== null) {
                $pending->delay($delay);
            }

            $expires = $event->broadcastExpires();
            if ($expires !== null) {
                $pending->expires($expires);
            }

            $priority = $event->broadcastPriority();
            if ($priority !== null) {
                $pending->priority($priority);
            }

            $pending->queue($queueName);
        }

        return $pending;
    }

    /**
     * Get a BroadcasterInterface object for the named broadcaster connection.
     *
     * @param string $connection The name of the configured broadcaster connection.
     * @return \Crustum\Broadcasting\Broadcaster\BroadcasterInterface
     */
    public static function get(string $connection = 'default'): BroadcasterInterface
    {
        if (!static::$_enabled) {
            return new NullBroadcaster();
        }

        $registry = static::getRegistry();

        if ($registry->has($connection)) {
            return $registry->get($connection);
        }

        static::_buildBroadcaster($connection);

        return $registry->get($connection);
    }

    /**
     * Register a channel authorization callback.
     *
     * @param string $channel Channel pattern
     * @param callable|string $callback Authorization callback
     * @param array<string, mixed> $options Channel options
     * @param string $connection Connection name
     * @return void
     */
    public static function channel(string $channel, callable|string $callback, array $options = [], string $connection = 'default'): void
    {
        if (empty(static::$_config[$connection])) {
            return;
        }

        $broadcaster = static::get($connection);
        $broadcaster->registerChannel($channel, $callback, $options);
    }

    /**
     * Load channel authorization routes from file.
     *
     * @return void
     */
    public static function routes(): void
    {
        if (static::$_channelsLoaded) {
            return;
        }

        $channelsFile = Configure::read('Broadcasting.channels_file');

        if (!$channelsFile) {
            $channelsFile = CONFIG . 'channels.php';
        }

        if (file_exists($channelsFile)) {
            include $channelsFile;
        }

        static::$_channelsLoaded = true;
    }

    /**
     * Broadcast a message to channels using the specified broadcaster.
     *
     * ### Usage:
     *
     * Broadcasting to the default broadcaster:
     *
     * ```
     * Broadcasting::broadcast('channel-name', 'event-name', ['data' => 'value']);
     * ```
     *
     * Broadcasting to a specific broadcaster:
     *
     * ```
     * Broadcasting::broadcast('channel-name', 'event-name', ['data' => 'value'], 'pusher');
     * ```
     *
     * Broadcasting with socket exclusion:
     *
     * ```
     * Broadcasting::broadcast('channel-name', 'event-name', ['data' => 'value'], 'pusher', 'socket-123');
     * ```
     *
     * @param array<string>|string $channels Channel names to broadcast to
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @param string $config Optional string configuration name to broadcast to. Defaults to 'default'
     * @param string|null $socket Optional socket ID to exclude from broadcast
     * @return void
     */
    public static function broadcast(string|array $channels, string $event, array $payload = [], string $config = 'default', ?string $socket = null): void
    {
        $pending = static::to($channels)
            ->event($event)
            ->data($payload)
            ->connection($config);

        if ($socket !== null) {
            $pending->setSocket($socket);
        }

        $pending->send();
    }

    /**
     * Internal method to queue a broadcast event.
     *
     * @param array<string>|string $channels Channel names to broadcast to
     * @param string $event Event name
     * @param array<string, mixed> $payload Message payload
     * @param string $config Configuration name
     * @param array<string, mixed> $options Queue options
     * @return void
     * @internal
     */
    public static function queueBroadcast(string|array $channels, string $event, array $payload = [], string $config = 'default', array $options = []): void
    {
        $channelArray = is_array($channels) ? $channels : [$channels];

        $jobData = [
            'eventName' => $event,
            'channels' => $channelArray,
            'payload' => $payload,
            'config' => $config,
        ];

        try {
            static::getQueueAdapter()->push(BroadcastJob::class, $jobData, $options);

            Log::info(__(
                'Broadcast event {0} queued successfully for channels {1} with config {2}',
                $event,
                implode(', ', $channelArray),
                $config,
            ));
        } catch (Exception $e) {
            Log::error(__(
                'Failed to queue broadcast event {0} for channels {1} with config {2}: {3}',
                $event,
                implode(', ', $channelArray),
                $config,
                $e->getMessage(),
            ));
        }
    }

    /**
     * Re-enable broadcasting.
     *
     * If broadcasting has been disabled with Broadcasting::disable() this method will reverse that effect.
     *
     * @return void
     */
    public static function enable(): void
    {
        static::$_enabled = true;
    }

    /**
     * Disable broadcasting.
     *
     * When disabled all broadcast operations will use the null broadcaster.
     *
     * @return void
     */
    public static function disable(): void
    {
        static::$_enabled = false;
    }

    /**
     * Check whether broadcasting is enabled.
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return static::$_enabled;
    }

    /**
     * Get the list of configured broadcasters.
     *
     * @return list<int|string> List of broadcaster names.
     */
    public static function configured(): array
    {
        return array_keys(static::$_config);
    }
}
