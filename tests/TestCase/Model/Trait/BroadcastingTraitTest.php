<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Model\Trait;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait as TestBroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use Crustum\Broadcasting\TestSuite\TestQueueAdapter;
use ReflectionClass;
use TestApp\Model\Entity\User;
use TestApp\Model\Table\UsersTable;

/**
 * Broadcasting Trait Test
 *
 * Tests all trait methods and configuration
 */
class BroadcastingTraitTest extends TestCase
{
    use TestBroadcastingTrait;

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
     * Clear all Broadcasting configurations
     *
     * @return void
     */
    protected function clearBroadcastingConfigurations(): void
    {
        foreach (Broadcasting::configured() as $configName) {
            Broadcasting::drop((string)$configName);
        }
        Broadcasting::getRegistry()->reset();

        $reflection = new ReflectionClass(Broadcasting::class);
        $property = $reflection->getProperty('_channelsLoaded');
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

        TestBroadcaster::replaceAllBroadcasters();
        TestQueueAdapter::replaceQueueAdapter();

        $this->table = new UsersTable();
        $this->table->addBehavior('Crustum/Broadcasting.Broadcasting');
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        TestQueueAdapter::clearQueuedJobs();
        $this->clearBroadcastingConfigurations();
        parent::tearDown();
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
     * Test setting broadcast channels
     *
     * @return void
     */
    public function testSetBroadcastChannels(): void
    {
        $channels = ['user.1', 'admin'];
        $this->table->setBroadcastChannels($channels);

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastSent('UserCreated');
        $this->assertBroadcastSentToChannels(['user.1', 'admin'], 'UserCreated');
    }

    /**
     * Test setting broadcast channels with closure
     *
     * @return void
     */
    public function testSetBroadcastChannelsWithClosure(): void
    {
        $this->table->setBroadcastChannels(function ($entity, $event) {
            return ['user.' . $entity->get('id')];
        });

        $user = new User([
            'id' => 123,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastSent('UserCreated');
        $this->assertBroadcastSentToChannels(['user.123'], 'UserCreated');
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

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals($payload, $broadcasts[0]['payload']);
    }

    /**
     * Test setting broadcast payload with closure
     *
     * @return void
     */
    public function testSetBroadcastPayloadWithClosure(): void
    {
        $this->table->setBroadcastPayload(function ($entity, $event) {
            return [
                'id' => $entity->get('id'),
                'event' => $event,
            ];
        });

        $user = new User([
            'id' => 123,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $broadcasts = TestBroadcaster::getBroadcasts();
        $this->assertCount(1, $broadcasts);
        $this->assertEquals(['id' => 123, 'event' => 'created'], $broadcasts[0]['payload']);
    }

    /**
     * Test setting broadcast connection
     *
     * @return void
     */
    public function testSetBroadcastConnection(): void
    {
        $this->table->setBroadcastConnection('null');

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastSentViaConnection('null', 'UserCreated');
    }

    /**
     * Test setting broadcast queue
     *
     * @return void
     */
    public function testSetBroadcastQueue(): void
    {
        $this->table->setBroadcastQueue('broadcasts');

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastQueued('UserCreated');
        $this->assertNoBroadcastsSent();

        TestQueueAdapter::clearQueuedJobs();

        $this->table->setBroadcastQueue(null);
        $this->table->broadcastEvent($user, 'updated');

        $this->assertBroadcastSent('UserUpdated');
        $this->assertNoBroadcastsQueued();
    }

    /**
     * Test setting broadcast event name
     *
     * @return void
     */
    public function testSetBroadcastEventName(): void
    {
        $this->table->setBroadcastEventName('CustomEventName');

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastSent('CustomEventName');
    }

    /**
     * Test setting broadcast event name with closure
     *
     * @return void
     */
    public function testSetBroadcastEventNameWithClosure(): void
    {
        $this->table->setBroadcastEventName(function ($entity, $event) {
            return 'Custom' . ucfirst($event);
        });

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastSent('CustomCreated');
    }

    /**
     * Test setting broadcast events
     *
     * @return void
     */
    public function testSetBroadcastEvents(): void
    {
        $this->table->setBroadcastEvents([
            'created' => true,
            'updated' => false,
            'deleted' => true,
        ]);

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);

        $this->table->broadcastEvent($user, 'created');
        $this->assertBroadcastSent('UserCreated');

        TestBroadcaster::clearBroadcasts();

        $this->table->broadcastEvent($user, 'updated');
        $this->assertNoBroadcastsSent();

        TestBroadcaster::clearBroadcasts();

        $this->table->broadcastEvent($user, 'deleted');
        $this->assertBroadcastSent('UserDeleted');
    }

    /**
     * Test enable/disable individual broadcast events
     *
     * @return void
     */
    public function testEnableDisableBroadcastEvent(): void
    {
        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);

        $this->table->disableBroadcastEvent('created');
        $this->table->broadcastEvent($user, 'created');
        $this->assertNoBroadcastsSent();

        $this->table->enableBroadcastEvent('created');
        $this->table->broadcastEvent($user, 'created');
        $this->assertBroadcastSent('UserCreated');
    }

    /**
     * Test broadcastEvent when broadcasting is disabled
     *
     * @return void
     */
    public function testBroadcastEventWhenDisabled(): void
    {
        $this->table->disableBroadcasting();

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->setBroadcastChannels(['test-channel']);
        $this->table->broadcastEvent($user, 'created');

        $this->assertNoBroadcastsSent();
    }

    /**
     * Test broadcastEvent with default channel (entity itself)
     *
     * @return void
     */
    public function testBroadcastEventWithDefaultChannel(): void
    {
        $user = new User([
            'id' => 123,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->table->broadcastEvent($user, 'created');

        $this->assertBroadcastSent('UserCreated');
        $this->assertBroadcastSentToChannels(['TestApp.Model.Entity.User.123'], 'UserCreated');
    }

    /**
     * Test initialization from behavior config
     *
     * @return void
     */
    public function testInitializationFromBehaviorConfig(): void
    {
        $table = new UsersTable();
        $table->addBehavior('Crustum/Broadcasting.Broadcasting', [
            'connection' => 'null',
            'queue' => null,
            'channels' => ['admin'],
            'payload' => ['custom' => 'data'],
            'enabled' => false,
            'broadcastEvents' => [
                'created' => false,
                'updated' => true,
            ],
        ]);

        $this->assertFalse($table->isBroadcastingEnabled());

        $table->enableBroadcasting();

        $user = new User([
            'id' => 999,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $table->broadcastEvent($user, 'created');
        $this->assertNoBroadcastsSent();

        TestBroadcaster::clearBroadcasts();

        $table->broadcastEvent($user, 'updated');
        $this->assertBroadcastSent('UserUpdated');
        $this->assertBroadcastSentToChannels(['admin'], 'UserUpdated');
    }
}
