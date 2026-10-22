<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Event;

use Attribute;

/**
 * Unique For Attribute
 *
 * Declares how long (in seconds) the uniqueness lock of a unique broadcast
 * should be maintained. Recognized on broadcast event classes, including
 * inherited attributes. An explicit `uniqueFor()` method or a
 * `$uniqueFor` property declared on a child class takes precedence.
 *
 * @package Crustum\Broadcasting\Event
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class UniqueFor
{
    /**
     * @param int $seconds Number of seconds the uniqueness lock is maintained
     */
    public function __construct(
        public int $seconds,
    ) {
    }
}
