<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Event;

/**
 * Event whose child property overrides the inherited attribute.
 */
class UniqueForPropertyEvent extends UniqueForAttributeEvent
{
    /**
     * @var int
     */
    public int $uniqueFor = 180;
}
