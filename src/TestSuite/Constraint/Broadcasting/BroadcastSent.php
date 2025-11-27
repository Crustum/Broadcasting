<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * BroadcastSent
 *
 * Asserts that a broadcast of a specific event was sent
 *
 * @internal
 */
class BroadcastSent extends BroadcastConstraintBase
{
    /**
     * Checks if broadcast was sent
     *
     * @param mixed $other Event name
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $event = $other;
        $broadcasts = $this->getBroadcasts();

        foreach ($broadcasts as $broadcast) {
            if ($broadcast['event'] === $event) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        if ($this->at !== null) {
            return sprintf('broadcast #%d was sent', $this->at);
        }

        return 'broadcast was sent';
    }
}
