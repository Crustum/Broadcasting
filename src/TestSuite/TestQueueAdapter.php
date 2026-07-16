<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite;

use Cake\Queue\QueueManager;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Job\BroadcastJob;
use Crustum\Broadcasting\Queue\QueueAdapterInterface;

/**
 * Test Queue Adapter
 *
 * Captures queued jobs instead of actually queuing them for testing purposes.
 * Simulates CakePHP Queue `$shouldBeUnique` deduplication via getUniqueId hash.
 *
 * Usage:
 * ```
 * // In test setup
 * TestQueueAdapter::replaceQueueAdapter();
 *
 * // Queue as normal
 * Broadcasting::to('orders')->event('OrderCreated')->queue();
 *
 * // Make assertions
 * $queued = TestQueueAdapter::getQueuedJobs();
 * ```
 */
class TestQueueAdapter implements QueueAdapterInterface
{
    /**
     * Captured queued jobs
     *
     * @var array<array<string, mixed>>
     */
    protected static array $queuedJobs = [];

    /**
     * Active unique job ids (Cake Queue uniqueCache simulation)
     *
     * @var array<string, true>
     */
    protected static array $uniqueJobIds = [];

    /**
     * Push a job onto the queue (capture it instead)
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $data Job data
     * @param array<string, mixed> $options Job options
     * @return void
     */
    public function push(string $jobClass, array $data = [], array $options = []): void
    {
        if (!empty($jobClass::$shouldBeUnique)) {
            $uniqueId = $this->getUniqueId($jobClass, 'execute', $data);
            if (isset(static::$uniqueJobIds[$uniqueId])) {
                return;
            }

            static::$uniqueJobIds[$uniqueId] = true;
        }

        static::$queuedJobs[] = [
            'jobClass' => $jobClass,
            'data' => $data,
            'options' => $options,
            'timestamp' => time(),
        ];
    }

    /**
     * Generate a unique ID for a job
     *
     * @param string $eventName Event name or job class
     * @param string $type Job type / method
     * @param array<string, mixed> $data Job data
     * @return string Unique job ID
     */
    public function getUniqueId(string $eventName, string $type, array $data = []): string
    {
        // @phpstan-ignore-next-line
        return QueueManager::getUniqueId($eventName, $type, $data);
    }

    /**
     * Replace the queue adapter with test adapter
     *
     * @return void
     */
    public static function replaceQueueAdapter(): void
    {
        Broadcasting::setQueueAdapter(new self());
    }

    /**
     * Get all queued jobs
     *
     * @return array<array<string, mixed>>
     */
    public static function getQueuedJobs(): array
    {
        return static::$queuedJobs;
    }

    /**
     * Get queued jobs by job class
     *
     * @param string $jobClass Job class name
     * @return array<array<string, mixed>>
     */
    public static function getQueuedJobsByClass(string $jobClass): array
    {
        $filtered = array_filter(static::$queuedJobs, fn(array $job): bool => $job['jobClass'] === $jobClass);

        return array_values($filtered);
    }

    /**
     * Get queued broadcast jobs by event name
     *
     * @param string $eventName Event name
     * @return array<array<string, mixed>>
     */
    public static function getQueuedBroadcastsByEvent(string $eventName): array
    {
        $broadcastJobs = array_filter(
            static::$queuedJobs,
            fn(array $job): bool => is_a($job['jobClass'], BroadcastJob::class, true),
        );

        $filtered = array_filter($broadcastJobs, fn(array $job): bool => isset($job['data']['eventName']) && $job['data']['eventName'] === $eventName);

        return array_values($filtered);
    }

    /**
     * Get queued broadcast jobs by channel
     *
     * @param string $channel Channel name
     * @return array<array<string, mixed>>
     */
    public static function getQueuedBroadcastsByChannel(string $channel): array
    {
        $broadcastJobs = array_filter(
            static::$queuedJobs,
            fn(array $job): bool => is_a($job['jobClass'], BroadcastJob::class, true),
        );

        $filtered = array_filter($broadcastJobs, function (array $job) use ($channel): bool {
            $channels = $job['data']['channels'] ?? [];
            if (is_array($channels)) {
                return in_array($channel, $channels);
            }

            return $channels === $channel;
        });

        return array_values($filtered);
    }

    /**
     * Clear all queued jobs and unique locks
     *
     * @return void
     */
    public static function clearQueuedJobs(): void
    {
        static::$queuedJobs = [];
        static::$uniqueJobIds = [];
    }

    /**
     * Get count of queued jobs
     *
     * @return int
     */
    public static function getQueuedJobCount(): int
    {
        return count(static::$queuedJobs);
    }
}
