<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Channel;

/**
 * Presence Channel
 *
 * Represents a presence channel for broadcasting with member awareness.
 * Automatically prefixes channel names with 'presence-'.
 *
 * @package Crustum\Broadcasting\Channel
 */
class PresenceChannel extends Channel
{
    /**
     * Create a new presence channel instance.
     *
     * @param string $name Channel name (without presence- prefix)
     */
    public function __construct(string $name)
    {
        parent::__construct('presence-' . $name);
    }

    /**
     * Get the channel type.
     *
     * @return string
     */
    public function getChannelType(): string
    {
        return 'presence';
    }

    /**
     * Get the original channel name without prefix.
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return substr($this->name, 9);
    }

    /**
     * Check if this is a presence channel.
     *
     * @return bool
     */
    public function isPresenceChannel(): bool
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
        return true;
    }
}
