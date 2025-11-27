<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Channel;

/**
 * Encrypted Private Channel
 *
 * Represents an encrypted private channel for secure broadcasting.
 * Automatically prefixes channel names with 'private-encrypted-'.
 * Following CakePHP conventions with explicit getter methods.
 *
 * @package Crustum\Broadcasting\Channel
 */
class EncryptedPrivateChannel extends Channel
{
    /**
     * Create a new encrypted private channel instance.
     *
     * @param string $name Channel name (without private-encrypted- prefix)
     */
    public function __construct(string $name)
    {
        parent::__construct('private-encrypted-' . $name);
    }

    /**
     * Get the original channel name without prefix.
     * Following CakePHP convention for explicit getter methods.
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return substr($this->name, 18);
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
     * Check if this is an encrypted channel.
     *
     * @return bool
     */
    public function isEncryptedChannel(): bool
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
