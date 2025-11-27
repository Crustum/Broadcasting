<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Trait;

/**
 * Pusher Channel Conventions Trait
 *
 * Provides methods for handling Pusher channel naming conventions.
 *
 * @package Crustum\Broadcasting\Trait
 */
trait PusherChannelConventionsTrait
{
    /**
     * Check if the channel is protected by authentication.
     * Following CakePHP convention for explicit getter methods.
     *
     * @param string $channel Channel name to check
     * @return bool True if channel requires authentication
     */
    public function isGuardedChannel(string $channel): bool
    {
        return str_starts_with($channel, 'private-') || str_starts_with($channel, 'presence-');
    }

    /**
     * Remove prefix from channel name.
     * Following CakePHP convention for explicit getter methods.
     *
     * @param string $channel Channel name to normalize
     * @return string Channel name without prefix
     */
    public function normalizeChannelName(string $channel): string
    {
        $prefixes = ['private-encrypted-', 'private-', 'presence-'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($channel, $prefix)) {
                return substr($channel, strlen($prefix));
            }
        }

        return $channel;
    }
}
