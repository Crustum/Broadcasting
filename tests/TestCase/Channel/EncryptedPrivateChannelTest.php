<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Channel;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Channel\EncryptedPrivateChannel;

/**
 * EncryptedPrivateChannel Test Case
 *
 * @package Crustum\Broadcasting\Test\TestCase\Channel
 */
class EncryptedPrivateChannelTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Broadcasting\Channel\EncryptedPrivateChannel
     */
    protected EncryptedPrivateChannel $encryptedPrivateChannel;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->encryptedPrivateChannel = new EncryptedPrivateChannel('test-channel');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->encryptedPrivateChannel);

        parent::tearDown();
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(EncryptedPrivateChannel::class, $this->encryptedPrivateChannel);
        $this->assertEquals('private-encrypted-test-channel', $this->encryptedPrivateChannel->getName());
    }

    /**
     * Test string conversion
     *
     * @return void
     */
    public function testToString(): void
    {
        $this->assertEquals('private-encrypted-test-channel', (string)$this->encryptedPrivateChannel);
    }

    /**
     * Test channel name with special characters
     *
     * @return void
     */
    public function testChannelNameWithSpecialCharacters(): void
    {
        $channel = new EncryptedPrivateChannel('user.123');
        $this->assertEquals('private-encrypted-user.123', $channel->getName());
    }

    /**
     * Test getOriginalName method
     *
     * @return void
     */
    public function testGetOriginalName(): void
    {
        $this->assertEquals('test-channel', $this->encryptedPrivateChannel->getOriginalName());
    }

    /**
     * Test isPrivateChannel method
     *
     * @return void
     */
    public function testIsPrivateChannel(): void
    {
        $this->assertTrue($this->encryptedPrivateChannel->isPrivateChannel());
    }

    /**
     * Test isEncryptedChannel method
     *
     * @return void
     */
    public function testIsEncryptedChannel(): void
    {
        $this->assertTrue($this->encryptedPrivateChannel->isEncryptedChannel());
    }

    /**
     * Test requiresAuthentication method
     *
     * @return void
     */
    public function testRequiresAuthentication(): void
    {
        $this->assertTrue($this->encryptedPrivateChannel->requiresAuthentication());
    }

    /**
     * Test supportsMemberInfo method
     *
     * @return void
     */
    public function testSupportsMemberInfo(): void
    {
        $this->assertFalse($this->encryptedPrivateChannel->supportsMemberInfo());
    }
}
