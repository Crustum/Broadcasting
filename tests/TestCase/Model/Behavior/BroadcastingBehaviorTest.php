<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Model\Behavior;

use Cake\Event\EventInterface;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Model\Behavior\BroadcastingBehavior;
use ReflectionClass;
use TestApp\Model\Entity\User;
use TestApp\Model\Table\UsersTable;

/**
 * Broadcasting Behavior Test
 */
class BroadcastingBehaviorTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum\Broadcasting.Users',
    ];

    /**
     * Test table instance
     *
     * @var \TestApp\Model\Table\UsersTable
     */
    protected UsersTable $table;

    /**
     * Broadcasting behavior instance
     *
     * @var \Crustum\Broadcasting\Model\Behavior\BroadcastingBehavior
     */
    protected BroadcastingBehavior $behavior;

    /**
     * Clear all Broadcasting configurations
     *
     * @return void
     */
    protected function clearBroadcastingConfigurations(): void
    {
        foreach (Broadcasting::configured() as $configName) {
            Broadcasting::drop($configName);
        }
        Broadcasting::getRegistry()->reset();

        $reflection = new ReflectionClass(Broadcasting::class);
        $property = $reflection->getProperty('_channelsLoaded');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }

    /**
     * Set up test case
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->clearBroadcastingConfigurations();

        Broadcasting::setConfig('default', [
            'className' => 'Crustum/Broadcasting.Null',
        ]);
        Broadcasting::setConfig('null', [
            'className' => 'Crustum/Broadcasting.Null',
        ]);

        $this->table = new UsersTable();
        $this->table->addBehavior('Crustum/Broadcasting.Broadcasting');
        $this->behavior = $this->table->getBehavior('Broadcasting');
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->clearBroadcastingConfigurations();
        parent::tearDown();
    }

    /**
     * Test default configuration
     *
     * @return void
     */
    public function testDefaultConfiguration(): void
    {
        $expectedEvents = [
            'Model.afterSave' => 'saved',
            'Model.afterDelete' => 'deleted',
        ];

        $this->assertEquals($expectedEvents, $this->behavior->getConfig('events'));
        $this->assertTrue($this->behavior->getConfig('enabled'));
        $this->assertEquals('default', $this->behavior->getConfig('connection'));
        $this->assertNull($this->behavior->getConfig('channels'));
        $this->assertNull($this->behavior->getConfig('payload'));
    }

    /**
     * Test implemented events
     *
     * @return void
     */
    public function testImplementedEvents(): void
    {
        $events = $this->behavior->implementedEvents();

        $this->assertArrayHasKey('Model.afterSave', $events);
        $this->assertArrayHasKey('Model.afterDelete', $events);
        $this->assertEquals('handleEvent', $events['Model.afterSave']);
        $this->assertEquals('handleEvent', $events['Model.afterDelete']);
    }

    /**
     * Test enable/disable broadcasting
     *
     * @return void
     */
    public function testEnableDisableBroadcasting(): void
    {
        $this->assertTrue($this->table->isBroadcastingEnabled());

        $this->table->disableBroadcasting();
        $this->assertFalse($this->table->isBroadcastingEnabled());

        $this->table->enableBroadcasting();
        $this->assertTrue($this->table->isBroadcastingEnabled());
    }

    /**
     * Test event handling when broadcasting is disabled
     *
     * @return void
     */
    public function testHandleEventWhenDisabled(): void
    {
        $this->table->disableBroadcasting();

        $user = new User([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        /** @var \Cake\Event\EventInterface&\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createMock(EventInterface::class);
        $event->method('getName')->willReturn('Model.afterSave');

        $this->behavior->handleEvent($event, $user);

        $this->assertFalse($this->table->isBroadcastingEnabled());
    }

    /**
     * Test setting broadcast channels
     *
     * @return void
     */
    public function testSetBroadcastChannels(): void
    {
        $channels = ['user.1', 'admin'];
        $this->table->setBroadcastChannels($channels);

        $this->assertEquals($channels, $this->behavior->getConfig('channels'));
    }

    /**
     * Test setting broadcast payload
     *
     * @return void
     */
    public function testSetBroadcastPayload(): void
    {
        $payload = ['id' => 1, 'status' => 'active'];
        $this->table->setBroadcastPayload($payload);

        $this->assertEquals($payload, $this->behavior->getConfig('payload'));
    }

    /**
     * Test setting broadcast connection
     *
     * @return void
     */
    public function testSetBroadcastConnection(): void
    {
        $this->table->setBroadcastConnection('pusher');
        $this->assertEquals('pusher', $this->behavior->getConfig('connection'));
    }

    /**
     * Test setting broadcast queue
     *
     * @return void
     */
    public function testSetBroadcastQueue(): void
    {
        $this->table->setBroadcastQueue('broadcasts');
        $this->assertEquals('broadcasts', $this->behavior->getConfig('queue'));

        $this->table->setBroadcastQueue(null);
        $this->assertNull($this->behavior->getConfig('queue'));
    }

    /**
     * Test broadcast event method exists
     *
     * @return void
     */
    public function testBroadcastEventMethod(): void
    {
        $this->table->setBroadcastChannels(['test-channel']);

        $user = new User([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->broadcastEvent($user, 'created');
    }

    /**
     * Test custom events configuration
     *
     * @return void
     */
    public function testCustomEventsConfiguration(): void
    {
        $customTable = new UsersTable();
        $customTable->addBehavior('Crustum/Broadcasting.Broadcasting', [
            'events' => [
                'Model.afterSave' => 'saved',
                'Model.afterDelete' => 'removed',
            ],
        ]);

        $behavior = $customTable->getBehavior('Broadcasting');
        $events = $behavior->getConfig('events');

        $this->assertEquals('saved', $events['Model.afterSave']);
        $this->assertEquals('removed', $events['Model.afterDelete']);
        $this->assertArrayNotHasKey('Model.afterUpdate', $events);
    }

    /**
     * Test behavior with all configuration options
     *
     * @return void
     */
    public function testFullConfiguration(): void
    {
        $fullTable = new UsersTable();
        $fullTable->addBehavior('Crustum/Broadcasting.Broadcasting', [
            'events' => [
                'Model.afterSave' => 'created',
            ],
            'connection' => 'pusher',
            'queue' => 'broadcasts',
            'channels' => ['admin'],
            'payload' => ['custom' => 'data'],
            'enabled' => false,
        ]);

        $behavior = $fullTable->getBehavior('Broadcasting');

        $this->assertEquals('pusher', $behavior->getConfig('connection'));
        $this->assertEquals('broadcasts', $behavior->getConfig('queue'));
        $this->assertEquals(['admin'], $behavior->getConfig('channels'));
        $this->assertEquals(['custom' => 'data'], $behavior->getConfig('payload'));
        $this->assertFalse($behavior->getConfig('enabled'));
    }

    /**
     * Test broadcasting with proper configuration
     *
     * @return void
     */
    public function testBroadcastingWithConfiguration(): void
    {
        $this->table->setBroadcastChannels(['user.123']);
        $this->table->setBroadcastConnection('null');
        $this->table->setBroadcastPayload(['id' => 123, 'name' => 'Test User']);

        $user = new User([
            'id' => 123,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->broadcastEvent($user, 'created');

        $this->assertEquals(['user.123'], $this->behavior->getConfig('channels'));
        $this->assertEquals('null', $this->behavior->getConfig('connection'));
        $this->assertEquals(['id' => 123, 'name' => 'Test User'], $this->behavior->getConfig('payload'));
    }

    /**
     * Test setBroadcastEventName method
     *
     * @return void
     */
    public function testSetBroadcastEventName(): void
    {
        // Test string event name
        $this->table->setBroadcastEventName('CustomEventName');
        $this->assertEquals('CustomEventName', $this->behavior->getConfig('eventName'));

        // Test callback event name
        $this->table->setBroadcastEventName(function ($entity, $event) {
            return 'Custom' . ucfirst($event);
        });
        $this->assertIsCallable($this->behavior->getConfig('eventName'));

        // Test null (use default)
        $this->table->setBroadcastEventName(null);
        $this->assertNull($this->behavior->getConfig('eventName'));
    }

    /**
     * Test selective broadcast events configuration
     *
     * @return void
     */
    public function testSelectiveBroadcastEvents(): void
    {
        $defaultEvents = $this->behavior->getConfig('broadcastEvents');
        $this->assertTrue($defaultEvents['created']);
        $this->assertTrue($defaultEvents['updated']);
        $this->assertTrue($defaultEvents['deleted']);

        $this->table->setBroadcastEvents([
            'created' => true,
            'updated' => false,
            'deleted' => true,
        ]);
        $events = $this->behavior->getConfig('broadcastEvents');
        $this->assertTrue($events['created']);
        $this->assertFalse($events['updated']);
        $this->assertTrue($events['deleted']);

        // Test enable/disable individual events
        $this->table->enableBroadcastEvent('updated');
        $this->assertTrue($this->behavior->getConfig('broadcastEvents')['updated']);

        $this->table->disableBroadcastEvent('created');
        $this->assertFalse($this->behavior->getConfig('broadcastEvents')['created']);
    }

    /**
     * Test Event naming through broadcastEvent
     *
     * @return void
     */
    public function testEventNaming(): void
    {
        $user = $this->table->newEntity([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->table->broadcastEvent($user, 'created');

        $this->assertTrue($this->behavior->isBroadcastingEnabled());

        $this->assertEquals('default', $this->behavior->getConfig('connection'));

        $this->assertEquals('Users', $user->getSource());
    }
}
