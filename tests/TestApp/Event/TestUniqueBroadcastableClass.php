<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Event;

/**
 * Queueable event that opts into Cake Queue uniqueness via broadcastUnique().
 */
class TestUniqueBroadcastableClass extends TestBroadcastableClass
{
    /**
     * Whether the broadcast job should be unique.
     *
     * @return bool
     */
    public function broadcastUnique(): bool
    {
        return true;
    }

    /**
     * Optional key folded into Cake Queue getUniqueId data hash.
     *
     * @return string
     */
    public function broadcastUniqueKey(): string
    {
        return 'unique-event-key';
    }
}
