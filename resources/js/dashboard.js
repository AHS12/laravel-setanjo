import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

const config = JSON.parse(document.getElementById('setanjo-config').textContent);
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const VIEWS = ['settings', 'flags', 'overrides', 'groups', 'drift', 'history', 'transfer'];
const JSON_TYPES = ['array', 'json', 'object'];

/* -------------------------------------------------------------------------- */
/* HTTP                                                                       */
/* -------------------------------------------------------------------------- */

class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

async function api(method, path, body = undefined) {
    const isForm = body instanceof FormData;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';

    const response = await fetch(config.api + path, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
    });

    if (response.status === 204) return null;

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = data.errors ?? {};
        const first = Object.values(errors).flat()[0];
        throw new ApiError(first ?? data.message ?? `Request failed (${response.status})`, response.status, errors);
    }

    return data;
}

const query = (params) => {
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') search.set(key, value);
    });
    const string = search.toString();
    return string ? `?${string}` : '';
};

/* -------------------------------------------------------------------------- */
/* Value editing helpers                                                      */
/* -------------------------------------------------------------------------- */

const editor = {
    kind(row) {
        const input = row.meta?.input;
        const type = row.type;

        if (input) return input;
        if (type === 'boolean') return 'toggle';
        if (type === 'integer' || type === 'float') return 'number';
        if (row.meta?.options && !JSON_TYPES.includes(type)) return 'select';
        if (JSON_TYPES.includes(type)) return 'json';

        return 'text';
    },

    options(row) {
        const options = row.meta?.options ?? {};
        return Array.isArray(options)
            ? options.map((value) => ({ value: String(value), label: String(value) }))
            : Object.entries(options).map(([value, label]) => ({ value, label: String(label) }));
    },

    draft(row, value) {
        if (row.masked || row.sensitive) return editor.kind(row) === 'toggle' ? false : '';
        if (editor.kind(row) === 'json') return value === null || value === undefined ? '' : JSON.stringify(value, null, 2);
        if (editor.kind(row) === 'toggle') return Boolean(value);
        return value === null || value === undefined ? '' : String(value);
    },

    parse(row, draft) {
        const kind = editor.kind(row);

        if (kind === 'toggle') return Boolean(draft);
        if (kind === 'number') {
            if (draft === '' || draft === null) return null;
            const number = Number(draft);
            if (Number.isNaN(number)) throw new ApiError('Enter a number.', 422);
            return number;
        }
        if (kind === 'json') {
            if (String(draft).trim() === '') return null;
            try {
                return JSON.parse(draft);
            } catch {
                throw new ApiError('Enter valid JSON.', 422);
            }
        }

        return draft;
    },

    display(row, value) {
        if (row.masked || (row.sensitive && value === null)) return '••••••••';
        if (value === null || value === undefined) return '—';
        if (typeof value === 'boolean') return value ? 'On' : 'Off';
        if (typeof value === 'object') return JSON.stringify(value);

        const option = editor.options(row).find((item) => item.value === String(value));
        const text = option ? option.label : String(value);

        return row.meta?.unit ? `${text} ${row.meta.unit}` : text;
    },
};

/**
 * Sortable moves DOM nodes that Alpine's x-for owns. Read the new order, put the node back,
 * then let the caller update the data so Alpine re-renders.
 */
function sortable(element, { onSorted, ...options }) {
    return Sortable.create(element, {
        animation: 150,
        ghostClass: 's-ghost',
        // Pointer events instead of native HTML5 drag: consistent across browsers and touch.
        forceFallback: true,
        fallbackOnBody: true,
        draggable: '[data-key]',
        disabled: !config.canManage,
        ...options,
        onEnd(event) {
            const keys = [...event.to.children].map((child) => child.dataset.key).filter(Boolean);
            const fromKeys = [...event.from.children].map((child) => child.dataset.key).filter(Boolean);

            if (event.from === event.to) {
                if (event.oldIndex === event.newIndex) return;
                const ref = event.from.children[event.oldIndex > event.newIndex ? event.oldIndex + 1 : event.oldIndex];
                event.from.insertBefore(event.item, ref ?? null);
            } else {
                event.from.insertBefore(event.item, event.from.children[event.oldIndex] ?? null);
            }

            onSorted({
                key: event.item.dataset.key,
                to: event.to.dataset.group,
                from: event.from.dataset.group,
                keys,
                fromKeys,
            });
        },
    });
}

const groupValue = (value) => (value === '' || value === undefined || value === 'null' ? null : Number(value));

/* -------------------------------------------------------------------------- */
/* Store                                                                      */
/* -------------------------------------------------------------------------- */

document.addEventListener('alpine:init', () => {
    Alpine.store('s', {
        config,
        groups: [],
        toasts: [],
        meta: { open: false },

        get canManage() {
            return config.canManage;
        },

        async loadGroups() {
            this.groups = (await api('GET', '/groups')).data;
        },

        groupName(id) {
            return this.groups.find((group) => group.id === id)?.name ?? 'Ungrouped';
        },

        toast(message, kind = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, kind });
            setTimeout(() => (this.toasts = this.toasts.filter((toast) => toast.id !== id)), kind === 'error' ? 6000 : 3000);
        },

        fail(error) {
            this.toast(error.message ?? String(error), 'error');
        },

        openMeta(kind, row, onSaved) {
            const stored = row.stored_meta ?? {};
            this.meta = {
                open: true,
                kind,
                row,
                onSaved,
                raw: false,
                rawText: JSON.stringify(stored, null, 2),
                pairs: Object.entries(stored).map(([key, value]) => ({ key, value: metaText(value) })),
                error: null,
                saving: false,
            };
        },
    });

    Alpine.data('setanjo', () => ({
        view: 'settings',
        dark: false,

        init() {
            try {
                const saved = localStorage.getItem('setanjo.theme');
                this.dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            } catch {
                this.dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }
            this.applyTheme();

            const route = () => {
                const view = location.hash.replace(/^#\/?/, '').split('?')[0];
                this.view = VIEWS.includes(view) ? view : 'settings';
            };
            window.addEventListener('hashchange', route);
            route();

            this.$store.s.loadGroups().catch((error) => this.$store.s.fail(error));
        },

        toggleTheme() {
            this.dark = !this.dark;
            try {
                localStorage.setItem('setanjo.theme', this.dark ? 'dark' : 'light');
            } catch {
                // private mode: theme is not remembered
            }
            this.applyTheme();
        },

        applyTheme() {
            document.documentElement.classList.toggle('dark', this.dark);
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Shared value editor (used by settings and overrides)              */
    /* ------------------------------------------------------------------ */

    /**
     * x-data="valueEditor(() => row, 'value', save(row), remove(row))"
     * getRow is a getter so the editor follows the parent's row after it is replaced.
     */
    Alpine.data('valueEditor', (getRow, field, save, remove) => ({
        draft: '',
        original: '',
        error: null,
        saving: false,
        editor,

        init() {
            this.reset(this.row[field]);
        },

        get row() {
            return getRow();
        },

        get kind() {
            return editor.kind(this.row);
        },

        get dirty() {
            return this.draft !== this.original;
        },

        get disabled() {
            return !config.canManage || this.row.readonly || this.row.allowed === false || this.saving;
        },

        reset(value) {
            this.draft = this.original = editor.draft(this.row, value);
            this.error = null;
        },

        async submit() {
            this.error = null;
            this.saving = true;
            try {
                const updated = await save(editor.parse(this.row, this.draft));
                this.reset(updated?.[field]);
                this.$store.s.toast(`Saved ${this.row.label}`);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        async toggle() {
            if (this.disabled) return;
            this.draft = !this.draft;
            await this.submit();
        },

        async clear() {
            this.error = null;
            this.saving = true;
            try {
                const updated = await remove();
                this.reset(updated?.[field]);
                this.$store.s.toast(`Reset ${this.row.label}`);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Settings                                                           */
    /* ------------------------------------------------------------------ */

    Alpine.data('settingsView', () => ({
        rows: [],
        loading: true,
        search: '',
        group: '',
        type: '',
        editor,

        async init() {
            await this.load();
        },

        async load() {
            this.loading = true;
            try {
                this.rows = (await api('GET', '/settings')).data;
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        get types() {
            return [...new Set(this.rows.map((row) => row.type))].sort();
        },

        get filtering() {
            return this.search !== '' || this.group !== '' || this.type !== '';
        },

        matches(row) {
            const term = this.search.toLowerCase();
            const haystack = [row.key, row.label, row.description, ...(row.meta?.tags ?? [])].join(' ').toLowerCase();

            return (term === '' || haystack.includes(term)) && (this.type === '' || row.type === this.type);
        },

        get sections() {
            const groups = [...this.$store.s.groups, { id: null, name: 'Ungrouped', slug: null }];

            return groups
                .filter((group) => this.group === '' || String(group.id) === this.group)
                .map((group) => ({
                    ...group,
                    rows: this.rows
                        .filter((row) => row.group_id === group.id && this.matches(row))
                        .sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label)),
                }))
                .filter((section) => section.rows.length > 0 || (!this.filtering && section.id !== null));
        },

        replace(updated) {
            if (!updated) return;
            this.rows = this.rows.map((row) => (row.key === updated.key ? updated : row));
        },

        save(row) {
            return async (value) => {
                const { data } = await api('PUT', `/settings/${encodeURIComponent(row.key)}`, { value });
                this.replace(data);
                return data;
            };
        },

        remove(row) {
            return async () => {
                const { data } = await api('DELETE', `/settings/${encodeURIComponent(row.key)}`);
                this.replace(data);
                return data;
            };
        },

        initRows(element) {
            sortable(element, {
                group: 'settings',
                handle: '.s-handle',
                onSorted: async ({ to, keys }) => {
                    const groupId = groupValue(to);
                    this.rows = this.rows.map((row) =>
                        keys.includes(row.key) ? { ...row, group_id: groupId, sort_order: keys.indexOf(row.key) } : row,
                    );
                    try {
                        this.rows = (await api('POST', '/settings/order', { group_id: groupId, keys })).data;
                    } catch (error) {
                        this.$store.s.fail(error);
                        await this.load();
                    }
                },
            });
        },

        initSections(element) {
            sortable(element, {
                handle: '.s-group-handle',
                draggable: '[data-key]',
                onSorted: async ({ keys }) => {
                    const ids = keys.filter((key) => key !== 'null').map(Number);
                    const store = this.$store.s;
                    store.groups = ids.map((id) => store.groups.find((group) => group.id === id));
                    try {
                        store.groups = (await api('POST', '/groups/order', { ids })).data;
                    } catch (error) {
                        store.fail(error);
                        await store.loadGroups();
                    }
                },
            });
        },

        editMeta(row) {
            this.$store.s.openMeta('settings', row, (updated) => this.replace(updated));
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Feature flags                                                      */
    /* ------------------------------------------------------------------ */

    Alpine.data('flagsView', () => ({
        rows: [],
        loading: true,
        search: '',
        status: '',
        busy: null,

        async init() {
            await this.load();
        },

        async load() {
            this.loading = true;
            try {
                this.rows = (await api('GET', '/features')).data;
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        matches(row) {
            const term = this.search.toLowerCase();
            const haystack = [row.key, row.label, row.description].join(' ').toLowerCase();
            const status =
                this.status === '' ||
                (this.status === 'on' && row.enabled) ||
                (this.status === 'off' && !row.enabled) ||
                (this.status === 'overridden' && row.override_count > 0);

            return (term === '' || haystack.includes(term)) && status;
        },

        get sections() {
            const groups = [...this.$store.s.groups, { id: null, name: 'Ungrouped' }];
            const filtering = this.search !== '' || this.status !== '';

            return groups
                .map((group) => ({
                    ...group,
                    rows: this.rows
                        .filter((row) => row.group_id === group.id && this.matches(row))
                        .sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label)),
                }))
                .filter((section) => section.rows.length > 0 || (!filtering && section.id !== null && this.rows.length > 0));
        },

        async set(row, state) {
            if (!config.canManage || row.global === state) return;
            this.busy = row.key;
            try {
                const { data } = await api('PUT', `/features/${encodeURIComponent(row.key)}`, { state });
                this.rows = this.rows.map((item) => (item.key === data.key ? data : item));
                this.$store.s.toast(`${row.label}: ${state === null ? 'default' : state ? 'on' : 'off'}`);
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        initRows(element) {
            sortable(element, {
                group: 'features',
                handle: '.s-handle',
                onSorted: async ({ to, keys }) => {
                    const groupId = groupValue(to);
                    this.rows = this.rows.map((row) =>
                        keys.includes(row.key) ? { ...row, group_id: groupId, sort_order: keys.indexOf(row.key) } : row,
                    );
                    try {
                        this.rows = (await api('POST', '/features/order', { group_id: groupId, keys })).data;
                    } catch (error) {
                        this.$store.s.fail(error);
                        await this.load();
                    }
                },
            });
        },

        editMeta(row) {
            this.$store.s.openMeta('features', row, (updated) => {
                this.rows = this.rows.map((item) => (item.key === updated.key ? updated : item));
            });
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Overrides                                                          */
    /* ------------------------------------------------------------------ */

    Alpine.data('overridesView', () => ({
        type: config.scopes[0]?.alias ?? '',
        search: '',
        records: [],
        overriddenList: true,
        loadingRecords: false,
        selected: null,
        scope: null,
        loading: false,
        tab: 'features',
        filter: '',
        onlyOverridden: false,
        busy: null,
        editor,
        timer: null,

        async init() {
            if (this.type) await this.loadRecords();
        },

        async loadRecords() {
            this.loadingRecords = true;
            try {
                const response = await api('GET', `/scopes/${this.type}${query({ search: this.search })}`);
                this.records = response.data;
                this.overriddenList = response.overridden;
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.loadingRecords = false;
            }
        },

        searchLater() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.loadRecords(), 250);
        },

        changeType() {
            this.search = '';
            this.selected = null;
            this.scope = null;
            this.loadRecords();
        },

        async select(record) {
            this.selected = record;
            this.loading = true;
            try {
                this.scope = (await api('GET', `/scopes/${record.type}/${encodeURIComponent(record.id)}`)).data;
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        visible(rows, overridden) {
            const term = this.filter.toLowerCase();
            return rows.filter(
                (row) =>
                    (term === '' || `${row.key} ${row.label}`.toLowerCase().includes(term)) &&
                    (!this.onlyOverridden || overridden(row)),
            );
        },

        get features() {
            return this.scope ? this.visible(this.scope.features, (row) => row.override !== null) : [];
        },

        get settings() {
            return this.scope ? this.visible(this.scope.settings, (row) => row.overridden) : [];
        },

        params() {
            return { scope_type: this.selected.type, scope_id: this.selected.id };
        },

        replace(list, updated) {
            this.scope[list] = this.scope[list].map((row) => (row.key === updated.key ? updated : row));
        },

        async setFlag(row, state) {
            if (!config.canManage || !row.allowed || row.override === state) return;
            this.busy = row.key;
            try {
                const { data } = await api('PUT', `/features/${encodeURIComponent(row.key)}`, { state, ...this.params() });
                this.replace('features', data);
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        save(row) {
            return async (value) => {
                const { data } = await api('PUT', `/settings/${encodeURIComponent(row.key)}`, { value, ...this.params() });
                this.replace('settings', data);
                return data;
            };
        },

        remove(row) {
            return async () => {
                const { data } = await api('DELETE', `/settings/${encodeURIComponent(row.key)}`, this.params());
                this.replace('settings', data);
                return data;
            };
        },

        sourceText(row) {
            if (row.source === 'scope') {
                const same =
                    row.source_scope && this.selected && row.source_scope.type === this.selected.type && String(row.source_scope.id) === String(this.selected.id);
                return same ? 'this record' : row.source_scope?.label ?? 'scope';
            }
            return row.source;
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Groups                                                             */
    /* ------------------------------------------------------------------ */

    Alpine.data('groupsView', () => ({
        form: { name: '', slug: '', description: '', icon: '' },
        editing: null,
        errors: {},
        saving: false,

        initList(element) {
            sortable(element, {
                handle: '.s-handle',
                onSorted: async ({ keys }) => {
                    const store = this.$store.s;
                    const ids = keys.map(Number);
                    store.groups = ids.map((id) => store.groups.find((group) => group.id === id));
                    try {
                        store.groups = (await api('POST', '/groups/order', { ids })).data;
                    } catch (error) {
                        store.fail(error);
                        await store.loadGroups();
                    }
                },
            });
        },

        async create() {
            this.errors = {};
            this.saving = true;
            try {
                await api('POST', '/groups', clean(this.form));
                this.form = { name: '', slug: '', description: '', icon: '' };
                await this.$store.s.loadGroups();
                this.$store.s.toast('Group created');
            } catch (error) {
                this.errors = error.errors ?? {};
                this.$store.s.fail(error);
            } finally {
                this.saving = false;
            }
        },

        edit(group) {
            this.errors = {};
            this.editing = { ...group };
        },

        async update() {
            this.saving = true;
            try {
                const { id, name, slug, description, icon } = this.editing;
                await api('PUT', `/groups/${id}`, { name, slug, description, icon });
                this.editing = null;
                await this.$store.s.loadGroups();
                this.$store.s.toast('Group updated');
            } catch (error) {
                this.errors = error.errors ?? {};
                this.$store.s.fail(error);
            } finally {
                this.saving = false;
            }
        },

        async deleteGroup(group) {
            if (!confirm(`Delete the group "${group.name}"? Its settings and flags become ungrouped.`)) return;
            try {
                await api('DELETE', `/groups/${group.id}`);
                await this.$store.s.loadGroups();
                this.$store.s.toast('Group deleted');
            } catch (error) {
                this.$store.s.fail(error);
            }
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Drift                                                              */
    /* ------------------------------------------------------------------ */

    Alpine.data('driftView', () => ({
        report: null,
        options: { prune: false, force_types: false, reset_meta: false, reset_groups: false },
        output: '',
        previewed: false,
        running: false,

        async init() {
            await this.load();
            this.$watch('options', () => (this.previewed = false), { deep: true });
        },

        async load() {
            try {
                this.report = (await api('GET', '/drift')).data;
            } catch (error) {
                this.$store.s.fail(error);
            }
        },

        get clean() {
            const r = this.report;
            return (
                r &&
                ['missing', 'missing_features', 'type_mismatch', 'stale', 'stale_features', 'flags_in_settings'].every(
                    (key) => r[key].length === 0,
                )
            );
        },

        async run(dryRun) {
            this.running = true;
            try {
                const response = await api('POST', '/sync', { ...this.options, dry_run: dryRun });
                this.output = response.output;
                this.report = response.data;
                this.previewed = dryRun;
                if (!dryRun) {
                    this.$store.s.toast('Sync finished');
                    await this.$store.s.loadGroups();
                }
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.running = false;
            }
        },
    }));

    /* ------------------------------------------------------------------ */
    /* History                                                            */
    /* ------------------------------------------------------------------ */

    Alpine.data('historyView', () => ({
        filters: { key: '', action: '', scope_type: '', scope_id: '' },
        entries: [],
        page: 1,
        meta: null,
        enabled: true,
        message: '',
        loading: false,
        timer: null,

        async init() {
            await this.load();
        },

        filterLater() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.page = 1;
                this.load();
            }, 300);
        },

        async load() {
            this.loading = true;
            try {
                const response = await api('GET', `/history${query({ ...this.filters, page: this.page })}`);
                this.enabled = response.enabled;
                this.message = response.message ?? '';
                this.entries = response.data;
                this.meta = response.meta ?? null;
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        go(page) {
            this.page = page;
            this.load();
        },

        change(entry) {
            if (entry.action === 'meta') return `${metaText(entry.old_meta)} → ${metaText(entry.new_meta)}`;
            if (entry.action === 'toggle') return entry.new_value === null ? 'inherit' : entry.new_value === 'true' ? 'on' : 'off';
            if (entry.action === 'forget') return `${entry.old_value ?? '—'} → removed`;
            return `${entry.old_value ?? '—'} → ${entry.new_value ?? '—'}`;
        },

        when(value) {
            return value ? new Date(value).toLocaleString() : '';
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Import / export                                                    */
    /* ------------------------------------------------------------------ */

    Alpine.data('transferView', () => ({
        file: null,
        output: '',
        checked: false,
        running: false,
        exportUrl: (global) => config.api + '/export' + (global ? '?global=1' : ''),

        pick(event) {
            this.file = event.target.files[0] ?? null;
            this.output = '';
            this.checked = false;
        },

        async run(dryRun) {
            if (!this.file) return;
            const form = new FormData();
            form.append('file', this.file);
            if (dryRun) form.append('dry_run', '1');

            this.running = true;
            try {
                const response = await api('POST', '/import', form);
                this.output = response.output;
                this.checked = dryRun;
                if (!dryRun) {
                    this.$store.s.toast('Import finished');
                    await this.$store.s.loadGroups();
                }
            } catch (error) {
                this.output = error.message;
                this.$store.s.fail(error);
            } finally {
                this.running = false;
            }
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Meta panel                                                         */
    /* ------------------------------------------------------------------ */

    Alpine.data('metaPanel', () => ({
        get m() {
            return this.$store.s.meta;
        },

        definitionMeta() {
            return Object.entries(this.m.row?.definition_meta ?? {}).map(([key, value]) => ({ key, value: metaText(value) }));
        },

        overridden(key) {
            return this.m.pairs.some((pair) => pair.key === key);
        },

        add() {
            this.m.pairs.push({ key: '', value: '' });
        },

        remove(index) {
            this.m.pairs.splice(index, 1);
        },

        toggleRaw() {
            this.m.error = null;
            if (this.m.raw) {
                try {
                    const parsed = this.m.rawText.trim() === '' ? {} : JSON.parse(this.m.rawText);
                    this.m.pairs = Object.entries(parsed).map(([key, value]) => ({ key, value: metaText(value) }));
                } catch {
                    this.m.error = 'Enter valid JSON.';
                    return;
                }
            } else {
                this.m.rawText = JSON.stringify(this.collect(), null, 2);
            }
            this.m.raw = !this.m.raw;
        },

        collect() {
            const meta = {};
            this.m.pairs.forEach(({ key, value }) => {
                if (key.trim() !== '') meta[key.trim()] = parseMetaValue(value);
            });
            return meta;
        },

        close() {
            this.m.open = false;
        },

        async save() {
            this.m.error = null;
            let meta;
            try {
                meta = this.m.raw ? (this.m.rawText.trim() === '' ? {} : JSON.parse(this.m.rawText)) : this.collect();
            } catch {
                this.m.error = 'Enter valid JSON.';
                return;
            }

            this.m.saving = true;
            try {
                const path = `/${this.m.kind}/${encodeURIComponent(this.m.row.key)}/meta`;
                const { data } = await api('PUT', path, { meta: Object.keys(meta).length ? meta : null });
                this.m.onSaved?.(data);
                this.$store.s.toast(`Meta saved for ${this.m.row.label}`);
                this.close();
            } catch (error) {
                this.m.error = error.message;
            } finally {
                this.m.saving = false;
            }
        },
    }));
});

function metaText(value) {
    if (value === null || value === undefined) return value === null ? 'null' : '';
    return typeof value === 'string' ? value : JSON.stringify(value);
}

/** Meta values are typed as JSON when they parse (numbers, booleans, arrays), strings otherwise. */
function parseMetaValue(text) {
    const trimmed = String(text).trim();
    if (trimmed === '') return '';
    try {
        return JSON.parse(trimmed);
    } catch {
        return text;
    }
}

function clean(object) {
    return Object.fromEntries(Object.entries(object).filter(([, value]) => value !== '' && value !== null));
}

window.Alpine = Alpine;
Alpine.start();
