<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Mercure;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Mercure\CachingTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;

/**
 * CachingTokenProvider Test Case
 */
class CachingTokenProviderTest extends TestCase
{
    /**
     * @return void
     */
    protected function skipIfMercureMissing(): void
    {
        if (!interface_exists(TokenProviderInterface::class)) {
            $this->markTestSkipped('symfony/mercure is not installed.');
        }
    }

    /**
     * Mints once while the token is fresh.
     *
     * @return void
     */
    public function testItMintsOnceWhileTheTokenIsFresh(): void
    {
        $this->skipIfMercureMissing();

        $provider = new CachingTokenProvider(
            new CountingTokenProvider($this->tokenWithClaims(['exp' => time() + 3600])),
        );

        $first = $provider->getJwt();

        $this->assertSame($first, $provider->getJwt());
        $this->assertSame($first, $provider->getJwt());
    }

    /**
     * Re-mints once the token nears its expiry.
     *
     * @return void
     */
    public function testItReMintsOnceTheTokenNearsItsExpiry(): void
    {
        $this->skipIfMercureMissing();

        $inner = new CountingTokenProvider($this->tokenWithClaims(['exp' => time() + 10]));
        $provider = new CachingTokenProvider($inner, 30);

        $provider->getJwt();
        $provider->getJwt();

        $this->assertSame(2, $inner->calls);
    }

    /**
     * A token without `exp` is cached forever.
     *
     * @return void
     */
    public function testATokenWithoutExpIsCachedForever(): void
    {
        $this->skipIfMercureMissing();

        $inner = new CountingTokenProvider($this->tokenWithClaims(['sub' => 'app']));
        $provider = new CachingTokenProvider($inner);

        $provider->getJwt();
        $provider->getJwt();

        $this->assertSame(1, $inner->calls);
    }

    /**
     * Build an unsigned JWT with the given claims.
     *
     * @param array<string, mixed> $claims Claims
     * @return string
     */
    protected function tokenWithClaims(array $claims): string
    {
        $encode = fn($data): string => rtrim(strtr(base64_encode((string)json_encode($data)), '+/', '-_'), '=');

        return $encode(['alg' => 'none']) . '.' . $encode($claims) . '.';
    }
}
