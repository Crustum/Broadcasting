<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Exception;

/**
 * Invalid Channel Exception
 *
 * Thrown when an invalid channel name or type is used.
 * Indicates channel naming or authorization issues.
 *
 * @package Crustum\Broadcasting\Exception
 * @phpstan-consistent-constructor
 */
class InvalidChannelException extends BroadcastingException
{
    /**
     * Default error code for invalid channel exceptions
     */
    public const DEFAULT_CODE = 400;

    /**
     * Create exception for invalid channel name.
     *
     * @param string $channel Channel name
     * @return static
     */
    public static function invalidName(string $channel): static
    {
        return new static(
            sprintf('Channel name [%s] is invalid or not allowed.', $channel),
            static::DEFAULT_CODE,
        );
    }

    /**
     * Create exception for unsupported channel type.
     *
     * @param string $type Channel type
     * @return static
     */
    public static function unsupportedType(string $type): static
    {
        return new static(
            sprintf('Channel type [%s] is not supported.', $type),
            static::DEFAULT_CODE,
        );
    }

    /**
     * Create exception for unauthorized channel access.
     *
     * @param string $channel Channel name
     * @return static
     */
    public static function unauthorized(string $channel): static
    {
        return new static(
            sprintf('Unauthorized access to channel [%s].', $channel),
            403,
        );
    }
}
