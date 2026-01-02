<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Queue;

use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Job\BroadcastJob;
use Crustum\Broadcasting\Queue\CakeQueueAdapter;
use Crustum\Broadcasting\Queue\QueueAdapterInterface;
use ReflectionClass;

/**
 * CakeQueueAdapter Test
 *
 * Tests for the CakeQueueAdapter class.
 */
class CakeQueueAdapterTest extends TestCase
{
    /**
     * Set up test case
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        QueueManager::setConfig('default', [
            'url' => 'null://',
        ]);
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        QueueManager::drop('default');
        parent::tearDown();
    }

    /**
     * Test push method calls QueueManager adapter
     *
     * @return void
     */
    public function testPush(): void
    {
        $adapter = new CakeQueueAdapter();

        $jobClass = BroadcastJob::class;
        $data = ['key' => 'value'];
        $options = ['delay' => 10];

        $adapter->push($jobClass, $data, $options);

        $this->assertInstanceOf(CakeQueueAdapter::class, $adapter);
    }

    /**
     * Test push with empty data
     *
     * @return void
     */
    public function testPushWithEmptyData(): void
    {
        $adapter = new CakeQueueAdapter();

        $jobClass = BroadcastJob::class;
        $data = [];
        $options = [];

        $adapter->push($jobClass, $data, $options);

        $this->assertInstanceOf(CakeQueueAdapter::class, $adapter);
    }

    /**
     * Test push with complex data
     *
     * @return void
     */
    public function testPushWithComplexData(): void
    {
        $adapter = new CakeQueueAdapter();

        $jobClass = BroadcastJob::class;
        $data = [
            'eventName' => 'user.updated',
            'channels' => ['private-user.123'],
            'payload' => ['user' => ['id' => 123, 'name' => 'Test']],
        ];
        $options = ['delay' => 60, 'priority' => 10];

        $adapter->push($jobClass, $data, $options);

        $this->assertInstanceOf(CakeQueueAdapter::class, $adapter);
    }

    /**
     * Test getUniqueId method calls QueueManager
     *
     * @return void
     */
    public function testGetUniqueId(): void
    {
        $adapter = new CakeQueueAdapter();

        $eventName = 'test.event';
        $type = 'broadcast';
        $data = ['channel' => 'test'];

        $uniqueId = $adapter->getUniqueId($eventName, $type, $data);

        $this->assertNotEmpty($uniqueId);
    }

    /**
     * Test getUniqueId with empty data
     *
     * @return void
     */
    public function testGetUniqueIdWithEmptyData(): void
    {
        $adapter = new CakeQueueAdapter();

        $eventName = 'test.event';
        $type = 'broadcast';
        $data = [];

        $uniqueId = $adapter->getUniqueId($eventName, $type, $data);

        $this->assertNotEmpty($uniqueId);
    }

    /**
     * Test getUniqueId returns same id for same inputs
     *
     * @return void
     */
    public function testGetUniqueIdConsistency(): void
    {
        $adapter = new CakeQueueAdapter();

        $eventName = 'test.event';
        $type = 'broadcast';
        $data = ['key' => 'value'];

        $uniqueId1 = $adapter->getUniqueId($eventName, $type, $data);
        $uniqueId2 = $adapter->getUniqueId($eventName, $type, $data);

        $this->assertEquals($uniqueId1, $uniqueId2);
    }

    /**
     * Test getUniqueId returns different ids for different inputs
     *
     * @return void
     */
    public function testGetUniqueIdUniqueness(): void
    {
        $adapter = new CakeQueueAdapter();

        $uniqueId1 = $adapter->getUniqueId('event1', 'broadcast', ['key' => 'value1']);
        $uniqueId2 = $adapter->getUniqueId('event2', 'broadcast', ['key' => 'value2']);

        $this->assertNotEquals($uniqueId1, $uniqueId2);
    }

    /**
     * Test adapter implements QueueAdapterInterface
     *
     * @return void
     */
    public function testImplementsQueueAdapterInterface(): void
    {
        $adapter = new CakeQueueAdapter();
        $reflection = new ReflectionClass($adapter);

        $this->assertTrue($reflection->implementsInterface(QueueAdapterInterface::class));
    }
}
