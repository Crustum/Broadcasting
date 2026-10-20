<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Event;

/**
 * Event whose uniqueFor() method overrides the attribute.
 */
class UniqueForMethodEvent extends UniqueForAttributeEvent
{
    /**
     * @return int
     */
    public function uniqueFor(): int
    {
        return 120;
    }
}
