@php($nonce = \Ahs12\Setanjo\Dashboard\Assets::nonce())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Setanjo · {{ config('app.name') }}</title>
    <style @if($nonce) nonce="{{ $nonce }}" @endif>{!! \Ahs12\Setanjo\Dashboard\Assets::css() !!}</style>
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<script id="setanjo-config" type="application/json">@json($config)</script>

@verbatim
<div x-data="setanjo" x-cloak class="flex min-h-screen flex-col">
    <!-- Header -->
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="#/settings" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="grid size-7 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">S</span>
                <span>Setanjo</span>
            </a>
            <nav class="-mx-1 flex flex-1 flex-wrap gap-1" aria-label="Sections">
                <a href="#/settings" class="s-tab" :class="{ 's-tab-active': view === 'settings' }">Settings</a>
                <a href="#/flags" class="s-tab" :class="{ 's-tab-active': view === 'flags' }">Feature flags</a>
                <a href="#/overrides" class="s-tab" :class="{ 's-tab-active': view === 'overrides' }">Overrides</a>
                <a href="#/groups" class="s-tab" :class="{ 's-tab-active': view === 'groups' }">Groups</a>
                <a href="#/drift" class="s-tab" :class="{ 's-tab-active': view === 'drift' }">Drift</a>
                <a href="#/history" class="s-tab" :class="{ 's-tab-active': view === 'history' }">History</a>
                <a href="#/transfer" class="s-tab" :class="{ 's-tab-active': view === 'transfer' }">Import / export</a>
            </nav>
            <div class="flex items-center gap-2 text-sm">
                <span x-show="!$store.s.canManage" class="s-badge bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Read-only</span>
                <span x-show="$store.s.config.user" x-text="$store.s.config.user" class="hidden text-zinc-500 sm:inline"></span>
                <button type="button" class="s-btn px-2" @click="toggleTheme()" :aria-label="dark ? 'Use light theme' : 'Use dark theme'">
                    <svg x-show="!dark" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    <svg x-show="dark" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">

        <!-- ================================================================ Settings -->
        <template x-if="view === 'settings'">
            <section x-data="settingsView" class="space-y-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-56 flex-1">
                        <label class="s-label" for="settings-search">Search</label>
                        <input id="settings-search" type="search" class="s-input" placeholder="Key, label, description or tag" x-model.debounce.150ms="search">
                    </div>
                    <div>
                        <label class="s-label" for="settings-group">Group</label>
                        <select id="settings-group" class="s-input" x-model="group">
                            <option value="">All groups</option>
                            <template x-for="g in $store.s.groups" :key="g.id"><option :value="String(g.id)" x-text="g.name"></option></template>
                            <option value="null">Ungrouped</option>
                        </select>
                    </div>
                    <div>
                        <label class="s-label" for="settings-type">Type</label>
                        <select id="settings-type" class="s-input" x-model="type">
                            <option value="">All types</option>
                            <template x-for="t in types" :key="t"><option :value="t" x-text="t"></option></template>
                        </select>
                    </div>
                </div>

                <p x-show="loading" class="text-sm text-zinc-500">Loading settings…</p>
                <div x-show="!loading && rows.length === 0" class="s-card p-8 text-center text-sm text-zinc-500">
                    No settings yet. Register definitions in <code class="font-mono">config/setanjo.php</code> or call <code class="font-mono">Settings::set()</code>.
                </div>

                <div class="space-y-4" x-init="initSections($el)">
                    <template x-for="section in sections" :key="section.id ?? 'null'">
                        <div class="s-card" :data-key="section.id === null ? null : String(section.id)">
                            <div class="flex items-center gap-2 border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                                <span x-show="section.id !== null && $store.s.canManage && !filtering" class="s-handle s-group-handle" title="Drag to reorder groups">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                </span>
                                <h2 class="font-semibold" x-text="section.name"></h2>
                                <span class="s-badge" x-text="section.rows.length"></span>
                                <p class="truncate text-sm text-zinc-500" x-text="section.description"></p>
                            </div>
                            <ul class="min-h-10 divide-y divide-zinc-100 dark:divide-zinc-800" :data-group="section.id === null ? 'null' : String(section.id)" x-init="initRows($el)">
                                <li x-show="section.rows.length === 0" class="px-4 py-2.5 text-xs text-zinc-400">Empty. Drag settings here.</li>
                                <template x-for="row in section.rows" :key="row.key">
                                    <li :data-key="row.key" class="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                                        <div class="flex min-w-0 gap-2">
                                            <span x-show="$store.s.canManage && !filtering" class="s-handle mt-0.5" title="Drag to reorder or move to another group">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="font-medium" x-text="row.label"></span>
                                                    <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                                </div>
                                                <p class="mt-0.5 text-sm text-zinc-500" x-show="row.description || row.meta.help" x-text="row.description || row.meta.help"></p>
                                                <div class="mt-1.5 flex flex-wrap gap-1">
                                                    <span class="s-badge" x-text="row.type"></span>
                                                    <span class="s-badge" x-show="row.meta.unit" x-text="row.meta.unit"></span>
                                                    <span class="s-badge" x-show="!row.defined" title="Stored in the database without a definition">undefined</span>
                                                    <span class="s-badge" x-show="row.source === 'default'">default</span>
                                                    <span class="s-badge bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300" x-show="row.is_locked">locked</span>
                                                    <span class="s-badge" x-show="!row.is_locked && row.readonly">read-only</span>
                                                    <span class="s-badge bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300" x-show="row.sensitive">sensitive</span>
                                                    <a class="s-badge bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300" x-show="row.override_count > 0" href="#/overrides" x-text="row.override_count + (row.override_count === 1 ? ' override' : ' overrides')"></a>
                                                    <template x-for="tag in (row.meta.tags || [])" :key="tag"><span class="s-badge" x-text="'#' + tag"></span></template>
                                                </div>
                                            </div>
                                        </div>

                                        <div x-data="valueEditor(() => row, 'value', save(row), remove(row))" class="space-y-1.5">
                                            <div class="flex items-start gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <template x-if="kind === 'toggle'">
                                                        <button type="button" role="switch" :aria-checked="draft" :disabled="disabled" @click="toggle()"
                                                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50"
                                                                :class="draft ? 'bg-indigo-600' : 'bg-zinc-300 dark:bg-zinc-700'">
                                                            <span class="inline-block size-5 rounded-full bg-white shadow transition" :class="draft ? 'translate-x-5.5' : 'translate-x-0.5'"></span>
                                                        </button>
                                                    </template>
                                                    <template x-if="kind === 'number'">
                                                        <div class="flex items-center gap-2">
                                                            <input type="number" class="s-input" x-model="draft" :min="row.meta.min" :max="row.meta.max" :step="row.meta.step ?? (row.type === 'integer' ? 1 : 'any')" :disabled="disabled" @keydown.enter="submit()">
                                                            <span class="shrink-0 text-sm text-zinc-500" x-show="row.meta.unit" x-text="row.meta.unit"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="kind === 'select'">
                                                        <select class="s-input" x-model="draft" :disabled="disabled">
                                                            <template x-if="draft === ''"><option value="">—</option></template>
                                                            <template x-for="option in editor.options(row)" :key="option.value"><option :value="option.value" :selected="option.value === draft" x-text="option.label"></option></template>
                                                        </select>
                                                    </template>
                                                    <template x-if="kind === 'json' || kind === 'textarea'">
                                                        <textarea rows="3" class="s-input font-mono text-xs" x-model="draft" :disabled="disabled" :placeholder="row.sensitive ? 'Hidden — type to replace' : (kind === 'json' ? 'JSON' : '')"></textarea>
                                                    </template>
                                                    <template x-if="['text', 'color', 'date', 'email', 'url', 'password'].includes(kind)">
                                                        <input :type="row.sensitive ? 'password' : (kind === 'color' && draft === '' ? 'text' : kind)" class="s-input" x-model="draft" :disabled="disabled" :placeholder="row.sensitive ? 'Hidden — type to replace' : (row.meta.placeholder ?? '')" @keydown.enter="submit()">
                                                    </template>
                                                </div>
                                                <button type="button" class="s-btn-primary" x-show="dirty && kind !== 'toggle'" :disabled="saving" @click="submit()">Save</button>
                                            </div>
                                            <p class="text-xs text-red-600 dark:text-red-400" x-show="error" x-text="error"></p>
                                            <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                                                <span x-show="row.defined && !row.sensitive">Default: <span class="font-mono" x-text="editor.display(row, row.default)"></span></span>
                                                <button type="button" class="underline-offset-2 hover:underline disabled:opacity-50" x-show="row.stored && row.defined && $store.s.canManage && !row.readonly" :disabled="saving" @click="clear()">Reset to default</button>
                                                <button type="button" class="underline-offset-2 hover:underline" @click="editMeta(row)">Meta</button>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </section>
        </template>

        <!-- ================================================================ Feature flags -->
        <template x-if="view === 'flags'">
            <section x-data="flagsView" class="space-y-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-56 flex-1">
                        <label class="s-label" for="flags-search">Search</label>
                        <input id="flags-search" type="search" class="s-input" placeholder="Key, label or description" x-model.debounce.150ms="search">
                    </div>
                    <div>
                        <label class="s-label" for="flags-status">Status</label>
                        <select id="flags-status" class="s-input" x-model="status">
                            <option value="">All</option>
                            <option value="on">On</option>
                            <option value="off">Off</option>
                            <option value="overridden">With overrides</option>
                        </select>
                    </div>
                </div>

                <p x-show="loading" class="text-sm text-zinc-500">Loading feature flags…</p>
                <div x-show="!loading && rows.length === 0" class="s-card p-8 text-center text-sm text-zinc-500">
                    No feature flags. Define one with <code class="font-mono">'feature' => true</code> or <code class="font-mono">isFeature()</code>.
                </div>

                <template x-for="section in sections" :key="section.id ?? 'null'">
                    <div class="s-card">
                        <div class="flex items-center gap-2 border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                            <h2 class="font-semibold" x-text="section.name"></h2>
                            <span class="s-badge" x-text="section.rows.length"></span>
                        </div>
                        <ul class="min-h-10 divide-y divide-zinc-100 dark:divide-zinc-800" :data-group="section.id === null ? 'null' : String(section.id)" x-init="initRows($el)">
                            <li x-show="section.rows.length === 0" class="px-4 py-2.5 text-xs text-zinc-400">Empty. Drag flags here.</li>
                            <template x-for="row in section.rows" :key="row.key">
                                <li :data-key="row.key" class="flex flex-wrap items-center gap-3 px-4 py-3">
                                    <span x-show="$store.s.canManage" class="s-handle" title="Drag to reorder or move to another group">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                    </span>
                                    <span class="size-2.5 shrink-0 rounded-full" :class="row.enabled ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600'" :title="row.enabled ? 'Enabled' : 'Disabled'"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="font-medium" x-text="row.label"></span>
                                            <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                            <span class="s-badge" x-show="!row.defined">undefined</span>
                                            <a class="s-badge bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300" x-show="row.override_count > 0" href="#/overrides" x-text="row.override_count + (row.override_count === 1 ? ' override' : ' overrides')"></a>
                                        </div>
                                        <p class="mt-0.5 text-sm text-zinc-500" x-show="row.description" x-text="row.description"></p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group" :aria-label="'Global state of ' + row.label">
                                            <button type="button" class="s-seg" :disabled="!$store.s.canManage || busy === row.key" :class="{ 'bg-zinc-900 text-white hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-100': row.global === null }" @click="set(row, null)" x-text="'Default (' + (row.default ? 'on' : 'off') + ')'"></button>
                                            <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === row.key" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': row.global === true }" @click="set(row, true)">On</button>
                                            <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === row.key" :class="{ 'bg-red-600 text-white hover:bg-red-600': row.global === false }" @click="set(row, false)">Off</button>
                                        </div>
                                        <button type="button" class="text-xs text-zinc-500 underline-offset-2 hover:underline" @click="editMeta(row)">Meta</button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </section>
        </template>

        <!-- ================================================================ Overrides -->
        <template x-if="view === 'overrides'">
            <section x-data="overridesView" class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                <div class="s-card h-fit space-y-3 p-4">
                    <template x-if="$store.s.config.scopes.length === 0">
                        <p class="text-sm text-zinc-500">No scope types configured. Add models to <code class="font-mono">setanjo.scopes</code>.</p>
                    </template>
                    <template x-if="$store.s.config.scopes.length > 0">
                        <div class="space-y-3">
                            <div>
                                <label class="s-label" for="scope-type">Scope type</label>
                                <select id="scope-type" class="s-input" x-model="type" @change="changeType()">
                                    <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" x-text="scopeType.name"></option></template>
                                </select>
                            </div>
                            <div>
                                <label class="s-label" for="scope-search">Find a record</label>
                                <input id="scope-search" type="search" class="s-input" placeholder="Search, or paste an id" x-model="search" @input="searchLater()">
                            </div>
                            <p class="text-xs text-zinc-500" x-text="overriddenList ? 'Records with overrides' + (meta ? ' (' + meta.total + ')' : '') : 'Search results'"></p>
                            <p class="text-sm text-zinc-500" x-show="loadingRecords">Loading…</p>
                            <p class="text-sm text-zinc-500" x-show="!loadingRecords && records.length === 0" x-text="overriddenList ? 'No overrides yet. Search for a record to add one.' : 'No matches.'"></p>
                            <ul class="-mx-1 max-h-[60vh] space-y-0.5 overflow-y-auto">
                                <template x-for="record in records" :key="record.type + ':' + record.id">
                                    <li>
                                        <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                                :class="{ 'bg-indigo-50 dark:bg-indigo-950': selected && selected.id === record.id && selected.type === record.type }" @click="select(record)">
                                            <span class="flex flex-wrap items-center gap-1.5">
                                                <span class="font-medium" x-text="record.label"></span>
                                                <span class="s-badge bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300" x-show="stateText(record)" x-text="stateText(record)"></span>
                                            </span>
                                            <span class="block break-all text-xs text-zinc-500">
                                                <span x-text="'#' + record.id"></span>
                                                <span x-show="record.description" x-text="'· ' + record.description"></span>
                                                <span x-show="record.settings || record.features" x-text="'· ' + plural((record.settings || 0) + (record.features || 0), 'override')"></span>
                                            </span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <div class="flex items-center justify-between gap-2 text-xs text-zinc-500" x-show="overriddenList && meta && meta.last_page > 1">
                                <button type="button" class="s-btn px-2 py-1 text-xs" :disabled="loadingRecords || page <= 1" @click="goTo(page - 1)">Previous</button>
                                <span x-text="'Page ' + page + ' of ' + (meta ? meta.last_page : 1)"></span>
                                <button type="button" class="s-btn px-2 py-1 text-xs" :disabled="loadingRecords || !meta || page >= meta.last_page" @click="goTo(page + 1)">Next</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="space-y-4">
                    <div x-show="!selected" class="s-card p-8 text-center text-sm text-zinc-500">
                        Pick a record to see every setting and flag as it sees them, and where each value comes from.
                    </div>
                    <p x-show="loading" class="text-sm text-zinc-500">Loading…</p>

                    <template x-if="scope && !loading">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-lg font-semibold" x-text="scope.scope.label"></h2>
                                <span class="s-badge break-all" x-text="scope.scope.type + ' #' + scope.scope.id"></span>
                                <span class="s-badge bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300" x-show="stateText(scope.scope)" x-text="stateText(scope.scope)"></span>
                                <div class="ml-auto flex flex-wrap items-center gap-3">
                                    <button type="button" class="s-btn-danger" x-show="$store.s.canManage && overrideCount > 0" :disabled="clearing" @click="clearAll()">Clear all overrides</button>
                                    <input type="search" class="s-input w-48" placeholder="Filter" x-model.debounce.150ms="filter">
                                    <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="onlyOverridden"> Only overridden</label>
                                </div>
                            </div>
                            <div x-show="scope.scope.state === 'missing'" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                This record no longer exists. Only the overrides still stored for it are listed, and they can only be cleared.
                            </div>
                            <div x-show="scope.scope.trashed" class="rounded-lg border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                This record is soft-deleted. Its overrides still apply if it is restored.
                            </div>
                            <div class="flex gap-1">
                                <button type="button" class="s-tab" :class="{ 's-tab-active': tab === 'features' }" @click="tab = 'features'" x-text="'Feature flags (' + scope.features.length + ')'"></button>
                                <button type="button" class="s-tab" :class="{ 's-tab-active': tab === 'settings' }" @click="tab = 'settings'" x-text="'Settings (' + scope.settings.length + ')'"></button>
                            </div>

                            <div x-show="tab === 'features'" class="s-card divide-y divide-zinc-100 dark:divide-zinc-800">
                                <p x-show="features.length === 0" class="p-4 text-sm text-zinc-500">Nothing to show.</p>
                                <template x-for="row in features" :key="row.key">
                                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                        <span class="size-2.5 shrink-0 rounded-full" :class="row.effective ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600'"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="font-medium" x-text="row.label"></span>
                                                <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                            </div>
                                            <p class="text-xs text-zinc-500">
                                                <span x-text="row.effective ? 'On' : 'Off'"></span> · from <span x-text="sourceText(row)"></span>
                                                <span x-show="!row.allowed">· overrides not allowed for this scope type</span>
                                            </p>
                                        </div>
                                        <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group">
                                            <button type="button" class="s-seg" :disabled="!$store.s.canManage || !row.allowed || busy === row.key" :class="{ 'bg-zinc-900 text-white hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-100': row.override === null }" @click="setFlag(row, null)">Inherit</button>
                                            <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || !row.allowed || busy === row.key || row.missing" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': row.override === true }" @click="setFlag(row, true)">On</button>
                                            <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || !row.allowed || busy === row.key || row.missing" :class="{ 'bg-red-600 text-white hover:bg-red-600': row.override === false }" @click="setFlag(row, false)">Off</button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="tab === 'settings'" class="s-card divide-y divide-zinc-100 dark:divide-zinc-800">
                                <p x-show="settings.length === 0" class="p-4 text-sm text-zinc-500">Nothing to show.</p>
                                <template x-for="row in settings" :key="row.key">
                                    <div class="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="font-medium" x-text="row.label"></span>
                                                <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                                <span class="s-badge" x-text="row.type"></span>
                                                <span class="s-badge bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300" x-show="row.overridden">overridden</span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-zinc-500">
                                                Effective: <span class="font-mono" x-text="editor.display(row, row.effective)"></span> · from <span x-text="sourceText(row)"></span>
                                                <span x-show="!row.allowed">· overrides not allowed for this scope type</span>
                                            </p>
                                        </div>
                                        <div x-data="valueEditor(() => row, 'override', save(row), remove(row))" class="space-y-1.5">
                                            <div class="flex items-start gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <template x-if="kind === 'toggle'">
                                                        <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group">
                                                            <button type="button" class="s-seg" :disabled="disabled" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': row.overridden && row.override === true }" @click="draft = true; submit()">On</button>
                                                            <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="disabled" :class="{ 'bg-red-600 text-white hover:bg-red-600': row.overridden && row.override === false }" @click="draft = false; submit()">Off</button>
                                                        </div>
                                                    </template>
                                                    <template x-if="kind === 'number'">
                                                        <input type="number" class="s-input" x-model="draft" :min="row.meta.min" :max="row.meta.max" :step="row.meta.step ?? (row.type === 'integer' ? 1 : 'any')" :disabled="disabled" :placeholder="row.overridden ? '' : 'Inherited'" @keydown.enter="submit()">
                                                    </template>
                                                    <template x-if="kind === 'select'">
                                                        <select class="s-input" x-model="draft" :disabled="disabled" @change="submit()">
                                                            <template x-if="draft === ''"><option value="">Inherited</option></template>
                                                            <template x-for="option in editor.options(row)" :key="option.value"><option :value="option.value" :selected="option.value === draft" x-text="option.label"></option></template>
                                                        </select>
                                                    </template>
                                                    <template x-if="kind === 'json' || kind === 'textarea'">
                                                        <textarea rows="3" class="s-input font-mono text-xs" x-model="draft" :disabled="disabled" :placeholder="row.overridden ? '' : 'Inherited'"></textarea>
                                                    </template>
                                                    <template x-if="['text', 'color', 'date', 'email', 'url', 'password'].includes(kind)">
                                                        <input :type="row.sensitive ? 'password' : (kind === 'color' && draft === '' ? 'text' : kind)" class="s-input" x-model="draft" :disabled="disabled" :placeholder="row.overridden ? (row.sensitive ? 'Hidden — type to replace' : '') : 'Inherited'" @keydown.enter="submit()">
                                                    </template>
                                                </div>
                                                <button type="button" class="s-btn-primary" x-show="dirty && !['toggle', 'select'].includes(kind)" :disabled="saving" @click="submit()">Save</button>
                                            </div>
                                            <p class="text-xs text-red-600 dark:text-red-400" x-show="error" x-text="error"></p>
                                            <button type="button" class="text-xs text-zinc-500 underline-offset-2 hover:underline disabled:opacity-50" x-show="row.overridden && $store.s.canManage && !row.readonly" :disabled="saving" @click="clear()">Remove override (inherit)</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </template>

        <!-- ================================================================ Groups -->
        <template x-if="view === 'groups'">
            <section x-data="groupsView" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="s-card">
                    <div class="border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                        <h2 class="font-semibold">Groups</h2>
                        <p class="text-sm text-zinc-500">Drag to change the order used on the settings and flags screens.</p>
                    </div>
                    <p x-show="$store.s.groups.length === 0" class="p-4 text-sm text-zinc-500">No groups yet.</p>
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800" x-init="initList($el)">
                        <template x-for="group in $store.s.groups" :key="group.id">
                            <li :data-key="String(group.id)" class="px-4 py-3">
                                <template x-if="!editing || editing.id !== group.id">
                                    <div class="flex items-center gap-3">
                                        <span x-show="$store.s.canManage" class="s-handle" title="Drag to reorder">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="font-medium" x-text="group.name"></span>
                                                <code class="font-mono text-xs text-zinc-500" x-text="group.slug"></code>
                                                <span class="s-badge" x-show="group.icon" x-text="group.icon"></span>
                                            </div>
                                            <p class="text-sm text-zinc-500" x-show="group.description" x-text="group.description"></p>
                                        </div>
                                        <div class="flex gap-2" x-show="$store.s.canManage">
                                            <button type="button" class="s-btn" @click="edit(group)">Edit</button>
                                            <button type="button" class="s-btn-danger" @click="deleteGroup(group)">Delete</button>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="editing && editing.id === group.id">
                                    <form class="grid gap-2 sm:grid-cols-2" @submit.prevent="update()">
                                        <div><label class="s-label">Name</label><input class="s-input" x-model="editing.name" required></div>
                                        <div><label class="s-label">Slug</label><input class="s-input font-mono" x-model="editing.slug"></div>
                                        <div class="sm:col-span-2"><label class="s-label">Description</label><input class="s-input" x-model="editing.description"></div>
                                        <div><label class="s-label">Icon</label><input class="s-input" x-model="editing.icon"></div>
                                        <div class="flex items-end justify-end gap-2">
                                            <button type="button" class="s-btn" @click="editing = null">Cancel</button>
                                            <button type="submit" class="s-btn-primary" :disabled="saving">Save</button>
                                        </div>
                                    </form>
                                </template>
                            </li>
                        </template>
                    </ul>
                </div>

                <form x-show="$store.s.canManage" class="s-card h-fit space-y-3 p-4" @submit.prevent="create()">
                    <h2 class="font-semibold">New group</h2>
                    <div>
                        <label class="s-label" for="group-name">Name</label>
                        <input id="group-name" class="s-input" x-model="form.name" required>
                        <p class="mt-1 text-xs text-red-600" x-show="errors.name" x-text="errors.name && errors.name[0]"></p>
                    </div>
                    <div>
                        <label class="s-label" for="group-slug">Slug <span class="font-normal">(optional)</span></label>
                        <input id="group-slug" class="s-input font-mono" x-model="form.slug" placeholder="from the name">
                    </div>
                    <div>
                        <label class="s-label" for="group-description">Description</label>
                        <input id="group-description" class="s-input" x-model="form.description">
                    </div>
                    <div>
                        <label class="s-label" for="group-icon">Icon</label>
                        <input id="group-icon" class="s-input" x-model="form.icon" placeholder="e.g. cog">
                    </div>
                    <button type="submit" class="s-btn-primary w-full" :disabled="saving">Create group</button>
                </form>
            </section>
        </template>

        <!-- ================================================================ Drift -->
        <template x-if="view === 'drift'">
            <section x-data="driftView" class="space-y-4">
                <p x-show="!report" class="text-sm text-zinc-500">Checking…</p>
                <template x-if="report">
                    <div class="space-y-4">
                        <div class="s-card p-4">
                            <h2 class="font-semibold">Definitions vs database</h2>
                            <p class="text-sm text-zinc-500" x-text="report.definitions + ' definitions registered.'"></p>
                            <p class="mt-2 text-sm text-emerald-700 dark:text-emerald-400" x-show="clean">Everything matches. Nothing to sync.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <template x-for="item in [
                                { key: 'missing', title: 'Missing settings', help: 'Defined in code, no global row yet. Sync creates them with the default.', list: report.missing.map(i => i.key) },
                                { key: 'missing_features', title: 'Missing flags', help: 'Defined as features, no flag row yet.', list: report.missing_features.map(i => i.key) },
                                { key: 'type_mismatch', title: 'Type mismatches', help: 'Stored with a different type. Sync with “Convert types”.', list: report.type_mismatch.map(i => i.key + ': ' + i.stored + ' → ' + i.defined) },
                                { key: 'stale', title: 'Stale settings', help: 'Stored without a definition. “Prune” deletes them in every scope.', list: report.stale },
                                { key: 'stale_features', title: 'Stale flags', help: 'Flag rows without a feature definition. “Prune” deletes them.', list: report.stale_features },
                                { key: 'flags_in_settings', title: 'Flags still in settings', help: 'Run php artisan setanjo:migrate-feature-flags to move them.', list: report.flags_in_settings },
                                { key: 'missing_records', title: 'Overrides of deleted records', help: 'The record is gone but its overrides are stored. Open it on the Overrides screen to clear them.', list: report.missing_records.map(i => i.label + ': ' + (i.settings + i.features) + ((i.settings + i.features) === 1 ? ' override' : ' overrides')) },
                                { key: 'unknown_scope_types', title: 'Unknown scope types', help: 'Overrides stored for models not in setanjo.scopes (or the tenancy config). Add the model to see them here.', list: report.unknown_scope_types.map(i => i.class + ': ' + i.records + ' records') },
                            ]" :key="item.key">
                                <div class="s-card p-4">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-medium" x-text="item.title"></h3>
                                        <span class="s-badge" :class="{ 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': item.list.length }" x-text="item.list.length"></span>
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500" x-text="item.help"></p>
                                    <ul class="mt-2 max-h-40 space-y-0.5 overflow-y-auto font-mono text-xs">
                                        <template x-for="entry in item.list" :key="entry"><li x-text="entry"></li></template>
                                    </ul>
                                </div>
                            </template>
                        </div>

                        <div class="s-card space-y-3 p-4" x-show="$store.s.canManage">
                            <h2 class="font-semibold">Sync</h2>
                            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="options.prune"> Prune stale keys</label>
                                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="options.force_types"> Convert types</label>
                                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="options.reset_meta"> Reset stored meta</label>
                                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="options.reset_groups"> Reset groups to code</label>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="s-btn" :disabled="running" @click="run(true)">Preview</button>
                                <button type="button" class="s-btn-primary" :disabled="running || !previewed" @click="run(false)" :title="previewed ? '' : 'Preview first'">Run sync</button>
                            </div>
                            <pre x-show="output" class="max-h-80 overflow-auto rounded-lg bg-zinc-950 p-3 font-mono text-xs text-zinc-100" x-text="output"></pre>
                        </div>
                    </div>
                </template>
            </section>
        </template>

        <!-- ================================================================ History -->
        <template x-if="view === 'history'">
            <section x-data="historyView" class="space-y-4">
                <template x-if="!enabled">
                    <div class="s-card p-8 text-center text-sm text-zinc-500" x-text="message"></div>
                </template>
                <template x-if="enabled">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-48 flex-1">
                                <label class="s-label" for="history-key">Key</label>
                                <input id="history-key" class="s-input font-mono" x-model="filters.key" @input="filterLater()">
                            </div>
                            <div>
                                <label class="s-label" for="history-action">Action</label>
                                <select id="history-action" class="s-input" x-model="filters.action" @change="filterLater()">
                                    <option value="">All</option>
                                    <option value="set">Set</option>
                                    <option value="forget">Forget</option>
                                    <option value="meta">Meta</option>
                                    <option value="toggle">Toggle</option>
                                </select>
                            </div>
                            <div>
                                <label class="s-label" for="history-scope">Scope type</label>
                                <select id="history-scope" class="s-input" x-model="filters.scope_type" @change="filterLater()">
                                    <option value="">Any</option>
                                    <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" x-text="scopeType.name"></option></template>
                                </select>
                            </div>
                            <div class="w-28">
                                <label class="s-label" for="history-scope-id">Scope id</label>
                                <input id="history-scope-id" class="s-input" x-model="filters.scope_id" @input="filterLater()">
                            </div>
                        </div>

                        <div class="s-card overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800">
                                    <tr><th class="px-4 py-2 font-medium">When</th><th class="px-4 py-2 font-medium">Key</th><th class="px-4 py-2 font-medium">Action</th><th class="px-4 py-2 font-medium">Scope</th><th class="px-4 py-2 font-medium">Change</th><th class="px-4 py-2 font-medium">By</th></tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    <tr x-show="!loading && entries.length === 0"><td colspan="6" class="px-4 py-6 text-center text-zinc-500">No changes recorded.</td></tr>
                                    <template x-for="entry in entries" :key="entry.id">
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-2 text-zinc-500" x-text="when(entry.created_at)"></td>
                                            <td class="px-4 py-2 font-mono text-xs" x-text="entry.key"></td>
                                            <td class="px-4 py-2"><span class="s-badge" x-text="entry.action"></span></td>
                                            <td class="px-4 py-2" x-text="entry.scope ? entry.scope.label : 'global'"></td>
                                            <td class="max-w-md truncate px-4 py-2 font-mono text-xs" :title="change(entry)" x-text="change(entry)"></td>
                                            <td class="px-4 py-2" x-text="entry.causer ? entry.causer.label : '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex items-center justify-between text-sm" x-show="meta && meta.last_page > 1">
                            <span class="text-zinc-500" x-text="meta ? meta.total + ' changes' : ''"></span>
                            <div class="flex gap-2">
                                <button type="button" class="s-btn" :disabled="page <= 1" @click="go(page - 1)">Previous</button>
                                <span class="self-center" x-text="meta ? page + ' / ' + meta.last_page : ''"></span>
                                <button type="button" class="s-btn" :disabled="meta && page >= meta.last_page" @click="go(page + 1)">Next</button>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </template>

        <!-- ================================================================ Import / export -->
        <template x-if="view === 'transfer'">
            <section x-data="transferView" class="grid gap-4 md:grid-cols-2">
                <div class="s-card space-y-3 p-4">
                    <h2 class="font-semibold">Export</h2>
                    <p class="text-sm text-zinc-500">Settings, meta, groups and feature flags as JSON, the same format as <code class="font-mono">setanjo:export</code>.</p>
                    <div class="flex flex-wrap gap-2">
                        <a class="s-btn-primary" :href="exportUrl(false)">Export everything</a>
                        <a class="s-btn" :href="exportUrl(true)">Global settings only</a>
                    </div>
                </div>
                <div class="s-card space-y-3 p-4" x-show="$store.s.canManage">
                    <h2 class="font-semibold">Import</h2>
                    <p class="text-sm text-zinc-500">Existing keys are updated, new keys are added. Check the file first.</p>
                    <input type="file" accept="application/json,.json" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:file:bg-zinc-800 dark:file:text-zinc-200" @change="pick($event)">
                    <div class="flex gap-2">
                        <button type="button" class="s-btn" :disabled="!file || running" @click="run(true)">Check file</button>
                        <button type="button" class="s-btn-primary" :disabled="!file || !checked || running" @click="run(false)">Import</button>
                    </div>
                    <pre x-show="output" class="overflow-auto rounded-lg bg-zinc-950 p-3 font-mono text-xs text-zinc-100" x-text="output"></pre>
                </div>
            </section>
        </template>
    </main>

    <!-- ================================================================ Meta panel -->
    <div x-data="metaPanel" x-show="m.open" class="fixed inset-0 z-40" @keydown.escape.window="close()">
        <div class="absolute inset-0 bg-zinc-950/40" @click="close()"></div>
        <aside class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-white shadow-xl dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="meta-title">
            <template x-if="m.row">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-start gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div class="min-w-0 flex-1">
                            <h2 id="meta-title" class="font-semibold" x-text="'Meta · ' + m.row.label"></h2>
                            <code class="font-mono text-xs text-zinc-500" x-text="m.row.key"></code>
                        </div>
                        <button type="button" class="s-btn px-2" @click="close()" aria-label="Close">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                        <div>
                            <h3 class="text-sm font-medium">From code</h3>
                            <p class="text-xs text-zinc-500">Declared in the definition. Stored meta with the same key wins.</p>
                            <p class="mt-2 text-sm text-zinc-500" x-show="definitionMeta().length === 0">None.</p>
                            <dl class="mt-2 space-y-1 text-sm">
                                <template x-for="item in definitionMeta()" :key="item.key">
                                    <div class="flex gap-2" :class="{ 'line-through opacity-60': overridden(item.key) }">
                                        <dt class="w-28 shrink-0 font-mono text-xs text-zinc-500" x-text="item.key"></dt>
                                        <dd class="min-w-0 break-all font-mono text-xs" x-text="item.value"></dd>
                                    </div>
                                </template>
                            </dl>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="flex-1 text-sm font-medium">Stored in the database</h3>
                                <button type="button" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400" @click="toggleRaw()" x-text="m.raw ? 'Key / value' : 'Raw JSON'"></button>
                            </div>
                            <p class="text-xs text-zinc-500">Values are read as JSON when they parse (<code>30</code>, <code>true</code>, <code>["a"]</code>), as text otherwise.</p>
                            <template x-if="!m.raw">
                                <div class="mt-2 space-y-2">
                                    <template x-for="(pair, index) in m.pairs" :key="index">
                                        <div class="flex gap-2">
                                            <input class="s-input w-36 font-mono text-xs" placeholder="key" x-model="pair.key" :disabled="!$store.s.canManage">
                                            <input class="s-input font-mono text-xs" placeholder="value" x-model="pair.value" :disabled="!$store.s.canManage">
                                            <button type="button" class="s-btn px-2" x-show="$store.s.canManage" @click="remove(index)" aria-label="Remove">×</button>
                                        </div>
                                    </template>
                                    <button type="button" class="s-btn" x-show="$store.s.canManage" @click="add()">Add item</button>
                                </div>
                            </template>
                            <template x-if="m.raw">
                                <textarea rows="12" class="s-input mt-2 font-mono text-xs" x-model="m.rawText" :disabled="!$store.s.canManage"></textarea>
                            </template>
                        </div>
                        <p class="text-sm text-red-600 dark:text-red-400" x-show="m.error" x-text="m.error"></p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-800">
                        <button type="button" class="s-btn" @click="close()">Cancel</button>
                        <button type="button" class="s-btn-primary" x-show="$store.s.canManage" :disabled="m.saving" @click="save()">Save meta</button>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    <!-- ================================================================ Confirm dialog -->
    <div x-show="$store.s.dialog.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="$store.s.dialog.open && $store.s.closeDialog(false)"
         x-effect="$store.s.dialog.open && setTimeout(() => $refs.dialogCancel.focus(), 20)">
        <div class="absolute inset-0 bg-zinc-950/50" @click="$store.s.closeDialog(false)"></div>
        <div class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
             role="alertdialog" aria-modal="true" aria-labelledby="dialog-title" aria-describedby="dialog-message">
            <div class="flex gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full"
                     :class="$store.s.dialog.danger ? 'bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400'">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 id="dialog-title" class="font-semibold" x-text="$store.s.dialog.title"></h2>
                    <p id="dialog-message" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400" x-show="$store.s.dialog.message" x-text="$store.s.dialog.message"></p>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="s-btn" x-ref="dialogCancel" @click="$store.s.closeDialog(false)">Cancel</button>
                <button type="button" :class="$store.s.dialog.danger ? 's-btn-danger-solid' : 's-btn-primary'" @click="$store.s.closeDialog(true)" x-text="$store.s.dialog.confirmLabel"></button>
            </div>
        </div>
    </div>

    <!-- ================================================================ Toasts -->
    <div class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 flex-col gap-2" aria-live="polite">
        <template x-for="toast in $store.s.toasts" :key="toast.id">
            <div class="pointer-events-auto rounded-lg px-4 py-2.5 text-sm shadow-lg"
                 :class="toast.kind === 'error' ? 'bg-red-600 text-white' : 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'" x-text="toast.message"></div>
        </template>
    </div>
</div>
@endverbatim

<script @if($nonce) nonce="{{ $nonce }}" @endif>{!! \Ahs12\Setanjo\Dashboard\Assets::js() !!}</script>
</body>
</html>
