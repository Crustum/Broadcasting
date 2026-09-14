<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Mercure;

use Symfony\Component\Mercure\Jwt\TokenProviderInterface;

/**
 * Caching Token Provider
 *
 * Caches the publish JWT until shortly before its `exp` claim.
 */
class CachingTokenProvider implements TokenProviderInterface
{
    /**
     * Cached JWT, re-minted once past the refresh timestamp.
     *
     * @var string|null
     */
    protected ?string $jwt = null;

    /**
     * Unix timestamp after which the cached token must be re-minted.
     *
     * @var int
     */
    protected int $refreshAfter = 0;

    /**
     * Create a new caching token provider.
     *
     * @param \Symfony\Component\Mercure\Jwt\TokenProviderInterface $provider Inner provider minting fresh tokens
     * @param int $clockSkew Seconds subtracted from `exp` to allow clock skew
     */
    public function __construct(
        protected TokenProviderInterface $provider,
        protected int $clockSkew = 30,
    ) {
    }

    /**
     * Get the JWT, minting a fresh one when the cached token nears expiry.
     *
     * @return string Fresh or cached JWT
     */
    public function getJwt(): string
    {
        if ($this->jwt !== null && time() < $this->refreshAfter) {
            return $this->jwt;
        }

        $this->jwt = $this->provider->getJwt();
        $this->refreshAfter = ($this->expiresAt($this->jwt) ?? PHP_INT_MAX) - $this->clockSkew;

        return $this->jwt;
    }

    /**
     * Extract the `exp` claim from a JWT, if any.
     *
     * @param string $jwt Token to inspect
     * @return int|null Expiration timestamp, null when the token carries no `exp` claim
     */
    protected function expiresAt(string $jwt): ?int
    {
        $payload = explode('.', $jwt)[1] ?? '';
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')) ?: 'null', true);

        return isset($claims['exp']) ? (int)$claims['exp'] : null;
    }
}
