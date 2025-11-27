<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Exception;

/**
 * Invalid Broadcaster Exception
 *
 * Thrown when an invalid or unsupported broadcaster is requested.
 * Indicates configuration or driver registration issues.
 *
 * @package Crustum\Broadcasting\Exception
 * @phpstan-consistent-constructor
 */
class InvalidBroadcasterException extends BroadcastingException
{
    /**
     * Default error code for invalid broadcaster exceptions
     */
    public const DEFAULT_CODE = 400;

    /**
     * Create exception for unknown broadcaster.
     *
     * @param string $broadcaster Broadcaster name
     * @return static
     */
    public static function unknownBroadcaster(string $broadcaster): static
    {
        return new static(
            sprintf('Broadcaster [%s] is not supported or not configured.', $broadcaster),
            static::DEFAULT_CODE,
        );
    }

    /**
     * Create exception for invalid broadcaster configuration.
     *
     * @param string $broadcaster Broadcaster name
     * @param string $reason Configuration error reason
     * @return static
     */
    public static function invalidConfiguration(string $broadcaster, string $reason): static
    {
        return new static(
            sprintf('Broadcaster [%s] has invalid configuration: %s', $broadcaster, $reason),
            static::DEFAULT_CODE,
        );
    }

    /**
     * Create exception for invalid broadcaster class.
     *
     * @param string $class Class name that is invalid
     * @return static
     */
    public static function invalidBroadcaster(string $class): static
    {
        return new static(
            sprintf('Class [%s] is not a valid broadcaster. It must implement BroadcasterInterface.', $class),
            static::DEFAULT_CODE,
        );
    }
}
