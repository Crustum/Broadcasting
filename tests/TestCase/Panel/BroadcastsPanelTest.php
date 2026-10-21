<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Panel;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Crustum\Broadcasting\BroadcastingPlugin;
use Crustum\Broadcasting\Panel\BroadcastsPanel;
use stdClass;

/**
 * BroadcastsPanel Test Case
 */
class BroadcastsPanelTest extends TestCase
{
    /**
     * @var \Crustum\Broadcasting\Panel\BroadcastsPanel
     */
    protected BroadcastsPanel $panel;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        BroadcastsPanel::clear();
        $this->panel = new BroadcastsPanel();
        EventManager::instance()->on($this->panel);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        EventManager::instance()->off($this->panel);
        BroadcastsPanel::clear();
        parent::tearDown();
    }

    /**
     * Toolbar adapter contract stays intact.
     *
     * @return void
     */
    public function testPanelContract(): void
    {
        $this->assertSame('Broadcasts', $this->panel->title());
        $this->assertSame('Crustum/Broadcasting.broadcasts_panel', $this->panel->elementName());
        $this->assertSame('', $this->panel->summary());
        $this->assertSame(
            ['groups' => [], 'summary' => ['count' => 0, 'attention' => 0], 'facets' => ['connection' => [], 'event' => []]],
            $this->panel->data(),
        );
    }

    /**
     * Sent broadcast groups by subject with channels and meta stripped.
     *
     * @return void
     */
    public function testSentBroadcastGroups(): void
    {
        $subject = new stdClass();
        $payload = [
            'message' => 'hello',
            BroadcastingPlugin::RESERVED_META_KEY => ['correlation_id' => 'c-1'],
        ];
        $this->dispatch(
            BroadcastingPlugin::EVENT_BEFORE_SEND,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general', 'news'], 'connection' => 'pusher', 'payload' => $payload],
        );
        $this->dispatch(
            BroadcastingPlugin::EVENT_SENT,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general', 'news'], 'connection' => 'pusher', 'payload' => $payload],
        );

        $this->assertSame('1 broadcasts', $this->panel->summary());
        $data = $this->panel->data();

        $this->assertCount(1, $data['groups']);
        $group = $data['groups'][0];
        $this->assertSame('sent', $group['status']);
        $this->assertSame('Sent', $group['status_label']);
        $this->assertSame('UserCreated', $group['event']);
        $this->assertSame('UserCreated', $group['label']);
        $this->assertSame(['general', 'news'], $group['channels']);
        $this->assertSame('general, news', $group['channels_label']);
        $this->assertSame('pusher', $group['connection']);
        $this->assertSame('c-1', $group['correlation_id']);
        $this->assertGreaterThanOrEqual(0, $group['duration_ms']);
        $this->assertArrayNotHasKey(BroadcastingPlugin::RESERVED_META_KEY, (array)$group['payload']);
        $this->assertSame('hello', $group['payload']['message'] ?? null);
        $this->assertSame(['count' => 1, 'attention' => 0], $data['summary']);
    }

    /**
     * Pending broadcasts need attention until the sent event arrives.
     *
     * @return void
     */
    public function testPendingBroadcastNeedsAttention(): void
    {
        $this->dispatch(
            BroadcastingPlugin::EVENT_BEFORE_SEND,
            new stdClass(),
            ['event' => 'UserCreated', 'channels' => 'general', 'payload' => ['message' => 'hi']],
        );

        $data = $this->panel->data();

        $this->assertCount(1, $data['groups']);
        $group = $data['groups'][0];
        $this->assertSame('pending', $group['status']);
        $this->assertSame('Pending', $group['status_label']);
        $this->assertSame(['general'], $group['channels']);
        $this->assertSame('default', $group['connection']);
        $this->assertSame(1, $data['summary']['attention']);
        $this->assertStringContainsString('attention', (string)$group['filter']);
    }

    /**
     * A sent event without a tracked before-send is ignored.
     *
     * @return void
     */
    public function testSentWithoutBeforeSendIgnored(): void
    {
        $this->dispatch(
            BroadcastingPlugin::EVENT_SENT,
            new stdClass(),
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => []],
        );

        $this->assertSame([], $this->panel->data()['groups']);
    }

    /**
     * Events without an object subject cannot correlate and are ignored.
     *
     * @return void
     */
    public function testNonObjectSubjectIgnored(): void
    {
        EventManager::instance()->dispatch(new Event(
            BroadcastingPlugin::EVENT_BEFORE_SEND,
            null,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => []],
        ));

        $this->assertSame([], $this->panel->data()['groups']);
        $this->assertSame('', $this->panel->summary());
    }

    /**
     * Inner objects survive capture instead of collapsing to class names.
     *
     * @return void
     */
    public function testInnerObjectsStayExpandable(): void
    {
        $subject = new stdClass();
        $user = new Entity(['id' => 7, 'name' => 'Ann'], ['markNew' => false]);
        $payload = ['user' => $user, BroadcastingPlugin::RESERVED_META_KEY => ['correlation_id' => 'c-9']];
        $this->dispatch(
            BroadcastingPlugin::EVENT_BEFORE_SEND,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => $payload],
        );
        $this->dispatch(
            BroadcastingPlugin::EVENT_SENT,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => $payload],
        );

        $data = $this->panel->data();
        $groupPayload = (array)$data['groups'][0]['payload'];

        $this->assertIsArray($groupPayload['user'] ?? null);
        $this->assertSame(Entity::class, $groupPayload['user']['__class'] ?? null);
        $this->assertSame('c-9', $data['groups'][0]['correlation_id']);
    }

    /**
     * Callsite skips vendor frames and filter facets collect tokens.
     *
     * @return void
     */
    public function testCallsiteAndFacets(): void
    {
        $subject = new stdClass();
        $this->dispatch(
            BroadcastingPlugin::EVENT_BEFORE_SEND,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => []],
        );
        $this->dispatch(
            BroadcastingPlugin::EVENT_SENT,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => []],
        );

        $data = $this->panel->data();
        $group = $data['groups'][0];

        $this->assertStringEndsWith('BroadcastsPanelTest.php', (string)($group['callsite']['file'] ?? ''));
        $this->assertStringContainsString('sent', (string)($group['filter'] ?? ''));
        $this->assertStringContainsString('connection:default', (string)($group['filter'] ?? ''));
        $this->assertStringContainsString('event:usercreated', (string)($group['filter'] ?? ''));
        $this->assertSame('default', $data['facets']['connection']['connection:default'] ?? null);
        $this->assertSame('UserCreated', $data['facets']['event']['event:usercreated'] ?? null);
        $this->assertStringContainsString('usercreated', (string)($group['search'] ?? ''));
        $this->assertStringContainsString('general', (string)($group['search'] ?? ''));
    }

    /**
     * Element renders groups with list hooks and detail panes.
     *
     * Toolbar kit elements resolve only inside a host app with both
     * plugins loaded, so the render test stubs those calls and asserts
     * our markup plus the integration points.
     *
     * @return void
     */
    public function testElementRendersGroups(): void
    {
        $subject = new stdClass();
        $this->dispatch(
            BroadcastingPlugin::EVENT_BEFORE_SEND,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => ['message' => 'hi']],
        );
        $this->dispatch(
            BroadcastingPlugin::EVENT_SENT,
            $subject,
            ['event' => 'UserCreated', 'channels' => ['general'], 'payload' => ['message' => 'hi']],
        );
        $data = $this->panel->data();

        $view = new class (new ServerRequest()) extends View {
            /**
             * Stubs toolbar kit elements unavailable in isolation.
             *
             * @param string $name Element name.
             * @param array<string, mixed> $data Element variables.
             * @param array<string, mixed> $options Element options.
             * @return string
             */
            public function element(string $name, array $data = [], array $options = []): string
            {
                if ($name === 'Crustum/DebugBar.ui/inspector_workspace') {
                    return ($data['list'] ?? '') . ($data['detail'] ?? '');
                }

                if ($name === 'Crustum/DebugBar.ui/inspector_list_panel') {
                    return ($data['controls'] ?? '') . ($data['body'] ?? '');
                }

                if (str_starts_with($name, 'Crustum/DebugBar.')) {
                    return '<!--kit:' . $name . '-->';
                }

                return parent::element($name, $data, $options);
            }
        };
        $html = $view->element('Crustum/Broadcasting.broadcasts_panel', $data);

        $this->assertStringContainsString('data-dk-broadcasts-panel', $html);
        $this->assertStringContainsString('data-dk-broadcast-entry', $html);
        $this->assertStringContainsString('data-dk-broadcast-detail="broadcast-1"', $html);
        $this->assertStringContainsString('UserCreated', $html);
        $this->assertStringContainsString('general', $html);
        $this->assertStringContainsString('data-dk-tab="broadcast-1:payload"', $html);
        $this->assertStringContainsString('data-dk-tab="broadcast-1:source"', $html);
        $this->assertStringContainsString('<!--kit:Crustum/DebugBar.ui/icon-->', $html);
        $this->assertStringContainsString('<!--kit:Crustum/DebugBar.ui/tree_filter-->', $html);
        $this->assertStringContainsString('<!--kit:Crustum/DebugBar.ui/debug_tree-->', $html);
        $this->assertStringContainsString('<!--kit:Crustum/DebugBar.ui/source_ref-->', $html);
        $this->assertStringContainsString('data-dk-tree-scope', $html);
    }

    /**
     * Dispatches a broadcast lifecycle event on the global manager.
     *
     * @param string $name Event name.
     * @param object|null $subject Correlated broadcaster subject.
     * @param array<string, mixed> $data Event data.
     * @return void
     */
    protected function dispatch(string $name, ?object $subject, array $data = []): void
    {
        EventManager::instance()->dispatch(new Event($name, $subject, $data));
    }
}
