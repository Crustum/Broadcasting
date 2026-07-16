<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Job;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Job\BroadcastJob;
use Crustum\Broadcasting\Job\UniqueBroadcastJob;
use Crustum\Broadcasting\Test\TestApp\Event\TestBroadcastableClass;
use Crustum\Broadcasting\Test\TestApp\Event\TestUniqueBroadcastableClass;
use Crustum\Broadcasting\TestSuite\TestQueueAdapter;
use ReflectionClass;

/**
 * Unique Broadcast Job Test
 *
 * Covers CakePHP Queue uniqueness via UniqueBroadcastJob::$shouldBeUnique.
 */
class UniqueBroadcastJobTest extends TestCase
{
    /**
     * Set up test case
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        TestQueueAdapter::replaceQueueAdapter();
        TestQueueAdapter::clearQueuedJobs();
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    protected function tearDown(): void
    {
        TestQueueAdapter::clearQueuedJobs();

        parent::tearDown();
    }

    /**
     * Test UniqueBroadcastJob opts into Cake Queue uniqueness
     *
     * @return void
     */
    public function testUniqueBroadcastJobShouldBeUniqueFlag(): void
    {
        $this->assertTrue(UniqueBroadcastJob::$shouldBeUnique);

        $staticProperties = (new ReflectionClass(BroadcastJob::class))->getStaticProperties();
        $this->assertArrayNotHasKey('shouldBeUnique', $staticProperties);
    }

    /**
     * Test unique() queues UniqueBroadcastJob
     *
     * @return void
     */
    public function testUniqueFluentQueuesUniqueBroadcastJob(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['id' => 1])
            ->unique()
            ->queue('broadcasting');

        $jobs = TestQueueAdapter::getQueuedJobsByClass(UniqueBroadcastJob::class);
        $this->assertCount(1, $jobs);
        $this->assertSame('OrderCreated', $jobs[0]['data']['eventName']);
    }

    /**
     * Test duplicate unique jobs with identical data are ignored
     *
     * @return void
     */
    public function testDuplicateUniqueJobsAreIgnored(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['id' => 1])
            ->unique()
            ->queue();

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['id' => 1])
            ->unique()
            ->queue();

        $this->assertSame(1, TestQueueAdapter::getQueuedJobCount());
        $this->assertCount(1, TestQueueAdapter::getQueuedJobsByClass(UniqueBroadcastJob::class));
    }

    /**
     * Test uniqueKey differentiates otherwise identical payloads
     *
     * @return void
     */
    public function testUniqueKeyDifferentiatesJobs(): void
    {
        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['id' => 1])
            ->unique(true, 'key-a')
            ->queue();

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data(['id' => 1])
            ->unique(true, 'key-b')
            ->queue();

        $this->assertSame(2, TestQueueAdapter::getQueuedJobCount());
    }

    /**
     * Test event broadcastUnique() selects UniqueBroadcastJob
     *
     * @return void
     */
    public function testEventBroadcastUniqueUsesUniqueJob(): void
    {
        $event = new TestUniqueBroadcastableClass();
        $event->setEventName('UniqueEvent')
            ->setChannels(new Channel('orders'))
            ->setData(['id' => 1])
            ->setQueue('broadcasting');

        Broadcasting::event($event);
        Broadcasting::event($event);

        $this->assertSame(1, TestQueueAdapter::getQueuedJobCount());
        $this->assertCount(1, TestQueueAdapter::getQueuedJobsByClass(UniqueBroadcastJob::class));
        $this->assertSame('unique-event-key', TestQueueAdapter::getQueuedJobs()[0]['data']['uniqueKey']);
    }

    /**
     * Test non-unique queueable events still use BroadcastJob
     *
     * @return void
     */
    public function testNonUniqueQueueableUsesBroadcastJob(): void
    {
        $event = new TestBroadcastableClass();
        $event->setEventName('RegularEvent')
            ->setChannels(new Channel('orders'))
            ->setData(['id' => 1])
            ->setQueue('broadcasting');

        Broadcasting::event($event);
        Broadcasting::event($event);

        $this->assertSame(2, TestQueueAdapter::getQueuedJobCount());
        $this->assertCount(2, TestQueueAdapter::getQueuedJobsByClass(BroadcastJob::class));
    }
}
