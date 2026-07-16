<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase;

use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Test\TestApp\Event\TestBroadcastableClass;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use Crustum\Broadcasting\TestSuite\TestQueueAdapter;

/**
 * Bulk Broadcasting Test
 *
 * Covers sync bulk fan-out and queueBulk chunking.
 */
class BulkBroadcastingTest extends TestCase
{
    use BroadcastingTrait;

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

    protected function tearDown(): void
    {
        QueueManager::drop('default');
        QueueManager::drop('broadcasting');

        parent::tearDown();
    }

    public function testBulkWithFlatArrays(): void
    {
        Broadcasting::bulk([
            [
                'channel' => 'user.1',
                'event' => 'Progress',
                'data' => ['progress' => 10],
            ],
            [
                'channel' => 'user.2',
                'event' => 'Progress',
                'data' => ['progress' => 20],
            ],
        ], 'pusher');

        $this->assertBroadcastSentTimes('Progress', 2);
        $this->assertBroadcastPayloadContains('Progress', 'progress', 10);
    }

    public function testBulkWithEventObjectsExpandsChannels(): void
    {
        $event = (new TestBroadcastableClass())
            ->setChannels([
                new Channel('student.1'),
                new Channel('student.2'),
            ])
            ->setEventName('CourseProgress')
            ->setData(['progress' => 55]);

        Broadcasting::bulk([$event], 'pusher');

        $this->assertBroadcastSentTimes('CourseProgress', 2);
        $this->assertBroadcastSentToChannel('student.1', 'CourseProgress');
        $this->assertBroadcastSentToChannel('student.2', 'CourseProgress');
    }

    public function testBulkSkipsConditionalEvents(): void
    {
        $event = (new TestBroadcastableClass())
            ->setChannels(new Channel('student.1'))
            ->setEventName('Skipped')
            ->setShouldBroadcast(false);

        Broadcasting::bulk([$event], 'pusher');

        $this->assertNoBroadcastsSent();
    }

    public function testBulkRejectsInvalidFlatItem(): void
    {
        $this->expectException(BroadcastingException::class);

        Broadcasting::bulk([
            ['event' => 'MissingChannel', 'data' => []],
        ], 'pusher');
    }

    public function testQueueBulkChunksJobs(): void
    {
        TestQueueAdapter::replaceQueueAdapter();

        $broadcasts = [];
        for ($i = 0; $i < 25; $i++) {
            $broadcasts[] = [
                'channel' => 'user.' . $i,
                'event' => 'Notify',
                'data' => ['i' => $i],
            ];
        }

        Broadcasting::queueBulk($broadcasts, 'pusher', [
            'queue' => 'broadcasting',
            'chunkSize' => 10,
        ]);

        $this->assertBulkBroadcastQueued();
        $this->assertBulkBroadcastQueuedCount(3);
        $this->assertBulkBroadcastQueuedEvent('Notify');
        $this->assertBulkBroadcastQueuedToChannel('user.0', 'Notify');
        $this->assertBulkBroadcastQueuedToChannel('user.24', 'Notify');

        $jobs = $this->getQueuedBulkBroadcastJobs();
        $this->assertCount(10, $jobs[0]['data']['broadcasts']);
        $this->assertCount(10, $jobs[1]['data']['broadcasts']);
        $this->assertCount(5, $jobs[2]['data']['broadcasts']);
        $this->assertCount(25, $this->getQueuedBulkBroadcastItems());
    }
}
