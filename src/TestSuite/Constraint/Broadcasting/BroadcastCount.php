<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * BroadcastCount
 *
 * Asserts a specific count of broadcasts were sent
 *
 * @internal
 */
class BroadcastCount extends BroadcastConstraintBase
{
    /**
     * Checks if broadcast count matches
     *
     * @param mixed $other Expected count
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $expectedCount = $other;
        $broadcasts = $this->getBroadcasts();

        return count($broadcasts) === $expectedCount;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        $broadcasts = $this->getBroadcasts();
        $actualCount = count($broadcasts);

        return sprintf('broadcast count is (actual: %d)', $actualCount);
    }
}
