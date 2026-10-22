<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * BroadcastSentToChannel
 *
 * Asserts that a broadcast was sent to a specific channel
 *
 * @internal
 */
class BroadcastSentToChannel extends BroadcastConstraintBase
{
    /**
     * Checks if broadcast was sent to channel
     *
     * @param mixed $other Array with 'channel' and 'event' keys
     * @return bool
     */
    protected function matches(mixed $other): bool
    {
        $channel = $other['channel'];
        $event = $other['event'];
        $broadcasts = $this->getBroadcasts();

        return array_any($broadcasts, fn(array $broadcast): bool => in_array($channel, $broadcast['channels']) && $broadcast['event'] === $event);
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return 'broadcast was sent to channel';
    }
}
