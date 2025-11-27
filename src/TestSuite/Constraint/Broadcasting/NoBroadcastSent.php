<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * NoBroadcastSent
 *
 * Asserts that no broadcasts were sent
 *
 * @internal
 */
class NoBroadcastSent extends BroadcastConstraintBase
{
    /**
     * Checks if no broadcasts were sent
     *
     * @param mixed $other Not used
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $broadcasts = $this->getBroadcasts();

        return empty($broadcasts);
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        $broadcasts = $this->getBroadcasts();
        $count = count($broadcasts);

        return sprintf('no broadcasts were sent (actual: %d)', $count);
    }
}
