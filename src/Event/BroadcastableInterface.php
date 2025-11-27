<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Event;

use Crustum\Broadcasting\Channel\Channel;

/**
 * Broadcastable Interface
 *
 * Defines the contract for events that can be broadcast.
 *
 * @package Crustum\Broadcasting\Event
 */
interface BroadcastableInterface
{
    /**
     * Get the event name for broadcasting.
     *
     * @return string
     */
    public function broadcastEvent(): string;

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Crustum\Broadcasting\Channel\Channel|array<\Crustum\Broadcasting\Channel\Channel>
     */
    public function broadcastChannel(): Channel|array;

    /**
     * Get the socket ID to exclude from receiving the event.
     *
     * @return string|null
     */
    public function broadcastSocket(): ?string;

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>|null
     */
    public function broadcastData(): ?array;
}
