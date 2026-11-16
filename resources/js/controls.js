/*
 * Styled form controls on top of native elements.
 *
 *   <select x-listbox x-model="..." @change="...">    a dropdown (searchable with data-search or > 8 options)
 *   <input type="datetime-local" x-datetime x-model="...">   a date and time picker
 *
 * The native element stays in the page, hidden, and remains the source of truth: x-model, @change,
 * :disabled, x-show and x-for options keep working, and choosing a value sets it and fires the same
 * event a user would. Popovers are appended to <body> (position: fixed) so a scrolling or transformed
 * parent (the drawer) never clips them.
 */

const ICON = {
    chevron: '<path d="m6 9 6 6 6-6"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    left: '<path d="m15 18-6-6 6-6"/>',
    right: '<path d="m9 18 6-6-6-6"/>',
    close: '<path d="M18 6 6 18M6 6l12 12"/>',
};

const svg = (name, classes = 'size-4 shrink-0') =>
    `<svg class="${classes}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON[name]}</svg>`;

const PANEL =
    'fixed z-[60] rounded-lg border border-zinc-200 bg-white text-sm text-zinc-900 shadow-xl dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100';
const ACTIVE = ['bg-zinc-100', 'dark:bg-zinc-800'];

let uid = 0;

function element(tag, className = '', attributes = {}) {
    const node = document.createElement(tag);
    node.className = className;
    for (const [name, value] of Object.entries(attributes)) node.setAttribute(name, value);
    return node;
}

/** Keep a fixed panel next to its anchor: below it, or above when there is no room. */
function place(anchor, panel) {
    const rect = anchor.getBoundingClientRect();
    panel.style.minWidth = `${rect.width}px`;
    const height = panel.offsetHeight;
    const width = panel.offsetWidth;
    const below = window.innerHeight - rect.bottom;
    const top = below < height + 8 && rect.top > below ? rect.top - height - 4 : rect.bottom + 4;
    panel.style.top = `${Math.max(8, top)}px`;
    panel.style.left = `${Math.min(Math.max(8, rect.left), window.innerWidth - width - 8)}px`;
}

/**
 * Open a panel for an anchor: positions it, closes it on outside clicks, follows scrolling and
 * resizing. Returns a function that closes it.
 */
function openPanel(anchor, panel, onClose) {
    document.body.append(panel);
    place(anchor, panel);

    const reposition = (event) => {
        if (!event || !panel.contains(event.target)) place(anchor, panel);
    };
    const outside = (event) => {
        if (!panel.contains(event.target) && !anchor.contains(event.target)) close();
    };
    let closed = false;
    function close() {
        if (closed) return;
        closed = true;
        panel.remove();
        window.removeEventListener('scroll', reposition, true);
        window.removeEventListener('resize', reposition);
        document.removeEventListener('pointerdown', outside, true);
        onClose();
    }

    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
    document.addEventListener('pointerdown', outside, true);

    return close;
}

/** Mirror x-show (inline display: none) and :disabled of the hidden native element. */
function mirror(source, target) {
    target.hidden = source.style.display === 'none';
    if ('disabled' in target) target.disabled = source.disabled;
}

/** Run sync after any change to the element, its options, or its x-model value. */
function watch(el, sync, { Alpine, effect, evaluateLater, cleanup }) {
    const observer = new MutationObserver(sync);
    observer.observe(el, { attributes: true, childList: true, subtree: true, characterData: true });
    el.addEventListener('change', sync);
    el.addEventListener('input', sync);

    const model = el.getAttributeNames().find((name) => name === 'x-model' || name.startsWith('x-model.'));
    if (model) {
        const read = evaluateLater(el.getAttribute(model));
        effect(() => read(() => Alpine.nextTick(sync)));
    }

    cleanup(() => observer.disconnect());
}

/* -------------------------------------------------------------------------- */
/* Dropdown                                                                   */
/* -------------------------------------------------------------------------- */

function listbox(select, utilities) {
    const id = `s-listbox-${++uid}`;
    const placeholder = select.hasAttribute('data-placeholder');
    const button = element('button', `${select.className} flex items-center gap-2 text-left`, {
        type: 'button',
        'aria-haspopup': 'listbox',
        'aria-expanded': 'false',
    });
    const label = element('span', 'min-w-0 flex-1 truncate');
    button.append(label);
    button.insertAdjacentHTML('beforeend', svg('chevron', 'size-4 shrink-0 text-zinc-400'));

    if (select.getAttribute('aria-label')) button.setAttribute('aria-label', select.getAttribute('aria-label'));
    if (select.id) {
        button.id = `${select.id}-button`;
        document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((node) => (node.htmlFor = button.id));
    }

    select.classList.add('hidden');
    select.tabIndex = -1;
    select.after(button);

    const choices = () => [...select.options].filter((option) => !option.hidden && !(placeholder && option.value === ''));

    function sync() {
        mirror(select, button);
        const option = select.selectedOptions[0];
        const empty = !option || (placeholder && option.value === '');
        label.textContent = option ? option.textContent.trim() : '';
        label.classList.toggle('text-zinc-400', empty);
    }

    let close = null;
    let active = 0;
    let typed = '';
    let typedAt = 0;

    function open() {
        if (close || button.disabled) return;
        const options = choices();
        const searchable = select.hasAttribute('data-search') || options.length > 8;
        const panel = element('div', `${PANEL} overflow-hidden`);
        const list = element('div', 'max-h-64 overflow-y-auto p-1 outline-none', { role: 'listbox', id, tabindex: '-1' });
        const search = searchable
            ? element('input', 'w-full border-b border-zinc-200 bg-transparent px-3 py-2 text-sm outline-none placeholder:text-zinc-400 dark:border-zinc-700', {
                  type: 'search',
                  placeholder: 'Search…',
                  'aria-label': 'Search options',
                  'aria-controls': id,
              })
            : null;
        let shown = options;

        const highlight = (index, scroll = true) => {
            active = Math.max(0, Math.min(index, shown.length - 1));
            [...list.children].forEach((node, position) => {
                const on = position === active;
                ACTIVE.forEach((name) => node.classList.toggle(name, on));
                if (on && scroll) node.scrollIntoView({ block: 'nearest' });
            });
            const current = list.children[active];
            (search ?? list).setAttribute('aria-activedescendant', current?.id ?? '');
        };

        const render = () => {
            const term = search?.value.trim().toLowerCase() ?? '';
            shown = options.filter((option) => term === '' || `${option.textContent} ${option.value}`.toLowerCase().includes(term));
            list.replaceChildren(
                ...shown.map((option, index) => {
                    const selected = option.value === select.value;
                    const item = element('div', 'flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5', {
                        role: 'option',
                        id: `${id}-${index}`,
                        'aria-selected': String(selected),
                    });
                    if (option.disabled) {
                        item.setAttribute('aria-disabled', 'true');
                        item.classList.add('cursor-not-allowed', 'opacity-50');
                    }
                    const text = element('span', `min-w-0 flex-1 truncate${selected ? ' font-medium' : ''}`);
                    text.textContent = option.textContent.trim();
                    item.append(text);
                    if (option.dataset.hint && option.dataset.hint !== text.textContent) {
                        const hint = element('span', 'shrink-0 font-mono text-xs text-zinc-500');
                        hint.textContent = option.dataset.hint;
                        item.append(hint);
                    }
                    item.insertAdjacentHTML('beforeend', svg('check', `size-4 shrink-0 text-brand-600 dark:text-brand-400${selected ? '' : ' invisible'}`));
                    item.addEventListener('mousedown', (event) => event.preventDefault());
                    item.addEventListener('mousemove', () => active !== index && highlight(index, false));
                    item.addEventListener('click', () => choose(option));
                    return item;
                }),
            );
            if (shown.length === 0) {
                const none = element('div', 'px-2.5 py-1.5 text-zinc-500');
                none.textContent = 'No matches';
                list.append(none);
            }
            const selectedIndex = shown.findIndex((option) => option.value === select.value);
            highlight(term === '' && selectedIndex >= 0 ? selectedIndex : 0);
        };

        const choose = (option) => {
            if (!option || option.disabled) return;
            const changed = select.value !== option.value;
            select.value = option.value;
            finish();
            if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
        };

        const finish = () => {
            close?.();
            button.focus();
        };

        panel.addEventListener('keydown', (event) => {
            const keys = {
                ArrowDown: () => highlight(active + 1),
                ArrowUp: () => highlight(active - 1),
                Home: () => highlight(0),
                End: () => highlight(shown.length - 1),
                PageDown: () => highlight(active + 8),
                PageUp: () => highlight(active - 8),
                Enter: () => choose(shown[active]),
                Escape: finish,
            };
            if (event.key === 'Tab') return close?.();
            // In the search box, Home and End move the caret.
            const caret = search && (event.key === 'Home' || event.key === 'End');
            if (keys[event.key] && !caret) {
                event.preventDefault();
                event.stopPropagation();
                keys[event.key]();
                return;
            }
            if (!search && event.key.length === 1 && !event.metaKey && !event.ctrlKey) {
                // Type to jump: letters typed within a second add up.
                typed = Date.now() - typedAt < 1000 ? typed + event.key.toLowerCase() : event.key.toLowerCase();
                typedAt = Date.now();
                const index = shown.findIndex((option) => option.textContent.trim().toLowerCase().startsWith(typed));
                if (index >= 0) highlight(index);
            }
        });

        if (search) {
            search.addEventListener('input', render);
            panel.append(search);
        }
        panel.append(list);
        render();

        button.setAttribute('aria-expanded', 'true');
        close = openPanel(button, panel, () => {
            close = null;
            button.setAttribute('aria-expanded', 'false');
        });
        (search ?? list).focus();
    }

    /** Give the select's own Enter / Escape handlers (save, undo) the key first. Returns whether one used it. */
    const forward = (event) => {
        let stopped = false;
        const copy = new KeyboardEvent('keydown', { key: event.key, bubbles: false, cancelable: true, metaKey: event.metaKey, ctrlKey: event.ctrlKey });
        copy.stopPropagation = () => (stopped = true);
        select.dispatchEvent(copy);
        if (stopped) event.stopPropagation();
        if (copy.defaultPrevented) event.preventDefault();
        return stopped || copy.defaultPrevented;
    };

    button.addEventListener('click', () => (close ? close() : open()));
    button.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            forward(event);
        } else if (event.key === 'Enter' && forward(event)) {
            // handled by the select (e.g. save)
        } else if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        }
    });

    watch(select, sync, utilities);
    sync();

    utilities.cleanup(() => {
        close?.();
        button.remove();
    });
}

/* -------------------------------------------------------------------------- */
/* Date and time picker                                                       */
/* -------------------------------------------------------------------------- */

const pad = (number) => String(number).padStart(2, '0');
const toValue = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

function fromValue(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value ?? '');
    return match ? new Date(+match[1], +match[2] - 1, +match[3], +match[4], +match[5]) : null;
}

/** First day of the week for the browser's locale: 0 = Sunday ... 6 = Saturday (Monday when unknown). */
function firstDayOfWeek() {
    try {
        const locale = new Intl.Locale(navigator.language);
        const info = locale.getWeekInfo?.() ?? locale.weekInfo;
        return (info?.firstDay ?? 1) % 7;
    } catch {
        return 1;
    }
}

function datetime(input, utilities) {
    const shown = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    const monthTitle = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' });
    const dayName = new Intl.DateTimeFormat(undefined, { weekday: 'short' });
    const dayLabel = new Intl.DateTimeFormat(undefined, { dateStyle: 'full' });
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const firstDay = firstDayOfWeek();

    const wrapper = element('div', 'relative');
    const button = element('button', `${input.className} flex items-center gap-2 pr-8 text-left`, { type: 'button', 'aria-haspopup': 'dialog' });
    button.insertAdjacentHTML('afterbegin', svg('calendar', 'size-4 shrink-0 text-zinc-400'));
    const label = element('span', 'min-w-0 flex-1 truncate');
    button.append(label);
    const clear = element('button', 's-btn-ghost absolute top-1/2 right-1 -translate-y-1/2 p-1 text-zinc-400', { type: 'button', 'aria-label': 'Clear' });
    clear.innerHTML = svg('close', 'size-3.5');
    wrapper.append(button, clear);

    if (input.id) {
        button.id = `${input.id}-button`;
        document.querySelectorAll(`label[for="${CSS.escape(input.id)}"]`).forEach((node) => (node.htmlFor = button.id));
    }

    input.classList.add('hidden');
    input.tabIndex = -1;
    input.after(wrapper);

    const value = () => fromValue(input.value);

    function sync() {
        mirror(input, wrapper);
        button.disabled = input.disabled;
        const date = value();
        label.textContent = date ? shown.format(date) : input.placeholder || 'Not set';
        label.classList.toggle('text-zinc-400', !date);
        clear.hidden = !date || input.disabled;
    }

    function set(date) {
        input.value = date ? toValue(date) : '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
    }

    clear.addEventListener('click', () => {
        set(null);
        button.focus();
    });

    let close = null;

    function open() {
        if (close || input.disabled) return;
        const start = value();
        let cursor = start ? new Date(start) : new Date();
        let hour = start?.getHours() ?? 9;
        let minute = start?.getMinutes() ?? 0;

        const panel = element('div', `${PANEL} w-72 space-y-3 p-3`, { role: 'dialog', 'aria-label': 'Choose date and time' });

        const header = element('div', 'flex items-center gap-1');
        const previous = element('button', 's-btn-ghost p-1', { type: 'button', 'aria-label': 'Previous month' });
        previous.innerHTML = svg('left');
        const next = element('button', 's-btn-ghost p-1', { type: 'button', 'aria-label': 'Next month' });
        next.innerHTML = svg('right');
        const title = element('div', 'flex-1 text-center font-medium', { 'aria-live': 'polite' });
        header.append(previous, title, next);

        const grid = element('div', 'grid grid-cols-7 gap-0.5 text-center', { role: 'grid' });

        const time = element('div', 'flex items-center gap-2');
        const timeLabel = element('span', 'flex-1 text-xs text-zinc-500');
        timeLabel.textContent = 'Time';
        const hours = element('input', 's-input w-14 px-2 py-1 text-center tabular-nums', { type: 'number', min: '0', max: '23', 'aria-label': 'Hour' });
        const minutes = element('input', 's-input w-14 px-2 py-1 text-center tabular-nums', { type: 'number', min: '0', max: '59', step: '5', 'aria-label': 'Minute' });
        const colon = element('span', 'text-zinc-400');
        colon.textContent = ':';
        time.append(timeLabel, hours, colon, minutes);

        const presets = element('div', 'flex flex-wrap gap-1.5');
        const preset = (text, make) => {
            const node = element('button', 's-btn px-2 py-1 text-xs', { type: 'button' });
            node.textContent = text;
            node.addEventListener('click', () => {
                const date = make();
                [cursor, hour, minute] = [new Date(date), date.getHours(), date.getMinutes()];
                set(date);
                render();
            });
            presets.append(node);
        };
        const at = (days, h, m) => {
            const date = new Date();
            date.setDate(date.getDate() + days);
            date.setHours(h, m, 0, 0);
            return date;
        };
        preset('Now', () => {
            const date = new Date();
            date.setSeconds(0, 0);
            return date;
        });
        preset('Tomorrow 9:00', () => at(1, 9, 0));
        preset('In a week', () => at(7, 9, 0));

        const footer = element('div', 'flex items-center gap-2 border-t border-zinc-200 pt-2 dark:border-zinc-700');
        const zone = element('span', 'min-w-0 flex-1 truncate text-xs text-zinc-500', { title: 'Times are in your browser\'s timezone' });
        zone.textContent = timezone;
        const done = element('button', 's-btn-primary px-3 py-1 text-xs', { type: 'button' });
        done.textContent = 'Done';
        footer.append(zone, done);

        panel.append(header, grid, time, presets, footer);

        const pick = (day) => {
            const date = new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute);
            cursor = new Date(date);
            set(date);
            render(true);
        };

        function render(focus = false) {
            title.textContent = monthTitle.format(cursor);
            hours.value = pad(hour);
            minutes.value = pad(minute);

            const selected = value();
            const today = new Date();
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const offset = (first.getDay() - firstDay + 7) % 7;
            const cells = [];

            for (let index = 0; index < 7; index++) {
                const name = element('div', 'py-1 text-xs text-zinc-500', { role: 'columnheader' });
                name.textContent = dayName.format(new Date(2024, 0, 7 + ((firstDay + index) % 7))); // 7 Jan 2024 is a Sunday
                cells.push(name);
            }

            for (let index = 0; index < 42; index++) {
                const day = new Date(cursor.getFullYear(), cursor.getMonth(), 1 - offset + index);
                const isSelected = sameDay(day, selected);
                const isCursor = sameDay(day, cursor);
                const classes = ['grid h-8 place-items-center rounded-md tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50'];
                if (isSelected) classes.push('bg-brand-600 font-medium text-white');
                else {
                    classes.push('hover:bg-zinc-100 dark:hover:bg-zinc-800');
                    if (day.getMonth() !== cursor.getMonth()) classes.push('text-zinc-400 dark:text-zinc-600');
                    if (sameDay(day, today)) classes.push('font-semibold text-brand-600 dark:text-brand-400');
                }
                const cell = element('button', classes.join(' '), {
                    type: 'button',
                    role: 'gridcell',
                    tabindex: isCursor ? '0' : '-1',
                    'aria-label': dayLabel.format(day),
                    'aria-selected': String(isSelected),
                });
                if (sameDay(day, today)) cell.setAttribute('aria-current', 'date');
                cell.textContent = String(day.getDate());
                cell.addEventListener('click', () => pick(day));
                if (isCursor) cell.dataset.cursor = '';
                cells.push(cell);
            }

            grid.replaceChildren(...cells);
            if (focus) grid.querySelector('[data-cursor]')?.focus();
            if (close) place(button, panel);
        }

        const move = (days, months = 0) => {
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() + months, cursor.getDate() + days);
            render(true);
        };

        previous.addEventListener('click', () => move(0, -1));
        next.addEventListener('click', () => move(0, 1));

        const setTime = () => {
            hour = Math.min(23, Math.max(0, parseInt(hours.value, 10) || 0));
            minute = Math.min(59, Math.max(0, parseInt(minutes.value, 10) || 0));
            const current = value();
            if (current) set(new Date(current.getFullYear(), current.getMonth(), current.getDate(), hour, minute));
            hours.value = pad(hour);
            minutes.value = pad(minute);
        };
        hours.addEventListener('change', setTime);
        minutes.addEventListener('change', setTime);

        const finish = () => {
            close?.();
            button.focus();
        };
        done.addEventListener('click', finish);

        grid.addEventListener('keydown', (event) => {
            const keys = {
                ArrowLeft: () => move(-1),
                ArrowRight: () => move(1),
                ArrowUp: () => move(-7),
                ArrowDown: () => move(7),
                PageUp: () => move(0, -1),
                PageDown: () => move(0, 1),
                Home: () => move(-((cursor.getDay() - firstDay + 7) % 7)),
                End: () => move(6 - ((cursor.getDay() - firstDay + 7) % 7)),
            };
            if (keys[event.key]) {
                event.preventDefault();
                keys[event.key]();
            }
        });
        panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                finish();
            }
        });

        render();
        button.setAttribute('aria-expanded', 'true');
        close = openPanel(button, panel, () => {
            close = null;
            button.setAttribute('aria-expanded', 'false');
        });
        grid.querySelector('[data-cursor]')?.focus();
    }

    button.addEventListener('click', () => (close ? close() : open()));
    button.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            open();
        }
    });

    watch(input, sync, utilities);
    sync();

    utilities.cleanup(() => {
        close?.();
        wrapper.remove();
    });
}

export function registerControls(Alpine) {
    Alpine.directive('listbox', (el, _directive, utilities) => listbox(el, { ...utilities, Alpine }));
    Alpine.directive('datetime', (el, _directive, utilities) => datetime(el, { ...utilities, Alpine }));
}
