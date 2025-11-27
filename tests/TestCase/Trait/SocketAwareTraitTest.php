<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Trait;

use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Test\TestApp\Trait\TestSocketAwareClass;

/**
 * Socket Aware Trait Test
 *
 * @package Crustum\Broadcasting\Test\TestCase\Trait
 */
class SocketAwareTraitTest extends TestCase
{
    /**
     * Test class using the trait.
     *
     * @var \Crustum\Broadcasting\Test\TestApp\Trait\TestSocketAwareClass
     */
    protected TestSocketAwareClass $testClass;

    /**
     * Set up test case.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testClass = new TestSocketAwareClass();
    }

    /**
     * Test dontBroadcastToCurrentUser extracts socket from request header.
     *
     * @return void
     */
    public function testDontBroadcastToCurrentUserWithHeader(): void
    {
        $request = new ServerRequest([
            'environment' => [
                'HTTP_X_SOCKET_ID' => 'test-socket-123',
            ],
        ]);
        Router::setRequest($request);

        $result = $this->testClass->dontBroadcastToCurrentUser();

        $this->assertSame($this->testClass, $result);
        $this->assertEquals('test-socket-123', $this->testClass->getSocket());

        Router::setRequest(new ServerRequest());
    }

    /**
     * Test dontBroadcastToCurrentUser extracts socket from query parameter.
     *
     * @return void
     */
    public function testDontBroadcastToCurrentUserWithQueryParam(): void
    {
        $request = new ServerRequest([
            'query' => [
                'socket_id' => 'test-socket-456',
            ],
        ]);
        Router::setRequest($request);

        $result = $this->testClass->dontBroadcastToCurrentUser();

        $this->assertSame($this->testClass, $result);
        $this->assertEquals('test-socket-456', $this->testClass->getSocket());

        Router::setRequest(new ServerRequest());
    }

    /**
     * Test dontBroadcastToCurrentUser with no request returns null.
     *
     * @return void
     */
    public function testDontBroadcastToCurrentUserWithoutRequest(): void
    {
        Router::setRequest(new ServerRequest());

        $result = $this->testClass->dontBroadcastToCurrentUser();

        $this->assertSame($this->testClass, $result);
        $this->assertNull($this->testClass->getSocket());
    }

    /**
     * Test broadcastToEveryone clears socket.
     *
     * @return void
     */
    public function testBroadcastToEveryone(): void
    {
        $this->testClass->setSocket('test-socket');

        $result = $this->testClass->broadcastToEveryone();

        $this->assertSame($this->testClass, $result);
        $this->assertNull($this->testClass->getSocket());
    }

    /**
     * Test setSocket method.
     *
     * @return void
     */
    public function testSetSocket(): void
    {
        $result = $this->testClass->setSocket('test-socket-789');

        $this->assertSame($this->testClass, $result);
        $this->assertEquals('test-socket-789', $this->testClass->getSocket());
    }

    /**
     * Test getSocket method.
     *
     * @return void
     */
    public function testGetSocket(): void
    {
        $this->testClass->setSocket('test-socket-abc');

        $socket = $this->testClass->getSocket();

        $this->assertEquals('test-socket-abc', $socket);
    }

    /**
     * Test header takes precedence over query parameter.
     *
     * @return void
     */
    public function testHeaderTakesPrecedenceOverQuery(): void
    {
        $request = new ServerRequest([
            'environment' => [
                'HTTP_X_SOCKET_ID' => 'header-socket',
            ],
            'query' => [
                'socket_id' => 'query-socket',
            ],
        ]);
        Router::setRequest($request);

        $this->testClass->dontBroadcastToCurrentUser();

        $this->assertEquals('header-socket', $this->testClass->getSocket());

        Router::setRequest(new ServerRequest());
    }

    /**
     * Tear down test case.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Router::setRequest(new ServerRequest());
        parent::tearDown();
    }
}
