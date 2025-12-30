<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

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

        // Test that broadcasting doesn't throw an exception
        $broadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
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

        // Test updating configuration
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
