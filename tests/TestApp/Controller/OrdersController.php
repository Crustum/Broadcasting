<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Crustum\Broadcasting\Broadcasting;

/**
 * Orders Controller for Testing
 *
 * Test controller that broadcasts events for integration testing
 */
class OrdersController extends Controller
{
    /**
     * Create order action
     *
     * @return \Cake\Http\Response
     */
    public function create(): Response
    {
        $this->getRequest()->allowMethod(['post']);

        $orderId = $this->getRequest()->getData('order_id', 123);
        $total = $this->getRequest()->getData('total', 99.99);

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data([
                'order_id' => $orderId,
                'total' => $total,
                'status' => 'paid',
            ])
            ->send();

        return $this->getResponse()
            ->withType('application/json')
            ->withStringBody((string)json_encode([
                'success' => true,
                'order_id' => $orderId,
            ]));
    }

    /**
     * Update order action
     *
     * @return \Cake\Http\Response
     */
    public function update(): Response
    {
        $this->getRequest()->allowMethod(['post']);

        $orderId = $this->getRequest()->getData('order_id', 123);

        Broadcasting::to(['orders', 'admin'])
            ->event('OrderUpdated')
            ->data(['order_id' => $orderId])
            ->send();

        return $this->getResponse()
            ->withType('application/json')
            ->withStringBody((string)json_encode(['success' => true]));
    }

    /**
     * Broadcast with connection
     *
     * @return \Cake\Http\Response
     */
    public function broadcastWithConnection(): Response
    {
        $this->getRequest()->allowMethod(['post']);

        $connection = $this->getRequest()->getData('connection', 'default');

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->connection($connection)
            ->send();

        return $this->getResponse()
            ->withType('application/json')
            ->withStringBody((string)json_encode(['success' => true]));
    }
}
