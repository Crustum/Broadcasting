<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\TestSuite\Constraint\Broadcasting;

/**
 * BroadcastPayloadContains
 *
 * Asserts that a broadcast contains specific data in its payload
 *
 * @internal
 */
class BroadcastPayloadContains extends BroadcastConstraintBase
{
    /**
     * Checks if broadcast payload contains key/value
     *
     * @param mixed $other Array with 'event', 'key', and 'value'
     * @return bool
     */
    protected function matches(mixed $other): bool
    {
        $event = $other['event'];
        $key = $other['key'];
        $value = $other['value'];
        $broadcasts = $this->getBroadcasts();

        return array_any($broadcasts, fn(array $broadcast): bool => $broadcast['event'] === $event && (isset($broadcast['payload'][$key]) && $broadcast['payload'][$key] == $value));
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return 'broadcast payload contains expected data';
    }
}
