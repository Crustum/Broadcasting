<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Exception;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidBroadcasterException;
use Exception;

/**
 * InvalidBroadcasterException Test
 *
 * Tests for the InvalidBroadcasterException class.
 */
class InvalidBroadcasterExceptionTest extends TestCase
{
    /**
     * Test exception extends BroadcastingException
     *
     * @return void
     */
    public function testExceptionExtendsBroadcastingException(): void
    {
        $exception = InvalidBroadcasterException::unknownBroadcaster('test');

        $this->assertInstanceOf(BroadcastingException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test unknown broadcaster factory method
     *
     * @return void
     */
    public function testUnknownBroadcaster(): void
    {
        $exception = InvalidBroadcasterException::unknownBroadcaster('invalid');

        $this->assertEquals(
            'Broadcaster [invalid] is not supported or not configured.',
            $exception->getMessage(),
        );
        $this->assertEquals(InvalidBroadcasterException::DEFAULT_CODE, $exception->getCode());
    }

    /**
     * Test invalid configuration factory method
     *
     * @return void
     */
    public function testInvalidConfiguration(): void
    {
        $exception = InvalidBroadcasterException::invalidConfiguration('pusher', 'Missing API key');

        $this->assertEquals(
            'Broadcaster [pusher] has invalid configuration: Missing API key',
            $exception->getMessage(),
        );
        $this->assertEquals(InvalidBroadcasterException::DEFAULT_CODE, $exception->getCode());
    }

    /**
     * Test invalid broadcaster factory method
     *
     * @return void
     */
    public function testInvalidBroadcaster(): void
    {
        $exception = InvalidBroadcasterException::invalidBroadcaster('InvalidClass');

        $this->assertEquals(
            'Class [InvalidClass] is not a valid broadcaster. It must implement BroadcasterInterface.',
            $exception->getMessage(),
        );
        $this->assertEquals(InvalidBroadcasterException::DEFAULT_CODE, $exception->getCode());
    }

    /**
     * Test default code constant
     *
     * @return void
     */
    public function testDefaultCodeConstant(): void
    {
        $this->assertEquals(400, InvalidBroadcasterException::DEFAULT_CODE);
    }
}
