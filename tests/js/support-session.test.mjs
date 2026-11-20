import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/customer/assets/js/support-session.js', import.meta.url), 'utf8');
function element() {
    const classes = new Set();
    return {
        hidden: false, disabled: false, value: '', textContent: '', innerHTML: '', children: [], listeners: {}, dataset: {}, options: [],
        classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c), toggle(c, force) {
            const on = force ?? !classes.has(c); on ? classes.add(c) : classes.delete(c); return on;
        } },
        addEventListener(type, fn) { this.listeners[type] = fn; },
        setAttribute() {}, removeAttribute() {}, focus() {},
        replaceChildren(...children) { this.children = children; },
        append(...children) { this.children.push(...children); },
        appendChild(child) { this.children.push(child); },
        dispatchEvent(event) { return this.listeners[event.type]?.(event); },
    };
}
function page({ stored, storageBlocked = false } = {}) {
    const nodes = new Map();
    const get = (id) => { if (!nodes.has(id)) nodes.set(id, element()); return nodes.get(id); };
    const calls = [];
    const storage = new Map(stored ? [['helpdesk_active_support_session', JSON.stringify(stored)]] : []);
    const document = {
        getElementById: get, querySelector: () => null, createElement: element, listeners: {},
        addEventListener(type, fn) { this.listeners[type] = fn; },
        dispatchEvent(event) { return this.listeners[event.type]?.(event); },
    };
    vm.runInNewContext(source, {
        window: { HELPDESK_CONFIG: { currentUserId: 1 } }, document, crypto: { randomUUID: (() => { let id = 0; return () => `uuid-${++id}`; })() },
        Event, CustomEvent, AbortController, setTimeout: (...args) => { const timer = setTimeout(...args); timer.unref(); return timer; }, clearTimeout,
        sessionStorage: Object.fromEntries(['getItem', 'setItem', 'removeItem'].map((name) => [name, (key, value) => {
            if (storageBlocked) throw new Error('Storage unavailable');
            if (name === 'getItem') return storage.get(key) ?? null;
            if (name === 'setItem') storage.set(key, value); else if (name === 'removeItem') storage.delete(key);
        }])),
        fetch: (url, options) => new Promise((resolve, reject) => calls.push({ url, options, resolve, reject })),
    });
    const click = (id) => get(id).listeners.click?.({ preventDefault() {} });
    const submit = () => get('support-create-form').listeners.submit({ preventDefault() {} });
    get('support-room-select').value = '2'; get('support-desc-input').value = 'Máy chiếu không sáng';
    return { get, calls, click, submit, storage, document };
}
const settle = () => new Promise((resolve) => setImmediate(resolve));
function respond(call, data, pagination, status = 200) {
    call.resolve({ ok: status < 400, status, json: async () => ({ data, meta: { pagination } }) });
}
const session = (id) => ({ id, room_id: 2, room_code: 'A2', room_label: 'A2', status: 'collecting', initial_description: `Sự cố ${id}` });

test('network failure releases create button and retry reuses key and payload including equipment', async () => {
    const p = page(); p.get('support-equipment-input').value = '7';
    const first = p.submit(); p.calls[0].reject(new TypeError('Failed to fetch'));
    await first.catch(() => {});
    assert.equal(p.get('support-create-submit').disabled, false);
    assert.equal(p.get('support-create-error').hidden, false);
    p.submit();
    assert.equal(p.calls[1].options.headers['Idempotency-Key'], p.calls[0].options.headers['Idempotency-Key']);
    assert.equal(p.calls[1].options.body, p.calls[0].options.body);
    assert.equal(JSON.parse(p.calls[1].options.body).equipment_id, 7);
    respond(p.calls[1], session(5), undefined, 201); await settle();
});

test('changing uncertain payload requires an explicit new intent', async () => {
    const p = page(); const first = p.submit(); p.calls[0].reject(new TypeError('offline')); await first.catch(() => {});
    p.get('support-desc-input').value = 'Một sự cố khác'; p.submit(); await settle();
    assert.equal(p.calls.length, 1);
    assert.match(p.get('support-create-error').textContent, /nội dung|ý định/i);
});

test('list network errors show retry instead of leaving a spinner', async () => {
    const p = page(); p.click('support-nav-list'); p.calls[0].reject(new TypeError('offline')); await settle();
    assert.equal(p.get('support-list-loading').hidden, true);
    assert.equal(p.get('support-list-error').hidden, false);
    p.click('support-list-retry'); respond(p.calls[1], [], { total: 0 }); await settle();
    assert.equal(p.get('support-list-empty').hidden, false);
});

test('reload reads all message pages from server', async () => {
    const p = page({ stored: { userId: 1, sessionId: 5 } }); respond(p.calls[0], session(5)); await settle();
    respond(p.calls[1], [{ role: 'user', content_text: 'đầu' }], { current_page: 1, last_page: 2 }); await settle();
    assert.equal(p.calls.length, 3);
    respond(p.calls[2], [{ role: 'assistant', content_text: 'cuối' }], { current_page: 2, last_page: 2 }); await settle();
    assert.equal(p.get('support-messages-log').children.length, 2);
});

test('late detail response cannot reopen a view the user left', async () => {
    const p = page({ stored: { userId: 1, sessionId: 5 } }); p.click('support-nav-create');
    respond(p.calls[0], session(5)); await settle();
    assert.equal(p.calls.length, 1);
    assert.equal(p.get('support-view-create').hidden, false);
    assert.equal(p.get('support-direct-help').disabled, true);
});

test('logout clears visible data and ignores responses still in flight', async () => {
    const p = page({ stored: { userId: 1, sessionId: 5 } });
    p.document.listeners.submit({ target: { closest: () => ({}) } });
    respond(p.calls[0], session(5)); await settle();
    assert.equal(p.storage.size, 0);
    assert.equal(p.calls.length, 1);
    assert.equal(p.get('support-messages-log').children.length, 0);
    assert.equal(p.get('support-desc-input').value, '');
});

test('blocked storage does not prevent navigation', () => {
    const p = page({ storageBlocked: true }); p.click('support-nav-list'); assert.equal(p.calls.length, 1);
});

test('account change removes the old session without requesting its data', () => {
    const p = page({ stored: { userId: 2, sessionId: 5 } }); assert.equal(p.storage.size, 0); assert.equal(p.calls.length, 0);
});

test('422 displays the support API field error and allows corrected input', async () => {
    const p = page(); const first = p.submit();
    p.calls[0].resolve({ ok: false, status: 422, json: async () => ({ error: {
        code: 'VALIDATION_ERROR', message: 'Dữ liệu chưa hợp lệ.', details: { room_id: ['Phòng không còn tồn tại.'] },
    } }) }); await first;
    assert.equal(p.get('support-create-error').textContent, 'Phòng không còn tồn tại.');
    p.get('support-room-select').value = '3'; p.submit();
    assert.equal(JSON.parse(p.calls[1].options.body).room_id, 3);
});

test('double submit sends one request', () => {
    const p = page(); p.submit(); p.submit(); assert.equal(p.calls.length, 1);
});

test('one support panel switches between saved sessions and AI without sending a chat request', () => {
    const p = page(); p.click('support-nav-chat');
    assert.equal(p.get('portal-chat').hidden, false);
    assert.equal(p.get('support-view-list').hidden, true);
    assert.equal(p.get('support-view-create').hidden, true);
    assert.equal(p.calls.length, 0);
    p.click('support-nav-create');
    assert.equal(p.get('portal-chat').hidden, true);
    assert.equal(p.get('support-view-create').hidden, false);
});

test('closing and reopening the unified panel preserves the selected view', () => {
    const p = page(); p.click('support-nav-chat');
    p.click('support-session-close'); p.click('support-session-launch');
    assert.equal(p.get('portal-chat').hidden, false);
    assert.equal(p.calls.length, 0);
});

test('equipment choices come from the selected room and reset when the room changes', () => {
    const p = page();
    p.get('support-room-select').options = [
        { value: '2', dataset: { equipment: '[{"id":7,"name":"Máy chiếu"}]' } },
        { value: '3', dataset: { equipment: '[]' } },
    ];
    p.get('support-room-select').dispatchEvent(new Event('change'));
    assert.equal(p.get('support-equipment-input').children[1].textContent, 'Máy chiếu');
    p.get('support-equipment-input').value = '7';
    p.get('support-room-select').value = '3';
    p.get('support-room-select').dispatchEvent(new Event('change'));
    assert.equal(p.get('support-equipment-input').children.length, 1);
    assert.equal(p.get('support-equipment-input').value, '');
});

test('direct help uses the server session room and description without sending a ticket', async () => {
    const p = page({ stored: { userId: 1, sessionId: 5 } });
    let selectedRoom;
    p.document.addEventListener('helpdesk:select-room', (event) => { selectedRoom = event.detail.roomId; });
    respond(p.calls[0], session(5)); await settle();
    respond(p.calls[1], []); await settle(); p.click('support-direct-help');
    assert.equal(selectedRoom, 2);
    assert.equal(p.get('description').value, 'Sự cố 5');
    assert.equal(p.calls.length, 2);
});

test('late list response cannot overwrite a newer refresh', async () => {
    const p = page(); p.click('support-nav-list'); p.click('support-btn-refresh-list');
    respond(p.calls[1], [session(2)], { total: 1 }); await settle();
    respond(p.calls[0], [], { total: 0 }); await settle();
    assert.equal(p.get('support-session-items').children.length, 1);
    assert.equal(p.get('support-list-empty').hidden, true);
});

function portal() {
    const nodes = new Map();
    const get = (id) => {
        if (!nodes.has(id)) { const node = element(); node.querySelector = () => get(id + '-copy'); nodes.set(id, node); }
        return nodes.get(id);
    };
    const listeners = {};
    let resolveRooms;
    const context = vm.createContext({ URL, URLSearchParams,
        window: { HELPDESK_CONFIG: {}, location: { search: '?room=OLD', origin: 'http://localhost' } },
        document: { querySelector: get, createElement: element, addEventListener: (type, fn) => { listeners[type] = fn; } },
        fetch: () => new Promise((resolve) => { resolveRooms = resolve; }),
    });
    vm.runInContext(readFileSync(new URL('../../public/customer/assets/js/portal-qr.js', import.meta.url), 'utf8'), context);
    return { get, select: (roomId) => listeners['helpdesk:select-room']?.({ detail: { roomId } }),
        load: () => resolveRooms({ ok: true, json: async () => ({ data: [
            { id: 1, code: 'OLD', name: 'Phòng cũ', building: { code: 'A', name: 'Tòa A' } },
            { id: 2, code: 'B2', name: 'Phòng B2', qr_code: 'QR_B2', building: { code: 'B', name: 'Tòa B' } },
        ] }) }),
    };
}

for (const beforeLoad of [true, false]) {
    test(`direct form selects the session room and replaces old QR context (before rooms loaded: ${beforeLoad})`, async () => {
        const p = portal();
        if (beforeLoad) p.select(2);
        p.load(); await settle();
        if (!beforeLoad) p.select(2);
        assert.equal(p.get('#room-id').value, '2');
        assert.equal(p.get('#building-code').value, 'B');
        assert.equal(p.get('#room-code').value, 'QR_B2');
        assert.equal(p.get('#qr-detected').hidden, true);
        assert.equal(p.get('#building').disabled, false);
    });
}

