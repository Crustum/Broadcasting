<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Broadcaster;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcaster\MercureBroadcaster;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidChannelException;
use Jose\Component\Encryption\JWEBuilder;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

/**
 * MercureBroadcaster Test Case
 *
 * Covers auth validation, the JSON auth response with its authorization
 * cookie, per-channel grants, publish batching, the update envelope shape,
 * encryption guards, and topic names.
 */
class MercureBroadcasterTest extends TestCase
{
    /**
     * @var list<\Symfony\Component\Mercure\Update>
     */
    protected array $published = [];

    /**
     * @var \Symfony\Component\Mercure\HubInterface&\PHPUnit\Framework\MockObject\Stub
     */
    protected mixed $hub;

    /**
     * @var \Symfony\Component\Mercure\Jwt\TokenFactoryInterface&\PHPUnit\Framework\MockObject\Stub
     */
    protected mixed $tokenFactory;

    /**
     * @var list<array{grants: list<\Symfony\Component\Mercure\Jwt\Grant>, claims: array<string, mixed>}>
     */
    protected array $tokenRequests = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->published = [];
        $this->tokenRequests = [];
        $this->tokenFactory = $this->createStub(TokenFactoryInterface::class);
        $this->tokenFactory->method('create')->willReturnCallback(function (...$args): string {
            $this->tokenRequests[] = [
                'grants' => $args[0] ?? [],
                'claims' => $args[1] ?? [],
            ];

            return 'test-jwt';
        });

        $this->hub = $this->createStub(HubInterface::class);
        $this->hub->method('getFactory')->willReturn($this->tokenFactory);
        $this->hub->method('getPublicUrl')->willReturn('https://hub.test/.well-known/mercure');
        $this->hub->method('getCookieName')->willReturn('mercureAuthorization');
        $this->hub->method('publish')->willReturnCallback(function (Update $update): string {
            $this->published[] = $update;

            return 'urn:uuid:test';
        });
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->hub = $this->createStub(HubInterface::class);
        $this->tokenFactory = $this->createStub(TokenFactoryInterface::class);
        $this->published = [];
        $this->tokenRequests = [];

        parent::tearDown();
    }

    /**
     * Build a broadcaster with the hub mock injected.
     *
     * @param array<string, mixed> $configOverrides Config overrides
     * @return \Crustum\Broadcasting\Broadcaster\MercureBroadcaster
     */
    protected function broadcaster(array $configOverrides = []): MercureBroadcaster
    {
        $broadcaster = new MercureBroadcaster($this->mercureConfig($configOverrides));

        $property = (new ReflectionClass($broadcaster))->getProperty('hub');
        $property->setValue($broadcaster, $this->hub);

        return $broadcaster;
    }

    /**
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
     * Build a request carrying channel names.
     *
     * The request host matches the hub host so the authorization cookie
     * domain resolves without a second-level-domain mismatch.
     *
     * @param array<mixed> $channelNames Channel names
     * @param array<string, mixed>|object|null $user Authenticated user payload or entity
     * @param string $host Application host
     * @return \Cake\Http\ServerRequest
     */
    protected function authRequest(array $channelNames, array|object|null $user = null, string $host = 'hub.test'): ServerRequest
    {
        $request = (new ServerRequest([
            'url' => '/broadcasting/auth',
            'environment' => [
                'HTTP_HOST' => $host,
                'REQUEST_URI' => '/broadcasting/auth',
            ],
        ]))->withParsedBody(['channel_names' => $channelNames]);

        if ($user !== null) {
            $identity = new class ($user) {
                /**
                 * @param array<string, mixed>|object $user User payload or entity
                 */
                public function __construct(private array|object $user)
                {
                }

                /**
                 * @return array<string, mixed>|object
                 */
                public function getOriginalData(): array|object
                {
                    return $this->user;
                }
            };

            $request = $request->withAttribute('identity', $identity);
        }

        return $request;
    }

    /**
     * Decode the JSON body of an auth response.
     *
     * @param \Cake\Http\Response $response Auth response
     * @return array<string, mixed>
     */
    protected function authBody(Response $response): array
    {
        /** @var array<string, mixed> */
        return json_decode((string)$response->getBody(), true);
    }

    /**
     * Decode an update envelope.
     *
     * @param \Symfony\Component\Mercure\Update $update Update
     * @return array<string, mixed>
     */
    protected function envelope(Update $update): array
    {
        /** @var array<string, mixed> */
        return json_decode($update->getData(), true);
    }

    /**
     * @return void
     */
    public function testGetName(): void
    {
        $this->assertSame('mercure', $this->broadcaster()->getName());
    }

    /**
     * @return void
     */
    public function testAuthDeniesEmptyChannelNames(): void
    {
        $this->expectException(InvalidChannelException::class);

        $this->broadcaster()->auth($this->authRequest([]));
    }

    /**
     * @return void
     */
    public function testAuthDeniesNonStringChannelNames(): void
    {
        $this->expectException(InvalidChannelException::class);

        $this->broadcaster()->auth($this->authRequest(['private-a', 42]));
    }

    /**
     * @return void
     */
    public function testAuthDeniesOversizedBatches(): void
    {
        $this->expectException(InvalidChannelException::class);

        $this->broadcaster()->auth($this->authRequest(array_fill(0, 101, 'private-a')));
    }

    /**
     * Auth answers public channels with a cookie and unflagged entries.
     *
     * @return void
     */
    public function testAuthSetsCookieWithNoGrantsWhenEveryChannelIsPublic(): void
    {
        $broadcaster = $this->broadcaster();

        $response = $broadcaster->auth($this->authRequest(['public-a']));
        $body = $this->authBody($response);

        $this->assertSame([['name' => 'public-a']], $body['channel_names']);
        $this->assertSame(300, $body['expires_in']);
        $this->assertSame('https://crustum.cake/echo/', $body['topic_prefix']);
        $this->assertTrue($body['client_events']);
        $this->assertSame([], $this->tokenRequests[0]['grants']);

        $cookie = $response->getCookie('mercureAuthorization');
        $this->assertNotNull($cookie);
        $this->assertSame('test-jwt', $cookie['value']);
    }

    /**
     * Auth deduplicates repeated channel names into one entry.
     *
     * @return void
     */
    public function testAuthDeduplicatesRequestedChannels(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->registerUserResolver(fn(): array => ['id' => 1]);
        $broadcaster->registerChannel('private-a', fn(): true => true);

        $body = $this->authBody(
            $broadcaster->auth($this->authRequest(['private-a', 'private-a'], ['id' => 1])),
        );

        $this->assertCount(1, $body['channel_names']);
    }

    /**
     * Auth flags a denied channel and keeps the granted ones.
     *
     * @return void
     */
    public function testAuthFlagsDeniedChannelAndKeepsGrantedOnes(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->registerUserResolver(fn(): array => ['id' => 1]);
        $broadcaster->registerChannel('private-a', fn(): true => true);

        $body = $this->authBody(
            $broadcaster->auth($this->authRequest(['private-a', 'private-b'], ['id' => 1])),
        );

        $this->assertSame('private-a', $body['channel_names'][0]['name']);
        $this->assertArrayNotHasKey('denied', $body['channel_names'][0]);
        $this->assertSame('private-b', $body['channel_names'][1]['name']);
        $this->assertTrue($body['channel_names'][1]['denied']);
    }

    /**
     * Auth rejects a hub on a different second-level domain.
     *
     * @return void
     */
    public function testAuthRejectsHubOnDifferentSecondLevelDomain(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->registerChannel('private-a', fn(): true => true);

        try {
            $broadcaster->auth($this->authRequest(['private-a'], ['id' => 1], 'other.test'));
            $this->fail('Expected BroadcastingException for the domain mismatch.');
        } catch (BroadcastingException $broadcastingException) {
            $this->assertStringContainsString('public_url', $broadcastingException->getMessage());
        }
    }

    /**
     * Auth grants a presence channel its member payload.
     *
     * @return void
     */
    public function testAuthGrantsPresenceChannelWithPayload(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->registerUserResolver(fn(): array => ['id' => 7]);
        $broadcaster->registerChannel('presence-room', fn(): array => ['id' => 7, 'name' => 'Ada']);

        $body = $this->authBody($broadcaster->auth($this->authRequest(['presence-room'], ['id' => 7])));

        $this->assertArrayNotHasKey('denied', $body['channel_names'][0]);

        $grants = $this->tokenRequests[0]['grants'];
        $presenceGrant = null;
        foreach ($grants as $grant) {
            if ($grant->payload !== null) {
                $presenceGrant = $grant;
            }
        }

        $this->assertNotNull($presenceGrant);
        $this->assertSame(['subscribe'], $presenceGrant->actions);
        $this->assertSame('7', $presenceGrant->payload['user_id']);
        $this->assertSame(['id' => 7, 'name' => 'Ada'], $presenceGrant->payload['user_info']);
    }

    /**
     * Auth prefers the entity broadcast identifier over the raw id.
     *
     * @return void
     */
    public function testAuthPrefersEntityBroadcastIdentifier(): void
    {
        $entity = new class extends Entity {
            /**
             * Custom broadcasting identifier.
             *
             * @return string
             */
            public function getBroadcastIdentifier(): string
            {
                return 'user-' . $this->get('id');
            }
        };
        $entity->set('id', 9);

        $broadcaster = $this->broadcaster();
        $broadcaster->registerChannel('presence-room', fn(): array => ['id' => 9, 'name' => 'Ada']);

        $broadcaster->auth($this->authRequest(['presence-room'], $entity));

        $grants = $this->tokenRequests[0]['grants'];
        $presenceGrant = null;
        foreach ($grants as $grant) {
            if ($grant->payload !== null) {
                $presenceGrant = $grant;
            }
        }

        $this->assertNotNull($presenceGrant);
        $this->assertSame('user-9', $presenceGrant->payload['user_id']);
    }

    /**
     * Auth falls back to the entity id without a custom identifier.
     *
     * @return void
     */
    public function testAuthFallsBackToEntityId(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->registerChannel('presence-room', fn(): array => ['id' => 4, 'name' => 'Bo']);

        $broadcaster->auth($this->authRequest(['presence-room'], new Entity(['id' => 4])));

        $grants = $this->tokenRequests[0]['grants'];
        $presenceGrant = null;
        foreach ($grants as $grant) {
            if ($grant->payload !== null) {
                $presenceGrant = $grant;
            }
        }

        $this->assertNotNull($presenceGrant);
        $this->assertSame('4', $presenceGrant->payload['user_id']);
    }

    /**
     * Auth exposes the JWK for an authorized encrypted channel.
     *
     * @return void
     */
    public function testAuthReturnsJwkForEncryptedChannel(): void
    {
        if (!class_exists(JWEBuilder::class)) {
            $this->markTestSkipped('web-token/jwt-library is not installed.');
        }

        $broadcaster = $this->broadcaster([
            'encryption_key' => 'base64:' . base64_encode(random_bytes(32)),
        ]);
        $broadcaster->registerUserResolver(fn(): array => ['id' => 1]);
        $broadcaster->registerChannel('private-encrypted-a', fn(): true => true);

        $body = $this->authBody($broadcaster->auth($this->authRequest(['private-encrypted-a'], ['id' => 1])));

        $this->assertArrayHasKey('jwk', $body['channel_names'][0]);
        $this->assertSame('oct', $body['channel_names'][0]['jwk']['kty']);
    }

    /**
     * @return void
     */
    public function testBroadcastPublishesSingleUnprivateUpdateForPublicChannels(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->broadcast(['public-a', 'public-b'], 'Tick', ['n' => 1]);

        $this->assertCount(1, $this->published);
        $update = $this->published[0];
        $this->assertFalse($update->isPrivate());
        $this->assertSame(
            [$broadcaster->topic('public-a'), $broadcaster->topic('public-b')],
            $update->getTopics(),
        );
        $this->assertSame(
            ['channels' => ['public-a', 'public-b'], 'event' => 'Tick', 'payload' => ['n' => 1]],
            $this->envelope($update),
        );
    }

    /**
     * @return void
     */
    public function testBroadcastPublishesOnePrivateUpdatePerGuardedChannel(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->broadcast(['private-a', 'private-b'], 'Tick', ['n' => 1]);

        $this->assertCount(2, $this->published);
        foreach ($this->published as $update) {
            $this->assertTrue($update->isPrivate());
            $this->assertCount(1, $update->getTopics());
        }

        $this->assertSame([$broadcaster->topic('private-a')], $this->published[0]->getTopics());
        $this->assertSame([$broadcaster->topic('private-b')], $this->published[1]->getTopics());
    }

    /**
     * @return void
     */
    public function testBroadcastStripsSocketIntoEnvelope(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->broadcast(['public-a'], 'Tick', ['n' => 1, 'socket' => 'socket-1']);

        $this->assertCount(1, $this->published);
        $this->assertSame(
            [
                'channels' => ['public-a'],
                'event' => 'Tick',
                'payload' => ['n' => 1],
                'socket' => 'socket-1',
            ],
            $this->envelope($this->published[0]),
        );
    }

    /**
     * @return void
     */
    public function testBroadcastSplitsMixedBatch(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->broadcast(['public-a', 'private-a'], 'Tick', ['n' => 1]);

        $this->assertCount(2, $this->published);
        $this->assertFalse($this->published[0]->isPrivate());
        $this->assertTrue($this->published[1]->isPrivate());
    }

    /**
     * @return void
     */
    public function testBroadcastWrapsHubExceptions(): void
    {
        $this->hub = $this->createStub(HubInterface::class);
        $this->hub->method('getFactory')->willReturn($this->tokenFactory);
        $this->hub->method('publish')->willThrowException(new RuntimeException('hub down'));

        $broadcaster = $this->broadcaster();

        try {
            $broadcaster->broadcast(['public-a'], 'Tick');
            $this->fail('Expected BroadcastingException.');
        } catch (BroadcastingException $broadcastingException) {
            $this->assertStringContainsString('Mercure error: hub down.', $broadcastingException->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $broadcastingException->getPrevious());
        }
    }

    /**
     * @return void
     */
    public function testBroadcastRejectsEncryptedChannelsWithoutKey(): void
    {
        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('encryption_key');

        $this->broadcaster()->broadcast(['private-encrypted-a'], 'Tick');
    }

    /**
     * @return void
     */
    public function testAuthRejectsEncryptedChannelsWithoutKey(): void
    {
        $broadcaster = $this->broadcaster();
        $broadcaster->registerUserResolver(fn(): array => ['id' => 1]);
        $broadcaster->registerChannel('private-encrypted-a', fn(): true => true);

        $this->expectException(BroadcastingException::class);
        $this->expectExceptionMessage('encryption_key');

        $broadcaster->auth($this->authRequest(['private-encrypted-a'], ['id' => 1]));
    }

    /**
     * Auth omits the whisper topic when client events are disabled.
     *
     * @return void
     */
    public function testAuthOmitsWhisperGrantWhenClientEventsDisabled(): void
    {
        $broadcaster = $this->broadcaster(['client_events' => false]);
        $broadcaster->registerUserResolver(fn(): array => ['id' => 1]);
        $broadcaster->registerChannel('private-a', fn(): true => true);

        $body = $this->authBody($broadcaster->auth($this->authRequest(['private-a'], ['id' => 1])));

        $this->assertArrayNotHasKey('whisper_topic', $body['channel_names'][0]);

        foreach ($this->tokenRequests[0]['grants'] as $grant) {
            foreach ($grant->topics as $topic) {
                $this->assertStringNotContainsString('whisper', is_string($topic) ? $topic : '');
            }
        }
    }

    /**
     * @return void
     */
    public function testAuthAndBroadcastHonorCustomTopicPrefix(): void
    {
        $broadcaster = $this->broadcaster(['topic_prefix' => 'https://app.test/hub/']);
        $broadcaster->registerUserResolver(fn(): array => ['id' => 1]);
        $broadcaster->registerChannel('private-a', fn(): true => true);

        $this->assertSame('https://app.test/hub/channel/private-a', $broadcaster->topic('private-a'));

        $broadcaster->broadcast(['private-a'], 'Tick');

        $this->assertSame(
            ['https://app.test/hub/channel/private-a'],
            $this->published[0]->getTopics(),
        );
    }

    /**
     * @return void
     */
    public function testTopicsEncodeChannelNamesIntoSingleSegment(): void
    {
        $broadcaster = $this->broadcaster();

        $this->assertSame(
            'https://crustum.cake/echo/channel/' . rawurlencode('orders/1'),
            $broadcaster->topic('orders/1'),
        );
    }
}
