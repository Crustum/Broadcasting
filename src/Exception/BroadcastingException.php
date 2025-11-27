<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Exception;

use Exception;

/**
 * Broadcasting Exception
 *
 * Base exception for broadcasting-related errors.
 * Provides common exception handling for the broadcasting system.
 *
 * @package Crustum\Broadcasting\Exception
 * @phpstan-consistent-constructor
 */
class BroadcastingException extends Exception
{
    /**
     * Default error code for broadcasting exceptions
     */
    public const DEFAULT_CODE = 500;

    /**
     * Constructor.
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = '', int $code = self::DEFAULT_CODE, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
