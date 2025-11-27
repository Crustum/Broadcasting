<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Channel;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Channel\PresenceChannel;

/**
 * PresenceChannel Test
 *
 * Tests for the PresenceChannel class functionality.
 */
class PresenceChannelTest extends TestCase
{
    /**
     * Test channel creation
     *
     * @return void
     */
    public function testChannelCreation(): void
    {
        $channel = new PresenceChannel('room.123');

        $this->assertEquals('presence-room.123', $channel->getName());
        $this->assertEquals('presence-room.123', (string)$channel);
        $this->assertEquals('room.123', $channel->getOriginalName());
    }

    /**
     * Test channel type
     *
     * @return void
     */
    public function testChannelType(): void
    {
        $channel = new PresenceChannel('test-room');

        $this->assertEquals('presence', $channel->getChannelType());
        $this->assertTrue($channel->isPresenceChannel());
        $this->assertTrue($channel->requiresAuthentication());
        $this->assertTrue($channel->supportsMemberInfo());
    }

    /**
     * Test channel with special characters
     *
     * @return void
     */
    public function testChannelWithSpecialCharacters(): void
    {
        $channel = new PresenceChannel('user.123.room_456');

        $this->assertEquals('presence-user.123.room_456', $channel->getName());
        $this->assertEquals('user.123.room_456', $channel->getOriginalName());
    }

    /**
     * Test empty channel name
     *
     * @return void
     */
    public function testEmptyChannelName(): void
    {
        $channel = new PresenceChannel('');

        $this->assertEquals('presence-', $channel->getName());
        $this->assertEquals('', $channel->getOriginalName());
    }
}
