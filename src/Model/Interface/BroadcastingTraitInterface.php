<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Model\Interface;

use Cake\Datasource\EntityInterface;
use Closure;

/**
 * Broadcasting Trait Interface
 *
 * Interface that must be implemented by tables using BroadcastingTrait.
 * This allows BroadcastingBehavior to type-check the table instance.
 */
interface BroadcastingTraitInterface
{
    /**
     * Broadcast a model event
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $event The broadcast event name
     * @return void
     */
    public function broadcastEvent(EntityInterface $entity, string $event): void;

    /**
     * Enable broadcasting
     *
     * @return void
     */
    public function enableBroadcasting(): void;

    /**
     * Disable broadcasting
     *
     * @return void
     */
    public function disableBroadcasting(): void;

    /**
     * Check if broadcasting is enabled
     *
     * @return bool
     */
    public function isBroadcastingEnabled(): bool;

    /**
     * Set custom channels for broadcasting
     *
     * @param \Closure|array<mixed>|null $channels Channels configuration
     * @return void
     */
    public function setBroadcastChannels(Closure|array|null $channels): void;

    /**
     * Set custom payload for broadcasting
     *
     * @param \Cake\Datasource\EntityInterface|\Closure|array<string, mixed>|null $payload Payload configuration
     * @return void
     */
    public function setBroadcastPayload(Closure|array|EntityInterface|null $payload): void;

    /**
     * Set broadcast connection
     *
     * @param \Closure|string|null $connection Connection name or callback
     * @return void
     */
    public function setBroadcastConnection(string|Closure|null $connection): void;

    /**
     * Set broadcast queue
     *
     * @param string|null $queue Queue name
     * @return void
     */
    public function setBroadcastQueue(?string $queue): void;

    /**
     * Set custom event name for broadcasting
     *
     * @param \Closure|string|null $eventName Event name or callback
     * @return void
     */
    public function setBroadcastEventName(Closure|string|null $eventName): void;

    /**
     * Set which broadcast events are enabled
     *
     * @param array<string, bool> $events Event configuration
     * @return void
     */
    public function setBroadcastEvents(array $events): void;

    /**
     * Enable a specific broadcast event
     *
     * @param string $event Event name (created, updated, deleted, etc.)
     * @return void
     */
    public function enableBroadcastEvent(string $event): void;

    /**
     * Disable a specific broadcast event
     *
     * @param string $event Event name (created, updated, deleted, etc.)
     * @return void
     */
    public function disableBroadcastEvent(string $event): void;
}
