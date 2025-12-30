<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Test\TestCase\Controller\Trait\ControllerAuthTrait;
use ReflectionClass;

/**
 * BroadcastingAuthController Test Case
 *
 * Comprehensive tests for the broadcasting authentication controller
 * using CakePHP's IntegrationTestTrait for proper HTTP testing.
 */
class BroadcastingAuthControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use ControllerAuthTrait;

    /**
     * Get response body as array, with null safety
     *
     * @return array<string, mixed>
     */
    private function getResponseBody(): array
    {
        $this->assertNotNull($this->_response);
        $body = $this->_response->getBody();

        $decoded = json_decode((string)$body, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum/Broadcasting.Users',
    ];

    /**
     * Clear all Broadcasting configurations
     *
     * @return void
     */
    protected function clearBroadcastingConfigurations(): void
    {
        foreach (Broadcasting::configured() as $configName) {
            Broadcasting::drop((string)$configName);
        }
        Broadcasting::getRegistry()->reset();

        $reflection = new ReflectionClass(Broadcasting::class);
        $property = $reflection->getProperty('_channelsLoaded');
        $property->setValue(null, false);
    }

    /**
     * Set up test case
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->clearBroadcastingConfigurations();

        Broadcasting::setConfig('default', [
            'className' => 'Crustum/Broadcasting.Pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app-id',
            'options' => [
                'cluster' => 'mt1',
                'useTLS' => false,
                'host' => 'localhost',
                'port' => 6001,
                'scheme' => 'http',
            ],
        ]);

        Broadcasting::routes();
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->clearBroadcastingConfigurations();
        parent::tearDown();
    }

    /**
     * Test /broadcasting/auth endpoint with valid private channel request
     *
     * @return void
     */
    public function testAuthEndpointValidPrivateChannel(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/auth', [
            'socket_id' => '123.123',
            'channel_name' => 'private-test-channel',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('auth', $response);
        $this->assertStringStartsWith('test-key:', $response['auth']);
        $this->assertArrayNotHasKey('channel_data', $response);
    }

    /**
     * Test /broadcasting/auth endpoint with valid presence channel request
     *
     * @return void
     */
    public function testAuthEndpointValidPresenceChannel(): void
    {
        $user = $this->loginAs(123);

        $this->post('/broadcasting/auth', [
            'socket_id' => '123.123',
            'channel_name' => 'presence-test-channel',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();

        $this->assertArrayHasKey('auth', $response);
        $this->assertArrayHasKey('channel_data', $response);
        $this->assertStringStartsWith('test-key:', $response['auth']);

        $channelData = json_decode($response['channel_data'], true);
        $this->assertEquals($user->get('id'), $channelData['user_id']);
        $this->assertEquals($user->get('full_name'), $channelData['user_info']['name']);
    }

    /**
     * Test /broadcasting/auth endpoint with missing socket_id
     *
     * @return void
     */
    public function testAuthEndpointMissingSocketId(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-test-channel',
        ]);

        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('socket_id', $response['error']);
    }

    /**
     * Test /broadcasting/auth endpoint with missing channel_name
     *
     * @return void
     */
    public function testAuthEndpointMissingChannelName(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/auth', [
            'socket_id' => '123.123',
        ]);

        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('channel_name', $response['error']);
    }

    /**
     * Test /broadcasting/auth endpoint with missing both parameters
     *
     * @return void
     */
    public function testAuthEndpointMissingBothParameters(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/auth', []);

        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('socket_id', $response['error']);
        $this->assertStringContainsString('channel_name', $response['error']);
    }

    /**
     * Test /broadcasting/auth endpoint with unauthenticated user
     *
     * @return void
     */
    public function testAuthEndpointUnauthenticatedUser(): void
    {
        $this->post('/broadcasting/auth', [
            'socket_id' => '123.123',
            'channel_name' => 'private-test-channel',
        ]);

        $this->assertResponseCode(403);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Unauthorized access to channel [private-test-channel].', $response['error']);
    }

    /**
     * Test /broadcasting/auth endpoint with unauthorized channel
     *
     * @return void
     */
    public function testAuthEndpointUnauthorizedChannel(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/auth', [
            'socket_id' => '123.123',
            'channel_name' => 'private-restricted-channel',
        ]);

        $this->assertResponseCode(403);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Unauthorized access to channel [private-restricted-channel].', $response['error']);
    }

    /**
     * Test /broadcasting/auth endpoint with channel authorization exception
     *
     * @return void
     */
    public function testAuthEndpointChannelAuthorizationException(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/auth', [
            'socket_id' => '123.123',
            'channel_name' => 'private-error-channel',
        ]);

        $this->assertResponseCode(500);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Authentication failed', $response['error']);
    }

    /**
     * Test /broadcasting/user-auth endpoint with valid request
     *
     * @return void
     */
    public function testUserAuthEndpointValidRequest(): void
    {
        $user = $this->loginAs(123);

        $this->post('/broadcasting/user-auth', [
            'socket_id' => '123.123',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('auth', $response);
        $this->assertStringStartsWith('test-key:', $response['auth']);

        $expectedUserData = [
            'id' => (string)$user->get('id'),
            'user_info' => [
                'id' => $user->get('id'),
                'name' => $user->get('full_name'),
            ],
        ];

        $this->assertEquals($expectedUserData, json_decode($response['user_data'], true));
    }

    /**
     * Test /broadcasting/user-auth endpoint with missing socket_id
     *
     * @return void
     */
    public function testUserAuthEndpointMissingSocketId(): void
    {
        $this->loginAs(123);

        $this->post('/broadcasting/user-auth', []);

        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        $response = $this->getResponseBody();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('socket_id', $response['error']);
    }

    /**
     * Test /broadcasting/user-auth endpoint with unauthenticated user
     *
     * @return void
     */
    public function testUserAuthEndpointUnauthenticatedUser(): void
    {
        $this->post('/broadcasting/user-auth', [
            'socket_id' => '123.123',
        ]);

        $this->assertResponseCode(403);
    }

    /**
     * Test only POST method is allowed for auth endpoint
     *
     * @return void
     */
    public function testAuthEndpointOnlyAllowsPost(): void
    {
        $this->get('/broadcasting/auth');
        $this->assertResponseCode(405);
    }

    /**
     * Test only POST method is allowed for user-auth endpoint
     *
     * @return void
     */
    public function testUserAuthEndpointOnlyAllowsPost(): void
    {
        $this->get('/broadcasting/user-auth');
        $this->assertResponseCode(405);
    }
}
