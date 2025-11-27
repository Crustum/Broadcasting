<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Exception;

/**
 * Queue Exception
 *
 * Thrown when queue operations fail.
 * Indicates job queuing or processing issues.
 *
 * @package Crustum\Broadcasting\Exception
 * @phpstan-consistent-constructor
 */
class QueueException extends BroadcastingException
{
    /**
     * Default error code for queue exceptions
     */
    public const DEFAULT_CODE = 500;

    /**
     * Create exception for queue adapter not found.
     *
     * @param string $adapter Adapter name
     * @return static
     */
    public static function adapterNotFound(string $adapter): static
    {
        return new static(
            sprintf('Queue adapter [%s] not found or not configured.', $adapter),
            static::DEFAULT_CODE,
        );
    }

    /**
     * Create exception for job push failure.
     *
     * @param string $job Job class name
     * @param string $reason Failure reason
     * @return static
     */
    public static function pushFailed(string $job, string $reason): static
    {
        return new static(
            sprintf('Failed to push job [%s] to queue: %s', $job, $reason),
            static::DEFAULT_CODE,
        );
    }

    /**
     * Create exception for adapter unavailable.
     *
     * @param string $adapter Adapter name
     * @return static
     */
    public static function adapterUnavailable(string $adapter): static
    {
        return new static(
            sprintf('Queue adapter [%s] is currently unavailable.', $adapter),
            503,
        );
    }
}
