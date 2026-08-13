<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Job;

use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Job\BulkBroadcastJob;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;

class BulkBroadcastJobTest extends TestCase
{
    use BroadcastingTrait;

    protected BulkBroadcastJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        Broadcasting::setConfig('test', [
            'className' => TestBroadcaster::class,
            'connectionName' => 'test',
        ]);

        $this->job = new BulkBroadcastJob();
    }

    protected function tearDown(): void
    {
        Broadcasting::drop('test');

        parent::tearDown();
    }

    public function testExecuteSuccess(): void
    {
        $message = $this->createMessageMock([
            'broadcasts' => [
                [
                    'channel' => 'user.1',
                    'event' => 'Notify',
                    'data' => ['id' => 1],
                    'socket' => null,
                ],
                [
                    'channel' => 'user.2',
                    'event' => 'Notify',
                    'data' => ['id' => 2],
                    'socket' => null,
                ],
            ],
            'connection' => 'test',
            'chunkSize' => 10,
        ]);

        $result = $this->job->execute($message);

        $this->assertSame(InteropProcessor::ACK, $result);
        $this->assertBroadcastSentTimes('Notify', 2);
        $this->assertBroadcastSentToChannel('user.1', 'Notify');
        $this->assertBroadcastSentToChannel('user.2', 'Notify');
    }

    public function testExecuteRejectsEmptyBroadcasts(): void
    {
        $message = $this->createMessageMock([
            'broadcasts' => [],
            'connection' => 'test',
        ]);

        $this->assertSame(InteropProcessor::REJECT, $this->job->execute($message));
    }

    public function testDisplayNameIncludesCount(): void
    {
        $displayName = BulkBroadcastJob::displayName([
            'broadcasts' => [
                ['channel' => 'a', 'event' => 'e', 'data' => []],
                ['channel' => 'b', 'event' => 'e', 'data' => []],
            ],
        ]);

        $this->assertSame(BulkBroadcastJob::class . '#2', $displayName);
    }

    /**
     * @param array<string, mixed> $data Message data
     * @return \Cake\Queue\Job\Message
     */
    protected function createMessageMock(array $data): Message
    {
        $originalMessage = $this->createStub(QueueMessage::class);
        $originalMessage->method('getMessageId')->willReturn('test-message-id');

        $message = $this->createStub(Message::class);
        $message->method('getArgument')->willReturnCallback(fn($key, $default = null) => $data[$key] ?? $default);
        $message->method('getOriginalMessage')->willReturn($originalMessage);

        return $message;
    }
}
