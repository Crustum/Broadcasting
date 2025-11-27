<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * BroadcastSentTimes
 *
 * Asserts that a broadcast was sent a specific number of times
 *
 * @internal
 */
class BroadcastSentTimes extends BroadcastConstraintBase
{
    /**
     * Expected number of times
     *
     * @var int
     */
    protected int $times;

    /**
     * Constructor
     *
     * @param int $times Expected number of times
     */
    public function __construct(int $times)
    {
        parent::__construct();
        $this->times = $times;
    }

    /**
     * Checks if broadcast was sent N times
     *
     * @param mixed $other Event name
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        $event = $other;
        $broadcasts = $this->getBroadcasts();
        $count = 0;

        foreach ($broadcasts as $broadcast) {
            if ($broadcast['event'] === $event) {
                $count++;
            }
        }

        return $count === $this->times;
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return sprintf('broadcast was sent %d times', $this->times);
    }
}
