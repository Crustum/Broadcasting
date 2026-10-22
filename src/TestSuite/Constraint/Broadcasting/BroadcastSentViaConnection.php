<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * BroadcastSentViaConnection
 *
 * Asserts that a broadcast was sent via a specific connection
 *
 * @internal
 */
class BroadcastSentViaConnection extends BroadcastConstraintBase
{
    /**
     * Checks if broadcast was sent via connection
     *
     * @param mixed $other Array with 'connection' and 'event' keys
     * @return bool
     */
    protected function matches(mixed $other): bool
    {
        $connection = $other['connection'];
        $event = $other['event'];
        $broadcasts = $this->getBroadcasts();

        return array_any($broadcasts, fn(array $broadcast): bool => $broadcast['connection'] === $connection && $broadcast['event'] === $event);
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return 'broadcast was sent via connection';
    }
}
