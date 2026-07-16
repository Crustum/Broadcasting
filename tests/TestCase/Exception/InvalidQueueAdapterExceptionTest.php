<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Exception;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidQueueAdapterException;
use Exception;

/**
 * InvalidQueueAdapterException Test
 *
 * Tests for the InvalidQueueAdapterException class.
 */
class InvalidQueueAdapterExceptionTest extends TestCase
{
    /**
     * Test exception extends BroadcastingException
     *
     * @return void
     */
    public function testExceptionExtendsBroadcastingException(): void
    {
        $exception = InvalidQueueAdapterException::unknownQueueAdapter('test');

        $this->assertInstanceOf(BroadcastingException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test unknown queue adapter factory method
     *
     * @return void
     */
    public function testUnknownQueueAdapter(): void
    {
        $exception = InvalidQueueAdapterException::unknownQueueAdapter('invalid');

        $this->assertEquals(
            'Queue adapter [invalid] is not supported or not configured.',
            $exception->getMessage(),
        );
        $this->assertEquals(InvalidQueueAdapterException::DEFAULT_CODE, $exception->getCode());
    }

    /**
     * Test default code constant
     *
     * @return void
     */
    public function testDefaultCodeConstant(): void
    {
        $this->assertEquals(400, InvalidQueueAdapterException::DEFAULT_CODE);
    }
}
