<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Channel;

/**
 * Channel
 *
 * Basic implementation of a broadcasting channel.
 * Represents a channel for broadcasting messages.
 *
 * @package Crustum\Broadcasting\Channel
 */
class PublicChannel extends Channel
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
        $this->name = 'public-' . $name;
    }

    /**
     * Get the channel type.
     *
     * @return string
     */
    public function getChannelType(): string
    {
        return 'public';
    }

    /**
     * Get the original channel name without prefix.
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return substr($this->name, 7);
    }

    /**
     * Check if this is a private channel.
     *
     * @return bool
     */
    public function isPrivateChannel(): bool
    {
        return false;
    }

    /**
     * Check if this channel requires authentication.
     *
     * @return bool
     */
    public function requiresAuthentication(): bool
    {
        return false;
    }

    /**
     * Check if this channel supports member information.
     *
     * @return bool
     */
    public function supportsMemberInfo(): bool
    {
        return false;
    }
}
