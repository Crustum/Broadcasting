<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Job;

use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Job\BroadcastJob;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;

class BroadcastJobTest extends TestCase
{
    protected BroadcastJob $broadcastJob;

    protected function setUp(): void
    {
        parent::setUp();

        Broadcasting::setConfig('test', [
            'className' => 'Crustum/Broadcasting.Null',
        ]);

        $this->broadcastJob = new BroadcastJob();
    }

    protected function tearDown(): void
    {
        Broadcasting::drop('test');

        parent::tearDown();
    }

    public function testExecuteSuccess(): void
    {
        $messageData = [
            'eventName' => 'test.event',
            'channels' => ['test-channel'],
            'payload' => ['key' => 'value'],
            'config' => 'test',
        ];

        $message = $this->createMessageMock($messageData);
        $result = $this->broadcastJob->execute($message);

        $this->assertEquals(InteropProcessor::ACK, $result);
    }

    public function testExecuteWithSocket(): void
    {
        $messageData = [
            'eventName' => 'test.event',
            'channels' => ['test-channel'],
            'payload' => ['key' => 'value'],
            'config' => 'test',
            'socket' => 'socket-123',
        ];

        $message = $this->createMessageMock($messageData);
        $result = $this->broadcastJob->execute($message);

        $this->assertEquals(InteropProcessor::ACK, $result);
    }

    public function testExecuteWithMissingEventName(): void
    {
        $messageData = [
            'channels' => ['test-channel'],
            'payload' => ['key' => 'value'],
        ];

        $message = $this->createMessageMock($messageData);
        $result = $this->broadcastJob->execute($message);

        $this->assertEquals(InteropProcessor::REJECT, $result);
    }

    public function testExecuteWithMissingChannels(): void
    {
        $messageData = [
            'eventName' => 'test.event',
            'payload' => ['key' => 'value'],
        ];

        $message = $this->createMessageMock($messageData);
        $result = $this->broadcastJob->execute($message);

        $this->assertEquals(InteropProcessor::REJECT, $result);
    }

    public function testExecuteWithEmptyPayload(): void
    {
        $messageData = [
            'eventName' => 'test.event',
            'channels' => ['test-channel'],
            'config' => 'test',
        ];

        $message = $this->createMessageMock($messageData);
        $result = $this->broadcastJob->execute($message);

        $this->assertEquals(InteropProcessor::ACK, $result);
    }

    /**
     * Create a mock message
     *
     * @param array<string, mixed> $data Message data
     * @return \Cake\Queue\Job\Message
     */
    protected function createMessageMock(array $data): Message
    {
        $originalMessage = $this->createStub(QueueMessage::class);
        $originalMessage->method('getMessageId')->willReturn('test-message-id');

        $message = $this->createStub(Message::class);
        $message->method('getArgument')->willReturnCallback(function ($key, $default = null) use ($data) {
            return $data[$key] ?? $default;
        });
        $message->method('getOriginalMessage')->willReturn($originalMessage);

        return $message;
    }
}
