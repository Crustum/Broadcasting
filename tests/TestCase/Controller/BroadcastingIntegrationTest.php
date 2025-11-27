<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;

/**
 * Broadcasting Integration Test
 *
 * Tests that BroadcastingTrait works correctly with IntegrationTestTrait
 * for controller/integration testing scenarios where controllers broadcast events.
 */
class BroadcastingIntegrationTest extends TestCase
{
    use IntegrationTestTrait;
    use BroadcastingTrait;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum/Broadcasting.Users',
        'plugin.Crustum/Broadcasting.Orders',
    ];

    /**
     * Test controller action broadcasts are captured
     *
     * @return void
     */
    public function testControllerActionBroadcastCaptured(): void
    {
        $this->post('/orders/create', [
            'order_id' => 123,
            'total' => 99.99,
        ]);

        $this->assertResponseOk();

        $this->assertBroadcastSent('OrderCreated');
        $this->assertBroadcastSentToChannel('orders', 'OrderCreated');
        $this->assertBroadcastPayloadContains('OrderCreated', 'order_id', 123);
        $this->assertBroadcastPayloadContains('OrderCreated', 'total', 99.99);
    }

    /**
     * Test controller broadcasts to multiple channels
     *
     * @return void
     */
    public function testControllerBroadcastsToMultipleChannels(): void
    {
        $this->post('/orders/update', ['order_id' => 456]);

        $this->assertResponseOk();

        $this->assertBroadcastSent('OrderUpdated');
        $this->assertBroadcastSentToChannels(['orders', 'admin'], 'OrderUpdated');
    }

    /**
     * Test broadcasts isolated between controller calls
     *
     * @return void
     */
    public function testBroadcastsIsolatedBetweenRequests(): void
    {
        $this->post('/orders/create', ['order_id' => 100]);
        $this->assertBroadcastCount(1);

        $this->post('/orders/create', ['order_id' => 200]);
        $this->assertBroadcastCount(2);

        $broadcasts = $this->getBroadcastsByEvent('OrderCreated');
        $this->assertCount(2, $broadcasts);
        $this->assertEquals(100, $broadcasts[0]['payload']['order_id']);
        $this->assertEquals(200, $broadcasts[1]['payload']['order_id']);
    }

    /**
     * Test controller broadcast with custom connection
     *
     * @return void
     */
    public function testControllerBroadcastWithConnection(): void
    {
        $this->post('/orders/broadcast-with-connection', [
            'connection' => 'default',
        ]);

        $this->assertResponseOk();
        $this->assertBroadcastSentViaConnection('default', 'OrderCreated');
    }
}
