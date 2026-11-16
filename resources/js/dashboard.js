import Alpine from 'alpinejs';
import { registerControls } from './controls.js';
import Sortable from 'sortablejs';

const config = JSON.parse(document.getElementById('setanjo-config').textContent);
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const JSON_TYPES = ['array', 'json', 'object'];

/** Sidebar entries; `key` is the second key of the "g then …" shortcut. */
const NAV = [
    { view: 'overview', label: 'Overview', key: 'o', icon: 'home', description: 'Settings, flags and overrides at a glance.' },
    { view: 'settings', label: 'Settings', key: 's', icon: 'sliders', description: 'Global values. Open a setting to see who overrides it and its history.' },
    { view: 'flags', label: 'Feature flags', key: 'f', icon: 'flag', description: 'Turn features on or off everywhere, or for chosen records.' },
    { view: 'overrides', label: 'Overrides', key: 'r', icon: 'users', description: 'Everything as one record sees it, and where each value comes from.' },
    { view: 'groups', label: 'Groups', key: 'g', icon: 'folder', description: 'Sections for the settings and flags screens, in the order you drag them.' },
    { view: 'drift', label: 'Drift', key: 'd', icon: 'scan', description: 'Differences between the definitions in code and the database.' },
    { view: 'history', label: 'History', key: 'h', icon: 'clock', description: 'Every change, who made it, and a way back.' },
    { view: 'transfer', label: 'Import / export', key: 'i', icon: 'swap', description: 'Move settings, meta, groups and flags between environments as JSON.' },
];
const VIEWS = NAV.map((item) => item.view);

/** Inner SVG markup (24×24, stroke icons). Static strings only. */
const ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    sliders: '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
    flag: '<path d="M5 21V4m0 0h11l-2 4 2 4H5"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
    folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    scan: '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    swap: '<path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
    monitor: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
    back: '<path d="M10 6 4 12l6 6M4 12h16"/>',
    collapse: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    close: '<path d="M18 6 6 18M6 6l12 12"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
    check: '<path d="m5 12 5 5L20 7"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    keyboard: '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h0M10 10h0M14 10h0M18 10h0M7 14h10"/>',
    code: '<path d="m8 8-4 4 4 4M16 8l4 4-4 4"/>',
    bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    alert: '<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
};

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

    let response;
    try {
        response = await fetch(config.api + path, {
            method,
            headers,
            credentials: 'same-origin',
            body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
        });
    } catch {
        throw new ApiError('Could not reach the server. Check your connection and try again.', 0);
    }

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

const enc = encodeURIComponent;

/* -------------------------------------------------------------------------- */
/* Routing (#/view/param/param?query)                                         */
/* -------------------------------------------------------------------------- */

function parseRoute(hash = location.hash) {
    const [path, search = ''] = hash.replace(/^#\/?/, '').split('?');
    const parts = path
        .split('/')
        .filter(Boolean)
        .map((part) => {
            try {
                return decodeURIComponent(part);
            } catch {
                return part;
            }
        });
    const known = VIEWS.includes(parts[0]);

    return {
        view: known ? parts[0] : 'overview',
        params: known ? parts.slice(1) : [],
        query: Object.fromEntries(new URLSearchParams(search)),
    };
}

const go = (path) => {
    if (location.hash !== path) location.hash = path;
};

const keyPath = (kind, key) => `#/${kind === 'feature' ? 'flags' : 'settings'}/${enc(key)}`;
const recordPath = (type, id) => `#/overrides/${enc(type)}/${enc(id)}`;

/* -------------------------------------------------------------------------- */
/* Small helpers                                                              */
/* -------------------------------------------------------------------------- */

const plural = (count, word, many = `${word}s`) => `${count} ${count === 1 ? word : many}`;
const stateText = (state) => (state === null ? 'default' : state ? 'on' : 'off');

const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

function ago(value) {
    if (!value) return '';
    const seconds = (new Date(value).getTime() - Date.now()) / 1000;
    if (Math.abs(seconds) < 45) return 'just now';
    const units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return relative.format(Math.round(seconds / size), unit);
    }
    return relative.format(Math.round(seconds), 'second');
}

const exact = (value) => (value ? new Date(value).toLocaleString() : '');

const pad = (number) => String(number).padStart(2, '0');

/** ISO time → the value of a datetime-local input (the browser's timezone). */
function toLocalInput(value) {
    if (!value) return '';
    const date = new Date(value);
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const fromLocalInput = (value) => (value ? new Date(value).toISOString() : null);

const rolloutValue = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

/** Whether a flag's rules narrow who gets it (an on flag may still be off for some records). */
const limited = (row) => (row.rules?.rollout ?? 100) < 100 || (row.rules?.requires ?? []).length > 0 || row.window === 'open';

/** Short badges for a flag's rules, for the list and the drawer. */
function ruleBadges(row) {
    const rules = row.rules ?? {};
    const badges = [];
    if (row.archived) badges.push({ text: 'archived', title: `Archived ${exact(rules.archived_at)}`, warn: true });
    if (row.window === 'pending') badges.push({ text: `starts ${ago(rules.enabled_from)}`, title: `Off until ${exact(rules.enabled_from)}`, warn: true });
    if (row.window === 'open' && rules.enabled_until) badges.push({ text: `ends ${ago(rules.enabled_until)}`, title: `Off from ${exact(rules.enabled_until)}` });
    if (row.window === 'ended') badges.push({ text: 'schedule ended', title: `Off since ${exact(rules.enabled_until)}`, warn: true });
    if ((rules.rollout ?? 100) < 100) badges.push({ text: `${rules.rollout}% rollout`, title: `On for ${rules.rollout}% of records` });
    if ((rules.requires ?? []).length > 0) badges.push({ text: `needs ${rules.requires.join(', ')}`, title: 'Off unless these flags are on too' });
    return badges;
}

/** Why a rule (not an override or the global state) decided a record's state. */
function ruleText(row) {
    switch (row.source) {
        case 'archived':
            return 'Off: the flag is archived';
        case 'schedule':
            return 'Off: outside its schedule';
        case 'dependency':
            return `Off: needs ${row.detail} on`;
        case 'rollout':
            return row.effective ? `On: in the ${row.rules?.rollout}% rollout` : `Off: not in the ${row.rules?.rollout}% rollout`;
        default:
            return '';
    }
}

function dayLabel(value) {
    const date = new Date(value);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

/** Fuzzy match: every word of the term must appear in order-free fashion; higher is better, 0 is no match. */
function score(text, term) {
    const haystack = String(text ?? '').toLowerCase();
    const words = term.toLowerCase().trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) return 1;

    let total = 0;
    for (const word of words) {
        const index = haystack.indexOf(word);
        if (index === 0) total += 100;
        else if (index > 0) total += /[\s_.\-:]/.test(haystack[index - 1]) ? 80 : 60;
        else {
            // characters in order (fewer gaps first), starting at the beginning of a word: "rtd" finds "retention_days"
            let at = 0;
            let last = -1;
            let gaps = 0;
            const start = [...haystack].findIndex((char, i) => char === word[0] && (i === 0 || /[\s_.\-:]/.test(haystack[i - 1])));
            if (start < 0) return 0;
            for (let i = start; i < haystack.length && at < word.length; i++) {
                if (haystack[i] === word[at]) {
                    if (last >= 0) gaps += i - last - 1;
                    last = i;
                    at++;
                }
            }
            if (at < word.length || gaps > word.length * 2) return 0;
            total += Math.max(1, 30 - gaps);
        }
    }
    return total;
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
}

/** History values: strings as they are, everything else JSON. */
function parseStored(value) {
    if (value === null || value === undefined) return { ok: false, value: null };
    try {
        return { ok: true, value: JSON.parse(value) };
    } catch {
        return { ok: false, value };
    }
}

const isObject = (value) => value !== null && typeof value === 'object';

/**
 * Line-by-line changes between two JSON values (objects by key, arrays by item), or null when
 * either side is a plain value and a simple "old → new" says it better.
 */
function structuredDiff(before, after) {
    if (!(isObject(before) || before === null) || !(isObject(after) || after === null)) return null;
    if (before === null && after === null) return null;

    const from = before ?? (Array.isArray(after) ? [] : {});
    const to = after ?? (Array.isArray(before) ? [] : {});

    if (Array.isArray(from) && Array.isArray(to)) {
        const a = from.map((item) => JSON.stringify(item));
        const b = to.map((item) => JSON.stringify(item));
        return [
            ...a.filter((item) => !b.includes(item)).map((text) => ({ type: 'remove', text })),
            ...b.filter((item) => !a.includes(item)).map((text) => ({ type: 'add', text })),
        ];
    }

    if (Array.isArray(from) || Array.isArray(to)) return null;

    const lines = [];
    for (const key of [...new Set([...Object.keys(from), ...Object.keys(to)])]) {
        const old = JSON.stringify(from[key]);
        const next = JSON.stringify(to[key]);
        if (!(key in to)) lines.push({ type: 'remove', text: `${key}: ${old}` });
        else if (!(key in from)) lines.push({ type: 'add', text: `${key}: ${next}` });
        else if (old !== next) lines.push({ type: 'change', text: `${key}: ${old} → ${next}` });
    }
    return lines;
}

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
        if (row.sensitive) return 'password';

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

    isColor(value) {
        return typeof value === 'string' && /^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value.trim());
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

const DRIFT_KEYS = ['missing', 'missing_features', 'type_mismatch', 'stale', 'stale_features', 'flags_in_settings', 'missing_records', 'unknown_scope_types'];
const driftIssues = (report) => DRIFT_KEYS.reduce((sum, key) => sum + (report?.[key]?.length ?? 0), 0);

const isTyping = (target) =>
    target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

let editorIds = 0;

/* -------------------------------------------------------------------------- */
/* Store                                                                      */
/* -------------------------------------------------------------------------- */

/**
 * x-include="template-id": copy a shared <template> (the value editor) into the element. Done once
 * before Alpine starts, inside <template> contents too, so Alpine initialises it like any markup.
 */
function expandIncludes(root) {
    root.querySelectorAll('[x-include]').forEach((element) => {
        element.appendChild(document.getElementById(element.getAttribute('x-include')).content.cloneNode(true));
        element.removeAttribute('x-include');
    });
    root.querySelectorAll('template').forEach((template) => expandIncludes(template.content));
}

expandIncludes(document);

document.addEventListener('alpine:init', () => {
    Alpine.store('s', {
        config,
        nav: NAV,
        icons: ICONS,
        mod: /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl',
        route: parseRoute(),
        groups: [],
        settings: null,
        features: null,
        archived: null,
        driftCount: null,
        toasts: [],
        meta: { open: false },
        dialog: { open: false },
        dirty: {},
        palette: false,
        shortcuts: false,
        navOpen: false,
        theme: 'system',

        get canManage() {
            return config.canManage;
        },

        get dirtyCount() {
            return Object.keys(this.dirty).length;
        },

        /** The key whose detail drawer is open: "setting:key", "feature:key" or ''. */
        get drawerId() {
            const { view, params } = this.route;
            if ((view === 'settings' || view === 'flags') && params[0]) return `${view === 'flags' ? 'feature' : 'setting'}:${params[0]}`;
            return '';
        },

        /**
         * In-page confirmation (instead of window.confirm). Resolves true on confirm,
         * false on cancel, Escape or a click outside.
         */
        confirm({ title, message = '', confirmLabel = 'Confirm', danger = false }) {
            this.dialog.resolve?.(false);
            return new Promise((resolve) => {
                this.dialog = { open: true, title, message, confirmLabel, danger, resolve };
            });
        },

        closeDialog(result) {
            const resolve = this.dialog.resolve;
            this.dialog = { open: false };
            resolve?.(result);
        },

        toast(message, kind = 'success', { undo = null } = {}) {
            const id = Date.now() + Math.random();
            // canUndo for the template: Alpine calls functions an expression returns, so never bind `undo` itself.
            this.toasts.push({ id, message, kind, undo, canUndo: typeof undo === 'function' });
            setTimeout(() => this.dismiss(id), kind === 'error' ? 7000 : undo ? 8000 : 3000);
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },

        async runUndo(toast) {
            this.dismiss(toast.id);
            try {
                await toast.undo();
                this.toast('Undone');
            } catch (error) {
                this.fail(error);
            }
        },

        fail(error) {
            this.toast(error.message ?? String(error), 'error');
        },

        markDirty(id, dirty, label) {
            if (dirty) this.dirty = { ...this.dirty, [id]: label };
            else if (id in this.dirty) {
                const { [id]: _removed, ...rest } = this.dirty;
                this.dirty = rest;
            }
        },

        /* ---------------------------------------------------------- data */

        async loadGroups() {
            this.groups = (await api('GET', '/groups')).data;
        },

        groupName(id) {
            return this.groups.find((group) => group.id === id)?.name ?? 'Ungrouped';
        },

        async loadSettings() {
            this.settings = (await api('GET', '/settings')).data;
            return this.settings;
        },

        async loadFeatures() {
            this.features = (await api('GET', '/features')).data;
            return this.features;
        },

        async loadArchived() {
            this.archived = (await api('GET', '/features?archived=1')).data;
            return this.archived;
        },

        /** Load the setting and flag lists once (palette, drawer); views refresh them themselves. */
        async ensureCatalog() {
            await Promise.all([this.settings ? null : this.loadSettings(), this.features ? null : this.loadFeatures()]);
        },

        async loadDrift() {
            try {
                this.driftCount = driftIssues((await api('GET', '/drift')).data);
            } catch {
                this.driftCount = null;
            }
        },

        row(kind, key) {
            const find = (list) => list?.find((row) => row.key === key) ?? null;
            return kind === 'feature' ? find(this.features) ?? find(this.archived) : find(this.settings);
        },

        /** Put an updated row into the cached list it belongs to (null removes the key). */
        setRow(kind, row, key = row?.key) {
            const lists = kind === 'feature' ? ['features', 'archived'] : ['settings'];
            const target = !row ? null : kind !== 'feature' ? 'settings' : row.archived ? 'archived' : 'features';
            for (const list of lists) {
                if (!this[list]) continue;
                if (list !== target) {
                    this[list] = this[list].filter((item) => item.key !== key);
                } else if (this[list].some((item) => item.key === row.key)) {
                    this[list] = this[list].map((item) => (item.key === row.key ? row : item));
                } else {
                    this[list] = [...this[list], row];
                }
            }
        },

        async saveSetting(key, value) {
            const { data } = await api('PUT', `/settings/${enc(key)}`, { value });
            this.setRow('setting', data, key);
            return data;
        },

        async forgetSetting(key) {
            const { data } = await api('DELETE', `/settings/${enc(key)}`);
            this.setRow('setting', data, key);
            return data;
        },

        /** Set a flag's global state, with an Undo on the toast. */
        async setFeature(row, state, { undoable = true } = {}) {
            const previous = row.global;
            const { data } = await api('PUT', `/features/${enc(row.key)}`, { state });
            this.setRow('feature', data, row.key);
            if (undoable) {
                this.toast(`${row.label}: ${stateText(state)}`, 'success', {
                    undo: () => this.setFeature(data, previous, { undoable: false }),
                });
            }
            return data;
        },

        /** Change a flag's schedule, rollout or required flags, with an Undo on the toast. */
        async saveRules(row, changes, { undoable = true } = {}) {
            const previous = row.rules ?? {};
            const { data } = await api('PUT', `/features/${enc(row.key)}/rules`, changes);
            this.setRow('feature', data, row.key);
            if (undoable) {
                const back = Object.fromEntries(Object.keys(changes).map((name) => [name, previous[name] ?? null]));
                this.toast(`${row.label}: rules saved`, 'success', { undo: () => this.saveRules(data, back, { undoable: false }) });
            }
            return data;
        },

        async archiveFeature(row, archived, { undoable = true } = {}) {
            const { data } = await api('POST', `/features/${enc(row.key)}/${archived ? 'archive' : 'restore'}`);
            // The row moves between the two lists: both must be loaded or it would vanish.
            await Promise.all([this.features ?? this.loadFeatures(), this.archived ?? this.loadArchived()]);
            this.setRow('feature', data, row.key);
            if (undoable) {
                this.toast(`${row.label}: ${archived ? 'archived' : 'restored'}`, 'success', {
                    undo: () => this.archiveFeature(data, !archived, { undoable: false }),
                });
            }
            return data;
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

        openKey(kind, key) {
            go(keyPath(kind, key));
        },

        openRecord(type, id) {
            go(recordPath(type, id));
        },

        /* ---------------------------------------------------------- theme */

        get dark() {
            return this.theme === 'dark' || (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        },

        setTheme(theme) {
            this.theme = theme;
            remember('setanjo.theme', theme);
            applyTheme(this);
        },

        cycleTheme() {
            this.setTheme({ system: 'light', light: 'dark', dark: 'system' }[this.theme] ?? 'system');
        },
    });

    function remember(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch {
            // private mode: not remembered
        }
    }

    function recall(key) {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    }

    function applyTheme(store) {
        document.documentElement.classList.toggle('dark', store.dark);
    }

    /* ------------------------------------------------------------------ */
    /* App shell: routing, keyboard shortcuts                             */
    /* ------------------------------------------------------------------ */

    Alpine.data('setanjo', () => ({
        pendingG: false,

        init() {
            const store = this.$store.s;
            store.theme = ['light', 'dark', 'system'].includes(recall('setanjo.theme')) ? recall('setanjo.theme') : 'system';
            applyTheme(store);
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme(store));

            let current = location.hash;
            window.addEventListener('hashchange', async () => {
                const next = parseRoute();
                // Opening a drawer keeps the list behind it, so only leaving the screen, or closing / switching
                // a drawer or record, drops the editors.
                const old = store.route;
                const leaving = next.view !== old.view || (old.params.length > 0 && next.params.join('/') !== old.params.join('/'));

                if (leaving && store.dirtyCount > 0) {
                    const target = location.hash;
                    history.replaceState(null, '', current || '#/');
                    const discard = await store.confirm({
                        title: 'Discard unsaved changes?',
                        message: `${plural(store.dirtyCount, 'field')} ${store.dirtyCount === 1 ? 'has' : 'have'} changes that are not saved yet.`,
                        confirmLabel: 'Discard changes',
                        danger: true,
                    });
                    if (!discard) return;
                    store.dirty = {};
                    history.replaceState(null, '', target);
                    current = target;
                    store.route = parseRoute(target);
                    store.navOpen = false;
                    return;
                }

                current = location.hash;
                store.route = next;
                store.navOpen = false;
            });

            window.addEventListener('beforeunload', (event) => {
                if (store.dirtyCount > 0) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            store.loadGroups().catch((error) => store.fail(error));
            store.loadDrift();
        },

        get page() {
            return NAV.find((item) => item.view === this.$store.s.route.view) ?? NAV[0];
        },

        onKey(event) {
            const store = this.$store.s;

            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                store.palette = !store.palette;
                return;
            }

            if (store.palette || store.dialog.open || store.meta.open || isTyping(event.target) || event.metaKey || event.ctrlKey || event.altKey) return;

            if (this.pendingG) {
                this.pendingG = false;
                const item = NAV.find((entry) => entry.key === event.key.toLowerCase());
                if (item) {
                    event.preventDefault();
                    go(`#/${item.view}`);
                }
                return;
            }

            if (event.key === 'g') {
                this.pendingG = true;
                setTimeout(() => (this.pendingG = false), 1200);
            } else if (event.key === '/') {
                const input = document.querySelector('[data-search]');
                if (input) {
                    event.preventDefault();
                    input.focus();
                    input.select?.();
                }
            } else if (event.key === '?') {
                event.preventDefault();
                store.shortcuts = true;
            }
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Shared value editor (settings list, overrides, drawer)             */
    /* ------------------------------------------------------------------ */

    /**
     * x-data="valueEditor(() => row, 'value', save, remove, 'global')" x-include="s-value-editor"
     *
     * getRow is a getter so the editor follows the parent's row after it is replaced. mode is
     * 'global' (a setting's own value) or 'override' (a record's override, empty = inherited).
     */
    Alpine.data('valueEditor', (getRow, field, save, remove, mode = 'global') => ({
        draft: '',
        original: '',
        error: null,
        saving: false,
        reveal: false,
        mode,
        editor,
        uid: `editor-${++editorIds}`,

        init() {
            this.reset(this.row[field]);
            this.$watch('draft', () => this.$store.s.markDirty(this.uid, this.dirty && !this.instant, this.row.label));

            // Follow changes made elsewhere (drawer, undo, another list) unless this field has its own edits.
            let seen = JSON.stringify(this.row[field] ?? null);
            this.$watch('row', () => {
                const now = JSON.stringify(this.row?.[field] ?? null);
                if (now === seen) return;
                seen = now;
                if (!this.dirty && !this.saving) this.reset(this.row[field]);
            });
        },

        destroy() {
            this.$store.s.markDirty(this.uid, false);
        },

        get row() {
            return getRow();
        },

        get kind() {
            return editor.kind(this.row);
        },

        /** Inputs that save as soon as they change (no Save button). */
        get instant() {
            return this.kind === 'toggle' || (this.mode === 'override' && this.kind === 'select');
        },

        get dirty() {
            return this.draft !== this.original;
        },

        get disabled() {
            // Rows of a deleted record (missing) can only be cleared, not edited.
            return !config.canManage || this.row.readonly || this.row.allowed === false || this.row.missing === true || this.saving;
        },

        get jsonError() {
            if (this.kind !== 'json' || String(this.draft).trim() === '') return null;
            try {
                JSON.parse(this.draft);
                return null;
            } catch (error) {
                return error.message.replace(/^JSON\.parse: /, '');
            }
        },

        get placeholder() {
            if (this.row.sensitive) return 'Hidden — type to replace';
            if (this.mode === 'override' && !this.row.overridden) return 'Inherited';
            if (this.kind === 'json') return 'JSON';
            return this.row.meta?.placeholder ?? '';
        },

        reset(value) {
            this.draft = this.original = editor.draft(this.row, value);
            this.error = null;
        },

        revert() {
            this.draft = this.original;
            this.error = null;
        },

        format() {
            try {
                this.draft = JSON.stringify(JSON.parse(this.draft), null, 2);
            } catch {
                // keep as typed; the error is shown below
            }
        },

        /** What to write back on Undo: the value before the change, or "not stored". Sensitive values cannot be undone. */
        previous() {
            const row = this.row;
            if (row.masked || row.sensitive) return null;
            if (this.mode === 'override') return row.overridden ? { set: true, value: row.override } : { set: false };
            return row.stored ? { set: true, value: row.value } : { set: false };
        },

        async submit() {
            if (this.disabled) return;
            this.error = null;
            this.saving = true;
            const before = this.previous();
            const label = this.row.label;
            try {
                const updated = await save(editor.parse(this.row, this.draft));
                this.reset(updated?.[field]);
                this.$store.s.toast(`Saved ${label}`, 'success', {
                    undo: before ? async () => this.reset((before.set ? await save(before.value) : await remove())?.[field]) : null,
                });
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

        async set(value) {
            if (this.disabled) return;
            this.draft = value;
            await this.submit();
        },

        async clear() {
            this.error = null;
            this.saving = true;
            const label = this.row.label; // the row may leave the list (deleted record's last override)
            const before = this.previous();
            try {
                const updated = await remove();
                this.reset(updated?.[field]);
                this.$store.s.toast(`Reset ${label}`, 'success', {
                    undo: before?.set ? async () => this.reset((await save(before.value))?.[field]) : null,
                });
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        onEnter(event) {
            if (this.kind === 'json' || this.kind === 'textarea') {
                if (event.metaKey || event.ctrlKey) this.submit();
                return;
            }
            event.preventDefault();
            this.submit();
        },

        onEscape(event) {
            if (!this.dirty) return;
            event.stopPropagation();
            this.revert();
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Overview                                                           */
    /* ------------------------------------------------------------------ */

    Alpine.data('overviewView', () => ({
        data: null,
        history: [],
        failed: null,
        ago,
        exact,

        async init() {
            await this.load();
        },

        async load() {
            this.failed = null;
            try {
                const [overview, history] = await Promise.all([
                    api('GET', '/overview'),
                    config.history ? api('GET', '/history?per_page=6') : Promise.resolve({ data: [] }),
                ]);
                this.data = overview.data;
                this.history = history.data ?? [];
            } catch (error) {
                this.failed = error.message;
            }
        },

        percent(part, total) {
            return total > 0 ? Math.round((part / total) * 100) : 0;
        },

        get overrideTotal() {
            return (this.data?.scopes ?? []).reduce((sum, type) => sum + type.settings + type.features, 0);
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Settings                                                           */
    /* ------------------------------------------------------------------ */

    Alpine.data('settingsView', () => ({
        loading: false,
        failed: null,
        search: '',
        group: '',
        type: '',
        editor,

        async init() {
            await this.load();
        },

        async load() {
            this.loading = this.$store.s.settings === null;
            this.failed = null;
            try {
                await this.$store.s.loadSettings();
            } catch (error) {
                if (this.$store.s.settings === null) this.failed = error.message;
                else this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        get rows() {
            return this.$store.s.settings ?? [];
        },

        get types() {
            return [...new Set(this.rows.map((row) => row.type))].sort();
        },

        get filtering() {
            return this.search !== '' || this.group !== '' || this.type !== '';
        },

        matches(row) {
            const haystack = [row.key, row.label, row.description, ...(row.meta?.tags ?? [])].join(' ');

            return (this.search === '' || score(haystack, this.search) > 0) && (this.type === '' || row.type === this.type);
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

        get shown() {
            return this.sections.reduce((sum, section) => sum + section.rows.length, 0);
        },

        clearFilters() {
            this.search = '';
            this.group = '';
            this.type = '';
        },

        initRows(element) {
            sortable(element, {
                group: 'settings',
                handle: '.s-handle',
                onSorted: async ({ to, keys }) => {
                    const store = this.$store.s;
                    const groupId = groupValue(to);
                    store.settings = store.settings.map((row) =>
                        keys.includes(row.key) ? { ...row, group_id: groupId, sort_order: keys.indexOf(row.key) } : row,
                    );
                    try {
                        store.settings = (await api('POST', '/settings/order', { group_id: groupId, keys })).data;
                    } catch (error) {
                        store.fail(error);
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
            this.$store.s.openMeta('settings', row, (updated) => this.$store.s.setRow('setting', updated));
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Feature flags                                                      */
    /* ------------------------------------------------------------------ */

    Alpine.data('flagsView', () => ({
        loading: false,
        failed: null,
        search: '',
        status: '',
        busy: null,
        ruleBadges,
        limited,

        async init() {
            this.$watch('status', (status) => status === 'archived' && this.loadArchived());
            await this.load();
        },

        async loadArchived() {
            this.loading = this.$store.s.archived === null;
            try {
                await this.$store.s.loadArchived();
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        async restore(row) {
            if (!config.canManage) return;
            this.busy = row.key;
            try {
                await this.$store.s.archiveFeature(row, false);
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        async load() {
            this.loading = this.$store.s.features === null;
            this.failed = null;
            try {
                await this.$store.s.loadFeatures();
            } catch (error) {
                if (this.$store.s.features === null) this.failed = error.message;
                else this.$store.s.fail(error);
            } finally {
                this.loading = false;
            }
        },

        get rows() {
            return (this.status === 'archived' ? this.$store.s.archived : this.$store.s.features) ?? [];
        },

        get filtering() {
            return this.search !== '' || this.status !== '';
        },

        matches(row) {
            const status =
                this.status === '' ||
                (this.status === 'on' && row.enabled) ||
                (this.status === 'off' && !row.enabled) ||
                (this.status === 'overridden' && row.override_count > 0) ||
                (this.status === 'scheduled' && row.window !== null) ||
                (this.status === 'rollout' && (row.rules?.rollout ?? 100) < 100) ||
                this.status === 'archived';

            return (this.search === '' || score([row.key, row.label, row.description].join(' '), this.search) > 0) && status;
        },

        get sections() {
            const groups = [...this.$store.s.groups, { id: null, name: 'Ungrouped' }];

            return groups
                .map((group) => ({
                    ...group,
                    rows: this.rows
                        .filter((row) => row.group_id === group.id && this.matches(row))
                        .sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label)),
                }))
                .filter((section) => section.rows.length > 0 || (!this.filtering && section.id !== null && this.rows.length > 0));
        },

        get shown() {
            return this.sections.reduce((sum, section) => sum + section.rows.length, 0);
        },

        async set(row, state) {
            if (!config.canManage || row.global === state) return;
            this.busy = row.key;
            try {
                await this.$store.s.setFeature(row, state);
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
                    const store = this.$store.s;
                    const groupId = groupValue(to);
                    store.features = store.features.map((row) =>
                        keys.includes(row.key) ? { ...row, group_id: groupId, sort_order: keys.indexOf(row.key) } : row,
                    );
                    try {
                        store.features = (await api('POST', '/features/order', { group_id: groupId, keys })).data;
                    } catch (error) {
                        store.fail(error);
                        await this.load();
                    }
                },
            });
        },

        editMeta(row) {
            this.$store.s.openMeta('features', row, (updated) => this.$store.s.setRow('feature', updated));
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Key detail drawer (#/settings/{key}, #/flags/{key})                */
    /* ------------------------------------------------------------------ */

    Alpine.data('keyDrawer', () => ({
        overrides: [],
        overridesMeta: null,
        overridesPage: 1,
        overridesType: '',
        loadingOverrides: false,
        history: [],
        busy: null,
        copied: null,
        add: { type: config.scopes[0]?.alias ?? '', search: '', results: [], loading: false },
        bulk: { type: config.scopes[0]?.alias ?? '', text: '', state: 'true', preview: null, running: false },
        rules: { from: '', until: '', rollout: '', requires: [], add: '' },
        timer: null,
        editor,
        ago,
        exact,
        plural,
        ruleBadges,

        init() {
            this.$watch('$store.s.drawerId', () => this.refresh());
            this.$watch('row', () => this.rulesDirty || this.resetRules());
            this.refresh();
        },

        /* --- flag rules ------------------------------------------------- */

        draftOf(rules) {
            return {
                from: toLocalInput(rules?.enabled_from),
                until: toLocalInput(rules?.enabled_until),
                rollout: rules?.rollout ?? '',
                requires: [...(rules?.requires ?? [])],
            };
        },

        resetRules() {
            this.rules = { ...this.draftOf(this.row?.rules), add: '' };
        },

        get rulesDirty() {
            if (this.kind !== 'feature' || !this.row) return false;
            const saved = this.draftOf(this.row.rules);
            const draft = this.rules;
            return (
                draft.from !== saved.from ||
                draft.until !== saved.until ||
                rolloutValue(draft.rollout) !== rolloutValue(saved.rollout) ||
                draft.requires.join('\n') !== saved.requires.join('\n')
            );
        },

        get requireOptions() {
            return (this.$store.s.features ?? []).filter((flag) => flag.key !== this.key && !this.rules.requires.includes(flag.key));
        },

        flagLabel(key) {
            return this.$store.s.row('feature', key)?.label ?? key;
        },

        addRequirement() {
            if (this.rules.add) this.rules.requires = [...this.rules.requires, this.rules.add];
            this.rules.add = '';
        },

        async saveRules() {
            if (!config.canManage || !this.row) return;
            this.busy = 'rules';
            try {
                await this.$store.s.saveRules(this.row, {
                    enabled_from: fromLocalInput(this.rules.from),
                    enabled_until: fromLocalInput(this.rules.until),
                    rollout: rolloutValue(this.rules.rollout),
                    requires: this.rules.requires,
                });
                this.resetRules();
                await this.loadHistory();
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        async setArchived(archived) {
            if (!config.canManage || !this.row) return;
            this.busy = 'archive';
            try {
                await this.$store.s.archiveFeature(this.row, archived);
                await this.loadHistory();
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        get open() {
            return this.$store.s.drawerId !== '';
        },

        get kind() {
            return this.$store.s.drawerId.split(':')[0] || null;
        },

        get key() {
            return this.$store.s.route.params[0] ?? null;
        },

        get row() {
            return this.key ? this.$store.s.row(this.kind, this.key) : null;
        },

        get notFound() {
            const loaded = this.kind === 'feature' ? this.$store.s.features !== null && this.$store.s.archived !== null : this.$store.s.settings !== null;
            return this.open && loaded && this.row === null;
        },

        async refresh() {
            if (!this.open) return;
            this.overrides = [];
            this.overridesMeta = null;
            this.overridesPage = 1;
            this.overridesType = '';
            this.history = [];
            this.add.search = '';
            this.add.results = [];
            this.bulk.preview = null;
            this.bulk.text = '';
            try {
                await this.$store.s.ensureCatalog();
                // A deep link to an archived flag: it is only in the archived list.
                if (this.kind === 'feature' && this.row === null && this.$store.s.archived === null) {
                    await this.$store.s.loadArchived();
                }
            } catch (error) {
                this.$store.s.fail(error);
            }
            this.resetRules();
            await Promise.all([this.loadOverrides(), this.loadHistory()]);
            this.$nextTick(() => this.$refs.drawerClose?.focus());
        },

        close() {
            if (!this.open) return;
            go(`#/${this.$store.s.route.view}`);
        },

        get base() {
            return `/${this.kind === 'feature' ? 'features' : 'settings'}/${enc(this.key)}`;
        },

        async loadOverrides() {
            if (!this.open) return;
            this.loadingOverrides = true;
            try {
                const response = await api('GET', `${this.base}/overrides${query({ page: this.overridesPage, scope_type: this.overridesType })}`);
                this.overrides = response.data;
                this.overridesMeta = response.meta;
            } catch (error) {
                this.overrides = [];
                if (error.status !== 404) this.$store.s.fail(error);
            } finally {
                this.loadingOverrides = false;
            }
        },

        pageTo(page) {
            this.overridesPage = page;
            this.loadOverrides();
        },

        filterOverrides() {
            this.overridesPage = 1;
            this.loadOverrides();
        },

        async loadHistory() {
            if (!config.history || !this.open) return;
            try {
                this.history = (await api('GET', `/history${query({ key: this.key, per_page: 5 })}`)).data;
            } catch {
                this.history = [];
            }
        },

        async reloadAll() {
            const store = this.$store.s;
            await Promise.all([this.kind === 'feature' ? store.loadFeatures() : store.loadSettings(), this.loadOverrides(), this.loadHistory()]);
        },

        /* --- global value ----------------------------------------------- */

        save() {
            return (value) => this.$store.s.saveSetting(this.key, value);
        },

        remove() {
            return () => this.$store.s.forgetSetting(this.key);
        },

        async setGlobal(state) {
            if (!config.canManage || !this.row || this.row.global === state) return;
            this.busy = 'global';
            try {
                await this.$store.s.setFeature(this.row, state);
                await this.loadHistory();
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        editMeta() {
            const kind = this.kind;
            this.$store.s.openMeta(kind === 'feature' ? 'features' : 'settings', this.row, (updated) => this.$store.s.setRow(kind, updated));
        },

        /* --- per-record overrides --------------------------------------- */

        async setOverride(scope, state) {
            if (!config.canManage) return;
            this.busy = `${scope.type}:${scope.id}`;
            try {
                if (this.kind === 'feature') {
                    await api('PUT', this.base, { state, scope_type: scope.type, scope_id: scope.id });
                } else {
                    await api('DELETE', this.base, { scope_type: scope.type, scope_id: scope.id });
                }
                this.$store.s.toast(state === null ? `Removed the override of ${scope.label}` : `${this.row?.label}: ${stateText(state)} for ${scope.label}`);
                await this.reloadAll();
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        searchLater() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.searchRecords(), 250);
        },

        async searchRecords() {
            const term = this.add.search.trim();
            if (term === '' || !this.add.type) {
                this.add.results = [];
                return;
            }
            this.add.loading = true;
            try {
                this.add.results = (await api('GET', `/scopes/${this.add.type}${query({ search: term })}`)).data.slice(0, 8);
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.add.loading = false;
            }
        },

        currentOverride(record) {
            return this.overrides.find((item) => item.scope.type === record.type && String(item.scope.id) === String(record.id))?.value ?? null;
        },

        /* --- bulk (paste ids) --------------------------------------------- */

        get bulkIds() {
            return [...new Set(this.bulk.text.split(/[\s,;]+/).map((id) => id.trim()).filter(Boolean))];
        },

        get bulkState() {
            return { true: true, false: false, null: null }[this.bulk.state];
        },

        async runBulk(dryRun) {
            if (this.bulkIds.length === 0) return;
            this.bulk.running = true;
            try {
                const { data } = await api('POST', `${this.base}/overrides`, {
                    scope_type: this.bulk.type,
                    ids: this.bulkIds,
                    state: this.bulkState,
                    dry_run: dryRun,
                });
                if (dryRun) {
                    this.bulk.preview = data;
                } else {
                    this.bulk.preview = null;
                    this.bulk.text = '';
                    this.$store.s.toast(`${this.row?.label}: ${stateText(this.bulkState)} for ${plural(data.changed, 'record')}`);
                    await this.reloadAll();
                }
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.bulk.running = false;
            }
        },

        /* --- history ------------------------------------------------------ */

        async revert(entry) {
            const confirmed = await this.$store.s.confirm({
                title: 'Revert this change?',
                message: `${entry.key}${entry.scope ? ` for ${entry.scope.label}` : ''} goes back to the value it had before this change (${ago(entry.created_at)}). The revert is recorded as a new change.`,
                confirmLabel: 'Revert',
            });
            if (!confirmed) return;
            try {
                await api('POST', `/history/${entry.id}/revert`);
                this.$store.s.toast(`Reverted ${entry.key}`);
                await this.reloadAll();
            } catch (error) {
                this.$store.s.fail(error);
            }
        },

        /* --- code --------------------------------------------------------- */

        get snippets() {
            const key = this.key;
            if (this.kind === 'feature') {
                return [
                    { label: 'Check', code: `Settings::feature('${key}')->enabled()` },
                    { label: 'For a record', code: `Settings::feature('${key}')->for($user)->enabled()` },
                    { label: 'Blade', code: `@feature('${key}') … @endfeature` },
                    { label: 'Route middleware', code: `->middleware('${config.featureMiddleware}:${key}')` },
                ];
            }
            return [
                { label: 'Read', code: `Settings::get('${key}')` },
                { label: 'For a record', code: `Settings::for($user)->get('${key}')` },
                { label: 'Write', code: `Settings::set('${key}', $value)` },
                { label: 'Blade', code: `@setting('${key}')` },
            ];
        },

        async copy(text) {
            await copyText(text);
            this.copied = text;
            setTimeout(() => (this.copied = null), 1500);
        },

        change: historyChange,
        display: historyDisplay,
    }));

    /* ------------------------------------------------------------------ */
    /* Command palette                                                    */
    /* ------------------------------------------------------------------ */

    Alpine.data('palette', () => ({
        term: '',
        active: 0,
        records: [],
        timer: null,
        icons: ICONS,

        init() {
            this.$watch('$store.s.palette', (open) => {
                if (!open) return;
                this.term = '';
                this.active = 0;
                this.records = [];
                this.$store.s.ensureCatalog().catch(() => {});
                this.$nextTick(() => this.$refs.paletteInput?.focus());
            });
            this.$watch('term', () => {
                this.active = 0;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.searchRecords(), 200);
            });
        },

        close() {
            this.$store.s.palette = false;
        },

        async searchRecords() {
            const term = this.term.trim();
            if (term.length < 2 || config.scopes.length === 0) {
                this.records = [];
                return;
            }
            const results = await Promise.all(
                config.scopes.map((type) =>
                    api('GET', `/scopes/${type.alias}${query({ search: term })}`)
                        .then((response) => response.data.slice(0, 4).map((record) => ({ ...record, typeName: type.name })))
                        .catch(() => []),
                ),
            );
            if (term === this.term.trim()) this.records = results.flat();
        },

        get items() {
            const store = this.$store.s;
            const term = this.term.trim();
            const items = [];

            NAV.forEach((item) =>
                items.push({ section: 'Go to', label: item.label, hint: `g ${item.key}`, icon: item.icon, text: item.label, run: () => go(`#/${item.view}`) }),
            );

            (store.settings ?? []).forEach((row) =>
                items.push({
                    section: 'Settings',
                    label: row.label,
                    detail: row.key,
                    hint: editor.display(row, row.value),
                    icon: 'sliders',
                    text: `${row.label} ${row.key} ${row.description ?? ''} ${(row.meta?.tags ?? []).join(' ')}`,
                    run: () => store.openKey('setting', row.key),
                }),
            );

            (store.features ?? []).forEach((row) => {
                items.push({
                    section: 'Feature flags',
                    label: row.label,
                    detail: row.key,
                    hint: row.enabled ? 'On' : 'Off',
                    icon: 'flag',
                    text: `${row.label} ${row.key} ${row.description ?? ''}`,
                    run: () => store.openKey('feature', row.key),
                });
                if (config.canManage && term !== '') {
                    const next = !row.enabled;
                    items.push({
                        section: 'Actions',
                        label: `Turn ${next ? 'on' : 'off'} ${row.label}`,
                        detail: 'everywhere it is not overridden',
                        icon: 'bolt',
                        text: `turn ${next ? 'on' : 'off'} toggle enable disable ${row.label} ${row.key}`,
                        run: () => store.setFeature(row, next).catch((error) => store.fail(error)),
                    });
                }
            });

            store.groups.forEach((group) =>
                items.push({ section: 'Groups', label: group.name, detail: group.slug, icon: 'folder', text: `${group.name} ${group.slug}`, run: () => go('#/groups') }),
            );

            items.push(
                { section: 'Actions', label: 'Check for drift', icon: 'scan', text: 'drift sync check definitions', run: () => go('#/drift') },
                { section: 'Actions', label: 'Export everything as JSON', icon: 'swap', text: 'export download backup json', run: () => (location.href = `${config.api}/export`) },
                { section: 'Actions', label: 'Switch theme', icon: 'moon', text: 'theme dark light mode', run: () => store.cycleTheme() },
                { section: 'Actions', label: 'Keyboard shortcuts', icon: 'keyboard', text: 'keyboard shortcuts help keys', run: () => (store.shortcuts = true) },
            );

            this.records.forEach((record) =>
                items.push({
                    section: 'Records',
                    label: record.label,
                    detail: `${record.typeName} #${record.id}`,
                    icon: 'users',
                    text: `${record.label} ${record.typeName} ${record.id} ${record.description ?? ''}`,
                    run: () => store.openRecord(record.type, record.id),
                }),
            );

            const scored = items
                .map((item) => ({ ...item, score: term === '' ? 1 : score(item.text, term) }))
                .filter((item) => item.score > 0);

            if (term === '') {
                // Start screen: screens and a few actions only.
                return scored.filter((item) => item.section === 'Go to' || (item.section === 'Actions' && !item.label.startsWith('Turn')));
            }

            const order = ['Settings', 'Feature flags', 'Records', 'Actions', 'Go to', 'Groups'];
            const best = (section) => Math.max(...scored.filter((item) => item.section === section).map((item) => item.score), 0);
            const sections = order.filter((section) => best(section) > 0).sort((a, b) => best(b) - best(a));

            return sections.flatMap((section) =>
                scored
                    .filter((item) => item.section === section)
                    .sort((a, b) => b.score - a.score)
                    .slice(0, section === 'Actions' ? 4 : 6),
            );
        },

        sectionStart(index) {
            const items = this.items;
            return index === 0 || items[index - 1].section !== items[index].section;
        },

        move(step) {
            const count = this.items.length;
            if (count === 0) return;
            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.$root.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' }));
        },

        choose(index = this.active) {
            const item = this.items[index];
            if (!item) return;
            this.close();
            item.run();
        },
    }));

    /* ------------------------------------------------------------------ */
    /* Overrides                                                          */
    /* ------------------------------------------------------------------ */

    Alpine.data('overridesView', () => ({
        ruleText,
        type: config.scopes[0]?.alias ?? '',
        search: '',
        records: [],
        overriddenList: true,
        page: 1,
        meta: null,
        loadingRecords: false,
        clearing: false,
        selected: null,
        scope: null,
        loading: false,
        failed: null,
        tab: 'features',
        filter: '',
        onlyOverridden: false,
        busy: null,
        editor,
        timer: null,

        async init() {
            this.$watch('$store.s.route', () => this.follow());
            await this.follow(true);
        },

        /** #/overrides/{type}/{id} picks the type and opens the record. */
        async follow(first = false) {
            const { view, params } = this.$store.s.route;
            if (view !== 'overrides') return;
            const [type, id] = params;

            if (type && config.scopes.some((item) => item.alias === type) && type !== this.type) {
                this.type = type;
                this.search = '';
                this.page = 1;
                await this.loadRecords();
            } else if (first && this.type) {
                await this.loadRecords();
            }

            if (type && id !== undefined) {
                if (!this.selected || this.selected.type !== type || String(this.selected.id) !== String(id)) await this.load(type, id);
            } else {
                this.selected = null;
                this.scope = null;
            }
        },

        async loadRecords() {
            this.loadingRecords = true;
            try {
                const params = this.search ? { search: this.search } : { page: this.page };
                const response = await api('GET', `/scopes/${this.type}${query(params)}`);
                this.records = response.data;
                this.overriddenList = response.overridden;
                this.meta = response.meta ?? null;
                if (this.meta) this.page = this.meta.page;
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
            this.page = 1;
            go(`#/overrides/${enc(this.type)}`);
            this.loadRecords();
        },

        goTo(page) {
            this.page = page;
            this.loadRecords();
        },

        plural,

        stateText(record) {
            if (record.state === 'missing') return 'deleted';
            if (record.state === 'hidden') return 'hidden by a global scope';
            return record.trashed ? 'trashed' : '';
        },

        get scopeKey() {
            return this.selected ? `${this.selected.type}:${this.selected.id}` : '';
        },

        get overrideCount() {
            if (!this.scope) return 0;
            return this.scope.settings.filter((row) => row.overridden).length + this.scope.features.filter((row) => row.override !== null).length;
        },

        async clearAll() {
            const label = this.scope.scope.label;
            const count = this.overrideCount;
            const confirmed = await this.$store.s.confirm({
                title: `Clear all overrides of ${label}?`,
                message: `${plural(count, 'override')} will be removed. ${label} falls back to inherited values.`,
                confirmLabel: 'Clear overrides',
                danger: true,
            });
            if (!confirmed) return;
            this.clearing = true;
            try {
                const { data } = await api('DELETE', `/scopes/${this.selected.type}/${enc(this.selected.id)}`);
                this.$store.s.toast(`Cleared overrides of ${label}`);
                if (data.scope.state === 'missing') {
                    go(`#/overrides/${enc(this.type)}`);
                } else {
                    this.scope = data;
                }
                await this.loadRecords();
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.clearing = false;
            }
        },

        select(record) {
            go(recordPath(record.type, record.id));
        },

        async load(type, id) {
            this.selected = { type, id };
            this.loading = true;
            this.failed = null;
            try {
                this.scope = (await api('GET', `/scopes/${type}/${enc(id)}`)).data;
                this.selected = { type, id: this.scope.scope.id };
                // A deleted record lists only what it stores: open the tab that has something.
                if (this.scope.features.length === 0 && this.scope.settings.length > 0) this.tab = 'settings';
                else if (this.scope.settings.length === 0 && this.scope.features.length > 0) this.tab = 'features';
            } catch (error) {
                this.scope = null;
                this.failed = error.message;
            } finally {
                this.loading = false;
            }
        },

        visible(rows, overridden) {
            return rows.filter(
                (row) => (this.filter === '' || score(`${row.key} ${row.label}`, this.filter) > 0) && (!this.onlyOverridden || overridden(row)),
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

        // A deleted record lists only stored overrides, so a cleared one comes back null: drop it.
        replace(list, updated, key, scopeKey) {
            if (!this.scope || scopeKey !== this.scopeKey) return; // another record is open now
            this.scope[list] = updated
                ? this.scope[list].map((row) => (row.key === updated.key ? updated : row))
                : this.scope[list].filter((row) => row.key !== key);
        },

        async setFlag(row, state, { undoable = true } = {}) {
            if (!config.canManage || !row.allowed || row.override === state || (row.missing && state !== null)) return;
            const params = this.params();
            const scopeKey = this.scopeKey;
            const label = this.scope.scope.label;
            const previous = row.override;
            this.busy = row.key;
            try {
                const { data } = await api('PUT', `/features/${enc(row.key)}`, { state, ...params });
                this.replace('features', data, row.key, scopeKey);
                if (undoable) {
                    this.$store.s.toast(`${row.label}: ${state === null ? 'inherit' : stateText(state)} for ${label}`, 'success', {
                        undo: async () => {
                            const { data: restored } = await api('PUT', `/features/${enc(row.key)}`, { state: previous, ...params });
                            this.replace('features', restored, row.key, scopeKey);
                        },
                    });
                }
            } catch (error) {
                this.$store.s.fail(error);
            } finally {
                this.busy = null;
            }
        },

        // Editors are keyed by record + key, so the params captured here always belong to their record.
        save(row) {
            const params = this.params();
            const scopeKey = this.scopeKey;
            return async (value) => {
                const { data } = await api('PUT', `/settings/${enc(row.key)}`, { value, ...params });
                this.replace('settings', data, row.key, scopeKey);
                return data;
            };
        },

        remove(row) {
            const params = this.params();
            const scopeKey = this.scopeKey;
            return async () => {
                const { data } = await api('DELETE', `/settings/${enc(row.key)}`, params);
                this.replace('settings', data, row.key, scopeKey);
                return data;
            };
        },

        sourceText(row) {
            if (row.source === 'scope') {
                const same =
                    row.source_scope && this.selected && row.source_scope.type === this.selected.type && String(row.source_scope.id) === String(this.selected.id);
                return same ? 'this record' : row.source_scope?.label ?? 'scope';
            }
            if (row.source === 'missing') return 'nowhere (record deleted)';
            return row.source;
        },

        /** The resolution chain as short steps: which level was checked, and which one won. */
        steps(row) {
            const chain = row.chain ?? [];
            const winner = chain.findIndex((level) => level.set);
            return chain.map((level, index) => ({
                label: level.level === 'scope' ? level.scope?.label ?? 'scope' : level.level === 'global' ? 'Global' : 'Default',
                value: level.set ? editor.display(row, level.value) : 'not set',
                set: level.set,
                winner: index === winner,
                skipped: winner >= 0 && index > winner,
            }));
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

        countOf(group) {
            const store = this.$store.s;
            const settings = (store.settings ?? []).filter((row) => row.group_id === group.id).length;
            const features = (store.features ?? []).filter((row) => row.group_id === group.id).length;
            return [settings ? plural(settings, 'setting') : '', features ? plural(features, 'flag') : ''].filter(Boolean).join(' · ') || 'Empty';
        },

        init() {
            this.$store.s.ensureCatalog().catch(() => {});
        },

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
            const confirmed = await this.$store.s.confirm({
                title: `Delete the group “${group.name}”?`,
                message: 'Its settings and flags become ungrouped. The settings themselves are kept.',
                confirmLabel: 'Delete group',
                danger: true,
            });
            if (!confirmed) return;
            try {
                await api('DELETE', `/groups/${group.id}`);
                await this.$store.s.loadGroups();
                await Promise.all([this.$store.s.loadSettings(), this.$store.s.loadFeatures()]);
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
        failed: null,
        options: { prune: false, force_types: false, reset_meta: false, reset_groups: false },
        output: '',
        previewed: false,
        running: false,

        async init() {
            await this.load();
            this.$watch('options', () => (this.previewed = false), { deep: true });
        },

        async load() {
            this.failed = null;
            try {
                this.report = (await api('GET', '/drift')).data;
                this.$store.s.driftCount = driftIssues(this.report);
            } catch (error) {
                this.failed = error.message;
            }
        },

        get clean() {
            return this.report && driftIssues(this.report) === 0;
        },

        get cards() {
            const r = this.report;
            if (!r) return [];
            return [
                { key: 'missing', title: 'Missing settings', help: 'Defined in code, no global row yet. Sync creates them with the default.', list: r.missing.map((i) => i.key) },
                { key: 'missing_features', title: 'Missing flags', help: 'Defined as features, no flag row yet.', list: r.missing_features.map((i) => i.key) },
                { key: 'type_mismatch', title: 'Type mismatches', help: 'Stored with a different type. Sync with “Convert types”.', list: r.type_mismatch.map((i) => `${i.key}: ${i.stored} → ${i.defined}`) },
                { key: 'stale', title: 'Stale settings', help: 'Stored without a definition. “Prune” deletes them in every scope.', list: r.stale },
                { key: 'stale_features', title: 'Stale flags', help: 'Flag rows without a feature definition. “Prune” deletes them.', list: r.stale_features },
                { key: 'flags_in_settings', title: 'Flags still in settings', help: 'Run php artisan setanjo:migrate-feature-flags to move them.', list: r.flags_in_settings },
                {
                    key: 'missing_records',
                    title: 'Overrides of deleted records',
                    help: 'The record is gone but its overrides are stored. Open it to clear them.',
                    list: r.missing_records.map((i) => `${i.label}: ${plural(i.settings + i.features, 'override')}`),
                    links: r.missing_records.map((i) => (i.type ? recordPath(i.type, i.id) : null)),
                },
                {
                    key: 'unknown_scope_types',
                    title: 'Unknown scope types',
                    help: 'Overrides stored for models not in setanjo.scopes (or the tenancy config). Add the model to see them here.',
                    list: r.unknown_scope_types.map((i) => `${i.class}: ${plural(i.records, 'record')}`),
                },
            ];
        },

        async run(dryRun) {
            this.running = true;
            try {
                const response = await api('POST', '/sync', { ...this.options, dry_run: dryRun });
                this.output = response.output;
                this.report = response.data;
                this.$store.s.driftCount = driftIssues(this.report);
                this.previewed = dryRun;
                if (!dryRun) {
                    this.$store.s.toast('Sync finished');
                    await Promise.all([this.$store.s.loadGroups(), this.$store.s.loadSettings(), this.$store.s.loadFeatures()]);
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
        loading: true,
        failed: null,
        timer: null,
        ago,
        exact,

        async init() {
            const { query: params } = this.$store.s.route;
            Object.keys(this.filters).forEach((key) => {
                if (params[key]) this.filters[key] = params[key];
            });
            await this.load();
        },

        filterLater() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.page = 1;
                this.load();
            }, 300);
        },

        get filtering() {
            return Object.values(this.filters).some((value) => value !== '');
        },

        clearFilters() {
            this.filters = { key: '', action: '', scope_type: '', scope_id: '' };
            this.page = 1;
            this.load();
        },

        async load() {
            this.loading = true;
            this.failed = null;
            try {
                const response = await api('GET', `/history${query({ ...this.filters, page: this.page })}`);
                this.enabled = response.enabled;
                this.message = response.message ?? '';
                this.entries = response.data;
                this.meta = response.meta ?? null;
            } catch (error) {
                this.failed = error.message;
            } finally {
                this.loading = false;
            }
        },

        go(page) {
            this.page = page;
            this.load();
        },

        get days() {
            const days = [];
            for (const entry of this.entries) {
                const label = dayLabel(entry.created_at);
                if (days.length === 0 || days[days.length - 1].label !== label) days.push({ label, entries: [] });
                days[days.length - 1].entries.push(entry);
            }
            return days;
        },

        async revert(entry) {
            const confirmed = await this.$store.s.confirm({
                title: 'Revert this change?',
                message: `${entry.key}${entry.scope ? ` for ${entry.scope.label}` : ''} goes back to the value it had before this change (${ago(entry.created_at)}). The revert is recorded as a new change.`,
                confirmLabel: 'Revert',
            });
            if (!confirmed) return;
            try {
                await api('POST', `/history/${entry.id}/revert`);
                this.$store.s.toast(`Reverted ${entry.key}`);
                this.page = 1;
                await this.load();
                this.$store.s.settings = null;
                this.$store.s.features = null;
            } catch (error) {
                this.$store.s.fail(error);
            }
        },

        open(entry) {
            if (entry.kind === 'feature' || this.$store.s.row('feature', entry.key)) this.$store.s.openKey('feature', entry.key);
            else this.$store.s.openKey('setting', entry.key);
        },

        change: historyChange,
        display: historyDisplay,
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
                    await Promise.all([this.$store.s.loadGroups(), this.$store.s.loadSettings(), this.$store.s.loadFeatures()]);
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
                const path = `/${this.m.kind}/${enc(this.m.row.key)}/meta`;
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

/** A flag's rules before and after (FeatureRules shape), as readable lines. */
function rulesDiff(before, after) {
    const fields = {
        enabled_from: ['on from', (value) => (value ? exact(value) : 'any time')],
        enabled_until: ['off from', (value) => (value ? exact(value) : 'never')],
        rollout: ['rollout', (value) => (value === null || value === undefined ? 'everyone' : `${value}%`)],
        requires: ['requires', (value) => (value?.length ? value.join(', ') : 'nothing')],
    };
    const lines = [];
    for (const [name, [label, show]] of Object.entries(fields)) {
        if (JSON.stringify(before[name] ?? null) !== JSON.stringify(after[name] ?? null)) {
            lines.push({ type: 'change', text: `${label}: ${show(before[name])} → ${show(after[name])}` });
        }
    }
    if (!before.archived_at && after.archived_at) lines.push({ type: 'remove', text: 'archived' });
    if (before.archived_at && !after.archived_at) lines.push({ type: 'add', text: 'restored' });
    return lines;
}

/** How a history entry changed, for display: { from, to, lines } (lines for JSON diffs). */
function historyChange(entry) {
    if (entry.action === 'meta') {
        return { from: null, to: null, lines: structuredDiff(entry.old_meta ?? null, entry.new_meta ?? null) ?? [], meta: true };
    }
    if (entry.action === 'rules') {
        return { from: null, to: null, lines: rulesDiff(entry.old_meta ?? {}, entry.new_meta ?? {}) };
    }
    if (entry.action === 'toggle') {
        // old_value: 'true' / 'false' / 'inherit'; null on rows written before the previous state was stored.
        const state = (value) => ({ true: 'on', false: 'off', inherit: 'inherit' })[value] ?? 'inherit';
        return { from: entry.old_value === null ? 'unknown' : state(entry.old_value), to: state(entry.new_value), lines: null };
    }
    const before = parseStored(entry.old_value);
    const after = entry.action === 'forget' ? { ok: true, value: null } : parseStored(entry.new_value);
    const lines =
        (before.ok || entry.old_value === null) && (after.ok || entry.new_value === null) ? structuredDiff(before.value, after.value) : null;

    return {
        from: entry.old_value,
        to: entry.action === 'forget' ? null : entry.new_value,
        removed: entry.action === 'forget',
        lines: lines && lines.length ? lines : null,
    };
}

function historyDisplay(value) {
    if (value === null || value === undefined) return '—';
    return String(value).length > 80 ? `${String(value).slice(0, 77)}…` : String(value);
}

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
registerControls(Alpine);
Alpine.start();
