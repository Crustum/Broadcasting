<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Exception;

/**
 * Invalid Queue Adapter Exception
 *
 * Thrown when an invalid or unsupported queue adapter is requested.
 *
 * @package Crustum\Broadcasting\Exception
 * @phpstan-consistent-constructor
 */
class InvalidQueueAdapterException extends BroadcastingException
{
    /**
     * Default error code for invalid broadcaster exceptions
     */
    public const DEFAULT_CODE = 400;

    /**
     * Create exception for unknown broadcaster.
     *
     * @param string $queueAdapter Queue adapter class name
     * @return static
     */
    public static function unknownQueueAdapter(string $queueAdapter): static
    {
        return new static(
            sprintf('Queue adapter [%s] is not supported or not configured.', $queueAdapter),
            static::DEFAULT_CODE,
        );
    }
}
