<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Channel;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Channel\PublicChannel;

/**
 * PublicChannel Test
 *
 * Tests for the PublicChannel class functionality.
 */
class PublicChannelTest extends TestCase
{
    /**
     * Test channel creation
     *
     * @return void
     */
    public function testChannelCreation(): void
    {
        $channel = new PublicChannel('chat');

        $this->assertEquals('public-chat', $channel->name);
        $this->assertEquals('public-chat', $channel->getName());
        $this->assertEquals('public-chat', (string)$channel);
    }

    /**
     * Test channel type
     *
     * @return void
     */
    public function testChannelType(): void
    {
        $channel = new PublicChannel('test-channel');

        $this->assertEquals('public', $channel->getChannelType());
    }

    /**
     * Test original name without prefix
     *
     * @return void
     */
    public function testGetOriginalName(): void
    {
        $channel = new PublicChannel('notifications');

        $this->assertEquals('notifications', $channel->getOriginalName());
    }

    /**
     * Test is private channel returns false
     *
     * @return void
     */
    public function testIsPrivateChannel(): void
    {
        $channel = new PublicChannel('test');

        $this->assertFalse($channel->isPrivateChannel());
    }

    /**
     * Test requires authentication returns false
     *
     * @return void
     */
    public function testRequiresAuthentication(): void
    {
        $channel = new PublicChannel('test');

        $this->assertFalse($channel->requiresAuthentication());
    }

    /**
     * Test supports member info returns false
     *
     * @return void
     */
    public function testSupportsMemberInfo(): void
    {
        $channel = new PublicChannel('test');

        $this->assertFalse($channel->supportsMemberInfo());
    }

    /**
     * Test channel with special characters
     *
     * @return void
     */
    public function testChannelWithSpecialCharacters(): void
    {
        $channel = new PublicChannel('user.123.notifications_456');

        $this->assertEquals('public-user.123.notifications_456', $channel->getName());
        $this->assertEquals('user.123.notifications_456', $channel->getOriginalName());
    }

    /**
     * Test empty channel name
     *
     * @return void
     */
    public function testEmptyChannelName(): void
    {
        $channel = new PublicChannel('');

        $this->assertEquals('public-', $channel->getName());
        $this->assertEquals('', $channel->getOriginalName());
    }

    /**
     * Test string representation
     *
     * @return void
     */
    public function testToString(): void
    {
        $channel = new PublicChannel('news');

        $this->assertEquals('public-news', (string)$channel);
    }
}
