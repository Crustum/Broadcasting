<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Trait;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Test\TestApp\Trait\TestTraitClass;

/**
 * PusherChannelConventionsTrait Test Case
 *
 * @package Crustum\Broadcasting\Test\TestCase\Trait
 */
class PusherChannelConventionsTraitTest extends TestCase
{
    /**
     * Test subject
     *
     * @var TestTraitClass
     */
    protected TestTraitClass $testClass;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testClass = new TestTraitClass();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->testClass);

        parent::tearDown();
    }

    /**
     * Test isGuardedChannel with private channels
     *
     * @return void
     */
    public function testIsGuardedChannelWithPrivateChannels(): void
    {
        $this->assertTrue($this->testClass->isGuardedChannel('private-user.123'));
        $this->assertTrue($this->testClass->isGuardedChannel('private-encrypted-user.123'));
        $this->assertTrue($this->testClass->isGuardedChannel('private-orders.456'));
    }

    /**
     * Test isGuardedChannel with presence channels
     *
     * @return void
     */
    public function testIsGuardedChannelWithPresenceChannels(): void
    {
        $this->assertTrue($this->testClass->isGuardedChannel('presence-room.123'));
        $this->assertTrue($this->testClass->isGuardedChannel('presence-chat.456'));
    }

    /**
     * Test isGuardedChannel with public channels
     *
     * @return void
     */
    public function testIsGuardedChannelWithPublicChannels(): void
    {
        $this->assertFalse($this->testClass->isGuardedChannel('public-news'));
        $this->assertFalse($this->testClass->isGuardedChannel('news'));
        $this->assertFalse($this->testClass->isGuardedChannel('chat'));
    }

    /**
     * Test normalizeChannelName with private channels
     *
     * @return void
     */
    public function testNormalizeChannelNameWithPrivateChannels(): void
    {
        $this->assertEquals('user.123', $this->testClass->normalizeChannelName('private-user.123'));
        $this->assertEquals('orders.456', $this->testClass->normalizeChannelName('private-orders.456'));
    }

    /**
     * Test normalizeChannelName with encrypted private channels
     *
     * @return void
     */
    public function testNormalizeChannelNameWithEncryptedPrivateChannels(): void
    {
        $this->assertEquals('user.123', $this->testClass->normalizeChannelName('private-encrypted-user.123'));
        $this->assertEquals('orders.456', $this->testClass->normalizeChannelName('private-encrypted-orders.456'));
    }

    /**
     * Test normalizeChannelName with presence channels
     *
     * @return void
     */
    public function testNormalizeChannelNameWithPresenceChannels(): void
    {
        $this->assertEquals('room.123', $this->testClass->normalizeChannelName('presence-room.123'));
        $this->assertEquals('chat.456', $this->testClass->normalizeChannelName('presence-chat.456'));
    }

    /**
     * Test normalizeChannelName with public channels
     *
     * @return void
     */
    public function testNormalizeChannelNameWithPublicChannels(): void
    {
        $this->assertEquals('news', $this->testClass->normalizeChannelName('news'));
        $this->assertEquals('chat', $this->testClass->normalizeChannelName('chat'));
        $this->assertEquals('public-news', $this->testClass->normalizeChannelName('public-news'));
    }

    /**
     * Test normalizeChannelName with empty string
     *
     * @return void
     */
    public function testNormalizeChannelNameWithEmptyString(): void
    {
        $this->assertEquals('', $this->testClass->normalizeChannelName(''));
    }

    /**
     * Test normalizeChannelName with special characters
     *
     * @return void
     */
    public function testNormalizeChannelNameWithSpecialCharacters(): void
    {
        $this->assertEquals('user@domain.com', $this->testClass->normalizeChannelName('private-user@domain.com'));
        $this->assertEquals('room-123', $this->testClass->normalizeChannelName('presence-room-123'));
    }
}
