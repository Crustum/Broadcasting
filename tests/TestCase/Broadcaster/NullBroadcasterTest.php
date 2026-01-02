<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\NullBroadcaster;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * NullBroadcaster Test
 *
 * Comprehensive tests for the NullBroadcaster class.
 */
class NullBroadcasterTest extends TestCase
{
    /**
     * Test constructor without config
     *
     * @return void
     */
    public function testConstructorWithoutConfig(): void
    {
        $broadcaster = new NullBroadcaster();

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
        $this->assertEquals([], $broadcaster->getConfig());
    }

    /**
     * Test constructor with config
     *
     * @return void
     */
    public function testConstructorWithConfig(): void
    {
        $config = ['test' => 'value'];
        $broadcaster = new NullBroadcaster($config);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
        $this->assertEquals($config, $broadcaster->getConfig());
    }

    /**
     * Test auth method returns valid response
     *
     * @return void
     */
    public function testAuth(): void
    {
        $broadcaster = new NullBroadcaster();
        $request = $this->createMockRequest();

        $result = $broadcaster->auth($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertTrue($result['auth']);
    }

    /**
     * Test validAuthenticationResponse formats response
     *
     * @return void
     */
    public function testValidAuthenticationResponse(): void
    {
        $broadcaster = new NullBroadcaster();
        $request = $this->createMockRequest();
        $result = true;

        $response = $broadcaster->validAuthenticationResponse($request, $result);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('auth', $response);
        $this->assertTrue($response['auth']);
    }

    /**
     * Test broadcast logs broadcast
     *
     * @return void
     */
    public function testBroadcast(): void
    {
        $broadcaster = new NullBroadcaster();
        $channels = ['test-channel'];
        $event = 'test.event';
        $payload = ['data' => 'value'];

        $broadcaster->broadcast($channels, $event, $payload);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
    }

    /**
     * Test getName returns 'null'
     *
     * @return void
     */
    public function testGetName(): void
    {
        $broadcaster = new NullBroadcaster();

        $this->assertEquals('null', $broadcaster->getName());
    }

    /**
     * Test setConfig sets configuration
     *
     * @return void
     */
    public function testSetConfig(): void
    {
        $broadcaster = new NullBroadcaster();
        $config = ['new' => 'value'];

        $broadcaster->setConfig($config);

        $this->assertEquals($config, $broadcaster->getConfig());
    }

    /**
     * Test getConfig returns configuration
     *
     * @return void
     */
    public function testGetConfig(): void
    {
        $config = ['test' => 'value'];
        $broadcaster = new NullBroadcaster($config);

        $this->assertEquals($config, $broadcaster->getConfig());
    }

    /**
     * Test supportsChannelType returns true for all types
     *
     * @return void
     */
    public function testSupportsChannelType(): void
    {
        $broadcaster = new NullBroadcaster();

        $this->assertTrue($broadcaster->supportsChannelType('private'));
        $this->assertTrue($broadcaster->supportsChannelType('presence'));
        $this->assertTrue($broadcaster->supportsChannelType('public'));
        $this->assertTrue($broadcaster->supportsChannelType('encrypted-private'));
        $this->assertTrue($broadcaster->supportsChannelType('invalid'));
    }

    /**
     * Test resolveUserAuth returns null
     *
     * @return void
     */
    public function testResolveUserAuth(): void
    {
        $broadcaster = new NullBroadcaster();
        $request = $this->createMockRequest();

        $result = $broadcaster->resolveUserAuth($request);

        $this->assertNull($result);
    }

    /**
     * Test resolveAuthenticatedUser returns null
     *
     * @return void
     */
    public function testResolveAuthenticatedUser(): void
    {
        $broadcaster = new NullBroadcaster();
        $request = $this->createMockRequest();

        $result = $broadcaster->resolveAuthenticatedUser($request);

        $this->assertNull($result);
    }

    /**
     * Test setChannelCallbacks is no-op
     *
     * @return void
     */
    public function testSetChannelCallbacks(): void
    {
        $broadcaster = new NullBroadcaster();

        $broadcaster->setChannelCallbacks(['test' => function () {
            return true;
        }]);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
    }

    /**
     * Test setChannelOptions is no-op
     *
     * @return void
     */
    public function testSetChannelOptions(): void
    {
        $broadcaster = new NullBroadcaster();

        $broadcaster->setChannelOptions(['test' => ['option' => 'value']]);

        $this->assertInstanceOf(NullBroadcaster::class, $broadcaster);
    }

    /**
     * Create mock request
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createMockRequest(): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/test');
        $uri->method('getQuery')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getParsedBody')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);

        return $request;
    }
}
