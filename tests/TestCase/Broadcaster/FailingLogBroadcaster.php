<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Crustum\Broadcasting\Broadcaster\LogBroadcaster;
use RuntimeException;

/**
 * Failing Log Broadcaster
 *
 * Test helper that fails during construction to exercise error wrapping.
 */
class FailingLogBroadcaster extends LogBroadcaster
{
    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Broadcaster configuration
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        throw new RuntimeException('Logger service not available');
    }
}
