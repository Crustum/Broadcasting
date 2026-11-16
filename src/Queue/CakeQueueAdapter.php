<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Queue;

use Cake\Cache\Cache;
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
        // @phpstan-ignore-next-line
        return QueueManager::getUniqueId($eventName, $type, $data);
    }

    /**
     * Check whether pushing the given unique job would be skipped as a duplicate.
     *
     * Probes the queue connection `uniqueCache` with the same unique id
     * `QueueManager::push()` computes (class, `execute` method, job data), so
     * the check never duplicates vendor key internals. Returns false when the
     * job class does not opt into uniqueness or when the connection has no
     * `uniqueCache` configured (in which case `push()` throws).
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $data Job data
     * @param array<string, mixed> $options Job options
     * @return bool True when an identical unique job is already queued
     */
    public function isDuplicate(string $jobClass, array $data = [], array $options = []): bool
    {
        if (empty($jobClass::$shouldBeUnique)) {
            return false;
        }

        $config = QueueManager::getConfig($options['config'] ?? 'default');
        if (!is_array($config) || empty($config['uniqueCache']) || empty($config['uniqueCacheKey'])) {
            return false;
        }

        return (bool)Cache::read($this->getUniqueId($jobClass, 'execute', $data), $config['uniqueCacheKey']);
    }
}
