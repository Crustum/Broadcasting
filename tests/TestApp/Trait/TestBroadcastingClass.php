<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Trait;

use Crustum\Broadcasting\Event\BroadcastEvent;
use Crustum\Broadcasting\Trait\BroadcastingTrait;

/**
 * Test class using BroadcastingTrait.
 */
class TestBroadcastingClass extends BroadcastEvent
{
    use BroadcastingTrait;

    /**
     * Create a new broadcast event instance.
     */
    public function __construct()
    {
        parent::__construct('TestEvent', []);
    }
}
