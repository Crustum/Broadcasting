<?php
/**
 * Broadcasts panel element.
 *
 * Server-rendered list of outgoing broadcasts (event plus channels) with a
 * connection/event filter, search across event/channels/connection, and
 * Payload (exported data) plus Source (callsite plus capped frames) tabs
 * per broadcast. Interactive states are server-rendered (`aria-pressed`,
 * `hidden`); the boot script owns search/filter/selection over the
 * `data-dk-*` hooks with zero registration — no selection config, no
 * panel JavaScript ships with the panel.
 *
 * CakePHP notes (no fake data): only `sent` and `pending` statuses render —
 * queued broadcasts emit no events in the web request until a worker runs.
 * The reserved `__crustum` payload key never renders; its correlation id
 * surfaces as a fact when present.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $groups Broadcasts from `BroadcastsPanel::data()`
 * @var array $summary Counters with `count` and `attention`
 */
use function Cake\Core\h;

$groups = array_values((array)($groups ?? []));
$summary = (array)($summary ?? []);
$facets = (array)($facets ?? []);
$connectionFacets = (array)($facets['connection'] ?? []);
$eventFacets = (array)($facets['event'] ?? []);
$facetCount = static function (string $token) use ($groups): int {
    $count = 0;
    foreach ($groups as $group) {
        $tokens = explode(' ', (string)((array)$group)['filter'] ?? '');
        if (in_array($token, $tokens, true)) {
            $count++;
        }
    }

    return $count;
};
$total = (int)($summary['count'] ?? count($groups));
$attentionCount = 0;
$sentCount = 0;
foreach ($groups as $group) {
    $group = (array)$group;
    if ((string)($group['status'] ?? 'sent') === 'sent') {
        $sentCount++;
    } else {
        $attentionCount++;
    }
}

$segmentedTabClass = 'dk:relative dk:flex dk:min-w-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-pressed:bg-white dk:aria-pressed:text-zinc-950 dk:aria-pressed:shadow-sm dk:aria-pressed:hover:bg-white dk:aria-pressed:hover:text-zinc-950 dk:aria-selected:bg-white dk:aria-selected:text-zinc-950 dk:aria-selected:shadow-sm dk:aria-selected:hover:bg-white dk:aria-selected:hover:text-zinc-950 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-pressed:bg-zinc-800 dk:dark:pressed:text-white dk:dark:aria-selected:bg-zinc-800 dk:dark:aria-selected:text-white';
$statusClass = static function (string $status): string {
    return match ($status) {
        'sent' => 'dk:text-emerald-600 dk:dark:text-emerald-300',
        default => 'dk:text-sky-600 dk:dark:text-sky-300',
    };
};
$firstKey = '';
foreach ($groups as $group) {
    $group = (array)$group;
    if ($firstKey === '') {
        $firstKey = (string)($group['group_id'] ?? '');
    }
}
?>
<div data-dk-broadcasts-panel data-dk-active-filter="all" class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($groups === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No broadcasts were sent.']) ?>
    <?php else : ?>
    <?php
    ob_start();
        ?>
    <p
        data-dk-broadcasts-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($total) ?> <?= $total === 1 ? 'broadcast' : 'broadcasts' ?>
        <span
            data-dk-visible-count
            hidden
            class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
        ></span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search broadcasts</span>
                <input
                    data-dk-search="broadcasts"
                    type="search"
                    placeholder="Search event or channel"
                    aria-label="Search broadcasts"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter broadcasts by status</span>
                <select
                    data-dk-select="broadcasts-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="all" selected>All (<?= number_format($total) ?>)</option>
                    <option value="attention">Needs attention (<?= number_format($attentionCount) ?>)</option>
                    <option value="sent">Sent (<?= number_format($sentCount) ?>)</option>
                    <?php if ($connectionFacets !== []) : ?>
                    <optgroup label="Connection">
                        <?php foreach ($connectionFacets as $token => $label) : ?>
                        <option value="<?= h((string)$token) ?>"><?= h((string)$label) ?> (<?= number_format($facetCount((string)$token)) ?>)</option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <?php if ($eventFacets !== []) : ?>
                    <optgroup label="Event">
                        <?php foreach ($eventFacets as $token => $label) : ?>
                        <option value="<?= h((string)$token) ?>"><?= h((string)$label) ?> (<?= number_format($facetCount((string)$token)) ?>)</option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
        ?>
    <div data-dk-broadcasts-list aria-label="Broadcasts">
        <?php foreach ($groups as $group) :
            $group = (array)$group;
            $key = (string)($group['group_id'] ?? '');
            $status = (string)($group['status'] ?? 'sent');
            $pressed = $key !== '' && $key === $firstKey;
            ?>
        <button
            type="button"
            data-dk-broadcast-entry
            data-dk-broadcast-key="<?= h($key) ?>"
            data-dk-filter-group="<?= h((string)($group['filter'] ?? ($status === 'sent' ? 'sent' : 'attention'))) ?>"
            data-dk-filter-attention="<?= $status === 'sent' ? 'false' : 'true' ?>"
            data-dk-search-text="<?= h((string)($group['search'] ?? '')) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_auto] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h((string)($group['label'] ?? '')) ?></span>
            <span class="dk:justify-self-end dk:text-xs dk:font-bold <?= h($statusClass($status)) ?>">
                <?= h((string)($group['status_label'] ?? '')) ?>
            </span>
            <span class="dk:col-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h((string)($group['channels_label'] ?? '')) ?></span>
            <span class="dk:col-start-2 dk:justify-self-end dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-400">
                <?= h((string)($group['duration_label'] ?? '')) ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No broadcasts match these filters.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
        ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No broadcast is selected. Adjust the filter or search.</div>
        <?php foreach ($groups as $group) :
            $group = (array)$group;
            $key = (string)($group['group_id'] ?? '');
            $payload = (array)($group['payload'] ?? []);
            $callsite = is_array($group['callsite'] ?? null) ? $group['callsite'] : null;
            $frames = array_values((array)($group['frames'] ?? []));
            $pressed = $key !== '' && $key === $firstKey;
            $tabScope = $key;
            ?>
        <section
            data-dk-broadcast-detail="<?= h($key) ?>"
            aria-label="Selected broadcast details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:grid dk:min-w-0 dk:grid-cols-[minmax(0,1fr)_auto] dk:items-start dk:gap-x-3 dk:gap-y-2">
                        <div class="dk:min-w-0">
                            <h3 class="dk:break-words dk:text-base dk:font-bold dk:leading-6"><?= h((string)($group['label'] ?? '')) ?></h3>
                            <code class="dk:mt-1 dk:block dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:font-medium dk:leading-4 dk:text-zinc-400"><?= h((string)($group['connection'] ?? '')) ?></code>
                        </div>
                        <span class="dk:shrink-0 dk:text-xs dk:font-bold <?= h($statusClass((string)($group['status'] ?? 'sent'))) ?>">
                            <?= h((string)($group['status_label'] ?? '')) ?>
                        </span>
                    </div>
                    <dl class="dk:mt-3 dk:grid dk:min-w-0 dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:sm:grid-cols-2">
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Channels</dt>
                            <dd class="dk:min-w-0 dk:truncate dk:text-sm dk:font-semibold dk:text-zinc-800 dk:dark:text-zinc-200"><?= h((string)($group['channels_label'] ?? '')) ?></dd>
                        </div>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Duration</dt>
                            <dd class="dk:min-w-0 dk:truncate dk:text-sm dk:font-semibold dk:tabular-nums dk:text-zinc-800 dk:dark:text-zinc-200"><?= h((string)($group['duration_label'] ?? '')) ?></dd>
                        </div>
                        <?php if (is_string($group['correlation_id'] ?? null) && $group['correlation_id'] !== '') : ?>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Correlation</dt>
                            <dd class="dk:min-w-0 dk:truncate dk:font-mono dk:text-sm dk:font-semibold dk:text-zinc-800 dk:dark:text-zinc-200"><?= h((string)$group['correlation_id']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </header>
                <div role="tablist" aria-label="Broadcast detail" data-dk-detail-tabs class="dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800 dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2">
                    <div role="presentation" class="dk:flex dk:min-w-0 dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                        <?php foreach (['payload' => ['Payload', 'database'], 'source' => ['Source', 'code']] as $tabKey => [$tabLabel, $tabIcon]) : ?>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="<?= $tabKey === 'payload' ? 'true' : 'false' ?>"
                            aria-label="<?= h($tabLabel) ?>"
                            data-dk-tab="<?= h($tabScope . ':' . $tabKey) ?>"
                            class="<?= h($segmentedTabClass) ?> dk:h-auto"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => $tabIcon, 'size' => '3.5', 'class' => 'dk:sm:hidden']) ?>
                            <span class="dk:hidden dk:sm:inline"><?= h($tabLabel) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:payload" class="dk:py-3 dk:sm:p-4">
                    <div data-dk-tree-scope class="dk:min-w-0">
                        <?= $this->element('Crustum/DebugBar.ui/tree_filter', [
                            'label' => 'Search payload',
                            'placeholder' => 'Search payload keys and values',
                        ]) ?>
                        <?php if ($payload !== []) : ?>
                        <?= $this->element('Crustum/DebugBar.ui/debug_tree', [
                            'nodes' => $payload,
                        ]) ?>
                        <?php else : ?>
                        <p class="dk:text-xs dk:leading-5 dk:text-zinc-400">No payload was captured.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:source" hidden class="dk:p-3 dk:sm:p-4">
                    <?php if ($callsite === null) : ?>
                    <p class="dk:text-xs dk:text-zinc-400">Source unavailable</p>
                    <?php else : ?>
                    <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                        'file' => (string)($callsite['file'] ?? ''),
                        'line' => (int)($callsite['line'] ?? 0),
                    ]) ?>
                    <?php if ($frames !== []) : ?>
                    <dl class="dk:mt-3 dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                        <?php foreach ($frames as $frame) :
                            $frame = (array)$frame;
                                ?>
                        <div class="dk:grid dk:gap-1 dk:py-2 dk:sm:grid-cols-[8rem_minmax(0,1fr)]">
                            <dt class="dk:min-w-0 dk:break-all dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h((string)($frame['function'] ?? '')) ?></dt>
                            <dd class="dk:min-w-0">
                                <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                    'file' => (string)($frame['file'] ?? ''),
                                    'line' => (int)($frame['line'] ?? 0),
                                ]) ?>
                            </dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <?php
    $detailBody = (string)ob_get_clean();
        ?>
    <?= $this->element('Crustum/DebugBar.ui/inspector_workspace', [
        'mode' => 'split',
        'frame' => 'top',
        'list' => $this->element('Crustum/DebugBar.ui/inspector_list_panel', [
            'controls' => $controls,
            'body' => $listBody,
        ]),
        'detail' => $detailBody,
    ]) ?>
    <?php endif; ?>
</div>
