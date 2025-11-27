<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Base class for all broadcasting assertion constraints
 *
 * @internal
 */
abstract class BroadcastConstraintBase extends Constraint
{
    /**
     * Broadcast index to check
     *
     * @var int|null
     */
    protected ?int $at = null;

    /**
     * Constructor
     *
     * @param int|null $at Optional index of specific broadcast to check
     */
    public function __construct(?int $at = null)
    {
        $this->at = $at;
    }

    /**
     * Get the broadcast or broadcasts to check
     *
     * @return array<array<string, mixed>>
     */
    protected function getBroadcasts(): array
    {
        $broadcasts = TestBroadcaster::getBroadcasts();

        if ($this->at !== null) {
            if (!isset($broadcasts[$this->at])) {
                return [];
            }

            return [$broadcasts[$this->at]];
        }

        return $broadcasts;
    }
}
