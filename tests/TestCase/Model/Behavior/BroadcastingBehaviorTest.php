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
 *
 * Tests behavior-specific functionality: event handling and event mapping
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

        $this->table = new UsersTable();
        $this->table->addBehavior('Crustum/Broadcasting.Broadcasting');
        /** @var \Crustum\Broadcasting\Model\Behavior\BroadcastingBehavior $behavior */
        $behavior = $this->table->getBehavior('Broadcasting');
        $this->behavior = $behavior;
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
     * Test default events configuration
     *
     * @return void
     */
    public function testDefaultEventsConfiguration(): void
    {
        $expectedEvents = [
            'Model.afterSave' => 'saved',
            'Model.afterDelete' => 'deleted',
        ];

        $this->assertEquals($expectedEvents, $this->behavior->getConfig('events'));
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
     * Test handleEvent maps events correctly
     *
     * @return void
     */
    public function testHandleEventMapsEvents(): void
    {
        $this->table->setBroadcastChannels(['test-channel']);

        $user = new User([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        /** @var \Cake\Event\EventInterface<\Cake\ORM\Table>&\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createStub(EventInterface::class);
        $event->method('getName')->willReturn('Model.afterSave');
        $event->method('getSubject')->willReturn($this->table);

        $this->behavior->handleEvent($event, $user);
    }

    /**
     * Test handleEvent maps afterSave to created for new entities
     *
     * @return void
     */
    public function testHandleEventMapsAfterSaveToCreated(): void
    {
        $this->table->setBroadcastChannels(['test-channel']);

        $user = new User([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $user->setNew(true);

        /** @var \Cake\Event\EventInterface<\Cake\ORM\Table>&\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createStub(EventInterface::class);
        $event->method('getName')->willReturn('Model.afterSave');
        $event->method('getSubject')->willReturn($this->table);

        $this->behavior->handleEvent($event, $user);
    }

    /**
     * Test handleEvent maps afterSave to updated for existing entities
     *
     * @return void
     */
    public function testHandleEventMapsAfterSaveToUpdated(): void
    {
        $this->table->setBroadcastChannels(['test-channel']);

        $user = new User([
            'id' => 1,
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $user->setNew(false);

        /** @var \Cake\Event\EventInterface<\Cake\ORM\Table>&\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createStub(EventInterface::class);
        $event->method('getName')->willReturn('Model.afterSave');
        $event->method('getSubject')->willReturn($this->table);

        $this->behavior->handleEvent($event, $user);
    }
}
