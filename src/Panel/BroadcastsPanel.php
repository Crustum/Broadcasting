<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Panel;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Crustum\Broadcasting\BroadcastingPlugin;
use Crustum\DebugBar\Service\CallsiteCapture;
use Crustum\DebugBar\Service\VarExporter;
use Throwable;

/**
 * Broadcasts debug panel.
 *
 * Duck-typed third-party panel for the DebugBar toolbar (loaded through its
 * legacy adapter, no dependency on toolbar classes): subscribes to the
 * `Broadcasting.beforeSend` / `Broadcasting.sent` events and records one
 * row per outgoing broadcast. Attempts correlate by subject identity —
 * `PendingBroadcast` dispatches both events with itself as subject — while
 * durations are measured here since the broadcaster captures none.
 *
 * Cake-honest subset: only `sent` (terminal event arrived) and `pending`
 * (sending seen, no terminal event by shutdown) are reported. Queued
 * broadcasts emit no events in the web request until a worker runs, so
 * queue states are never invented. The reserved `__crustum` payload key
 * stays out of the displayed payload; its correlation id surfaces as a
 * fact when present. Payloads export the Variables way (`VarExporter`),
 * so inner objects stay expandable instead of collapsing to class names.
 */
class BroadcastsPanel implements EventListenerInterface
{
    /**
     * Plugin alias locating the panel element.
     *
     * @var string
     */
    public string $plugin = 'Crustum/Broadcasting';

    /**
     * Captured broadcasts for the current request.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $inbox = [];

    /**
     * Group sequence counter, reset on every `data()` drain.
     *
     * @var int
     */
    protected static int $sequence = 0;

    /**
     * Records one broadcast under its correlation key.
     *
     * @param string $key Correlation key.
     * @param array<string, mixed> $attempt Attempt shape.
     * @return void
     */
    public static function record(string $key, array $attempt): void
    {
        static::$inbox[$key] = $attempt;
    }

    /**
     * Resets captured broadcasts (long-lived workers).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$inbox = [];
        static::$sequence = 0;
    }

    /**
     * Reads captured broadcasts without draining.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function recorded(): array
    {
        return static::$inbox;
    }

    /**
     * Panel title shown in the inspector navigation.
     *
     * @return string
     */
    public function title(): string
    {
        return 'Broadcasts';
    }

    /**
     * Element rendering the panel, resolved inside this plugin.
     *
     * @return string
     */
    public function elementName(): string
    {
        return 'Crustum/Broadcasting.broadcasts_panel';
    }

    /**
     * Lists captured broadcasts for the inspector.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        static::$sequence = 0;
        $groups = array_map(
            static::shape(...),
            array_values(static::$inbox),
        );
        static::$inbox = [];

        $attention = 0;
        $connectionFacets = [];
        $eventFacets = [];
        foreach ($groups as $group) {
            if (($group['status'] ?? 'sent') !== 'sent') {
                $attention++;
            }

            foreach ((array)($group['filter_facets'] ?? []) as $token => $label) {
                if (str_starts_with((string)$token, 'connection:')) {
                    $connectionFacets[(string)$token] = $label;
                } elseif (str_starts_with((string)$token, 'event:')) {
                    $eventFacets[(string)$token] = $label;
                }
            }
        }

        return [
            'groups' => $groups,
            'summary' => ['count' => count($groups), 'attention' => $attention],
            'facets' => ['connection' => $connectionFacets, 'event' => $eventFacets],
        ];
    }

    /**
     * Toolbar badge for the panel.
     *
     * @return string
     */
    public function summary(): string
    {
        $count = count(static::$inbox);

        return $count === 0 ? '' : "{$count} broadcasts";
    }

    /**
     * No setup work beyond event subscription.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Subscribes the panel to broadcasting lifecycle events.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            BroadcastingPlugin::EVENT_BEFORE_SEND => 'captureBeforeSend',
            BroadcastingPlugin::EVENT_SENT => 'captureSent',
        ];
    }

    /**
     * Starts one broadcast timer.
     *
     * @param \Cake\Event\EventInterface<object> $event Before-send event.
     * @return void
     */
    public function captureBeforeSend(EventInterface $event): void
    {
        $key = static::key($event);
        if ($key === null) {
            return;
        }

        $site = CallsiteCapture::capture([__FILE__]);
        $payload = (array)$event->getData('payload');
        [$display, $correlationId] = static::displayPayload($payload);

        static::$inbox[$key] = [
            'event' => (string)($event->getData('event') ?? $event->getData('eventName') ?? 'unknown'),
            'channels' => static::channels($event->getData('channels')),
            'connection' => (string)($event->getData('connection') ?? 'default'),
            'payload' => VarExporter::export($display),
            'correlation_id' => $correlationId,
            'status' => 'pending',
            'started' => microtime(true),
            'duration_ms' => 0.0,
            'callsite' => $site['callsite'],
            'frames' => static::frames($site['stack']),
        ];
    }

    /**
     * Completes one broadcast as sent.
     *
     * @param \Cake\Event\EventInterface<object> $event Sent event.
     * @return void
     */
    public function captureSent(EventInterface $event): void
    {
        $key = static::key($event);
        if ($key === null || !isset(static::$inbox[$key])) {
            return;
        }

        $attempt = &static::$inbox[$key];
        $attempt['status'] = 'sent';
        $attempt['duration_ms'] = (microtime(true) - (float)$attempt['started']) * 1000;
        $payload = (array)($event->getData('payload') ?? []);
        [$display, $correlationId] = static::displayPayload($payload);
        $attempt['payload'] = VarExporter::export($display);
        if ($correlationId !== null) {
            $attempt['correlation_id'] = $correlationId;
        }
    }

    /**
     * Correlates lifecycle events by broadcaster identity.
     *
     * Events without an object subject cannot correlate and are ignored.
     *
     * @param \Cake\Event\EventInterface<object> $event Lifecycle event.
     * @return string|null
     */
    protected static function key(EventInterface $event): ?string
    {
        try {
            $subject = $event->getSubject();
        } catch (Throwable) {
            return null;
        }

        if (!is_object($subject)) {
            return null;
        }

        return 'b' . spl_object_id($subject);
    }

    /**
     * Normalizes channels to a name list.
     *
     * @param mixed $channels Raw channel data.
     * @return array<int, string>
     */
    protected static function channels(mixed $channels): array
    {
        if (is_string($channels)) {
            $channels = [$channels];
        }

        if (!is_array($channels)) {
            return [];
        }

        return array_values(array_map(strval(...), $channels));
    }

    /**
     * Strips reserved debug metadata from the displayed payload.
     *
     * @param array<string, mixed> $payload Raw payload.
     * @return array{0: array<string, mixed>, 1: string|null} Display payload and correlation id.
     */
    protected static function displayPayload(array $payload): array
    {
        $meta = $payload[BroadcastingPlugin::RESERVED_META_KEY] ?? null;
        unset($payload[BroadcastingPlugin::RESERVED_META_KEY]);

        $correlationId = null;
        if (is_array($meta) && is_string($meta['correlation_id'] ?? null) && $meta['correlation_id'] !== '') {
            $correlationId = $meta['correlation_id'];
        }

        return [$payload, $correlationId];
    }

    /**
     * Shapes stack frames for the source tab.
     *
     * @param array<int, array<string, mixed>> $stack Captured stack.
     * @return array<int, array<string, mixed>>
     */
    protected static function frames(array $stack): array
    {
        $frames = [];
        foreach ($stack as $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? null,
                'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                'function' => (string)($frame['function'] ?? ''),
                'vendor' => !($frame['app'] ?? false),
            ];
        }

        return $frames;
    }

    /**
     * Short class name for labels and tokens.
     *
     * @param string $class Fully qualified class name.
     * @return string
     */
    protected static function short(string $class): string
    {
        $class = ltrim($class, '\\');
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * URL-safe facet token fragment.
     *
     * @param string $value Raw value.
     * @return string
     */
    protected static function slug(string $value): string
    {
        $slug = strtolower(trim($value));

        return (string)preg_replace('/[^a-z0-9]+/', '-', $slug);
    }

    /**
     * Shapes one broadcast for the inspector.
     *
     * @param array<string, mixed> $attempt Captured attempt.
     * @return array<string, mixed>
     */
    protected static function shape(array $attempt): array
    {
        static::$sequence++;
        $index = static::$sequence;

        $event = (string)($attempt['event'] ?? 'unknown');
        $channels = array_values((array)($attempt['channels'] ?? []));
        $status = (string)($attempt['status'] ?? 'pending');
        $statusLabel = $status === 'sent' ? 'Sent' : 'Pending';
        $duration = (float)($attempt['duration_ms'] ?? 0);
        $connection = (string)($attempt['connection'] ?? 'default');
        $correlationId = $attempt['correlation_id'] ?? null;

        $filterTokens = [$status === 'sent' ? 'sent' : 'attention'];
        $filterFacets = [];
        $connectionToken = 'connection:' . static::slug($connection);
        if ($connectionToken !== 'connection:') {
            $filterTokens[] = $connectionToken;
            $filterFacets[$connectionToken] = $connection;
        }

        $eventToken = 'event:' . static::slug($event);
        if ($eventToken !== 'event:') {
            $filterTokens[] = $eventToken;
            $filterFacets[$eventToken] = $event;
        }

        return [
            'group_id' => 'broadcast-' . $index,
            'event' => $event,
            'label' => $event,
            'channels' => $channels,
            'channels_label' => $channels === [] ? 'No channels' : implode(', ', $channels),
            'connection' => $connection,
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'status' => $status,
            'status_label' => $statusLabel,
            'duration_ms' => $duration,
            'duration_label' => static::formatDuration($duration),
            'payload' => (array)($attempt['payload'] ?? []),
            'callsite' => $attempt['callsite'] ?? null,
            'frames' => array_values((array)($attempt['frames'] ?? [])),
            'filter' => implode(' ', $filterTokens),
            'filter_facets' => $filterFacets,
            'search' => mb_strtolower(implode(' ', array_filter([
                $event,
                implode(' ', $channels),
                $connection,
                $statusLabel,
                is_string($correlationId) ? $correlationId : '',
            ]))),
        ];
    }

    /**
     * Formats milliseconds for list rows.
     *
     * @param float $milliseconds Duration.
     * @return string
     */
    protected static function formatDuration(float $milliseconds): string
    {
        if ($milliseconds >= 1000) {
            return number_format($milliseconds / 1000, 2) . ' s';
        }

        if ($milliseconds >= 1) {
            return number_format($milliseconds, $milliseconds >= 100 ? 0 : 1) . ' ms';
        }

        return number_format($milliseconds * 1000, 0) . ' µs';
    }
}
