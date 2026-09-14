<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Mercure;

use Symfony\Component\Mercure\Jwt\TokenProviderInterface;

/**
 * Counting Token Provider
 *
 * Test double counting `getJwt()` calls to prove caching behavior.
 */
class CountingTokenProvider implements TokenProviderInterface
{
    /**
     * Number of `getJwt()` calls.
     *
     * @var int
     */
    public int $calls = 0;

    /**
     * @param string $jwt Token to return
     */
    public function __construct(protected string $jwt)
    {
    }

    /**
     * @return string
     */
    public function getJwt(): string
    {
        $this->calls++;

        return $this->jwt;
    }
}
