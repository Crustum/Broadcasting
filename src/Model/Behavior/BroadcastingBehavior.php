<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Crustum\Broadcasting\Broadcasting;

/**
 * Broadcasting Behavior
 *
 * Enables automatic broadcasting of model lifecycle events.
 *
 * Examples:
 *
 * Basic usage:
 * ```
 * $this->addBehavior('Broadcasting.Broadcasting', [
 *     'events' => [
 *         'Model.afterSave' => 'saved',
 *         'Model.afterDelete' => 'deleted',
 *     ]
 * ]);
 * ```
 *
 * The behavior automatically maps to Laravel event names:
 * - New entities (isNew() = true) → broadcast 'created' event
 * - Existing entities (isNew() = false) → broadcast 'updated' event
 * - Deleted entities → broadcast 'deleted' event
 *
 * Custom event mapping:
 * ```
 * $this->addBehavior('Broadcasting.Broadcasting', [
 *     'events' => [
 *         'Model.afterSave' => 'created',
 *         'Model.afterUpdate' => 'updated',
 *     ],
 *     'connection' => 'pusher',
 *     'queue' => 'broadcasts',
 * ]);
 * ```
 *
 * Custom channel callback:
 * ```
 * $this->addBehavior('Broadcasting.Broadcasting', [
 *     'events' => [
 *         'Model.afterSave' => 'created',
 *     ],
 *     'channels' => function (EntityInterface $entity, string $event) {
 *         return ['user.' . $entity->get('user_id')];
 *     }
 * ]);
 * ```
 *
 * Custom payload callback:
 * ```
 * $this->addBehavior('Broadcasting.Broadcasting', [
 *     'events' => [
 *         'Model.afterSave' => 'saved',
 *     ],
 *     'payload' => function (EntityInterface $entity, string $event) {
 *         return [
 *             'id' => $entity->get('id'),
 *             'name' => $entity->get('name'),
 *             'event' => $event
 *         ];
 *     }
 * ]);
 * ```
 */
class BroadcastingBehavior extends Behavior
{
    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'implementedFinders' => [],
        'implementedMethods' => [
            'broadcastEvent' => 'broadcastEvent',
            'enableBroadcasting' => 'enableBroadcasting',
            'disableBroadcasting' => 'disableBroadcasting',
            'isBroadcastingEnabled' => 'isBroadcastingEnabled',
            'setBroadcastChannels' => 'setBroadcastChannels',
            'setBroadcastPayload' => 'setBroadcastPayload',
            'setBroadcastConnection' => 'setBroadcastConnection',
            'setBroadcastQueue' => 'setBroadcastQueue',
            'setBroadcastEventName' => 'setBroadcastEventName',
            'setBroadcastEvents' => 'setBroadcastEvents',
            'enableBroadcastEvent' => 'enableBroadcastEvent',
            'disableBroadcastEvent' => 'disableBroadcastEvent',
        ],
        'events' => [
            'Model.afterSave' => 'saved',
            'Model.afterDelete' => 'deleted',
        ],
        'broadcastEvents' => [
            'created' => true,
            'updated' => true,
            'deleted' => true,
        ],
        'connection' => 'default',
        'queue' => null,
        'channels' => null,
        'payload' => null,
        'enabled' => true,
    ];

    /**
     * Initialize the behavior
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        if (isset($config['events'])) {
            $this->setConfig('events', $config['events'], false);
        }
    }

    /**
     * Get the list of events this behavior is interested in
     *
     * @return array<int|string, mixed>
     */
    public function implementedEvents(): array
    {
        return array_fill_keys(array_keys($this->_config['events']), 'handleEvent');
    }

    /**
     * Handle model lifecycle events
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event The model event
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @return void
     */
    public function handleEvent(EventInterface $event, EntityInterface $entity): void
    {
        if (!$this->getConfig('enabled')) {
            return;
        }

        $eventName = $event->getName();

        if ($eventName === 'Model.afterSave') {
            $broadcastEvent = $entity->isNew() ? 'created' : 'updated';
        } else {
            $broadcastEvent = $this->_config['events'][$eventName];
        }

        $broadcastEvents = $this->getConfig('broadcastEvents', []);
        if (isset($broadcastEvents[$broadcastEvent]) && !$broadcastEvents[$broadcastEvent]) {
            return;
        }

        $this->broadcastEvent($entity, $broadcastEvent);
    }

    /**
     * Broadcast a model event
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The broadcast event name
     * @return void
     */
    public function broadcastEvent(EntityInterface $entity, string $event): void
    {
        if (!$this->getConfig('enabled')) {
            return;
        }

        $channels = $this->getBroadcastChannels($entity, $event);
        $payload = $this->getBroadcastPayload($entity, $event);
        $connection = $this->getBroadcastConnection($entity);

        if (!empty($channels)) {
            $eventName = $this->getEventName($entity, $event);
            $connectionName = $connection ?? 'default';
            $queue = $this->getConfig('queue');

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
     * Get the channels to broadcast on
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The event name
     * @return array<mixed> Array of channels
     */
    protected function getBroadcastChannels(EntityInterface $entity, string $event): array
    {
        $channels = $this->getConfig('channels');

        if (is_callable($channels)) {
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
    protected function convertEntitiesToChannelNames(array $channels): array
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
    protected function getBroadcastPayload(EntityInterface $entity, string $event): array
    {
        $payload = $this->getConfig('payload');

        if (is_callable($payload)) {
            return $payload($entity, $event);
        }

        if (is_array($payload)) {
            return $payload;
        }
        if ($payload instanceof EntityInterface) {
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
    protected function getBroadcastConnection(EntityInterface $entity): ?string
    {
        $connection = $this->getConfig('connection');

        if (is_callable($connection)) {
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
    protected function getEventName(EntityInterface $entity, string $event): string
    {
        $eventNameConfig = $this->getConfig('eventName');

        if (is_callable($eventNameConfig)) {
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

    /**
     * Enable broadcasting
     *
     * @return void
     */
    public function enableBroadcasting(): void
    {
        $this->setConfig('enabled', true);
    }

    /**
     * Disable broadcasting
     *
     * @return void
     */
    public function disableBroadcasting(): void
    {
        $this->setConfig('enabled', false);
    }

    /**
     * Check if broadcasting is enabled
     *
     * @return bool
     */
    public function isBroadcastingEnabled(): bool
    {
        return $this->getConfig('enabled');
    }

    /**
     * Set custom channels for broadcasting
     *
     * @param callable|array<mixed>|null $channels Channels configuration
     * @return void
     */
    public function setBroadcastChannels(callable|array|null $channels): void
    {
        $this->setConfig('channels', $channels);
    }

    /**
     * Set custom payload for broadcasting
     *
     * @param callable|array<string, mixed>|null $payload Payload configuration
     * @return void
     */
    public function setBroadcastPayload(callable|array|null $payload): void
    {
        $this->setConfig('payload', $payload);
    }

    /**
     * Set broadcast connection
     *
     * @param callable|string|null $connection Connection name or callback
     * @return void
     */
    public function setBroadcastConnection(string|callable|null $connection): void
    {
        $this->setConfig('connection', $connection);
    }

    /**
     * Set custom event name for broadcasting
     *
     * @param callable|string|null $eventName Event name or callback
     * @return void
     */
    public function setBroadcastEventName(callable|string|null $eventName): void
    {
        $this->setConfig('eventName', $eventName);
    }

    /**
     * Set which broadcast events are enabled
     *
     * @param array<string, bool> $events Event configuration
     * @return void
     */
    public function setBroadcastEvents(array $events): void
    {
        $this->setConfig('broadcastEvents', $events);
    }

    /**
     * Enable a specific broadcast event
     *
     * @param string $event Event name (created, updated, deleted, etc.)
     * @return void
     */
    public function enableBroadcastEvent(string $event): void
    {
        $broadcastEvents = $this->getConfig('broadcastEvents', []);
        $broadcastEvents[$event] = true;
        $this->setConfig('broadcastEvents', $broadcastEvents);
    }

    /**
     * Disable a specific broadcast event
     *
     * @param string $event Event name (created, updated, deleted, etc.)
     * @return void
     */
    public function disableBroadcastEvent(string $event): void
    {
        $broadcastEvents = $this->getConfig('broadcastEvents', []);
        $broadcastEvents[$event] = false;
        $this->setConfig('broadcastEvents', $broadcastEvents);
    }

    /**
     * Set broadcast queue
     *
     * @param string|null $queue Queue name
     * @return void
     */
    public function setBroadcastQueue(?string $queue): void
    {
        $this->setConfig('queue', $queue);
    }
}
