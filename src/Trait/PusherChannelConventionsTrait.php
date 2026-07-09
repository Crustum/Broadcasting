<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Trait;

use Crustum\Broadcasting\Polyfill\StringFunctions;

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
     *
     * @param string $channel Channel name to check
     * @return bool True if channel requires authentication
     */
    public function isGuardedChannel(string $channel): bool
    {
        return StringFunctions::startsWith($channel, 'private-') || StringFunctions::startsWith($channel, 'presence-');
    }

    /**
     * Remove prefix from channel name.
     *
     * @param string $channel Channel name to normalize
     * @return string Channel name without prefix
     */
    public function normalizeChannelName(string $channel): string
    {
        $prefixes = ['private-encrypted-', 'private-', 'presence-'];

        foreach ($prefixes as $prefix) {
            if (StringFunctions::startsWith($channel, $prefix)) {
                return substr($channel, strlen($prefix));
            }
        }

        return $channel;
    }
}
