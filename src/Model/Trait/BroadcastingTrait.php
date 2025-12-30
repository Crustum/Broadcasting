<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Model\Trait;

use Cake\Datasource\EntityInterface;
use Closure;
use Crustum\Broadcasting\Broadcasting;

/**
 * Broadcasting Trait
 *
 * Provides broadcasting methods for tables. Use this trait in your table class
 * along with the BroadcastingBehavior for event handling.
 *
 * The table class must implement BroadcastingTraitInterface when using this trait.
 *
 * Usage:
 * ```
 * use Crustum\Broadcasting\Model\Interface\BroadcastingTraitInterface;
 * use Crustum\Broadcasting\Model\Trait\BroadcastingTrait;
 *
 * class UsersTable extends Table implements BroadcastingTraitInterface
 * {
 *     use BroadcastingTrait;
 *
 *     public function initialize(array $config): void
 *     {
 *         $this->addBehavior('Crustum/Broadcasting.Broadcasting');
 *     }
 * }
 * ```
 */
trait BroadcastingTrait
{
    /**
     * Broadcasting enabled flag
     *
     * @var bool
     */
    private bool $_broadcastingEnabled = true;

    /**
     * Broadcasting channels configuration
     *
     * @var \Closure|array<mixed>|null
     */
    private Closure|array|null $_broadcastingChannels = null;

    /**
     * Broadcasting payload configuration
     *
     * @var \Cake\Datasource\EntityInterface|\Closure|array<string, mixed>|null
     */
    private Closure|array|EntityInterface|null $_broadcastingPayload = null;

    /**
     * Broadcasting connection configuration
     *
     * @var \Closure|string|null
     */
    private Closure|string|null $_broadcastingConnection = 'default';

    /**
     * Broadcasting queue configuration
     *
     * @var string|null
     */
    private ?string $_broadcastingQueue = null;

    /**
     * Broadcasting event name configuration
     *
     * @var \Closure|string|null
     */
    private Closure|string|null $_broadcastingEventName = null;

    /**
     * Broadcasting events configuration
     *
     * @var array<string, bool>
     */
    private array $_broadcastingEvents = [
        'created' => true,
        'updated' => true,
        'deleted' => true,
    ];

    /**
     * Broadcast a model event
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The broadcast event name
     * @return void
     */
    public function broadcastEvent(EntityInterface $entity, string $event): void
    {
        if (!$this->_broadcastingEnabled) {
            return;
        }

        if (isset($this->_broadcastingEvents[$event]) && !$this->_broadcastingEvents[$event]) {
            return;
        }

        $channels = $this->getBroadcastChannels($entity, $event);
        $payload = $this->getBroadcastPayload($entity, $event);
        $connection = $this->getBroadcastConnection($entity);

        if (!empty($channels)) {
            $eventName = $this->getEventName($entity, $event);
            $connectionName = $connection ?? 'default';
            $queue = $this->_broadcastingQueue;

            $pending = Broadcasting::to($channels)
                ->event($eventName)
                ->data($payload)
                ->connection($connectionName);

            if ($queue !== null) {
                $pending->queue($queue);
            } else {
                $pending->send();
            }
        }
    }

    /**
     * Enable broadcasting
     *
     * @return void
     */
    public function enableBroadcasting(): void
    {
        $this->_broadcastingEnabled = true;
    }

    /**
     * Disable broadcasting
     *
     * @return void
     */
    public function disableBroadcasting(): void
    {
        $this->_broadcastingEnabled = false;
    }

    /**
     * Check if broadcasting is enabled
     *
     * @return bool
     */
    public function isBroadcastingEnabled(): bool
    {
        return $this->_broadcastingEnabled;
    }

    /**
     * Set custom channels for broadcasting
     *
     * @param \Closure|array<mixed>|null $channels Channels configuration
     * @return void
     */
    public function setBroadcastChannels(Closure|array|null $channels): void
    {
        $this->_broadcastingChannels = $channels;
    }

    /**
     * Set custom payload for broadcasting
     *
     * @param \Cake\Datasource\EntityInterface|\Closure|array<string, mixed>|null $payload Payload configuration
     * @return void
     */
    public function setBroadcastPayload(Closure|array|EntityInterface|null $payload): void
    {
        $this->_broadcastingPayload = $payload;
    }

    /**
     * Set broadcast connection
     *
     * @param \Closure|string|null $connection Connection name or callback
     * @return void
     */
    public function setBroadcastConnection(string|Closure|null $connection): void
    {
        $this->_broadcastingConnection = $connection;
    }

    /**
     * Set broadcast queue
     *
     * @param string|null $queue Queue name
     * @return void
     */
    public function setBroadcastQueue(?string $queue): void
    {
        $this->_broadcastingQueue = $queue;
    }

    /**
     * Set custom event name for broadcasting
     *
     * @param \Closure|string|null $eventName Event name or callback
     * @return void
     */
    public function setBroadcastEventName(Closure|string|null $eventName): void
    {
        $this->_broadcastingEventName = $eventName;
    }

    /**
     * Set which broadcast events are enabled
     *
     * @param array<string, bool> $events Event configuration
     * @return void
     */
    public function setBroadcastEvents(array $events): void
    {
        $this->_broadcastingEvents = $events;
    }

    /**
     * Enable a specific broadcast event
     *
     * @param string $event Event name (created, updated, deleted, etc.)
     * @return void
     */
    public function enableBroadcastEvent(string $event): void
    {
        $this->_broadcastingEvents[$event] = true;
    }

    /**
     * Disable a specific broadcast event
     *
     * @param string $event Event name (created, updated, deleted, etc.)
     * @return void
     */
    public function disableBroadcastEvent(string $event): void
    {
        $this->_broadcastingEvents[$event] = false;
    }

    /**
     * Get the channels to broadcast on
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The event name
     * @return array<mixed> Array of channels
     */
    private function getBroadcastChannels(EntityInterface $entity, string $event): array
    {
        $channels = $this->_broadcastingChannels;

        if ($channels instanceof Closure) {
            $result = $channels($entity, $event);
            $channelsArray = is_array($result) ? $result : [$result];
        } elseif (is_array($channels)) {
            $channelsArray = $channels;
        } else {
            $channelsArray = [$entity];
        }

        return $this->convertEntitiesToChannelNames($channelsArray);
    }

    /**
     * Convert entity instances to Laravel-style channel names
     *
     * @param array<mixed> $channels Array of channels or entities
     * @return array<string> Array of channel names
     */
    private function convertEntitiesToChannelNames(array $channels): array
    {
        $converted = [];
        foreach ($channels as $channel) {
            if ($channel instanceof EntityInterface) {
                $entityClass = get_class($channel);
                $channelName = str_replace('\\', '.', $entityClass);
                $id = $channel->get('id');
                if ($id !== null) {
                    $channelName .= '.' . $id;
                }
                $converted[] = $channelName;
            } else {
                $converted[] = $channel;
            }
        }

        return $converted;
    }

    /**
     * Get the payload to broadcast
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The event name
     * @return array<string, mixed>
     */
    private function getBroadcastPayload(EntityInterface $entity, string $event): array
    {
        $payload = $this->_broadcastingPayload;

        if ($payload instanceof Closure) {
            return $payload($entity, $event);
        }

        if (is_array($payload)) {
            return $payload;
        }

        if ($payload !== null) {
            return $payload->toArray();
        }
        $data = $entity->toArray();
        $data['event_type'] = $event;

        return $data;
    }

    /**
     * Get the broadcast connection
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @return string|null
     */
    private function getBroadcastConnection(EntityInterface $entity): ?string
    {
        $connection = $this->_broadcastingConnection;

        if ($connection instanceof Closure) {
            return $connection($entity);
        }

        return $connection;
    }

    /**
     * Get the event name for broadcasting
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The event name
     * @return string
     */
    private function getEventName(EntityInterface $entity, string $event): string
    {
        $eventNameConfig = $this->_broadcastingEventName;

        if ($eventNameConfig instanceof Closure) {
            $result = $eventNameConfig($entity, $event);
            if ($result !== null) {
                return $result;
            }
        }

        if (is_string($eventNameConfig)) {
            return $eventNameConfig;
        }

        $entityClass = get_class($entity);
        $className = substr($entityClass, strrpos($entityClass, '\\') + 1);

        return $className . ucfirst($event);
    }
}
