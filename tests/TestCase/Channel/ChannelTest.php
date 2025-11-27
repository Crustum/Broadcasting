<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Channel;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Channel\Channel;

/**
 * Channel Test
 *
 * Test case for the Channel class to verify basic functionality.
 */
class ChannelTest extends TestCase
{
    /**
     * Test channel creation and basic properties
     *
     * @return void
     */
    public function testChannelCreation(): void
    {
        $channel = new Channel('test-channel');

        $this->assertEquals('test-channel', $channel->name);
        $this->assertEquals('test-channel', $channel->getName());
        $this->assertEquals('test-channel', (string)$channel);
    }

    /**
     * Test channel string representation
     *
     * @return void
     */
    public function testToString(): void
    {
        $channel = new Channel('test-channel');

        $this->assertEquals('test-channel', (string)$channel);
    }

    /**
     * Test channel with special characters
     *
     * @return void
     */
    public function testChannelWithSpecialCharacters(): void
    {
        $channel = new Channel('private-user.123');

        $this->assertEquals('private-user.123', $channel->name);
        $this->assertEquals('private-user.123', (string)$channel);
    }
}
