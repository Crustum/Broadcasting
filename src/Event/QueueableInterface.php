<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Event;

/**
 * Queueable Interface
 *
 * Defines the contract for broadcasting using queues.
 * Methods map to CakePHP Queue plugin options.
 *
 * @package Crustum\Broadcasting\Event
 */
interface QueueableInterface
{
    /**
     * Get the queue name that should process the broadcast.
     *
     * @return string|null
     */
    public function broadcastQueue(): ?string;

    /**
     * Get the delay before processing (in seconds).
     *
     * @return int|null
     */
    public function broadcastDelay(): ?int;

    /**
     * Get the message expiration time (in seconds).
     *
     * @return int|null
     */
    public function broadcastExpires(): ?int;

    /**
     * Get the message priority.
     *
     * @return string|null
     */
    public function broadcastPriority(): ?string;
}
