<?php
declare(strict_types=1);

use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Event\BroadcastableInterface;
use Crustum\Broadcasting\PendingBroadcast;

if (!function_exists('broadcast')) {
    /**
     * Begin broadcasting an event.
     *
     * @param \Crustum\Broadcasting\Event\BroadcastableInterface $event Event object
     * @return \Crustum\Broadcasting\PendingBroadcast
     */
    function broadcast(BroadcastableInterface $event): PendingBroadcast
    {
        return Broadcasting::event($event);
    }
}
