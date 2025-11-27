<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Channel;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Channel\PrivateChannel;

/**
 * PrivateChannel Test
 *
 * Tests for the PrivateChannel class functionality.
 */
class PrivateChannelTest extends TestCase
{
    /**
     * Test channel creation
     *
     * @return void
     */
    public function testChannelCreation(): void
    {
        $channel = new PrivateChannel('user.123');

        $this->assertEquals('private-user.123', $channel->getName());
        $this->assertEquals('private-user.123', (string)$channel);
        $this->assertEquals('user.123', $channel->getOriginalName());
    }

    /**
     * Test channel type
     *
     * @return void
     */
    public function testChannelType(): void
    {
        $channel = new PrivateChannel('test-channel');

        $this->assertEquals('private', $channel->getChannelType());
        $this->assertTrue($channel->isPrivateChannel());
        $this->assertTrue($channel->requiresAuthentication());
        $this->assertFalse($channel->supportsMemberInfo());
    }

    /**
     * Test channel with special characters
     *
     * @return void
     */
    public function testChannelWithSpecialCharacters(): void
    {
        $channel = new PrivateChannel('user.123.notifications_456');

        $this->assertEquals('private-user.123.notifications_456', $channel->getName());
        $this->assertEquals('user.123.notifications_456', $channel->getOriginalName());
    }

    /**
     * Test empty channel name
     *
     * @return void
     */
    public function testEmptyChannelName(): void
    {
        $channel = new PrivateChannel('');

        $this->assertEquals('private-', $channel->getName());
        $this->assertEquals('', $channel->getOriginalName());
    }
}
