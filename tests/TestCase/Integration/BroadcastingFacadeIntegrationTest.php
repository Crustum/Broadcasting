<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Integration;

use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidBroadcasterException;
use Crustum\Broadcasting\Job\BroadcastJob;
use Crustum\Broadcasting\Job\UniqueBroadcastJob;
use Crustum\Broadcasting\Test\TestApp\Event\TestBroadcastableClass;
use Crustum\Broadcasting\Test\TestApp\Event\TestUniqueBroadcastableClass;
use Crustum\Broadcasting\Test\TestCase\Broadcaster\FailingLogBroadcaster;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use Crustum\Broadcasting\TestSuite\TestQueueAdapter;

/**
 * Broadcasting Facade Integration Test
 *
 * End-to-end facade paths: sync send, queue, unique queue, connection errors.
 */
class BroadcastingFacadeIntegrationTest extends TestCase
{
    use BroadcastingTrait;

    /**
     * Set up test case
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Broadcasting::setConfig('pusher', [
            'className' => TestBroadcaster::class,
            'connectionName' => 'pusher',
        ]);

        QueueManager::setConfig('default', [
            'url' => 'null://',
        ]);
        QueueManager::setConfig('broadcasting', [
            'url' => 'null://',
        ]);
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    protected function tearDown(): void
    {
        QueueManager::drop('default');
        QueueManager::drop('broadcasting');

        parent::tearDown();
    }

    /**
     * Sync fluent send is captured by TestBroadcaster
     *
     * @return void
     */
    public function testSyncSendPath(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['order_id' => 42])
            ->send();

        $this->assertBroadcastSent('OrderCreated');
        $this->assertBroadcastSentToChannel('orders', 'OrderCreated');
        $this->assertBroadcastPayloadContains('OrderCreated', 'order_id', 42);
        $this->assertBroadcastQueuedCount(0);
    }

    /**
     * Queued fluent path pushes BroadcastJob
     *
     * @return void
     */
    public function testQueuedPathUsesBroadcastJob(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['order_id' => 42])
            ->queue('broadcasting');

        $this->assertBroadcastQueued('OrderCreated');
        $this->assertBroadcastQueuedToChannel('orders', 'OrderCreated');
        $this->assertCount(1, TestQueueAdapter::getQueuedJobsByClass(BroadcastJob::class));
        $this->assertNoBroadcastsSent();
    }

    /**
     * Unique queued path uses UniqueBroadcastJob and ignores duplicates
     *
     * @return void
     */
    public function testUniqueQueuedPathUsesUniqueBroadcastJob(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['order_id' => 1])
            ->unique()
            ->queue();

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['order_id' => 1])
            ->unique()
            ->queue();

        $this->assertBroadcastQueuedCount(1);
        $this->assertCount(1, TestQueueAdapter::getQueuedJobsByClass(UniqueBroadcastJob::class));
    }

    /**
     * Event with broadcastUnique() queues UniqueBroadcastJob once
     *
     * @return void
     */
    public function testEventBroadcastUniqueQueuesOnce(): void
    {
        $event = new TestUniqueBroadcastableClass();
        $event->setEventName('UniqueEvent')
            ->setChannels(new Channel('orders'))
            ->setData(['id' => 1])
            ->setQueue('broadcasting');

        Broadcasting::event($event);
        Broadcasting::event($event);

        $this->assertBroadcastQueuedCount(1);
        $this->assertCount(1, TestQueueAdapter::getQueuedJobsByClass(UniqueBroadcastJob::class));
    }

    /**
     * Queueable event without unique uses BroadcastJob
     *
     * @return void
     */
    public function testQueueableEventUsesBroadcastJob(): void
    {
        $event = new TestBroadcastableClass();
        $event->setEventName('RegularEvent')
            ->setChannels(new Channel('orders'))
            ->setData(['id' => 1])
            ->setQueue('broadcasting');

        Broadcasting::event($event);

        $this->assertCount(1, TestQueueAdapter::getQueuedJobsByClass(BroadcastJob::class));
    }

    /**
     * Unknown connection throws InvalidBroadcasterException
     *
     * @return void
     */
    public function testUnknownConnectionThrows(): void
    {
        $this->expectException(InvalidBroadcasterException::class);

        Broadcasting::get('alien_connection');
    }

    /**
     * Driver creation failure with fallback false wraps the error
     *
     * @return void
     */
    public function testDriverCreationFailureIsWrapped(): void
    {
        Broadcasting::setConfig('log_connection_1', [
            'className' => FailingLogBroadcaster::class,
            'fallback' => false,
        ]);

        try {
            Broadcasting::get('log_connection_1');
            $this->fail('Expected BroadcastingException was not thrown');
        } catch (BroadcastingException $broadcastingException) {
            $this->assertStringContainsString(
                'Failed to create broadcaster for connection "log_connection_1"',
                $broadcastingException->getMessage(),
            );
            $this->assertStringContainsString('Logger service not available', $broadcastingException->getMessage());
            $this->assertNotNull($broadcastingException->getPrevious());
        }
    }
}
