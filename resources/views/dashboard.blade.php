@php($nonce = \Ahs12\Setanjo\Dashboard\Assets::nonce())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $config['brand']['title'] }} · {{ config('app.name') }}</title>
    <style @if($nonce) nonce="{{ $nonce }}" @endif>{!! \Ahs12\Setanjo\Dashboard\Assets::css() !!}@if($accent):root{--s-accent:{{ $accent }}}@endif</style>
    <script @if($nonce) nonce="{{ $nonce }}" @endif>
        try {
            const theme = localStorage.getItem('setanjo.theme');
            if (theme === 'dark' || (theme !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<script id="setanjo-config" type="application/json">@json($config)</script>

@verbatim
<div x-data="setanjo" x-cloak class="flex min-h-screen flex-col" @keydown.window="onKey($event)">

    <!-- ================================================================ Top navigation -->
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90">
        <div class="mx-auto flex h-14 w-full max-w-7xl items-center gap-3 px-4 sm:px-6">
            <a href="#/overview" class="flex shrink-0 items-center gap-2 font-semibold tracking-tight">
                <template x-if="$store.s.config.brand.logo">
                    <img :src="$store.s.config.brand.logo" alt="" class="size-7 rounded-lg object-contain">
                </template>
                <template x-if="!$store.s.config.brand.logo">
                    <span class="grid size-7 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white" x-text="$store.s.config.brand.title.slice(0, 1).toUpperCase()"></span>
                </template>
                <span class="max-w-40 truncate" x-text="$store.s.config.brand.title"></span>
            </a>

            <nav class="s-scroll-x hidden min-w-0 flex-1 items-center gap-0.5 overflow-x-auto lg:flex" aria-label="Sections">
                <template x-for="item in $store.s.nav" :key="item.view">
                    <a :href="'#/' + item.view" class="s-tab flex shrink-0 items-center gap-1.5 px-2.5 whitespace-nowrap" :class="$store.s.route.view === item.view && 's-tab-active'"
                       :aria-current="$store.s.route.view === item.view ? 'page' : null" :title="'g ' + item.key">
                        <span x-text="item.label"></span>
                        <span x-show="item.view === 'drift' && $store.s.driftCount > 0" class="s-badge s-badge-warn px-1 py-0" x-text="$store.s.driftCount"></span>
                    </a>
                </template>
            </nav>
            <span class="flex-1 lg:hidden"></span>

            <div class="flex shrink-0 items-center gap-1">
                <span x-show="!$store.s.canManage" class="s-badge s-badge-warn hidden sm:inline-flex">Read-only</span>
                <button type="button" @click="$store.s.palette = true" :title="'Search (' + $store.s.mod + ' K)'"
                        class="hidden items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-sm text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-700 xl:flex dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-zinc-700 dark:hover:text-zinc-300">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.search"></svg>
                    <span>Search</span>
                    <span class="s-kbd" x-text="$store.s.mod + ' K'"></span>
                </button>
                <button type="button" class="s-btn-ghost xl:hidden" @click="$store.s.palette = true" :aria-label="'Search (' + $store.s.mod + ' K)'">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.search"></svg>
                </button>
                <button type="button" class="s-btn-ghost" @click="$store.s.cycleTheme()" :title="'Theme: ' + $store.s.theme" :aria-label="'Theme: ' + $store.s.theme">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                         x-html="$store.s.icons[{ system: 'monitor', light: 'sun', dark: 'moon' }[$store.s.theme]]"></svg>
                </button>
                <button type="button" class="s-btn-ghost hidden sm:inline-flex" @click="$store.s.shortcuts = true" title="Keyboard shortcuts (?)" aria-label="Keyboard shortcuts">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="$store.s.icons.keyboard"></svg>
                </button>
                <a x-show="$store.s.config.home" :href="$store.s.config.home" class="s-btn-ghost hidden sm:inline-flex" :title="'Back to the app' + ($store.s.config.user ? ' (signed in as ' + $store.s.config.user + ')' : '')">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="$store.s.icons.back"></svg>
                    <span class="hidden 2xl:inline">Back to app</span>
                </a>
                <button type="button" class="s-btn-ghost lg:hidden" @click="$store.s.navOpen = !$store.s.navOpen" :aria-expanded="$store.s.navOpen" aria-label="Menu">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons[$store.s.navOpen ? 'close' : 'menu']"></svg>
                </button>
            </div>
        </div>

        <!-- Menu below lg -->
        <nav x-show="$store.s.navOpen" x-transition.origin.top class="border-t border-zinc-200 px-4 py-3 lg:hidden dark:border-zinc-800" aria-label="Sections">
            <div class="grid grid-cols-2 gap-1 sm:grid-cols-4">
                <template x-for="item in $store.s.nav" :key="item.view">
                    <a :href="'#/' + item.view" class="s-nav" :class="$store.s.route.view === item.view && 's-nav-active'">
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="$store.s.icons[item.icon]"></svg>
                        <span class="flex-1 truncate" x-text="item.label"></span>
                        <span x-show="item.view === 'drift' && $store.s.driftCount > 0" class="s-badge s-badge-warn" x-text="$store.s.driftCount"></span>
                    </a>
                </template>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-zinc-200 pt-2 text-xs text-zinc-500 dark:border-zinc-800">
                <span x-show="$store.s.config.user" x-text="'Signed in as ' + $store.s.config.user"></span>
                <span x-show="!$store.s.canManage" class="s-badge s-badge-warn">Read-only</span>
                <a x-show="$store.s.config.home" :href="$store.s.config.home" class="s-link">Back to app</a>
            </div>
        </nav>
    </header>

    <!-- ================================================================ Main -->
    <main class="min-w-0 flex-1">
        <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight" x-text="page.label"></h1>
                <p class="mt-0.5 text-sm text-zinc-500" x-text="page.description"></p>
            </div>

            <!-- ============================================================ Overview -->
            <template x-if="$store.s.route.view === 'overview'">
                <section x-data="overviewView" class="space-y-6">
                    <template x-if="failed">
                        <div class="s-card flex flex-wrap items-center gap-3 p-6 text-sm">
                            <span class="text-red-600 dark:text-red-400" x-text="failed"></span>
                            <button type="button" class="s-btn ml-auto" @click="load()">Try again</button>
                        </div>
                    </template>

                    <template x-if="!data && !failed">
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <template x-for="i in 4" :key="i">
                                <div class="s-card space-y-3 p-4"><div class="s-skeleton h-3 w-20"></div><div class="s-skeleton h-7 w-12"></div><div class="s-skeleton h-3 w-32"></div></div>
                            </template>
                        </div>
                    </template>

                    <template x-if="data">
                        <div class="space-y-6">
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <a href="#/settings" class="s-card group block p-4 transition hover:border-brand-300 dark:hover:border-brand-800">
                                    <p class="s-section-title">Settings</p>
                                    <p class="s-stat mt-1" x-text="data.settings.total"></p>
                                    <p class="mt-1 text-xs text-zinc-500" x-text="data.settings.stored + ' stored · ' + data.settings.overridden + ' overridden' + (data.settings.readonly ? ' · ' + data.settings.readonly + ' read-only' : '')"></p>
                                </a>
                                <a href="#/flags" class="s-card block p-4 transition hover:border-brand-300 dark:hover:border-brand-800">
                                    <p class="s-section-title">Feature flags</p>
                                    <p class="s-stat mt-1" x-text="data.features.total"></p>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-emerald-500" :style="'width:' + percent(data.features.on, data.features.total) + '%'"></div>
                                    </div>
                                    <p class="mt-1.5 text-xs text-zinc-500" x-text="data.features.on + ' on · ' + data.features.off + ' off' + (data.features.overridden ? ' · ' + data.features.overridden + ' targeted' : '')"></p>
                                </a>
                                <a href="#/overrides" class="s-card block p-4 transition hover:border-brand-300 dark:hover:border-brand-800">
                                    <p class="s-section-title">Overrides</p>
                                    <p class="s-stat mt-1" x-text="overrideTotal"></p>
                                    <p class="mt-1 text-xs text-zinc-500" x-text="data.scopes.length ? 'across ' + data.scopes.reduce((sum, type) => sum + type.records, 0) + ' records' : 'No record has its own value yet'"></p>
                                </a>
                                <a href="#/drift" class="s-card block p-4 transition hover:border-brand-300 dark:hover:border-brand-800">
                                    <p class="s-section-title">Drift</p>
                                    <template x-if="$store.s.driftCount === null"><div class="s-skeleton mt-2 h-7 w-10"></div></template>
                                    <template x-if="$store.s.driftCount !== null">
                                        <p class="s-stat mt-1" :class="$store.s.driftCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'" x-text="$store.s.driftCount"></p>
                                    </template>
                                    <p class="mt-1 text-xs text-zinc-500" x-text="$store.s.driftCount === 0 ? 'Code and database match' : 'Differences to review'"></p>
                                </a>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-3">
                                <div class="s-card lg:col-span-2">
                                    <div class="flex items-center gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                                        <h2 class="flex-1 font-semibold">Recent changes</h2>
                                        <a href="#/history" class="s-link text-sm" x-show="$store.s.config.history">All history</a>
                                    </div>
                                    <template x-if="!$store.s.config.history">
                                        <div class="p-6 text-sm text-zinc-500">
                                            History is off. Set <code class="font-mono">SETANJO_HISTORY_ENABLED=true</code> (or <code class="font-mono">setanjo.history.enabled</code>) to record who changed what, and to revert changes from here.
                                        </div>
                                    </template>
                                    <template x-if="$store.s.config.history && history.length === 0">
                                        <p class="p-6 text-sm text-zinc-500">No changes recorded yet.</p>
                                    </template>
                                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        <template x-for="entry in history" :key="entry.id">
                                            <li class="flex items-center gap-3 px-4 py-2.5 text-sm">
                                                <span class="size-2 shrink-0 rounded-full" :class="{ set: 'bg-brand-500', toggle: 'bg-emerald-500', forget: 'bg-red-500', meta: 'bg-zinc-400', group: 'bg-zinc-400' }[entry.action]"></span>
                                                <button type="button" class="truncate font-mono text-xs font-medium hover:underline" x-text="entry.key"
                                                        @click="$store.s.openKey(entry.kind === 'feature' ? 'feature' : 'setting', entry.key)"></button>
                                                <span class="s-badge" x-text="entry.action"></span>
                                                <span class="min-w-0 flex-1 truncate text-xs text-zinc-500" x-text="(entry.scope ? entry.scope.label : 'global') + (entry.causer ? ' · by ' + entry.causer.label : '')"></span>
                                                <span class="shrink-0 text-xs text-zinc-500" :title="exact(entry.created_at)" x-text="ago(entry.created_at)"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="space-y-4">
                                    <div class="s-card">
                                        <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold dark:border-zinc-800">Overrides by type</h2>
                                        <p x-show="data.scopes.length === 0" class="p-4 text-sm text-zinc-500">None yet. Open a record on the Overrides screen to give it its own values.</p>
                                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <template x-for="type in data.scopes" :key="type.class">
                                                <li>
                                                    <a :href="type.type ? '#/overrides/' + type.type : '#/drift'" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                                        <span class="min-w-0 flex-1 truncate font-medium" x-text="type.name"></span>
                                                        <span class="text-xs text-zinc-500" x-text="type.records + (type.records === 1 ? ' record' : ' records')"></span>
                                                        <span class="s-badge" x-text="type.settings + type.features"></span>
                                                    </a>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <div class="s-card" x-show="data.most_overridden.length">
                                        <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold dark:border-zinc-800">Most overridden</h2>
                                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <template x-for="item in data.most_overridden" :key="item.kind + item.key">
                                                <li>
                                                    <button type="button" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/50" @click="$store.s.openKey(item.kind, item.key)">
                                                        <svg class="size-4 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" x-html="$store.s.icons[item.kind === 'feature' ? 'flag' : 'sliders']"></svg>
                                                        <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                                                        <span class="s-badge s-badge-brand" x-text="item.overrides"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-zinc-500">
                                <span>Press <span class="s-kbd" x-text="$store.s.mod + ' K'"></span> to find any setting, flag or record.</span>
                                <span><span class="s-kbd">?</span> shows every shortcut.</span>
                            </div>
                        </div>
                    </template>
                </section>
            </template>

            <!-- ============================================================ Settings -->
            <template x-if="$store.s.route.view === 'settings'">
                <section x-data="settingsView" class="space-y-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-56 flex-1">
                            <label class="s-label" for="settings-search">Search</label>
                            <input id="settings-search" data-search type="search" class="s-input" placeholder="Key, label, description or tag  ( / )" x-model.debounce.120ms="search">
                        </div>
                        <div>
                            <label class="s-label" for="settings-group">Group</label>
                            <select id="settings-group" class="s-input" x-model="group">
                                <option value="">All groups</option>
                                <template x-for="g in $store.s.groups" :key="g.id"><option :value="String(g.id)" :selected="String(g.id) === group" x-text="g.name"></option></template>
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
                    <p x-show="filtering && !loading" class="text-xs text-zinc-500">
                        <span x-text="shown + ' of ' + rows.length + ' settings'"></span> ·
                        <button type="button" class="s-link" @click="clearFilters()">Clear filters</button>
                    </p>

                    <template x-if="loading">
                        <div class="s-card divide-y divide-zinc-100 dark:divide-zinc-800">
                            <template x-for="i in 5" :key="i">
                                <div class="grid gap-3 px-4 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                                    <div class="space-y-2"><div class="s-skeleton h-4 w-40"></div><div class="s-skeleton h-3 w-64"></div></div>
                                    <div class="s-skeleton h-8"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="failed">
                        <div class="s-card flex flex-wrap items-center gap-3 p-6 text-sm">
                            <span class="text-red-600 dark:text-red-400" x-text="failed"></span>
                            <button type="button" class="s-btn ml-auto" @click="load()">Try again</button>
                        </div>
                    </template>
                    <div x-show="!loading && !failed && rows.length === 0" class="s-card p-10 text-center text-sm text-zinc-500">
                        <p class="font-medium text-zinc-700 dark:text-zinc-300">No settings yet</p>
                        <p class="mt-1">Register definitions in <code class="font-mono">config/setanjo.php</code> or call <code class="font-mono">Settings::set()</code>.</p>
                    </div>
                    <div x-show="!loading && rows.length > 0 && filtering && shown === 0" class="s-card p-10 text-center text-sm text-zinc-500">
                        <p>Nothing matches these filters.</p>
                        <button type="button" class="s-link mt-1" @click="clearFilters()">Clear filters</button>
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
                                                        <a :href="'#/settings/' + encodeURIComponent(row.key)" class="font-medium hover:text-brand-600 hover:underline dark:hover:text-brand-400" x-text="row.label"></a>
                                                        <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                                    </div>
                                                    <p class="mt-0.5 text-sm text-zinc-500" x-show="row.description || row.meta.help" x-text="row.description || row.meta.help"></p>
                                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                                        <span class="s-badge" x-text="row.type"></span>
                                                        <span class="s-badge" x-show="row.meta.unit" x-text="row.meta.unit"></span>
                                                        <span class="s-badge" x-show="!row.defined" title="Stored in the database without a definition">undefined</span>
                                                        <span class="s-badge" x-show="row.source === 'default'">default</span>
                                                        <span class="s-badge s-badge-warn" x-show="row.is_locked">locked</span>
                                                        <span class="s-badge" x-show="!row.is_locked && row.readonly">read-only</span>
                                                        <span class="s-badge bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300" x-show="row.sensitive">sensitive</span>
                                                        <a class="s-badge s-badge-brand" x-show="row.override_count > 0" :href="'#/settings/' + encodeURIComponent(row.key)" x-text="row.override_count + (row.override_count === 1 ? ' override' : ' overrides')"></a>
                                                        <template x-for="tag in (row.meta.tags || [])" :key="tag"><span class="s-badge" x-text="'#' + tag"></span></template>
                                                    </div>
                                                </div>
                                            </div>

                                            <div x-data="valueEditor(() => row, 'value', (value) => $store.s.saveSetting(row.key, value), () => $store.s.forgetSetting(row.key), 'global')" class="space-y-1.5">
                                                <div x-include="s-value-editor"></div>
                                                <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                                                    <span x-show="row.defined && !row.sensitive">Default: <span class="font-mono" x-text="editor.display(row, row.default)"></span></span>
                                                    <button type="button" class="underline-offset-2 hover:underline disabled:opacity-50" x-show="row.stored && row.defined && $store.s.canManage && !row.readonly" :disabled="saving" @click="clear()">Reset to default</button>
                                                    <button type="button" class="underline-offset-2 hover:underline" @click="editMeta(row)">Meta</button>
                                                    <a class="underline-offset-2 hover:underline" :href="'#/settings/' + encodeURIComponent(row.key)">Details</a>
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

            <!-- ============================================================ Feature flags -->
            <template x-if="$store.s.route.view === 'flags'">
                <section x-data="flagsView" class="space-y-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-56 flex-1">
                            <label class="s-label" for="flags-search">Search</label>
                            <input id="flags-search" data-search type="search" class="s-input" placeholder="Key, label or description  ( / )" x-model.debounce.120ms="search">
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

                    <template x-if="loading">
                        <div class="s-card divide-y divide-zinc-100 dark:divide-zinc-800">
                            <template x-for="i in 4" :key="i">
                                <div class="flex items-center gap-3 px-4 py-4"><div class="s-skeleton size-2.5 rounded-full"></div><div class="s-skeleton h-4 flex-1"></div><div class="s-skeleton h-7 w-40"></div></div>
                            </template>
                        </div>
                    </template>
                    <template x-if="failed">
                        <div class="s-card flex flex-wrap items-center gap-3 p-6 text-sm">
                            <span class="text-red-600 dark:text-red-400" x-text="failed"></span>
                            <button type="button" class="s-btn ml-auto" @click="load()">Try again</button>
                        </div>
                    </template>
                    <div x-show="!loading && !failed && rows.length === 0" class="s-card p-10 text-center text-sm text-zinc-500">
                        <p class="font-medium text-zinc-700 dark:text-zinc-300">No feature flags yet</p>
                        <p class="mt-1">Define one with <code class="font-mono">'feature' => true</code> or <code class="font-mono">isFeature()</code>.</p>
                    </div>
                    <div x-show="!loading && rows.length > 0 && filtering && shown === 0" class="s-card p-10 text-center text-sm text-zinc-500">
                        <p>No flags match.</p>
                        <button type="button" class="s-link mt-1" @click="search = ''; status = ''">Clear filters</button>
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
                                        <span x-show="$store.s.canManage && !filtering" class="s-handle" title="Drag to reorder or move to another group">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                        </span>
                                        <span class="size-2.5 shrink-0 rounded-full" :class="row.enabled ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600'" :title="row.enabled ? 'Enabled' : 'Disabled'"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <a :href="'#/flags/' + encodeURIComponent(row.key)" class="font-medium hover:text-brand-600 hover:underline dark:hover:text-brand-400" x-text="row.label"></a>
                                                <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                                <span class="s-badge" x-show="!row.defined">undefined</span>
                                                <a class="s-badge s-badge-brand" x-show="row.override_count > 0" :href="'#/flags/' + encodeURIComponent(row.key)" x-text="row.override_count + (row.override_count === 1 ? ' override' : ' overrides')"></a>
                                            </div>
                                            <p class="mt-0.5 text-sm text-zinc-500" x-show="row.description" x-text="row.description"></p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group" :aria-label="'Global state of ' + row.label">
                                                <button type="button" class="s-seg" :disabled="!$store.s.canManage || busy === row.key" :class="{ 'bg-zinc-900 text-white hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-100': row.global === null }" @click="set(row, null)" x-text="'Default (' + (row.default ? 'on' : 'off') + ')'"></button>
                                                <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === row.key" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': row.global === true }" @click="set(row, true)">On</button>
                                                <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === row.key" :class="{ 'bg-red-600 text-white hover:bg-red-600': row.global === false }" @click="set(row, false)">Off</button>
                                            </div>
                                            <a class="text-xs text-zinc-500 underline-offset-2 hover:underline" :href="'#/flags/' + encodeURIComponent(row.key)">Targeting</a>
                                            <button type="button" class="text-xs text-zinc-500 underline-offset-2 hover:underline" @click="editMeta(row)">Meta</button>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </section>
            </template>

            <!-- ============================================================ Overrides -->
            <template x-if="$store.s.route.view === 'overrides'">
                <section x-data="overridesView" class="grid gap-4 xl:grid-cols-[18rem_minmax(0,1fr)]">
                    <div class="s-card h-fit space-y-3 p-4">
                        <template x-if="$store.s.config.scopes.length === 0">
                            <p class="text-sm text-zinc-500">No scope types configured. Add models to <code class="font-mono">setanjo.scopes</code>.</p>
                        </template>
                        <template x-if="$store.s.config.scopes.length > 0">
                            <div class="space-y-3">
                                <div>
                                    <label class="s-label" for="scope-type">Scope type</label>
                                    <select id="scope-type" class="s-input" x-model="type" @change="changeType()">
                                        <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" :selected="scopeType.alias === type" x-text="scopeType.name"></option></template>
                                    </select>
                                </div>
                                <div>
                                    <label class="s-label" for="scope-search">Find a record</label>
                                    <input id="scope-search" data-search type="search" class="s-input" placeholder="Search, or paste an id" x-model="search" @input="searchLater()">
                                </div>
                                <p class="text-xs text-zinc-500" x-text="overriddenList ? 'Records with overrides' + (meta ? ' (' + meta.total + ')' : '') : 'Search results'"></p>
                                <template x-if="loadingRecords && records.length === 0">
                                    <div class="space-y-2"><div class="s-skeleton h-9"></div><div class="s-skeleton h-9"></div><div class="s-skeleton h-9"></div></div>
                                </template>
                                <p class="text-sm text-zinc-500" x-show="!loadingRecords && records.length === 0" x-text="overriddenList ? 'No overrides yet. Search for a record to give it its own values.' : 'No matches.'"></p>
                                <ul class="-mx-1 max-h-[60vh] space-y-0.5 overflow-y-auto" :class="loadingRecords && 'opacity-60'">
                                    <template x-for="record in records" :key="record.type + ':' + record.id">
                                        <li>
                                            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                                    :class="{ 'bg-brand-50 dark:bg-brand-950': selected && String(selected.id) === String(record.id) && selected.type === record.type }" @click="select(record)">
                                                <span class="flex flex-wrap items-center gap-1.5">
                                                    <span class="font-medium" x-text="record.label"></span>
                                                    <span class="s-badge s-badge-warn" x-show="stateText(record)" x-text="stateText(record)"></span>
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

                    <div class="min-w-0 space-y-4" :class="selected && 'order-first xl:order-none'">
                        <div x-show="!selected && !loading" class="s-card p-10 text-center text-sm text-zinc-500">
                            <p class="font-medium text-zinc-700 dark:text-zinc-300">Pick a record</p>
                            <p class="mt-1">See every setting and flag as that record sees it, and where each value comes from.</p>
                        </div>
                        <template x-if="loading">
                            <div class="s-card space-y-3 p-4"><div class="s-skeleton h-6 w-48"></div><div class="s-skeleton h-10"></div><div class="s-skeleton h-10"></div><div class="s-skeleton h-10"></div></div>
                        </template>
                        <template x-if="failed && !loading">
                            <div class="s-card p-6 text-sm text-red-600 dark:text-red-400" x-text="failed"></div>
                        </template>

                        <template x-if="scope && !loading">
                            <div class="space-y-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-lg font-semibold" x-text="scope.scope.label"></h2>
                                    <span class="s-badge break-all" x-text="scope.scope.type + ' #' + scope.scope.id"></span>
                                    <span class="s-badge s-badge-warn" x-show="stateText(scope.scope)" x-text="stateText(scope.scope)"></span>
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
                                    <template x-for="row in features" :key="scopeKey + ':' + row.key">
                                        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                            <span class="size-2.5 shrink-0 rounded-full" :class="row.effective ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600'"></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <a :href="'#/flags/' + encodeURIComponent(row.key)" class="font-medium hover:underline" x-text="row.label"></a>
                                                    <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                                </div>
                                                <template x-if="row.chain">
                                                    <ol class="mt-1 flex flex-wrap items-center gap-1 text-xs" aria-label="Where the value comes from">
                                                        <template x-for="(step, index) in steps(row)" :key="index">
                                                            <li class="flex items-center gap-1">
                                                                <span x-show="index > 0" class="text-zinc-300 dark:text-zinc-600">›</span>
                                                                <span class="rounded px-1.5 py-0.5" :class="step.winner ? 's-badge-brand font-medium' : (step.set ? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' : 'text-zinc-400')" :title="step.winner ? 'This value is used' : (step.set ? 'Set, but a level before it wins' : 'Nothing stored here')">
                                                                    <span x-text="step.label"></span>: <span :class="step.skipped && step.set && 'line-through'" x-text="step.value"></span>
                                                                </span>
                                                            </li>
                                                        </template>
                                                    </ol>
                                                </template>
                                                <p x-show="!row.chain" class="text-xs text-zinc-500">
                                                    <span x-text="row.effective ? 'On' : 'Off'"></span> · from <span x-text="sourceText(row)"></span>
                                                </p>
                                                <p x-show="!row.allowed" class="text-xs text-zinc-500">Overrides are not allowed for this scope type.</p>
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
                                    <template x-for="row in settings" :key="scopeKey + ':' + row.key">
                                        <div class="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <a :href="'#/settings/' + encodeURIComponent(row.key)" class="font-medium hover:underline" x-text="row.label"></a>
                                                    <code class="font-mono text-xs text-zinc-500" x-text="row.key"></code>
                                                    <span class="s-badge" x-text="row.type"></span>
                                                    <span class="s-badge s-badge-brand" x-show="row.overridden">overridden</span>
                                                </div>
                                                <template x-if="row.chain">
                                                    <ol class="mt-1 flex flex-wrap items-center gap-1 text-xs" aria-label="Where the value comes from">
                                                        <template x-for="(step, index) in steps(row)" :key="index">
                                                            <li class="flex items-center gap-1">
                                                                <span x-show="index > 0" class="text-zinc-300 dark:text-zinc-600">›</span>
                                                                <span class="max-w-56 truncate rounded px-1.5 py-0.5" :class="step.winner ? 's-badge-brand font-medium' : (step.set ? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' : 'text-zinc-400')" :title="step.label + ': ' + step.value">
                                                                    <span x-text="step.label"></span>: <span class="font-mono" :class="step.skipped && step.set && 'line-through'" x-text="step.value"></span>
                                                                </span>
                                                            </li>
                                                        </template>
                                                    </ol>
                                                </template>
                                                <p x-show="!row.chain" class="mt-0.5 text-xs text-zinc-500">
                                                    Effective: <span class="font-mono" x-text="editor.display(row, row.effective)"></span> · from <span x-text="sourceText(row)"></span>
                                                </p>
                                                <p x-show="!row.allowed" class="text-xs text-zinc-500">Overrides are not allowed for this scope type.</p>
                                            </div>
                                            <div x-data="valueEditor(() => row, 'override', save(row), remove(row), 'override')" class="space-y-1.5">
                                                <div x-include="s-value-editor"></div>
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

            <!-- ============================================================ Groups -->
            <template x-if="$store.s.route.view === 'groups'">
                <section x-data="groupsView" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="s-card">
                        <div class="border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                            <h2 class="font-semibold">Groups</h2>
                            <p class="text-sm text-zinc-500">Drag to change the order used on the settings and flags screens.</p>
                        </div>
                        <p x-show="$store.s.groups.length === 0" class="p-6 text-sm text-zinc-500">No groups yet. Create one, or set <code class="font-mono">group()</code> on a definition and run sync.</p>
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
                                                <p class="text-xs text-zinc-400" x-text="countOf(group)"></p>
                                            </div>
                                            <div class="flex gap-2" x-show="$store.s.canManage">
                                                <button type="button" class="s-btn" @click="edit(group)">Edit</button>
                                                <button type="button" class="s-btn-danger" @click="deleteGroup(group)">Delete</button>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="editing && editing.id === group.id">
                                        <form class="grid gap-2 sm:grid-cols-2" @submit.prevent="update()" @keydown.escape.stop="editing = null">
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

            <!-- ============================================================ Drift -->
            <template x-if="$store.s.route.view === 'drift'">
                <section x-data="driftView" class="space-y-4">
                    <template x-if="failed">
                        <div class="s-card flex flex-wrap items-center gap-3 p-6 text-sm">
                            <span class="text-red-600 dark:text-red-400" x-text="failed"></span>
                            <button type="button" class="s-btn ml-auto" @click="load()">Try again</button>
                        </div>
                    </template>
                    <template x-if="!report && !failed">
                        <div class="space-y-4">
                            <div class="s-card space-y-2 p-4"><div class="s-skeleton h-4 w-48"></div><div class="s-skeleton h-3 w-32"></div></div>
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <template x-for="i in 6" :key="i"><div class="s-card space-y-2 p-4"><div class="s-skeleton h-4 w-32"></div><div class="s-skeleton h-3"></div><div class="s-skeleton h-3 w-2/3"></div></div></template>
                            </div>
                        </div>
                    </template>
                    <template x-if="report">
                        <div class="space-y-4">
                            <div class="s-card flex flex-wrap items-center gap-3 p-4">
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-semibold">Definitions vs database</h2>
                                    <p class="text-sm text-zinc-500" x-text="report.definitions + ' definitions registered.'"></p>
                                </div>
                                <span x-show="clean" class="s-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Everything matches</span>
                                <button type="button" class="s-btn" @click="report = null; load()">Check again</button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <template x-for="item in cards" :key="item.key">
                                    <div class="s-card p-4" :class="item.list.length === 0 && 'opacity-70'">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-medium" x-text="item.title"></h3>
                                            <span class="s-badge" :class="{ 's-badge-warn': item.list.length }" x-text="item.list.length"></span>
                                        </div>
                                        <p class="mt-1 text-xs text-zinc-500" x-text="item.help"></p>
                                        <ul class="mt-2 max-h-40 space-y-0.5 overflow-y-auto font-mono text-xs">
                                            <template x-for="(entry, index) in item.list" :key="index">
                                                <li>
                                                    <template x-if="item.links && item.links[index]"><a :href="item.links[index]" class="s-link" x-text="entry"></a></template>
                                                    <template x-if="!(item.links && item.links[index])"><span x-text="entry"></span></template>
                                                </li>
                                            </template>
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

            <!-- ============================================================ History -->
            <template x-if="$store.s.route.view === 'history'">
                <section x-data="historyView" class="space-y-4">
                    <template x-if="!enabled">
                        <div class="s-card p-10 text-center text-sm text-zinc-500">
                            <p class="font-medium text-zinc-700 dark:text-zinc-300">History is off</p>
                            <p class="mt-1" x-text="message"></p>
                        </div>
                    </template>
                    <template x-if="enabled">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-48 flex-1">
                                    <label class="s-label" for="history-key">Key</label>
                                    <input id="history-key" data-search class="s-input font-mono" x-model="filters.key" @input="filterLater()">
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
                                        <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" :selected="scopeType.alias === filters.scope_type" x-text="scopeType.name"></option></template>
                                    </select>
                                </div>
                                <div class="w-28">
                                    <label class="s-label" for="history-scope-id">Scope id</label>
                                    <input id="history-scope-id" class="s-input" x-model="filters.scope_id" @input="filterLater()">
                                </div>
                            </div>
                            <p x-show="filtering" class="text-xs text-zinc-500">
                                <span x-text="meta ? meta.total + ' matching changes' : ''"></span> · <button type="button" class="s-link" @click="clearFilters()">Clear filters</button>
                            </p>

                            <template x-if="failed">
                                <div class="s-card flex flex-wrap items-center gap-3 p-6 text-sm">
                                    <span class="text-red-600 dark:text-red-400" x-text="failed"></span>
                                    <button type="button" class="s-btn ml-auto" @click="load()">Try again</button>
                                </div>
                            </template>
                            <template x-if="loading && entries.length === 0">
                                <div class="s-card divide-y divide-zinc-100 dark:divide-zinc-800">
                                    <template x-for="i in 5" :key="i"><div class="space-y-2 px-4 py-3"><div class="s-skeleton h-4 w-56"></div><div class="s-skeleton h-3 w-80 max-w-full"></div></div></template>
                                </div>
                            </template>
                            <div x-show="!loading && !failed && entries.length === 0" class="s-card p-10 text-center text-sm text-zinc-500" x-text="filtering ? 'No changes match these filters.' : 'No changes recorded yet.'"></div>

                            <div class="space-y-4" :class="loading && 'opacity-60'">
                                <template x-for="day in days" :key="day.label">
                                    <div>
                                        <h3 class="s-section-title mb-2" x-text="day.label"></h3>
                                        <ul class="s-card divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <template x-for="entry in day.entries" :key="entry.id">
                                                <li class="flex gap-3 px-4 py-3" x-data="{ c: change(entry) }">
                                                    <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="{ set: 'bg-brand-500', toggle: 'bg-emerald-500', forget: 'bg-red-500', meta: 'bg-zinc-400', group: 'bg-zinc-400' }[entry.action]"></span>
                                                    <div class="min-w-0 flex-1 space-y-1">
                                                        <div class="flex flex-wrap items-center gap-1.5 text-sm">
                                                            <button type="button" class="font-mono text-xs font-medium hover:underline" @click="open(entry)" x-text="entry.key"></button>
                                                            <span class="s-badge" x-text="entry.action"></span>
                                                            <span class="text-zinc-500" x-text="entry.scope ? 'for ' + entry.scope.label : 'global'"></span>
                                                        </div>
                                                        <template x-if="c.lines">
                                                            <ul class="space-y-0.5 font-mono text-xs">
                                                                <template x-for="(line, index) in c.lines" :key="index">
                                                                    <li class="break-all rounded px-1.5 py-0.5"
                                                                        :class="{ add: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300', remove: 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-300', change: 'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200' }[line.type]"
                                                                        x-text="({ add: '+ ', remove: '− ', change: '~ ' })[line.type] + line.text"></li>
                                                                </template>
                                                                <li x-show="c.lines.length === 0" class="text-zinc-500">No differences.</li>
                                                            </ul>
                                                        </template>
                                                        <template x-if="!c.lines">
                                                            <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs">
                                                                <span class="break-all rounded bg-red-50 px-1.5 py-0.5 text-red-800 line-through decoration-red-400/60 dark:bg-red-950 dark:text-red-300" x-text="display(c.from)" :title="c.from"></span>
                                                                <span class="text-zinc-400">→</span>
                                                                <span class="break-all rounded px-1.5 py-0.5" :class="c.removed ? 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'" x-text="c.removed ? 'removed' : display(c.to)" :title="c.to"></span>
                                                            </div>
                                                        </template>
                                                        <p class="text-xs text-zinc-500">
                                                            <span x-text="entry.causer ? 'by ' + entry.causer.label : 'by the system'"></span> ·
                                                            <span :title="exact(entry.created_at)" x-text="ago(entry.created_at)"></span>
                                                        </p>
                                                    </div>
                                                    <button type="button" class="s-btn-ghost h-fit shrink-0 text-xs" x-show="$store.s.canManage && entry.revertable" @click="revert(entry)" title="Write back the value from before this change">
                                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.undo"></svg>
                                                        Revert
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
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

            <!-- ============================================================ Import / export -->
            <template x-if="$store.s.route.view === 'transfer'">
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
        </div>
    </main>

    <!-- ================================================================ Key detail drawer -->
    <div x-data="keyDrawer" x-show="open" x-cloak class="fixed inset-0 z-40"
         @keydown.escape.window="open && !$store.s.dialog.open && !$store.s.meta.open && !$store.s.palette && close()">
        <div class="absolute inset-0 bg-zinc-950/40" @click="close()" x-show="open" x-transition.opacity></div>
        <aside class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="drawer-title"
               x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
            <template x-if="notFound">
                <div class="space-y-3 p-6">
                    <div class="flex items-center gap-2">
                        <h2 id="drawer-title" class="flex-1 font-semibold">Not found</h2>
                        <button type="button" class="s-btn-ghost" @click="close()" aria-label="Close"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.close"></svg></button>
                    </div>
                    <p class="text-sm text-zinc-500">There is no <span x-text="kind === 'feature' ? 'feature flag' : 'setting'"></span> <code class="font-mono" x-text="key"></code>.</p>
                </div>
            </template>
            <template x-if="open && !row && !notFound">
                <div class="space-y-4 p-6"><div class="s-skeleton h-6 w-48"></div><div class="s-skeleton h-24"></div><div class="s-skeleton h-40"></div></div>
            </template>

            <template x-for="current in (row ? [$store.s.drawerId] : [])" :key="current">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-start gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-950 dark:text-brand-300">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="$store.s.icons[kind === 'feature' ? 'flag' : 'sliders']"></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 id="drawer-title" class="text-lg leading-tight font-semibold" x-text="row.label"></h2>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <button type="button" class="group inline-flex items-center gap-1 font-mono text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200" @click="copy(key)" title="Copy key">
                                    <span x-text="key"></span>
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons[copied === key ? 'check' : 'copy']"></svg>
                                </button>
                                <span class="s-badge" x-text="kind === 'feature' ? 'feature flag' : row.type"></span>
                                <span class="s-badge" x-show="!row.defined">undefined</span>
                                <span class="s-badge s-badge-warn" x-show="row.is_locked">locked</span>
                                <span class="s-badge" x-show="!row.is_locked && row.readonly">read-only</span>
                                <span class="s-badge bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300" x-show="row.sensitive">sensitive</span>
                                <span class="s-badge" x-show="row.group_id" x-text="$store.s.groupName(row.group_id)"></span>
                            </div>
                        </div>
                        <button type="button" class="s-btn-ghost" x-ref="drawerClose" @click="close()" aria-label="Close (Esc)">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.close"></svg>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400" x-show="row.description || (row.meta && row.meta.help)" x-text="row.description || row.meta.help"></p>

                        <!-- Value (setting) -->
                        <template x-if="kind === 'setting'">
                            <section class="space-y-2">
                                <h3 class="s-section-title">Global value</h3>
                                <div x-data="valueEditor(() => row, 'value', save(), remove(), 'global')" class="space-y-1.5">
                                    <div x-include="s-value-editor"></div>
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                                        <span x-show="row.source === 'default'">Not stored: the default is used.</span>
                                        <span x-show="row.defined && !row.sensitive">Default: <span class="font-mono" x-text="editor.display(row, row.default)"></span></span>
                                        <button type="button" class="underline-offset-2 hover:underline disabled:opacity-50" x-show="row.stored && row.defined && $store.s.canManage && !row.readonly" :disabled="saving" @click="clear()">Reset to default</button>
                                        <button type="button" class="underline-offset-2 hover:underline" @click="editMeta()">Meta</button>
                                    </div>
                                </div>
                            </section>
                        </template>

                        <!-- Global state (flag) -->
                        <template x-if="kind === 'feature'">
                            <section class="space-y-2">
                                <h3 class="s-section-title">Global state</h3>
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group" aria-label="Global state">
                                        <button type="button" class="s-seg px-3 py-1.5" :disabled="!$store.s.canManage || busy === 'global'" :class="{ 'bg-zinc-900 text-white hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-100': row.global === null }" @click="setGlobal(null)" x-text="'Default (' + (row.default ? 'on' : 'off') + ')'"></button>
                                        <button type="button" class="s-seg border-l border-zinc-300 px-3 py-1.5 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === 'global'" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': row.global === true }" @click="setGlobal(true)">On</button>
                                        <button type="button" class="s-seg border-l border-zinc-300 px-3 py-1.5 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === 'global'" :class="{ 'bg-red-600 text-white hover:bg-red-600': row.global === false }" @click="setGlobal(false)">Off</button>
                                    </div>
                                    <span class="text-sm text-zinc-500">
                                        <span class="font-medium" :class="row.enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-700 dark:text-zinc-300'" x-text="row.enabled ? 'On' : 'Off'"></span>
                                        for everyone without an override
                                    </span>
                                    <button type="button" class="ml-auto text-xs text-zinc-500 underline-offset-2 hover:underline" @click="editMeta()">Meta</button>
                                </div>
                            </section>
                        </template>

                        <!-- Overrides -->
                        <section class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="s-section-title flex-1" x-text="(kind === 'feature' ? 'Targeting' : 'Overrides') + (overridesMeta ? ' · ' + overridesMeta.total : '')"></h3>
                                <select class="s-input w-auto py-1 text-xs" x-model="overridesType" @change="filterOverrides()" x-show="$store.s.config.scopes.length > 1">
                                    <option value="">All types</option>
                                    <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" x-text="scopeType.name"></option></template>
                                </select>
                            </div>

                            <div class="s-card divide-y divide-zinc-100 dark:divide-zinc-800" :class="loadingOverrides && 'opacity-60'">
                                <template x-if="loadingOverrides && overrides.length === 0">
                                    <div class="space-y-2 p-3"><div class="s-skeleton h-8"></div><div class="s-skeleton h-8"></div></div>
                                </template>
                                <p x-show="!loadingOverrides && overrides.length === 0" class="p-4 text-sm text-zinc-500"
                                   x-text="kind === 'feature' ? 'No record has its own state. Everyone gets the global state.' : 'No record has its own value. Everyone gets the global value.'"></p>
                                <template x-for="item in overrides" :key="item.scope.class + ':' + item.scope.id">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 px-3 py-2.5 text-sm">
                                        <div class="min-w-0 flex-1 basis-full sm:basis-0">
                                            <a :href="item.scope.type ? '#/overrides/' + item.scope.type + '/' + encodeURIComponent(item.scope.id) : null" class="font-medium hover:underline" x-text="item.scope.label"></a>
                                            <span class="ml-1 text-xs text-zinc-500" x-text="(item.scope.type || item.scope.class) + ' #' + item.scope.id"></span>
                                            <span class="s-badge s-badge-warn ml-1" x-show="item.scope.state !== 'found'" x-text="item.scope.state === 'missing' ? 'deleted' : 'hidden'"></span>
                                        </div>
                                        <template x-if="kind === 'setting'">
                                            <span class="max-w-48 truncate font-mono text-xs" x-text="editor.display(row, item.value)" :title="editor.display(row, item.value)"></span>
                                        </template>
                                        <span class="text-xs text-zinc-400" :title="exact(item.updated_at)" x-text="ago(item.updated_at)"></span>
                                        <template x-if="kind === 'feature'">
                                            <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group">
                                                <button type="button" class="s-seg" :disabled="!$store.s.canManage || busy === item.scope.type + ':' + item.scope.id || item.scope.state === 'missing'" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': item.value === true }" @click="item.value !== true && setOverride(item.scope, true)">On</button>
                                                <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="!$store.s.canManage || busy === item.scope.type + ':' + item.scope.id || item.scope.state === 'missing'" :class="{ 'bg-red-600 text-white hover:bg-red-600': item.value === false }" @click="item.value !== false && setOverride(item.scope, false)">Off</button>
                                            </div>
                                        </template>
                                        <button type="button" class="s-btn-ghost px-1.5 py-1 text-xs" x-show="$store.s.canManage && item.scope.type && !row.readonly" :disabled="busy === item.scope.type + ':' + item.scope.id" @click="setOverride(item.scope, null)" :title="kind === 'feature' ? 'Remove: inherit again' : 'Remove the override'">Remove</button>
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center justify-between text-xs text-zinc-500" x-show="overridesMeta && overridesMeta.last_page > 1">
                                <button type="button" class="s-btn px-2 py-1 text-xs" :disabled="overridesPage <= 1" @click="pageTo(overridesPage - 1)">Previous</button>
                                <span x-text="overridesMeta ? 'Page ' + overridesPage + ' of ' + overridesMeta.last_page : ''"></span>
                                <button type="button" class="s-btn px-2 py-1 text-xs" :disabled="overridesMeta && overridesPage >= overridesMeta.last_page" @click="pageTo(overridesPage + 1)">Next</button>
                            </div>

                            <p x-show="kind === 'setting'" class="text-xs text-zinc-500">To give one record its own value, open it on the <a href="#/overrides" class="s-link">Overrides</a> screen.</p>

                            <!-- Add records (flag) -->
                            <template x-if="kind === 'feature' && $store.s.canManage && $store.s.config.scopes.length > 0">
                                <div class="space-y-3">
                                    <div class="s-card space-y-2 p-3">
                                        <p class="text-sm font-medium">Turn on or off for a record</p>
                                        <div class="flex gap-2">
                                            <select class="s-input w-auto" x-model="add.type" @change="searchRecords()">
                                                <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" x-text="scopeType.name"></option></template>
                                            </select>
                                            <input type="search" class="s-input" placeholder="Search by name, or paste an id" x-model="add.search" @input="searchLater()">
                                        </div>
                                        <p x-show="add.loading" class="text-xs text-zinc-500">Searching…</p>
                                        <p x-show="!add.loading && add.search.trim() !== '' && add.results.length === 0" class="text-xs text-zinc-500">No matches.</p>
                                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <template x-for="record in add.results" :key="record.type + ':' + record.id">
                                                <li class="flex items-center gap-3 py-2 text-sm">
                                                    <div class="min-w-0 flex-1">
                                                        <span class="font-medium" x-text="record.label"></span>
                                                        <span class="ml-1 text-xs text-zinc-500" x-text="'#' + record.id + (record.description ? ' · ' + record.description : '')"></span>
                                                    </div>
                                                    <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group">
                                                        <button type="button" class="s-seg" :disabled="busy === record.type + ':' + record.id" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': currentOverride(record) === true }" @click="setOverride(record, true)">On</button>
                                                        <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="busy === record.type + ':' + record.id" :class="{ 'bg-red-600 text-white hover:bg-red-600': currentOverride(record) === false }" @click="setOverride(record, false)">Off</button>
                                                    </div>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    <details class="s-card group p-3">
                                        <summary class="cursor-pointer text-sm font-medium select-none">Paste a list of ids</summary>
                                        <div class="mt-3 space-y-2">
                                            <p class="text-xs text-zinc-500">One per line, or separated by commas or spaces. Up to 1000 at a time. Preview shows what changes before anything is written.</p>
                                            <div class="flex flex-wrap gap-2">
                                                <select class="s-input w-auto" x-model="bulk.type" @change="bulk.preview = null">
                                                    <template x-for="scopeType in $store.s.config.scopes" :key="scopeType.alias"><option :value="scopeType.alias" x-text="scopeType.name"></option></template>
                                                </select>
                                                <select class="s-input w-auto" x-model="bulk.state" @change="bulk.preview = null">
                                                    <option value="true">Turn on</option>
                                                    <option value="false">Turn off</option>
                                                    <option value="null">Remove override (inherit)</option>
                                                </select>
                                            </div>
                                            <textarea rows="4" class="s-input font-mono text-xs" placeholder="12&#10;15&#10;42" x-model="bulk.text" @input="bulk.preview = null" spellcheck="false"></textarea>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs text-zinc-500" x-text="plural(bulkIds.length, 'id')"></span>
                                                <button type="button" class="s-btn ml-auto" :disabled="bulkIds.length === 0 || bulk.running" @click="runBulk(true)">Preview</button>
                                                <button type="button" class="s-btn-primary" :disabled="!bulk.preview || bulk.preview.changed === 0 || bulk.running" @click="runBulk(false)"
                                                        x-text="bulk.preview ? 'Apply to ' + plural(bulk.preview.changed, 'record') : 'Apply'"></button>
                                            </div>
                                            <template x-if="bulk.preview">
                                                <div class="space-y-1 rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-950">
                                                    <p><span class="font-medium" x-text="plural(bulk.preview.changed, 'record')"></span> will change<span x-show="bulk.preview.unchanged" x-text="', ' + bulk.preview.unchanged + ' already set'"></span>.</p>
                                                    <p x-show="bulk.preview.records.length" class="text-zinc-500" x-text="bulk.preview.records.map((record) => record.label).join(', ') + (bulk.preview.changed > bulk.preview.records.length ? ' and ' + (bulk.preview.changed - bulk.preview.records.length) + ' more' : '')"></p>
                                                    <p x-show="bulk.preview.missing.length" class="text-amber-700 dark:text-amber-400" x-text="'Not found: ' + bulk.preview.missing.slice(0, 20).join(', ') + (bulk.preview.missing.length > 20 ? ' …' : '')"></p>
                                                    <p x-show="bulk.preview.not_allowed.length" class="text-amber-700 dark:text-amber-400" x-text="'Not allowed for this flag: ' + bulk.preview.not_allowed.slice(0, 20).join(', ')"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </details>
                                </div>
                            </template>
                        </section>

                        <!-- History -->
                        <section class="space-y-2" x-show="$store.s.config.history">
                            <div class="flex items-center gap-2">
                                <h3 class="s-section-title flex-1">Recent changes</h3>
                                <a :href="'#/history?key=' + encodeURIComponent(key)" class="s-link text-xs">All changes</a>
                            </div>
                            <p x-show="history.length === 0" class="text-sm text-zinc-500">No changes recorded.</p>
                            <ul class="s-card divide-y divide-zinc-100 dark:divide-zinc-800" x-show="history.length">
                                <template x-for="entry in history" :key="entry.id">
                                    <li class="flex items-center gap-3 px-3 py-2 text-xs" x-data="{ c: change(entry) }">
                                        <span class="s-badge" x-text="entry.action"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-mono" x-text="c.lines ? (c.lines.length + ' changes') : (display(c.from) + ' → ' + (c.removed ? 'removed' : display(c.to)))"></p>
                                            <p class="text-zinc-500" x-text="(entry.scope ? entry.scope.label : 'global') + (entry.causer ? ' · by ' + entry.causer.label : '') + ' · ' + ago(entry.created_at)" :title="exact(entry.created_at)"></p>
                                        </div>
                                        <button type="button" class="s-btn-ghost px-1.5 py-1 text-xs" x-show="$store.s.canManage && entry.revertable" @click="revert(entry)">Revert</button>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <!-- Code -->
                        <section class="space-y-2">
                            <h3 class="s-section-title">Use it in code</h3>
                            <ul class="space-y-1.5">
                                <template x-for="snippet in snippets" :key="snippet.label">
                                    <li class="flex items-center gap-2">
                                        <span class="w-28 shrink-0 text-xs text-zinc-500" x-text="snippet.label"></span>
                                        <code class="min-w-0 flex-1 truncate rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs dark:bg-zinc-800" x-text="snippet.code" :title="snippet.code"></code>
                                        <button type="button" class="s-btn-ghost px-1.5 py-1" @click="copy(snippet.code)" :aria-label="'Copy ' + snippet.label">
                                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons[copied === snippet.code ? 'check' : 'copy']"></svg>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    <!-- ================================================================ Meta panel -->
    <div x-data="metaPanel" x-show="m.open" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="m.open && close()">
        <div class="absolute inset-0 bg-zinc-950/40" @click="close()"></div>
        <aside class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-white shadow-xl dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="meta-title">
            <template x-if="m.row">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-start gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div class="min-w-0 flex-1">
                            <h2 id="meta-title" class="font-semibold" x-text="'Meta · ' + m.row.label"></h2>
                            <code class="font-mono text-xs text-zinc-500" x-text="m.row.key"></code>
                        </div>
                        <button type="button" class="s-btn-ghost" @click="close()" aria-label="Close">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.close"></svg>
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
                                <button type="button" class="s-link text-xs" @click="toggleRaw()" x-text="m.raw ? 'Key / value' : 'Raw JSON'"></button>
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
                                <textarea rows="12" class="s-input mt-2 font-mono text-xs" x-model="m.rawText" :disabled="!$store.s.canManage" spellcheck="false"></textarea>
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

    <!-- ================================================================ Command palette -->
    <div x-data="palette" x-show="$store.s.palette" x-cloak class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[10vh]"
         @keydown.escape.window="$store.s.palette && close()">
        <div class="absolute inset-0 bg-zinc-950/50" @click="close()"></div>
        <div class="relative flex max-h-[75vh] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
             role="dialog" aria-modal="true" aria-label="Search and commands" x-show="$store.s.palette" x-transition.scale.98.opacity.duration.120ms>
            <div class="flex items-center gap-2 border-b border-zinc-200 px-4 dark:border-zinc-800">
                <svg class="size-5 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="icons.search"></svg>
                <input x-ref="paletteInput" x-model="term" type="text" class="h-12 min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-zinc-400"
                       placeholder="Find a setting, flag or record, or type a command…" role="combobox" aria-controls="palette-list" :aria-activedescendant="'palette-' + active"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="choose()" @keydown.tab.prevent="move($event.shiftKey ? -1 : 1)">
                <span class="s-kbd">Esc</span>
            </div>
            <ul id="palette-list" class="min-h-0 flex-1 overflow-y-auto p-2" role="listbox">
                <template x-for="(item, index) in items" :key="item.section + ':' + (item.detail ?? '') + ':' + item.label">
                    <li role="presentation">
                        <p x-show="sectionStart(index)" class="s-section-title px-2 pt-2 pb-1" x-text="item.section"></p>
                        <button type="button" :id="'palette-' + index" :data-index="index" role="option" :aria-selected="active === index"
                                class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm"
                                :class="active === index ? 'bg-brand-50 text-brand-900 dark:bg-brand-950 dark:text-brand-100' : 'text-zinc-700 dark:text-zinc-300'"
                                @click="choose(index)" @mousemove="active = index">
                            <svg class="size-4 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="icons[item.icon]"></svg>
                            <span class="min-w-0 flex-1 truncate">
                                <span x-text="item.label"></span>
                                <span class="ml-1 font-mono text-xs opacity-60" x-show="item.detail" x-text="item.detail"></span>
                            </span>
                            <span class="max-w-40 shrink-0 truncate text-xs opacity-60" x-show="item.hint" x-text="item.hint"></span>
                        </button>
                    </li>
                </template>
                <li x-show="items.length === 0" class="px-3 py-10 text-center text-sm text-zinc-500">Nothing found for “<span x-text="term"></span>”.</li>
            </ul>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-zinc-200 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800">
                <span><span class="s-kbd">↑</span> <span class="s-kbd">↓</span> move</span>
                <span><span class="s-kbd">Enter</span> open</span>
                <span class="hidden sm:inline">Type 2+ letters to search records too</span>
            </div>
        </div>
    </div>

    <!-- ================================================================ Shortcuts -->
    <div x-show="$store.s.shortcuts" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="$store.s.shortcuts = false">
        <div class="absolute inset-0 bg-zinc-950/50" @click="$store.s.shortcuts = false"></div>
        <div class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title">
            <div class="flex items-center gap-2">
                <h2 id="shortcuts-title" class="flex-1 font-semibold">Keyboard shortcuts</h2>
                <button type="button" class="s-btn-ghost" @click="$store.s.shortcuts = false" aria-label="Close"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.close"></svg></button>
            </div>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd" x-text="$store.s.mod"></span> <span class="s-kbd">K</span></dt><dd>Search and commands</dd></div>
                <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd">/</span></dt><dd>Search on this screen</dd></div>
                <template x-for="item in $store.s.nav" :key="item.view">
                    <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd">g</span> <span class="s-kbd" x-text="item.key"></span></dt><dd x-text="'Go to ' + item.label"></dd></div>
                </template>
                <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd">Enter</span></dt><dd>Save the field you are editing</dd></div>
                <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd" x-text="$store.s.mod"></span> <span class="s-kbd">Enter</span></dt><dd>Save a JSON or long text field</dd></div>
                <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd">Esc</span></dt><dd>Undo typing, or close a panel</dd></div>
                <div class="flex items-center gap-3"><dt class="w-28 shrink-0"><span class="s-kbd">?</span></dt><dd>This list</dd></div>
            </dl>
        </div>
    </div>

    <!-- ================================================================ Confirm dialog -->
    <div x-show="$store.s.dialog.open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4"
         @keydown.escape.window="$store.s.dialog.open && $store.s.closeDialog(false)"
         x-effect="$store.s.dialog.open && setTimeout(() => $refs.dialogCancel.focus(), 20)">
        <div class="absolute inset-0 bg-zinc-950/50" @click="$store.s.closeDialog(false)"></div>
        <div class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
             role="alertdialog" aria-modal="true" aria-labelledby="dialog-title" aria-describedby="dialog-message">
            <div class="flex gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full"
                     :class="$store.s.dialog.danger ? 'bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400' : 'bg-brand-100 text-brand-600 dark:bg-brand-950 dark:text-brand-400'">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-html="$store.s.icons[$store.s.dialog.danger ? 'alert' : 'undo']"></svg>
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
    <div class="pointer-events-none fixed right-4 bottom-4 z-[70] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite">
        <template x-for="toast in $store.s.toasts" :key="toast.id">
            <div class="pointer-events-auto flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm shadow-lg"
                 :class="toast.kind === 'error' ? 'bg-red-600 text-white' : 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'"
                 x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
                <span class="min-w-0 flex-1" x-text="toast.message"></span>
                <button type="button" x-show="toast.canUndo" class="shrink-0 rounded px-1.5 py-0.5 font-semibold text-brand-300 hover:bg-white/10 dark:text-brand-700 dark:hover:bg-zinc-900/10" @click="$store.s.runUndo(toast)">Undo</button>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" @click="$store.s.dismiss(toast.id)" aria-label="Dismiss">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.close"></svg>
                </button>
            </div>
        </template>
    </div>

    <!-- ================================================================ Shared value editor -->
    <template id="s-value-editor">
        <div class="space-y-1.5">
            <div class="flex items-start gap-2" :class="dirty && !instant && 's-dirty'">
                <div class="min-w-0 flex-1">
                    <template x-if="kind === 'toggle' && mode === 'global'">
                        <button type="button" role="switch" :aria-checked="draft" :disabled="disabled" @click="toggle()" :aria-label="row.label"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50"
                                :class="draft ? 'bg-brand-600' : 'bg-zinc-300 dark:bg-zinc-700'">
                            <span class="inline-block size-5 rounded-full bg-white shadow transition" :class="draft ? 'translate-x-5.5' : 'translate-x-0.5'"></span>
                        </button>
                    </template>
                    <template x-if="kind === 'toggle' && mode === 'override'">
                        <div class="inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700" role="group">
                            <button type="button" class="s-seg" :disabled="disabled" :class="{ 'bg-emerald-600 text-white hover:bg-emerald-600': row.overridden && row.override === true }" @click="set(true)">On</button>
                            <button type="button" class="s-seg border-l border-zinc-300 dark:border-zinc-700" :disabled="disabled" :class="{ 'bg-red-600 text-white hover:bg-red-600': row.overridden && row.override === false }" @click="set(false)">Off</button>
                        </div>
                    </template>
                    <template x-if="kind === 'number'">
                        <div class="flex items-center gap-2">
                            <input type="number" class="s-input" x-model="draft" :min="row.meta.min" :max="row.meta.max" :step="row.meta.step ?? (row.type === 'integer' ? 1 : 'any')" :disabled="disabled" :placeholder="placeholder" :aria-label="row.label"
                                   @keydown.enter="onEnter($event)" @keydown.escape="onEscape($event)">
                            <span class="shrink-0 text-sm text-zinc-500" x-show="row.meta.unit" x-text="row.meta.unit"></span>
                        </div>
                    </template>
                    <template x-if="kind === 'select'">
                        <select class="s-input" x-model="draft" :disabled="disabled" :aria-label="row.label" @change="mode === 'override' && submit()" @keydown.escape="onEscape($event)">
                            <template x-if="draft === ''"><option value="" x-text="mode === 'override' ? 'Inherited' : '—'"></option></template>
                            <template x-for="option in editor.options(row)" :key="option.value"><option :value="option.value" :selected="option.value === draft" x-text="option.label"></option></template>
                        </select>
                    </template>
                    <template x-if="kind === 'json' || kind === 'textarea'">
                        <div>
                            <textarea :rows="kind === 'json' ? 4 : 3" class="s-input font-mono text-xs" x-model="draft" :disabled="disabled" :placeholder="placeholder" :aria-label="row.label" spellcheck="false"
                                      @keydown.enter="onEnter($event)" @keydown.escape="onEscape($event)"></textarea>
                            <div x-show="kind === 'json' && dirty && String(draft).trim() !== ''" class="mt-1 flex items-center gap-2 text-xs">
                                <span x-show="!jsonError" class="text-emerald-600 dark:text-emerald-400">Valid JSON</span>
                                <span x-show="jsonError" class="text-red-600 dark:text-red-400" x-text="jsonError"></span>
                                <button type="button" class="s-link ml-auto" x-show="!jsonError" @click="format()">Format</button>
                            </div>
                        </div>
                    </template>
                    <template x-if="kind === 'color'">
                        <div class="flex items-center gap-2">
                            <input type="color" class="h-8 w-10 shrink-0 cursor-pointer rounded-md border border-zinc-300 bg-white p-0.5 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-950"
                                   :value="editor.isColor(draft) && draft.length === 7 ? draft : '#000000'" @input="draft = $event.target.value" :disabled="disabled" :aria-label="row.label + ' colour picker'">
                            <input type="text" class="s-input font-mono" x-model="draft" :disabled="disabled" :placeholder="placeholder || '#000000'" :aria-label="row.label"
                                   @keydown.enter="onEnter($event)" @keydown.escape="onEscape($event)">
                        </div>
                    </template>
                    <template x-if="['text', 'date', 'time', 'datetime-local', 'email', 'url', 'password'].includes(kind)">
                        <div class="relative">
                            <span x-show="editor.isColor(draft)" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 rounded border border-zinc-300 dark:border-zinc-600" :style="editor.isColor(draft) ? 'background:' + draft : ''"></span>
                            <input :type="kind === 'password' ? (reveal ? 'text' : 'password') : kind" class="s-input" :class="{ 'pl-8': editor.isColor(draft), 'pr-9': kind === 'password' }"
                                   x-model="draft" :disabled="disabled" :placeholder="placeholder" :aria-label="row.label" :autocomplete="kind === 'password' ? 'new-password' : 'off'"
                                   @keydown.enter="onEnter($event)" @keydown.escape="onEscape($event)">
                            <button type="button" x-show="kind === 'password'" class="absolute top-1/2 right-1.5 -translate-y-1/2 rounded p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                    @click="reveal = !reveal" :aria-label="reveal ? 'Hide what you typed' : 'Show what you typed'">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" x-html="$store.s.icons.eye"></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" class="s-btn-primary" x-show="dirty && !instant" :disabled="saving || (kind === 'json' && !!jsonError)" @click="submit()">Save</button>
            </div>
            <p class="text-xs text-red-600 dark:text-red-400" x-show="error" x-text="error"></p>
            <p class="text-xs text-amber-700 dark:text-amber-400" x-show="dirty && !instant && !error">
                Not saved ·
                <span class="s-kbd" x-text="kind === 'json' || kind === 'textarea' ? $store.s.mod + ' Enter' : 'Enter'"></span> save ·
                <span class="s-kbd">Esc</span> undo
            </p>
        </div>
    </template>
</div>
@endverbatim

<script @if($nonce) nonce="{{ $nonce }}" @endif>{!! \Ahs12\Setanjo\Dashboard\Assets::js() !!}</script>
</body>
</html>
