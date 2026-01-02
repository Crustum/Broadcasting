<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Channel;

/**
 * Private Channel
 *
 * Represents a private channel for broadcasting with authentication requirements.
 * Automatically prefixes channel names with 'private-'.
 *
 * @package Crustum\Broadcasting\Channel
 */
class PrivateChannel extends Channel
{
    /**
     * Create a new private channel instance.
     *
     * @param string $name Channel name (without private- prefix)
     */
    public function __construct(string $name)
    {
        parent::__construct('private-' . $name);
    }

    /**
     * Get the channel type.
     *
     * @return string
     */
    public function getChannelType(): string
    {
        return 'private';
    }

    /**
     * Get the original channel name without prefix.
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return substr($this->name, 8);
    }

    /**
     * Check if this is a private channel.
     *
     * @return bool
     */
    public function isPrivateChannel(): bool
    {
        return true;
    }

    /**
     * Check if this channel requires authentication.
     *
     * @return bool
     */
    public function requiresAuthentication(): bool
    {
        return true;
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
