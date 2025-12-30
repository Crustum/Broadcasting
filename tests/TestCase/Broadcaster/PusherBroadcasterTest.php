<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Cake\Datasource\EntityInterface;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\PusherBroadcaster;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidChannelException;
use Exception;
use Pusher\Pusher;
use ReflectionClass;
use TestApp\Broadcasting\InvalidChannel;
use TestApp\Broadcasting\TestOrderChannel;
use TestApp\Broadcasting\TestPresenceChannel;
use TestApp\Broadcasting\UnauthorizedChannel;

/**
 * PusherBroadcaster Test
 *
 * Tests for the PusherBroadcaster class functionality.
 */
class PusherBroadcasterTest extends TestCase
{
    /**
     * PusherBroadcaster instance for testing.
     *
     * @var \Crustum\Broadcasting\Broadcaster\PusherBroadcaster
     */
    protected PusherBroadcaster $pusherBroadcaster;

    /**
     * Mock Pusher client.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $mockPusher;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'options' => [
                'cluster' => 'test-cluster',
                'useTLS' => true,
            ],
        ];

        $this->mockPusher = $this->createMock(Pusher::class);
        $this->pusherBroadcaster = $this->createMockPusherBroadcaster($config);
    }

    /**
     * Tear down test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->pusherBroadcaster, $this->mockPusher);
        parent::tearDown();
    }

    /**
     * Create a mock PusherBroadcaster with mocked Pusher client.
     *
     * @param array<string, mixed> $config Configuration array
     * @return \Crustum\Broadcasting\Broadcaster\PusherBroadcaster
     */
    protected function createMockPusherBroadcaster(array $config): PusherBroadcaster
    {
        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['createPusherClient'])
            ->getMock();

        $broadcaster->method('createPusherClient')
            ->willReturn($this->mockPusher);

        return $broadcaster;
    }

    /**
     * Test constructor with valid configuration
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(PusherBroadcaster::class, $this->pusherBroadcaster);
        $this->assertEquals('test-app-id', $this->pusherBroadcaster->getConfig()['app_id']);
        $this->assertEquals('test-key', $this->pusherBroadcaster->getConfig()['key']);
        $this->assertEquals('test-secret', $this->pusherBroadcaster->getConfig()['secret']);
        $this->assertEquals('test-cluster', $this->pusherBroadcaster->getConfig()['options']['cluster']);
    }

    /**
     * Test constructor with missing required configuration
     *
     * @return void
     */
    public function testConstructorWithMissingConfig(): void
    {
        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('Pusher configuration \'key\' is required.');

        new PusherBroadcaster(['app_id' => 'test']);
    }

    /**
     * Test getName method
     *
     * @return void
     */
    public function testGetName(): void
    {
        $this->assertEquals('pusher', $this->pusherBroadcaster->getName());
    }

    /**
     * Test supportsChannelType method
     *
     * @return void
     */
    public function testSupportsChannelType(): void
    {
        $this->assertTrue($this->pusherBroadcaster->supportsChannelType('public'));
        $this->assertTrue($this->pusherBroadcaster->supportsChannelType('private'));
        $this->assertTrue($this->pusherBroadcaster->supportsChannelType('presence'));
        $this->assertFalse($this->pusherBroadcaster->supportsChannelType('invalid'));
        $this->assertFalse($this->pusherBroadcaster->supportsChannelType(''));
    }

    /**
     * Test auth method with missing channel name
     *
     * @return void
     */
    public function testAuthWithMissingChannelName(): void
    {
        $request = new ServerRequest();
        $request = $request->withParsedBody(['socket_id' => 'test-socket-id']);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('Missing required parameters: channel_name');

        $this->pusherBroadcaster->auth($request);
    }

    /**
     * Test auth method with missing socket ID
     *
     * @return void
     */
    public function testAuthWithMissingSocketId(): void
    {
        $request = new ServerRequest();
        $request = $request->withParsedBody(['channel_name' => 'test-channel']);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('Missing required parameters: socket_id');

        $this->pusherBroadcaster->auth($request);
    }

    /**
     * Test auth method with invalid channel
     *
     * @return void
     */
    public function testAuthWithInvalidChannel(): void
    {
        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'invalid-channel',
            'socket_id' => 'test-socket-id',
        ]);

        $this->expectException(InvalidChannelException::class);
        $this->expectExceptionMessage('Unauthorized access to channel [invalid-channel].');

        $this->pusherBroadcaster->auth($request);
    }

    /**
     * Test auth method with private channel
     *
     * @return void
     */
    public function testAuthWithPrivateChannel(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $this->mockPusher->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = new PusherBroadcaster($config);

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        // Register the channel first
        $broadcaster->registerChannel('private-test', function ($user) {
            return true;
        });

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('test-key:test-signature', $result['auth']);
    }

    /**
     * Test auth method with presence channel
     *
     * @return void
     */
    public function testAuthWithPresenceChannel(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $this->mockPusher->method('authorizePresenceChannel')
            ->with('presence-test', '123.456', '1', $this->anything())
            ->willReturn('{"auth":"test-key:test-signature","channel_data":"{\"user_info\":{\"id\":1,\"name\":\"Test User\"}}"}');

        $mockEntity = $this->createMock(EntityInterface::class);
        $mockEntity->method('get')
            ->willReturnMap([
                ['id', 1],
                ['full_name', 'Test User'],
                ['username', 'testuser'],
            ]);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['resolveUserFromRequest'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('resolveUserFromRequest')
            ->willReturn($mockEntity);

        $broadcaster->setChannelCallbacks(['presence-test' => function ($user) {
            return true;
        }]);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'presence-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('test-key:test-signature', $result['auth']);
        $this->assertArrayHasKey('channel_data', $result);
    }

    /**
     * Test auth method with presence channel without user
     *
     * @return void
     */
    public function testAuthWithPresenceChannelWithoutUser(): void
    {
        $this->pusherBroadcaster->registerChannel('presence-test', function ($user) {
            return true;
        });

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'presence-test',
            'socket_id' => 'test-socket-id',
        ]);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('User not authenticated for presence channel');

        $this->pusherBroadcaster->auth($request);
    }

    /**
     * Test validAuthenticationResponse method
     *
     * @return void
     */
    public function testValidAuthenticationResponse(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $this->mockPusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = new PusherBroadcaster($config);

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('test-key:test-signature', $result['auth']);
    }

    /**
     * Test broadcast method with valid channels
     *
     * @return void
     */
    public function testBroadcast(): void
    {
        $channels = ['test-channel'];
        $event = 'test-event';
        $payload = ['data' => 'test-data'];

        $this->pusherBroadcaster->broadcast($channels, $event, $payload);

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

        $this->mockPusher->expects($this->never())
            ->method('trigger');

        $this->pusherBroadcaster->broadcast($channels, $event, $payload);
    }

    /**
     * Test broadcast method with multiple channels
     *
     * @return void
     */
    public function testBroadcastWithMultipleChannels(): void
    {
        $channels = ['channel1', 'channel2', 'channel3'];
        $event = 'multi-channel-event';
        $payload = ['message' => 'Hello World', 'timestamp' => time()];

        $this->pusherBroadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
    }

    /**
     * Test broadcast method with complex payload
     *
     * @return void
     */
    public function testBroadcastWithComplexPayload(): void
    {
        $channels = ['complex-channel'];
        $event = 'complex-event';
        $payload = [
            'user' => ['id' => 123, 'name' => 'Test User'],
            'data' => ['nested' => ['value' => 'test']],
            'metadata' => ['tags' => ['important', 'urgent']],
        ];

        $this->pusherBroadcaster->broadcast($channels, $event, $payload);

        // Method executed successfully without throwing exception
    }

    /**
     * Test getClient method
     *
     * @return void
     */
    public function testGetClient(): void
    {
        $client = $this->pusherBroadcaster->getClient();
        $this->assertInstanceOf(Pusher::class, $client);
    }

    /**
     * Test auth with class-based channel that implements ChannelInterface
     *
     * @return void
     */
    public function testAuthWithChannelClass(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $this->mockPusher->method('authorizeChannel')
            ->with('orders.123', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $mockUser = $this->createMock(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
                ['name', 'Test User'],
            ]);

        $mockOrder = $this->createMock(EntityInterface::class);
        $mockOrder->method('get')
            ->willReturnMap([
                ['id', 123],
                ['user_id', 1],
            ]);

        $mockTable = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $mockTable->method('get')
            ->with('123')
            ->willReturn($mockOrder);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['retrieveUserFromCakeRequest', 'getTableLocator'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $mockLocator = $this->createMock(LocatorInterface::class);
        $mockLocator->method('get')
            ->with('Orders')
            ->willReturn($mockTable);

        $broadcaster->method('getTableLocator')
            ->willReturn($mockLocator);

        $broadcaster->registerChannel('orders.{order}', TestOrderChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'orders.123',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('test-key:test-signature', $result['auth']);
    }

    /**
     * Test auth with class-based presence channel returning user data array
     *
     * @return void
     */
    public function testAuthWithChannelClassPresence(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $this->mockPusher->method('authorizePresenceChannel')
            ->with('presence-rooms.456', '123.456', '1', $this->anything())
            ->willReturn('{"auth":"test-key:test-signature","channel_data":"{\"user_info\":{\"id\":1,\"name\":\"Test User\",\"email\":\"test@example.com\"}}"}');

        $mockUser = $this->createMock(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
                ['name', 'Test User'],
                ['email', 'test@example.com'],
            ]);

        $mockRoom = $this->createMock(EntityInterface::class);
        $mockRoom->method('get')
            ->willReturnMap([
                ['id', 456],
                ['user_id', 1],
            ]);

        $mockTable = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $mockTable->method('get')
            ->with('456')
            ->willReturn($mockRoom);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['retrieveUserFromCakeRequest', 'getTableLocator'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $mockLocator = $this->createMock(LocatorInterface::class);
        $mockLocator->method('get')
            ->with('Rooms')
            ->willReturn($mockTable);

        $broadcaster->method('getTableLocator')
            ->willReturn($mockLocator);

        $broadcaster->registerChannel('presence-rooms.{room}', TestPresenceChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'presence-rooms.456',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayHasKey('channel_data', $result);
    }

    /**
     * Test auth with class that doesn't implement ChannelInterface throws exception
     *
     * @return void
     */
    public function testAuthWithInvalidChannelClassThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Channel class TestApp\Broadcasting\InvalidChannel must implement ChannelInterface');

        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-app-id',
            'secret' => 'test-secret',
        ];

        $mockUser = $this->createMock(EntityInterface::class);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['retrieveUserFromCakeRequest'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $broadcaster->registerChannel('invalid.{id}', InvalidChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'invalid.123',
            'socket_id' => '123.456',
        ]);

        $broadcaster->auth($request);
    }

    /**
     * Test auth with non-existent channel class throws exception
     *
     * @return void
     */
    public function testAuthWithNonExistentChannelClassThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Class NonExistentChannel not found');

        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $mockUser = $this->createMock(EntityInterface::class);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['retrieveUserFromCakeRequest'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $broadcaster->registerChannel('nonexistent.{id}', 'NonExistentChannel');

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'nonexistent.123',
            'socket_id' => '123.456',
        ]);

        $broadcaster->auth($request);
    }

    /**
     * Test auth with channel class that returns false throws InvalidChannelException
     *
     * @return void
     */
    public function testAuthWithChannelClassUnauthorizedThrowsException(): void
    {
        $this->expectException(InvalidChannelException::class);
        $this->expectExceptionMessage('Unauthorized access to channel [private-orders.123].');

        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $mockUser = $this->createMock(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
            ]);

        $mockOrder = $this->createMock(EntityInterface::class);
        $mockOrder->method('get')
            ->willReturnMap([
                ['id', 123],
                ['user_id', 999],
            ]);

        $mockTable = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $mockTable->method('get')
            ->with('123')
            ->willReturn($mockOrder);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['retrieveUserFromCakeRequest', 'getTableLocator'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $mockLocator = $this->createMock(LocatorInterface::class);
        $mockLocator->method('get')
            ->with('Orders')
            ->willReturn($mockTable);

        $broadcaster->method('getTableLocator')
            ->willReturn($mockLocator);

        $broadcaster->registerChannel('private-orders.{order}', UnauthorizedChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-orders.123',
            'socket_id' => '123.456',
        ]);

        $broadcaster->auth($request);
    }

    /**
     * Test auth with channel class where user doesn't own resource
     *
     * @return void
     */
    public function testAuthWithChannelClassWrongUserThrowsException(): void
    {
        $this->expectException(InvalidChannelException::class);
        $this->expectExceptionMessage('Unauthorized access to channel [orders.123].');

        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $mockUser = $this->createMock(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
            ]);

        $mockOrder = $this->createMock(EntityInterface::class);
        $mockOrder->method('get')
            ->willReturnMap([
                ['id', 123],
                ['user_id', 999],
            ]);

        $mockTable = $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $mockTable->method('get')
            ->with('123')
            ->willReturn($mockOrder);

        $broadcaster = $this->getMockBuilder(PusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['retrieveUserFromCakeRequest', 'getTableLocator'])
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $this->mockPusher);

        $broadcaster->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $mockLocator = $this->createMock(LocatorInterface::class);
        $mockLocator->method('get')
            ->with('Orders')
            ->willReturn($mockTable);

        $broadcaster->method('getTableLocator')
            ->willReturn($mockLocator);

        $broadcaster->registerChannel('orders.{order}', TestOrderChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'orders.123',
            'socket_id' => '123.456',
        ]);

        $broadcaster->auth($request);
    }
}
