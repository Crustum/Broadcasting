<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Queue;

/**
 * Queue Adapter Interface
 *
 * Defines the contract for queue adapter implementations.
 * Provides abstraction for different queue backends.
 *
 * @package Crustum\Broadcasting\Queue
 */
interface QueueAdapterInterface
{
    /**
     * Push a job onto the queue.
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $data Job data
     * @param array<string, mixed> $options Job options
     * @return void
     */
    public function push(string $jobClass, array $data = [], array $options = []): void;

    /**
     * Generate a unique ID for a job.
     *
     * @param string $eventName Event name
     * @param string $type Job type
     * @param array<string, mixed> $data Job data
     * @return string Unique job ID
     */
    public function getUniqueId(string $eventName, string $type, array $data = []): string;
}
