<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Cake\Datasource\EntityInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\PusherBroadcaster;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidChannelException;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
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
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum\Broadcasting.Orders',
        'plugin.Crustum\Broadcasting.Rooms',
    ];

    /**
     * Create Pusher stub.
     *
     * @return \Pusher\Pusher
     */
    protected function createPusherStub(): Pusher
    {
        return $this->createStub(Pusher::class);
    }

    /**
     * Create Pusher mock for method configuration.
     *
     * @return \Pusher\Pusher&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createPusherMock(): Pusher
    {
        return $this->createMock(Pusher::class);
    }

    /**
     * Create PusherBroadcaster with injected Pusher stub.
     *
     * @param array<string, mixed> $config Configuration array
     * @param \Pusher\Pusher|null $pusher Pusher client stub
     * @return \Crustum\Broadcasting\Broadcaster\PusherBroadcaster
     */
    protected function createPusherBroadcasterWithStub(array $config, ?Pusher $pusher = null): PusherBroadcaster
    {
        $pusher ??= $this->createPusherStub();
        $broadcaster = new TestablePusherBroadcaster($config);

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $pusher);

        return $broadcaster;
    }

    /**
     * Create PusherBroadcaster with partial mock for method stubbing.
     *
     * @param array<string, mixed> $config Configuration array
     * @param list<non-empty-string> $methodsToStub Methods to stub
     * @param \Pusher\Pusher|null $pusher Pusher client stub
     * @return \Crustum\Broadcasting\Broadcaster\PusherBroadcaster&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createPusherBroadcasterWithMock(array $config, array $methodsToStub, ?Pusher $pusher = null): MockObject
    {
        $pusher ??= $this->createPusherStub();
        /** @var list<non-empty-string> $methodsToStub */
        $broadcaster = $this->getMockBuilder(TestablePusherBroadcaster::class)
            ->setConstructorArgs([$config])
            ->onlyMethods($methodsToStub)
            ->getMock();

        $reflection = new ReflectionClass($broadcaster);
        $pusherClientProperty = $reflection->getProperty('pusherClient');
        $pusherClientProperty->setValue($broadcaster, $pusher);

        return $broadcaster;
    }

    /**
     * Test constructor with valid configuration
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'options' => [
                'cluster' => 'test-cluster',
                'useTLS' => true,
            ],
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $this->assertInstanceOf(PusherBroadcaster::class, $broadcaster);
        $this->assertEquals('test-app-id', $broadcaster->getConfig()['app_id']);
        $this->assertEquals('test-key', $broadcaster->getConfig()['key']);
        $this->assertEquals('test-secret', $broadcaster->getConfig()['secret']);
        $this->assertEquals('test-cluster', $broadcaster->getConfig()['options']['cluster']);
    }

    /**
     * Test constructor with missing required configuration
     *
     * @return void
     */
    public function testConstructorWithMissingConfig(): void
    {
        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage("Pusher configuration 'key' is required.");

        new PusherBroadcaster(['app_id' => 'test']);
    }

    /**
     * Test getName method
     *
     * @return void
     */
    public function testGetName(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $this->assertEquals('pusher', $broadcaster->getName());
    }

    /**
     * Test supportsChannelType method
     *
     * @return void
     */
    public function testSupportsChannelType(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $this->assertTrue($broadcaster->supportsChannelType('public'));
        $this->assertTrue($broadcaster->supportsChannelType('private'));
        $this->assertTrue($broadcaster->supportsChannelType('presence'));
        $this->assertFalse($broadcaster->supportsChannelType('invalid'));
        $this->assertFalse($broadcaster->supportsChannelType(''));
    }

    /**
     * Test auth method with missing channel name
     *
     * @return void
     */
    public function testAuthWithMissingChannelName(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody(['socket_id' => 'test-socket-id']);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('Missing required parameters: channel_name');

        $broadcaster->auth($request);
    }

    /**
     * Test auth method with missing socket ID
     *
     * @return void
     */
    public function testAuthWithMissingSocketId(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody(['channel_name' => 'test-channel']);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('Missing required parameters: socket_id');

        $broadcaster->auth($request);
    }

    /**
     * Test auth method with invalid channel
     *
     * @return void
     */
    public function testAuthWithInvalidChannel(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'invalid-channel',
            'socket_id' => 'test-socket-id',
        ]);

        $this->expectException(InvalidChannelException::class);
        $this->expectExceptionMessage('Unauthorized access to channel [invalid-channel].');

        $broadcaster->auth($request);
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

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        // Register the channel first
        $broadcaster->registerChannel('private-test', fn($user): true => true);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertIsArray($result);
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

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizePresenceChannel')
            ->with('presence-test', '123.456', '1', $this->anything())
            ->willReturn('{"auth":"test-key:test-signature","channel_data":"{\"user_info\":{\"id\":1,\"name\":\"Test User\"}}"}');

        $mockEntity = $this->createStub(EntityInterface::class);
        $mockEntity->method('get')
            ->willReturnMap([
                ['id', 1],
                ['full_name', 'Test User'],
                ['username', 'testuser'],
            ]);

        $broadcaster = $this->createPusherBroadcasterWithMock($config, ['resolveUserFromRequest'], $pusher);
        $broadcaster->expects($this->atLeastOnce())
            ->method('resolveUserFromRequest')
            ->willReturn($mockEntity);

        $broadcaster->setChannelCallbacks(['presence-test' => fn($user): true => true]);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'presence-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertIsArray($result);
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
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $broadcaster->registerChannel('presence-test', fn($user): true => true);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'presence-test',
            'socket_id' => 'test-socket-id',
        ]);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('User not authenticated for presence channel');

        $broadcaster->auth($request);
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

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertIsArray($result);
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
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('trigger')
            ->willReturn((object)['status' => 200]);

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $channels = ['test-channel'];
        $event = 'test-event';
        $payload = ['data' => 'test-data'];

        $broadcaster->broadcast($channels, $event, $payload);
    }

    /**
     * Test broadcast method with empty channels
     *
     * @return void
     */
    public function testBroadcastWithEmptyChannels(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->never())
            ->method('trigger');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $channels = [];
        $event = 'test-event';
        $payload = ['data' => 'test-data'];

        $broadcaster->broadcast($channels, $event, $payload);
    }

    /**
     * Test broadcast method with multiple channels
     *
     * @return void
     */
    public function testBroadcastWithMultipleChannels(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('trigger')
            ->willReturn((object)['status' => 200]);

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $channels = ['channel1', 'channel2', 'channel3'];
        $event = 'multi-channel-event';
        $payload = ['message' => 'Hello World', 'timestamp' => time()];

        $broadcaster->broadcast($channels, $event, $payload);
    }

    /**
     * Test broadcast method with complex payload
     *
     * @return void
     */
    public function testBroadcastWithComplexPayload(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('trigger')
            ->willReturn((object)['status' => 200]);

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $channels = ['complex-channel'];
        $event = 'complex-event';
        $payload = [
            'user' => ['id' => 123, 'name' => 'Test User'],
            'data' => ['nested' => ['value' => 'test']],
            'metadata' => ['tags' => ['important', 'urgent']],
        ];

        $broadcaster->broadcast($channels, $event, $payload);
    }

    /**
     * Test getClient method
     *
     * @return void
     */
    public function testGetClient(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();
        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $client = $broadcaster->getClient();
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

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('orders.123', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $mockUser = $this->createStub(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
                ['name', 'Test User'],
            ]);

        $broadcaster = $this->createPusherBroadcasterWithMock(
            $config,
            ['retrieveUserFromCakeRequest', 'getTableLocator'],
            $pusher,
        );

        $broadcaster->expects($this->once())
            ->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $broadcaster->expects($this->once())
            ->method('getTableLocator')
            ->willReturn($this->getTableLocator());

        $broadcaster->registerChannel('orders.{order}', TestOrderChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'orders.123',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertIsArray($result);
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

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizePresenceChannel')
            ->with('presence-rooms.456', '123.456', '1', $this->anything())
            ->willReturn('{"auth":"test-key:test-signature","channel_data":"{\"user_info\":{\"id\":1,\"name\":\"Test User\",\"email\":\"test@example.com\"}}"}');

        $mockUser = $this->createStub(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnCallback(fn($field): int|string|null => match ($field) {
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                default => null,
            });

        $mockRoom = $this->createStub(EntityInterface::class);
        $mockRoom->method('get')
            ->willReturnMap([
                ['id', 456],
                ['user_id', 1],
            ]);

        $broadcaster = $this->createPusherBroadcasterWithMock(
            $config,
            ['retrieveUserFromCakeRequest', 'resolveEntityFromKey'],
            $pusher,
        );

        $broadcaster->expects($this->any())
            ->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $broadcaster->expects($this->once())
            ->method('resolveEntityFromKey')
            ->with('room', '456')
            ->willReturn($mockRoom);

        $broadcaster->registerChannel('presence-rooms.{room}', TestPresenceChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'presence-rooms.456',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->auth($request);

        $this->assertIsArray($result);
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

        $pusher = $this->createPusherStub();
        $mockUser = $this->createStub(EntityInterface::class);

        $broadcaster = $this->createPusherBroadcasterWithMock(
            $config,
            ['retrieveUserFromCakeRequest'],
            $pusher,
        );

        $broadcaster->expects($this->atLeastOnce())
            ->method('retrieveUserFromCakeRequest')
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

        $pusher = $this->createPusherStub();
        $mockUser = $this->createStub(EntityInterface::class);

        $broadcaster = $this->createPusherBroadcasterWithMock(
            $config,
            ['retrieveUserFromCakeRequest'],
            $pusher,
        );

        $broadcaster->expects($this->atLeastOnce())
            ->method('retrieveUserFromCakeRequest')
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

        $pusher = $this->createPusherStub();
        $mockUser = $this->createStub(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
            ]);

        $broadcaster = $this->createPusherBroadcasterWithMock(
            $config,
            ['retrieveUserFromCakeRequest', 'getTableLocator'],
            $pusher,
        );

        $broadcaster->expects($this->once())
            ->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $broadcaster->expects($this->once())
            ->method('getTableLocator')
            ->willReturn($this->getTableLocator());

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
        $this->expectExceptionMessage('Unauthorized access to channel [orders.124].');

        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherStub();

        $mockUser = $this->createStub(EntityInterface::class);
        $mockUser->method('get')
            ->willReturnMap([
                ['id', 1],
            ]);

        $broadcaster = $this->createPusherBroadcasterWithMock(
            $config,
            ['retrieveUserFromCakeRequest', 'getTableLocator'],
            $pusher,
        );

        $broadcaster->expects($this->once())
            ->method('retrieveUserFromCakeRequest')
            ->willReturn($mockUser);

        $broadcaster->expects($this->once())
            ->method('getTableLocator')
            ->willReturn($this->getTableLocator());

        $broadcaster->registerChannel('orders.{order}', TestOrderChannel::class);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'orders.124',
            'socket_id' => '123.456',
        ]);

        $broadcaster->auth($request);
    }

    /**
     * Test JSONP callback is ignored when jsonp is disabled (default)
     *
     * @return void
     */
    public function testValidAuthenticationResponseIgnoresCallbackWhenJsonpDisabled(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'jsonp' => false,
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $this->assertFalse($broadcaster->allowsJsonp());

        $request = new ServerRequest();
        $request = $request
            ->withQueryParams(['callback' => 'pusherCallback'])
            ->withParsedBody([
                'channel_name' => 'private-test',
                'socket_id' => '123.456',
            ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('test-key:test-signature', $result['auth']);
    }

    /**
     * Test JSONP is disabled by default when config omits jsonp
     *
     * @return void
     */
    public function testJsonpDisabledByDefaultWhenConfigOmitsFlag(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $this->assertFalse($broadcaster->allowsJsonp());

        $request = new ServerRequest();
        $request = $request
            ->withQueryParams(['callback' => 'pusherCallback'])
            ->withParsedBody([
                'channel_name' => 'private-test',
                'socket_id' => '123.456',
            ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertIsArray($result);
    }

    /**
     * Test JSONP response is returned when jsonp is enabled and callback is present
     *
     * @return void
     */
    public function testValidAuthenticationResponseReturnsJsonpWhenEnabled(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'jsonp' => true,
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $this->assertTrue($broadcaster->allowsJsonp());

        $request = new ServerRequest();
        $request = $request
            ->withQueryParams(['callback' => 'pusherCallback'])
            ->withParsedBody([
                'channel_name' => 'private-test',
                'socket_id' => '123.456',
            ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('application/javascript', $result->getType());
        $this->assertStringContainsString('pusherCallback(', (string)$result->getBody());
        $this->assertStringContainsString('test-key:test-signature', (string)$result->getBody());
        $this->assertStringEndsWith(');', (string)$result->getBody());
    }

    /**
     * Test JSONP enabled without callback still returns a plain array
     *
     * @return void
     */
    public function testValidAuthenticationResponseReturnsArrayWhenJsonpEnabledWithoutCallback(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'jsonp' => true,
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-test',
            'socket_id' => '123.456',
        ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertIsArray($result);
        $this->assertEquals('test-key:test-signature', $result['auth']);
    }

    /**
     * Test JSONP callback can be read from the request body
     *
     * @return void
     */
    public function testValidAuthenticationResponseAcceptsJsonpCallbackFromBody(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'jsonp' => true,
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);

        $request = new ServerRequest();
        $request = $request->withParsedBody([
            'channel_name' => 'private-test',
            'socket_id' => '123.456',
            'callback' => 'bodyCallback',
        ]);

        $result = $broadcaster->validAuthenticationResponse($request, []);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('bodyCallback(', (string)$result->getBody());
    }

    /**
     * Test auth() returns a JSONP Response when jsonp is enabled
     *
     * @return void
     */
    public function testAuthReturnsJsonpResponseWhenEnabled(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'jsonp' => true,
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizeChannel')
            ->with('private-test', '123.456')
            ->willReturn('{"auth":"test-key:test-signature"}');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $broadcaster->registerChannel('private-test', fn($user): true => true);

        $request = new ServerRequest();
        $request = $request
            ->withQueryParams(['callback' => 'authCallback'])
            ->withParsedBody([
                'channel_name' => 'private-test',
                'socket_id' => '123.456',
            ]);

        $result = $broadcaster->auth($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('authCallback(', (string)$result->getBody());
        $this->assertStringContainsString('test-key:test-signature', (string)$result->getBody());
    }

    /**
     * Test presence channel auth returns JSONP when enabled
     *
     * @return void
     */
    public function testPresenceValidAuthenticationResponseReturnsJsonpWhenEnabled(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'jsonp' => true,
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('authorizePresenceChannel')
            ->with('presence-test', '123.456', '1', $this->anything())
            ->willReturn('{"auth":"test-key:presence-signature","channel_data":"{}"}');

        $mockEntity = $this->createStub(EntityInterface::class);
        $mockEntity->method('get')
            ->willReturnMap([
                ['id', 1],
                ['full_name', 'Test User'],
                ['username', 'testuser'],
            ]);

        $broadcaster = $this->createPusherBroadcasterWithMock($config, ['resolveUserFromRequest'], $pusher);
        $broadcaster->expects($this->atLeastOnce())
            ->method('resolveUserFromRequest')
            ->willReturn($mockEntity);

        $request = new ServerRequest();
        $request = $request
            ->withQueryParams(['callback' => 'presenceCallback'])
            ->withParsedBody([
                'channel_name' => 'presence-test',
                'socket_id' => '123.456',
            ]);

        $result = $broadcaster->validAuthenticationResponse($request, ['id' => 1]);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('presenceCallback(', (string)$result->getBody());
        $this->assertStringContainsString('test-key:presence-signature', (string)$result->getBody());
    }

    /**
     * Test bulkBroadcast uses triggerBatch in chunks of 10 by default
     *
     * @return void
     */
    public function testBulkBroadcastUsesTriggerBatch(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $broadcasts = [];
        for ($i = 0; $i < 25; $i++) {
            $broadcasts[] = [
                'channel' => 'user.' . $i,
                'event' => 'Notify',
                'data' => ['i' => $i],
            ];
        }

        $pusher = $this->createPusherMock();
        $pusher->expects($this->exactly(3))
            ->method('triggerBatch')
            ->willReturn((object)['status' => 200]);
        $pusher->expects($this->never())
            ->method('trigger');

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $broadcaster->bulkBroadcast($broadcasts, 10);
    }

    /**
     * Test bulkBroadcast respects configured max_batch_size
     *
     * @return void
     */
    public function testBulkBroadcastRespectsMaxBatchSize(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'bulk' => [
                'max_batch_size' => 5,
            ],
        ];

        $broadcasts = [];
        for ($i = 0; $i < 12; $i++) {
            $broadcasts[] = [
                'channel' => 'user.' . $i,
                'event' => 'Notify',
                'data' => ['i' => $i],
            ];
        }

        $pusher = $this->createPusherMock();
        $pusher->expects($this->exactly(3))
            ->method('triggerBatch')
            ->willReturn((object)['status' => 200]);

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $broadcaster->bulkBroadcast($broadcasts, 10);
    }

    /**
     * Test bulkBroadcast includes socket_id in batch entries
     *
     * @return void
     */
    public function testBulkBroadcastPassesSocketId(): void
    {
        $config = [
            'app_id' => 'test-app-id',
            'key' => 'test-key',
            'secret' => 'test-secret',
        ];

        $pusher = $this->createPusherMock();
        $pusher->expects($this->once())
            ->method('triggerBatch')
            ->with($this->callback(fn(array $batch): bool => count($batch) === 1
                && $batch[0]['channel'] === 'user.1'
                && $batch[0]['name'] === 'Notify'
                && $batch[0]['data'] === ['ok' => true]
                && ($batch[0]['socket_id'] ?? null) === '123.456'))
            ->willReturn((object)['status' => 200]);

        $broadcaster = $this->createPusherBroadcasterWithStub($config, $pusher);
        $broadcaster->bulkBroadcast([
            [
                'channel' => 'user.1',
                'event' => 'Notify',
                'data' => ['ok' => true],
                'socket' => '123.456',
            ],
        ]);
    }
}
