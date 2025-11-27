<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Channel;

use Stringable;

/**
 * Channel
 *
 * Basic implementation of a broadcasting channel.
 * Represents a channel for broadcasting messages.
 *
 * @package Crustum\Broadcasting\Channel
 */
class Channel implements Stringable
{
    /**
     * The channel's name.
     *
     * @var string
     */
    public string $name;

    /**
     * Create a new channel instance.
     *
     * @param string $name Channel name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the channel name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Convert the channel instance to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
