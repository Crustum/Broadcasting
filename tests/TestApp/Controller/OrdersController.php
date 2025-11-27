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
        $this->request->allowMethod(['post']);

        $orderId = $this->request->getData('order_id', 123);
        $total = $this->request->getData('total', 99.99);

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->data([
                'order_id' => $orderId,
                'total' => $total,
                'status' => 'paid',
            ])
            ->send();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
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
        $this->request->allowMethod(['post']);

        $orderId = $this->request->getData('order_id', 123);

        Broadcasting::to(['orders', 'admin'])
            ->event('OrderUpdated')
            ->data(['order_id' => $orderId])
            ->send();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true]));
    }

    /**
     * Broadcast with connection
     *
     * @return \Cake\Http\Response
     */
    public function broadcastWithConnection(): Response
    {
        $this->request->allowMethod(['post']);

        $connection = $this->request->getData('connection', 'default');

        Broadcasting::to('orders')
            ->event('OrderCreated')
            ->connection($connection)
            ->send();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true]));
    }
}
