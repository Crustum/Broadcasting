<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite;

use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Job\BroadcastJob;
use Crustum\Broadcasting\Queue\QueueAdapterInterface;

/**
 * Test Queue Adapter
 *
 * Captures queued jobs instead of actually queuing them for testing purposes.
 * Similar to TestBroadcaster for broadcast testing.
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
     * Push a job onto the queue (capture it instead)
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $data Job data
     * @param array<string, mixed> $options Job options
     * @return void
     */
    public function push(string $jobClass, array $data = [], array $options = []): void
    {
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
     * @param string $eventName Event name
     * @param string $type Job type
     * @param array<string, mixed> $data Job data
     * @return string Unique job ID
     */
    public function getUniqueId(string $eventName, string $type, array $data = []): string
    {
        return md5($eventName . $type . serialize($data));
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
        $filtered = array_filter(static::$queuedJobs, function ($job) use ($jobClass) {
            return $job['jobClass'] === $jobClass;
        });

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
        $broadcastJobs = static::getQueuedJobsByClass(BroadcastJob::class);

        $filtered = array_filter($broadcastJobs, function ($job) use ($eventName) {
            return isset($job['data']['eventName']) && $job['data']['eventName'] === $eventName;
        });

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
        $broadcastJobs = static::getQueuedJobsByClass(BroadcastJob::class);

        $filtered = array_filter($broadcastJobs, function ($job) use ($channel) {
            $channels = $job['data']['channels'] ?? [];
            if (is_array($channels)) {
                return in_array($channel, $channels);
            }

            return $channels === $channel;
        });

        return array_values($filtered);
    }

    /**
     * Clear all queued jobs
     *
     * @return void
     */
    public static function clearQueuedJobs(): void
    {
        static::$queuedJobs = [];
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
