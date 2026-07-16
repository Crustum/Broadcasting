<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Integration;

use Cake\Http\ServerRequest;
use Cake\Queue\QueueManager;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Channel\PrivateChannel;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Crustum\Broadcasting\TestSuite\TestBroadcaster;

/**
 * Pending Broadcast Fluent Integration Test
 *
 * Fluent send API coverage adapted from Laravel AnonymousEvent suite.
 * Asserts via TestBroadcaster, not EventManager / AnonymousEvent.
 */
class PendingBroadcastFluentIntegrationTest extends TestCase
{
    use BroadcastingTrait;

    /**
     * Set up test case
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Broadcasting::setConfig('pusher', [
            'className' => TestBroadcaster::class,
            'connectionName' => 'pusher',
        ]);

        QueueManager::setConfig('default', [
            'url' => 'null://',
        ]);
    }

    /**
     * Tear down test case
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Router::setRequest(new ServerRequest());
        QueueManager::drop('default');

        parent::tearDown();
    }

    /**
     * Broadcast is sent with channel, event, and payload
     *
     * @return void
     */
    public function testBroadcastIsSent(): void
    {
        Broadcasting::to('test-channel')
            ->event('test-event')
            ->data(['some' => 'data'])
            ->send();

        $this->assertBroadcastSent('test-event');
        $this->assertBroadcastSentToChannel('test-channel', 'test-event');
        $this->assertBroadcastPayloadContains('test-event', 'some', 'data');
        $this->assertBroadcastSentViaConnection('default', 'test-event');
    }

    /**
     * Empty payload is allowed
     *
     * @return void
     */
    public function testDefaultPayloadIsEmptyArray(): void
    {
        Broadcasting::to('test-channel')
            ->event('test-event')
            ->send();

        $this->assertBroadcastSent('test-event');
        $this->assertBroadcastPayloadEquals('test-event', []);
    }

    /**
     * Multiple channels including PrivateChannel are normalized to names
     *
     * @return void
     */
    public function testSendToMultipleChannels(): void
    {
        Broadcasting::to([
            'test-channel',
            new PrivateChannel('test-channel'),
            'presence-test-channel',
        ])
            ->event('MultiChannelEvent')
            ->send();

        $this->assertBroadcastSent('MultiChannelEvent');
        $this->assertBroadcastSentToChannels(
            ['test-channel', 'private-test-channel', 'presence-test-channel'],
            'MultiChannelEvent',
        );
    }

    /**
     * Non-default connection is recorded on the broadcast
     *
     * @return void
     */
    public function testSendViaNonDefaultConnection(): void
    {
        Broadcasting::to('test-channel')
            ->event('test-event')
            ->connection('pusher')
            ->send();

        $this->assertBroadcastSentViaConnection('pusher', 'test-event');
    }

    /**
     * toOthers() excludes the current socket from the payload path
     *
     * @return void
     */
    public function testSendToOthersOnly(): void
    {
        $request = new ServerRequest([
            'environment' => [
                'HTTP_X_SOCKET_ID' => '12345',
            ],
        ]);
        Router::setRequest($request);

        Broadcasting::to('test-channel')
            ->event('WithoutOthers')
            ->send();

        $withoutOthers = TestBroadcaster::getBroadcastsByEvent('WithoutOthers');
        $this->assertCount(1, $withoutOthers);
        $this->assertNull($withoutOthers[0]['socket']);

        Broadcasting::to('test-channel')
            ->event('WithOthers')
            ->toOthers()
            ->send();

        $this->assertBroadcastExcludedSocket('12345', 'WithOthers');
    }

    /**
     * private() prefixes the channel name
     *
     * @return void
     */
    public function testSendToPrivateChannel(): void
    {
        Broadcasting::private('test-channel')
            ->event('PrivateEvent')
            ->send();

        $this->assertBroadcastSentToChannel('private-test-channel', 'PrivateEvent');
    }

    /**
     * presence() prefixes the channel name
     *
     * @return void
     */
    public function testSendToPresenceChannel(): void
    {
        Broadcasting::presence('test-channel')
            ->event('PresenceEvent')
            ->send();

        $this->assertBroadcastSentToChannel('presence-test-channel', 'PresenceEvent');
    }
}
