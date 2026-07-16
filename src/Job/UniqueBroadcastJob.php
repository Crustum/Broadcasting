<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Job;

/**
 * Unique Broadcast Job
 *
 * CakePHP Queue unique job for broadcast events. Relies on
 * `QueueManager` + `uniqueCache` config (`Job::$shouldBeUnique`), not a
 * Laravel-style ShouldBeUnique interface.
 *
 * @package Crustum\Broadcasting\Job
 * @see \Cake\Queue\QueueManager::push()
 */
class UniqueBroadcastJob extends BroadcastJob
{
    /**
     * Whether only one instance of this job (by data hash) may be queued.
     *
     * @var bool
     */
    public static bool $shouldBeUnique = true;
}
