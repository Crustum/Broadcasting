<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Mercure;

use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\MercureBroadcaster;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Mercure\MercureHubFactory;
use InvalidArgumentException;
use Jose\Component\Encryption\JWEBuilder;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;

/**
 * MercureHubFactory Test Case
 */
class MercureHubFactoryTest extends TestCase
{
    /**
     * @return void
     */
    protected function skipIfMercureMissing(): void
    {
        if (!interface_exists(HubInterface::class)) {
            $this->markTestSkipped('symfony/mercure is not installed.');
        }
    }

    /**
     * @return void
     */
    protected function skipIfJoseMissing(): void
    {
        if (!class_exists(JWEBuilder::class)) {
            $this->markTestSkipped('web-token/jwt-library is not installed.');
        }
    }

    /**
     * @return void
     */
    public function testMercureRequiresAUrl(): void
    {
        if (function_exists('mercure_publish')) {
            $this->markTestSkipped('FrankenPHP hub available.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"url"');

        MercureHubFactory::hub(['secret' => str_repeat('s', 32)]);
    }

    /**
     * @return void
     */
    public function testMercureRequiresASecret(): void
    {
        $this->skipIfMercureMissing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"secret"');

        MercureHubFactory::hub(['url' => 'https://hub.test/.well-known/mercure']);
    }

    /**
     * @return void
     */
    public function testMercureRejectsAShortHmacSecret(): void
    {
        $this->skipIfMercureMissing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 32 bytes');

        MercureHubFactory::hub($this->mercureConfig(['secret' => 'too-short']));
    }

    /**
     * @return void
     */
    public function testMercureRejectsANegativePublishExpiration(): void
    {
        $this->skipIfMercureMissing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('publish_expiration');

        MercureHubFactory::hub($this->mercureConfig(['publish_expiration' => -1]));
    }

    /**
     * @return void
     */
    public function testMercureRejectsAPublishExpirationTruncatingToZeroSeconds(): void
    {
        $this->skipIfMercureMissing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('publish_expiration');

        MercureHubFactory::hub($this->mercureConfig(['publish_expiration' => 0.01]));
    }

    /**
     * @return void
     */
    public function testMercureAcceptsASubMinutePublishExpiration(): void
    {
        $this->skipIfMercureMissing();

        /** @var \Symfony\Component\Mercure\Hub $hub */
        $hub = MercureHubFactory::hub($this->mercureConfig(['publish_expiration' => 0.5]));

        $this->assertNotEmpty($hub->getProvider()->getJwt());
    }

    /**
     * @return void
     */
    public function testMercureRejectsANonPositiveSubscribeExpiration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subscribe_expiration');

        MercureHubFactory::subscribeExpiration(['subscribe_expiration' => 0]);
    }

    /**
     * @return void
     */
    public function testMercureRejectsASubscribeExpirationTruncatingToZeroSeconds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subscribe_expiration');

        MercureHubFactory::subscribeExpiration(['subscribe_expiration' => 0.01]);
    }

    /**
     * @return void
     */
    public function testMercureRejectsAMalformedEncryptionKey(): void
    {
        $this->skipIfJoseMissing();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('encryption_key');

        MercureHubFactory::channelEncrypter(['encryption_key' => 'not-a-valid-key']);
    }

    /**
     * @return void
     */
    public function testMercureRejectsASecurePrefixedCookieOverAPlainHttpPublicUrl(): void
    {
        $this->skipIfMercureMissing();

        $hub = MercureHubFactory::hub($this->mercureConfig([
            'public_url' => 'http://localhost/.well-known/mercure',
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cookie_name');

        MercureHubFactory::assertCookiePath($hub);
    }

    /**
     * @return void
     */
    public function testMercureAcceptsAPlainHttpPublicUrlWithAnUnprefixedCookieName(): void
    {
        $this->skipIfMercureMissing();

        $hub = MercureHubFactory::hub($this->mercureConfig([
            'public_url' => 'http://localhost/.well-known/mercure',
            'cookie_name' => 'mercureAuthorization',
        ]));

        MercureHubFactory::assertCookiePath($hub);

        $this->assertSame('mercureAuthorization', $hub->getCookieName());
    }

    /**
     * @return void
     */
    public function testMercureAcceptsABase64PrefixedEncryptionKey(): void
    {
        $this->skipIfMercureMissing();
        $this->skipIfJoseMissing();

        $broadcaster = new MercureBroadcaster($this->mercureConfig([
            'encryption_key' => 'base64:' . base64_encode(random_bytes(32)),
        ]));

        $this->assertInstanceOf(MercureBroadcaster::class, $broadcaster);
    }

    /**
     * @return void
     */
    public function testMercureSideSpecificSecretsTakePrecedence(): void
    {
        $this->skipIfMercureMissing();

        /** @var \Symfony\Component\Mercure\Hub $hub */
        $hub = MercureHubFactory::hub($this->mercureConfig([
            'subscribe_secret' => str_repeat('a', 32),
            'publish_secret' => str_repeat('b', 32),
        ]));

        $factory = $hub->getFactory();
        $this->assertNotNull($factory);
        $this->assertJwtSignedWith($factory->create(), str_repeat('a', 32));
        $this->assertJwtSignedWith($hub->getProvider()->getJwt(), str_repeat('b', 32));
    }

    /**
     * @return void
     */
    public function testMercureDefaultsTheRfc9068Claims(): void
    {
        $this->skipIfMercureMissing();

        /** @var \Symfony\Component\Mercure\Hub $hub */
        $hub = MercureHubFactory::hub($this->mercureConfig());

        $factory = $hub->getFactory();
        $this->assertNotNull($factory);
        $claims = $this->decodeJwtClaims($factory->create());

        $this->assertNotEmpty($claims['iss']);
        $this->assertSame($claims['iss'], $claims['client_id']);
        $this->assertSame('https://hub.test/.well-known/mercure', $claims['aud']);
        $this->assertSame('anonymous', $claims['sub']);

        $publishClaims = $this->decodeJwtClaims($hub->getProvider()->getJwt());

        $this->assertSame($claims['iss'], $publishClaims['iss']);
        $this->assertSame($claims['client_id'], $publishClaims['client_id']);
    }

    /**
     * @return void
     */
    public function testMercureExplicitClaimsWinOverTheDefaults(): void
    {
        $this->skipIfMercureMissing();

        /** @var \Symfony\Component\Mercure\Hub $hub */
        $hub = MercureHubFactory::hub($this->mercureConfig([
            'claims' => [
                'iss' => 'https://issuer.test',
                'aud' => 'https://audience.test',
                'client_id' => 'my-app',
            ],
        ]));

        $factory = $hub->getFactory();
        $this->assertNotNull($factory);
        $claims = $this->decodeJwtClaims($factory->create());

        $this->assertSame('https://issuer.test', $claims['iss']);
        $this->assertSame('https://audience.test', $claims['aud']);
        $this->assertSame('my-app', $claims['client_id']);
    }

    /**
     * @return void
     */
    public function testBroadcasterHintsMissingMercureDependency(): void
    {
        if (interface_exists(HubInterface::class)) {
            $this->markTestSkipped('symfony/mercure is installed.');
        }

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('symfony/mercure');

        new MercureBroadcaster(['url' => 'https://hub/.well-known/mercure']);
    }

    /**
     * Build a minimal Mercure connection config.
     *
     * @param array<string, mixed> $overrides Overrides
     * @return array<string, mixed>
     */
    protected function mercureConfig(array $overrides = []): array
    {
        return $overrides + [
            'url' => 'https://hub.test/.well-known/mercure',
            'secret' => str_repeat('s', 32),
        ];
    }

    /**
     * Decode JWT claims.
     *
     * @param string $jwt Token
     * @return array<string, mixed>
     */
    protected function decodeJwtClaims(string $jwt): array
    {
        $payload = explode('.', $jwt)[1];

        /** @var array<string, mixed> */
        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    /**
     * Assert a JWT is HMAC-signed with the given secret.
     *
     * @param string $jwt Token
     * @param string $secret Secret
     * @return void
     */
    protected function assertJwtSignedWith(string $jwt, string $secret): void
    {
        [$header, $payload, $signature] = explode('.', $jwt);

        $this->assertSame(
            rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, $secret, true)), '+/', '-_'), '='),
            $signature,
        );
    }
}
