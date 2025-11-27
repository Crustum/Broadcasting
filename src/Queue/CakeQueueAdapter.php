<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Queue;

use Cake\Queue\QueueManager;

/**
 * CakePHP Queue Adapter
 *
 * Default implementation that wraps CakePHP's Cake\Queue\QueueManager.
 * Provides the standard queue functionality for broadcasting.
 *
 * @package Crustum\Broadcasting\Queue
 */
class CakeQueueAdapter implements QueueAdapterInterface
{
    /**
     * Push a job onto the queue.
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $data Job data
     * @param array<string, mixed> $options Job options
     * @return void
     */
    public function push(string $jobClass, array $data = [], array $options = []): void
    {
        QueueManager::push($jobClass, $data, $options);
    }

    /**
     * Generate a unique ID for a job.
     *
     * @param string $eventName Event name
     * @param string $type Job type
     * @param array<string, mixed> $data Job data
     * @return string Unique job ID
     */
    public function getUniqueId(string $eventName, string $type, array $data = []): string
    {
        return QueueManager::getUniqueId($eventName, $type, $data);
    }
}
