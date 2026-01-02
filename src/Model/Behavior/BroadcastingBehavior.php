<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Crustum\Broadcasting\Model\Interface\BroadcastingTraitInterface;
use LogicException;

/**
 * Broadcasting Behavior
 *
 * Enables automatic broadcasting of model lifecycle events.
 *
 * The table class must use BroadcastingTrait and implement BroadcastingTraitInterface.
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
 *         $this->addBehavior('Crustum/Broadcasting.Broadcasting', [
 *             'events' => [
 *                 'Model.afterSave' => 'saved',
 *                 'Model.afterDelete' => 'deleted',
 *             ]
 *         ]);
 *     }
 * }
 * ```
 *
 * Examples:
 *
 * Basic usage:
 * ```
 * $this->addBehavior('Crustum/Broadcasting.Broadcasting', [
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
 * $this->addBehavior('Crustum/Broadcasting.Broadcasting', [
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
 * $this->addBehavior('Crustum/Broadcasting.Broadcasting', [
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
 * $this->addBehavior('Crustum/Broadcasting.Broadcasting', [
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
        'events' => [
            'Model.afterSave' => 'saved',
            'Model.afterDelete' => 'deleted',
        ],
    ];

    /**
     * Initialize the behavior
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     * @throws \LogicException If table does not implement BroadcastingTraitInterface
     */
    public function initialize(array $config): void
    {
        if (isset($config['events'])) {
            $this->setConfig('events', $config['events'], false);
        }

        if (!$this->_table instanceof BroadcastingTraitInterface) {
            throw new LogicException(
                sprintf(
                    'Table %s must use BroadcastingTrait and implement BroadcastingTraitInterface.',
                    get_class($this->_table),
                ),
            );
        }

        /** @var \Cake\ORM\Table&\Crustum\Broadcasting\Model\Interface\BroadcastingTraitInterface $table */
        $table = $this->_table;

        if (isset($config['enabled'])) {
            if ($config['enabled']) {
                $table->enableBroadcasting();
            } else {
                $table->disableBroadcasting();
            }
        }

        if (isset($config['channels'])) {
            $table->setBroadcastChannels($config['channels']);
        }

        if (isset($config['payload'])) {
            $table->setBroadcastPayload($config['payload']);
        }

        if (isset($config['connection'])) {
            $table->setBroadcastConnection($config['connection']);
        }

        if (isset($config['queue'])) {
            $table->setBroadcastQueue($config['queue']);
        }

        if (isset($config['eventName'])) {
            $table->setBroadcastEventName($config['eventName']);
        }

        if (isset($config['broadcastEvents'])) {
            $table->setBroadcastEvents($config['broadcastEvents']);
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
        $eventName = $event->getName();

        if ($eventName === 'Model.afterSave') {
            $broadcastEvent = $entity->isNew() ? 'created' : 'updated';
        } else {
            $broadcastEvent = $this->_config['events'][$eventName];
        }

        /** @var \Cake\ORM\Table&\Crustum\Broadcasting\Model\Interface\BroadcastingTraitInterface $table */
        $table = $event->getSubject();
        $table->broadcastEvent($entity, $broadcastEvent);
    }
}
