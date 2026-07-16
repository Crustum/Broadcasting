<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Exception;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\QueueException;
use Exception;

/**
 * QueueException Test
 *
 * Tests for the QueueException class.
 */
class QueueExceptionTest extends TestCase
{
    /**
     * Test exception extends BroadcastingException
     *
     * @return void
     */
    public function testExceptionExtendsBroadcastingException(): void
    {
        $exception = QueueException::adapterNotFound('test');

        $this->assertInstanceOf(BroadcastingException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test adapter not found factory method
     *
     * @return void
     */
    public function testAdapterNotFound(): void
    {
        $exception = QueueException::adapterNotFound('redis');

        $this->assertEquals(
            'Queue adapter [redis] not found or not configured.',
            $exception->getMessage(),
        );
        $this->assertEquals(QueueException::DEFAULT_CODE, $exception->getCode());
    }

    /**
     * Test push failed factory method
     *
     * @return void
     */
    public function testPushFailed(): void
    {
        $exception = QueueException::pushFailed('BroadcastJob', 'Connection timeout');

        $this->assertEquals(
            'Failed to push job [BroadcastJob] to queue: Connection timeout',
            $exception->getMessage(),
        );
        $this->assertEquals(QueueException::DEFAULT_CODE, $exception->getCode());
    }

    /**
     * Test adapter unavailable factory method
     *
     * @return void
     */
    public function testAdapterUnavailable(): void
    {
        $exception = QueueException::adapterUnavailable('database');

        $this->assertEquals(
            'Queue adapter [database] is currently unavailable.',
            $exception->getMessage(),
        );
        $this->assertEquals(503, $exception->getCode());
    }

    /**
     * Test default code constant
     *
     * @return void
     */
    public function testDefaultCodeConstant(): void
    {
        $this->assertEquals(500, QueueException::DEFAULT_CODE);
    }
}
