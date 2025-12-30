<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\RedisBroadcaster;
use Crustum\Broadcasting\Exception\BroadcastingException;

/**
 * RedisBroadcaster Test Case
 *
 * @package Crustum\Broadcasting\Test\TestCase\Broadcaster
 */
class RedisBroadcasterTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Broadcasting\Broadcaster\RedisBroadcaster
     */
    protected RedisBroadcaster $redisBroadcaster;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'connection' => 'default',
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => null,
                'database' => 0,
            ],
        ];

        $this->redisBroadcaster = new RedisBroadcaster($config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(RedisBroadcaster::class, $this->redisBroadcaster);
        $this->assertEquals('default', $this->redisBroadcaster->getConfig()['connection']);
    }

    /**
     * Test getName method
     *
     * @return void
     */
    public function testGetName(): void
    {
        $this->assertEquals('redis', $this->redisBroadcaster->getName());
    }

    /**
     * Test supportsChannelType method
     *
     * @return void
     */
    public function testSupportsChannelType(): void
    {
        $this->assertTrue($this->redisBroadcaster->supportsChannelType('public'));
        $this->assertTrue($this->redisBroadcaster->supportsChannelType('private'));
        $this->assertTrue($this->redisBroadcaster->supportsChannelType('presence'));
        $this->assertFalse($this->redisBroadcaster->supportsChannelType('invalid'));
    }

    /**
     * Test auth method with missing channel name
     *
     * @return void
     */
    public function testAuthWithMissingChannelName(): void
    {
        $request = new ServerRequest();
        $request = $request->withParsedBody([]);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('Missing channel name');

        $this->redisBroadcaster->auth($request);
    }

    /**
     * Test auth method with public channel
     *
     * @return void
     */
    public function testAuthWithPublicChannel(): void
    {
        $request = new ServerRequest();
        $request = $request->withParsedBody(['channel_name' => 'test-channel']);

        $result = $this->redisBroadcaster->auth($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertTrue($result['auth']);
    }

    /**
     * Test auth method with private channel
     *
     * @return void
     */
    public function testAuthWithPrivateChannel(): void
    {
        $this->redisBroadcaster->registerChannel('private-test', function ($user) {
            return true;
        });

        $this->redisBroadcaster->registerUserResolver(function ($request) {
            return ['id' => 1, 'name' => 'Test User'];
        });

        $request = new ServerRequest();
        $request = $request->withParsedBody(['channel_name' => 'private-test']);

        $result = $this->redisBroadcaster->auth($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertTrue($result['auth']);
    }

    /**
     * Test auth method with presence channel
     *
     * @return void
     */
    public function testAuthWithPresenceChannel(): void
    {
        $this->redisBroadcaster->registerChannel('presence-test', function ($user) {
            return true;
        });

        $this->redisBroadcaster->registerUserResolver(function ($request) {
            return ['id' => 1, 'name' => 'Test User'];
        });

        $request = new ServerRequest();
        $request = $request->withParsedBody(['channel_name' => 'presence-test']);

        $result = $this->redisBroadcaster->auth($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayHasKey('channel_data', $result);
        $this->assertTrue($result['auth']);
    }

    /**
     * Test auth method with presence channel without user
     *
     * @return void
     */
    public function testAuthWithPresenceChannelWithoutUser(): void
    {
        $this->redisBroadcaster->registerChannel('presence-test', function ($user) {
            return true;
        });

        $request = new ServerRequest();
        $request = $request->withParsedBody(['channel_name' => 'presence-test']);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('User not authenticated for presence channel');

        $this->redisBroadcaster->auth($request);
    }

    /**
     * Test validAuthenticationResponse method
     *
     * @return void
     */
    public function testValidAuthenticationResponse(): void
    {
        $request = new ServerRequest();
        $result = ['auth' => true];

        $response = $this->redisBroadcaster->validAuthenticationResponse($request, $result);

        $this->assertEquals($result, $response);
    }

    /**
     * Test broadcast method
     *
     * @return void
     */
    public function testBroadcast(): void
    {
        $channels = ['test-channel'];
        $event = 'test-event';
        $payload = ['data' => 'test-data'];

        $this->redisBroadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
    }

    /**
     * Test broadcast method with empty channels
     *
     * @return void
     */
    public function testBroadcastWithEmptyChannels(): void
    {
        $channels = [];
        $event = 'test-event';
        $payload = ['data' => 'test-data'];

        $this->redisBroadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
    }

    /**
     * Test broadcast method with multiple channels
     *
     * @return void
     */
    public function testBroadcastWithMultipleChannels(): void
    {
        $channels = ['channel1', 'channel2', 'channel3'];
        $event = 'test-event';
        $payload = ['data' => 'test-data'];

        $this->redisBroadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
    }

    /**
     * Test broadcast method with socket payload
     *
     * @return void
     */
    public function testBroadcastWithSocketPayload(): void
    {
        $channels = ['test-channel'];
        $event = 'test-event';
        $payload = ['data' => 'test-data', 'socket' => 'test-socket-id'];

        $this->redisBroadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
    }
}
