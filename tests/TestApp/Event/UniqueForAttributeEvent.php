<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestApp\Event;

use Crustum\Broadcasting\Event\UniqueFor;

/**
 * Event carrying a UniqueFor attribute.
 */
#[UniqueFor(60)]
class UniqueForAttributeEvent
{
}
