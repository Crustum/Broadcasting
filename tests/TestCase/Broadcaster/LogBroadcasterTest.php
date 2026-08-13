<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\LogBroadcaster;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * LogBroadcaster Test
 *
 * Tests for the LogBroadcaster class functionality.
 */
class LogBroadcasterTest extends TestCase
{
    /**
     * Array log engine name used by tests that capture messages.
     *
     * @var string
     */
    protected string $arrayLogName = 'broadcasting_log_test';

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (Log::getConfig($this->arrayLogName) !== null) {
            Log::drop($this->arrayLogName);
        }

        parent::tearDown();
    }

    /**
     * Test broadcaster creation and basic properties
     *
     * @return void
     */
    public function testBroadcasterCreation(): void
    {
        $broadcaster = new LogBroadcaster();

        $this->assertEquals('log', $broadcaster->getName());
        $this->assertTrue($broadcaster->supportsChannelType('private'));
        $this->assertTrue($broadcaster->supportsChannelType('presence'));
        $this->assertTrue($broadcaster->supportsChannelType('public'));
        $this->assertTrue($broadcaster->supportsChannelType('invalid')); // LogBroadcaster supports all types
        $this->assertTrue($broadcaster->supportsChannelType('custom')); // LogBroadcaster supports all types
    }

    /**
     * Test broadcaster with custom configuration
     *
     * @return void
     */
    public function testBroadcasterWithConfig(): void
    {
        $config = ['test' => 'value'];

        $broadcaster = new LogBroadcaster($config);

        $this->assertEquals($config, $broadcaster->getConfig());
    }

    /**
     * Test authentication returns valid response structure
     *
     * @return void
     */
    public function testAuthentication(): void
    {
        $broadcaster = new LogBroadcaster();

        $request = $this->createMockRequest();

        $result = $broadcaster->auth($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertTrue($result['auth']);

        $authResponse = $broadcaster->validAuthenticationResponse($request, $result);
        $this->assertEquals($result, $authResponse);
    }

    /**
     * Test broadcasting
     *
     * @return void
     */
    public function testBroadcasting(): void
    {
        $broadcaster = new LogBroadcaster();
        $channels = ['public-chat', 'private-user.123'];
        $event = 'message.sent';
        $payload = ['text' => 'Hello World!', 'user' => 'Evgeny'];

        $broadcaster->broadcast($channels, $event, $payload);

        $this->assertSame('log', $broadcaster->getName());
    }

    /**
     * Test bulkBroadcast logs a single summary instead of N broadcast lines
     *
     * @return void
     */
    public function testBulkBroadcastLogsSummary(): void
    {
        Log::setConfig($this->arrayLogName, [
            'className' => ArrayLog::class,
            'levels' => ['info'],
        ]);

        /** @var \Cake\Log\Engine\ArrayLog $engine */
        $engine = Log::engine($this->arrayLogName);
        $engine->clear();

        $broadcaster = new LogBroadcaster();
        $broadcaster->bulkBroadcast([
            [
                'channel' => 'user.1',
                'event' => 'Notify',
                'data' => ['id' => 1],
            ],
            [
                'channel' => 'user.2',
                'event' => 'Notify',
                'data' => ['id' => 2],
            ],
        ]);

        $messages = $engine->read();
        $summaryMessages = array_values(array_filter(
            $messages,
            fn(string $message): bool => str_contains($message, 'Bulk Broadcasting 2 messages'),
        ));

        $this->assertCount(1, $summaryMessages);
        $this->assertStringContainsString('user.1', $summaryMessages[0]);
        $this->assertStringContainsString('user.2', $summaryMessages[0]);
        $this->assertCount(
            0,
            array_filter(
                $messages,
                fn(string $message): bool => str_contains($message, 'Broadcasting [Notify] on channels'),
            ),
        );
    }

    /**
     * Test configuration methods
     *
     * @return void
     */
    public function testConfigurationMethods(): void
    {
        $broadcaster = new LogBroadcaster();

        $config = ['test' => 'value'];
        $broadcaster->setConfig($config);
        $this->assertEquals($config, $broadcaster->getConfig());

        $newConfig = ['updated' => 'value', 'new' => 'data'];
        $broadcaster->setConfig($newConfig);
        $this->assertEquals($newConfig, $broadcaster->getConfig());
    }

    /**
     * Create a mock server request
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    private function createMockRequest(): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('__toString')->willReturn('http://example.com/test');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaders')->willReturn([]);

        return $request;
    }
}
