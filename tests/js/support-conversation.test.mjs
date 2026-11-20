import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/customer/assets/js/support-conversation.js', import.meta.url), 'utf8');
function element() {
    return { value: '', hidden: false, disabled: false, readOnly: false, textContent: '', children: [], listeners: {}, attributes: {},
        classList: { toggle() {} }, focus() {},
        remove() { if (this.parentNode) this.parentNode.children = this.parentNode.children.filter((item) => item !== this); },
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(type, fn) { this.listeners[type] = fn; },
        replaceChildren(...items) { this.children = []; this.append(...items); },
        append(...items) { items.forEach((item) => { item.parentNode = this; this.children.push(item); }); },
        appendChild(item) { this.append(item); },
    };
}
function page(stored = null, { reducedMotion = false, withFeedback = false, confirmDelete = true } = {}) {
    const nodes = new Map();
    const get = (id) => { if (!nodes.has(id)) nodes.set(id, element()); return nodes.get(id); };
    get('report-ai-view').hidden = true;
    const removed = new Set();
    const feedbackJobs = new Map(); let feedbackId = 200000; let now = 0;
    const rootMarkup = readFileSync(new URL('../../resources/views/customer/partials/support-conversation.blade.php', import.meta.url), 'utf8').split('>')[0];
    const persistent = rootMarkup.includes('data-auto-dismiss="false"');
    const statuses = ['conversation-history-status', 'conversation-status', 'conversation-report', 'flash-status'].map((id) => {
        const node = get(id);
        node.style = {};
        node.matches = () => true;
        node.closest = () => persistent && id !== 'flash-status' ? get('report-ai-view') : null;
        node.remove = () => removed.add(id);
        return node;
    });
    const storage = new Map(stored ? [['helpdesk_active_support_session', JSON.stringify(stored)]] : []);
    const calls = []; const documentListeners = {};
    const confirmations = [];
    const animationJobs = new Map(); let animationId = 100000;
    const context = vm.createContext({
        window: { HELPDESK_CONFIG: { currentUserId: 1 }, matchMedia: () => ({matches: reducedMotion}),
            confirm: (message) => { confirmations.push(message); return confirmDelete; },
            setTimeout: (fn, delay) => { const id = feedbackId++; feedbackJobs.set(id, {fn, at: now + delay}); return id; },
            clearTimeout: (id) => feedbackJobs.delete(id) }, AbortController,
        MutationObserver: class { observe() {} },
        crypto: { randomUUID: (() => { let id = 0; return () => `uuid-${++id}`; })() },
        setTimeout: (fn, delay, ...args) => {
            if (delay === 20) { const id = animationId++; animationJobs.set(id, fn); return id; }
            const timer = setTimeout(fn, delay, ...args); timer.unref(); return timer;
        },
        clearTimeout: (id) => { if (!animationJobs.delete(id)) clearTimeout(id); },
        document: { readyState: 'loading', body: {}, querySelectorAll: () => statuses,
            getElementById: (id) => removed.has(id) ? null : get(id), querySelector: () => null, createElement: element, createTextNode: (text) => ({ textContent: text }), addEventListener: (type, fn) => { documentListeners[type] = fn; } },
        sessionStorage: { getItem: (key) => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
        fetch: (url, options) => {
            if (url.includes('?page=') && !withFeedback) return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: [], meta: { pagination: { total: 0 } } }) });
            return new Promise((resolve, reject) => calls.push({ url, options, resolve, reject }));
        },
    });
    if (withFeedback) vm.runInContext(readFileSync(new URL('../../public/shared/js/page-feedback.js', import.meta.url), 'utf8'), context);
    vm.runInContext(source, context);
    return { get, calls, storage, documentListeners, confirmations,
        removed,
        advanceFeedback: (milliseconds) => {
            const end = now + milliseconds;
            while (true) {
                const job = [...feedbackJobs].filter(([, job]) => job.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
                if (!job) break;
                feedbackJobs.delete(job[0]); now = job[1].at; job[1].fn();
            }
            now = end;
        },
        animationJobs,
        tick: () => { const [id, fn] = animationJobs.entries().next().value || []; if (fn) { animationJobs.delete(id); fn(); } },
        click: (id) => get(id).listeners.click?.(),
        submit: () => get('conversation-form').listeners.submit({ preventDefault() {} }),
    };
}
const settle = () => new Promise((resolve) => setImmediate(resolve));
const data = (changes = {}) => ({ session: { id: 5, status: 'collecting', version: 3, room_id: 2, room_code: 'A203', category_id: 1 },
    category_label: 'Máy chiếu', messages: [{ role: 'user', content_text: 'Máy chiếu không có hình' }, { role: 'assistant', content_text: 'Hãy kiểm tra chế độ trình chiếu.' }], needs_context: false, report: null, ...changes });
function respond(call, result = data(), status = 200) { call.resolve({ ok: status < 400, status, json: async () => ({ data: result }) }); }
const text = (node) => node.textContent + (node.children || []).map(text).join('');
const thinking = (p) => p.get('conversation-log').children.filter((node) => node.className?.includes('is-thinking'));
const userMessages = (p) => p.get('conversation-log').children.filter((node) => node.className?.includes('is-user'));
const historyResponse = (call, items, current = 1, last = 1) => call.resolve({ok: true, status: 200,
    json: async () => ({data: items, meta: {pagination: {current_page: current, last_page: last, total: items.length}}})});
const completedHistory = {id: 5, room_code: 'A203', initial_description: 'Máy chiếu không có hình', status: 'handed_off', ticket_status: 'closed', is_completed: true, can_delete: true};
const deleteButton = (p, index = 0) => p.get('conversation-history-list').children[index]?.children.find((node) => node.className?.includes('conversation-delete'));

test('new conversations show room and work selection before guidance and keep an available QR room', async () => {
    const p = page(); p.get('room-id').value = '2'; p.click('report-mode-ai');
    assert.equal(p.get('conversation-context-picker').open, true);
    assert.equal(p.get('conversation-room').value, '2');
    assert.match(text(p.get('conversation-log')), /phòng.*công việc/s);
    p.click('conversation-new');
    assert.equal(p.get('conversation-context-picker').open, true);
    assert.equal(p.get('conversation-room').value, '2');
});

test('missing room or work keeps the context picker open even without a report request', async () => {
    const p = page(); p.get('conversation-input').value = 'Mình cần hỗ trợ'; p.submit();
    respond(p.calls[0], data({session: {...data().session, room_id:null, category_id:null}, needs_context:true, needs_description:true})); await settle();
    assert.equal(p.get('conversation-context-picker').open, true);
    p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    respond(p.calls[1], data({needs_context:false, needs_description:false})); await settle();
    assert.equal(p.get('conversation-context-picker').open, false);
});

test('history shows completion badges and only eligible sessions have a delete action', async () => {
    const p = page(null, {withFeedback: true}); p.click('report-mode-ai');
    historyResponse(p.calls[0], [completedHistory, {...completedHistory, id: 6, can_delete: false, is_completed: false, ticket_status: 'resolved'}]); await settle();
    assert.ok(deleteButton(p)); assert.equal(deleteButton(p, 1), undefined);
    assert.match(text(p.get('conversation-history-list')), /Đã xử lý xong/);
    assert.match(text(p.get('conversation-history-list')), /Chờ xác nhận hoàn thành/);
    assert.match(deleteButton(p).attributes['aria-label'], /A203/);
});

test('cancelling confirmation keeps the conversation and sends no delete request', async () => {
    const p = page(null, {withFeedback: true, confirmDelete: false}); p.click('report-mode-ai');
    historyResponse(p.calls[0], [completedHistory]); await settle();
    assert.ok(deleteButton(p)); deleteButton(p).listeners.click();
    assert.equal(p.confirmations.length, 1); assert.equal(p.calls.length, 1);
    assert.equal(p.get('conversation-history-list').children.length, 1);
});

test('deleting the open completed session clears it and prevents duplicate deletes', async () => {
    const p = page({userId: 1, sessionId: 5, mode: 'ai'}, {withFeedback: true});
    historyResponse(p.calls[0], [completedHistory]); respond(p.calls[1], data({session: {...data().session, status:'handed_off'}, report: {ticket_id:9, ticket_number:'HD-OLD', status:'closed'}})); await settle();
    const button = deleteButton(p); assert.ok(button); button.listeners.click(); button.listeners.click();
    assert.equal(p.calls.length, 3); assert.equal(p.calls[2].url, '/api/support-sessions/5');
    assert.equal(p.calls[2].options.method, 'DELETE');
    assert.equal(button.attributes['aria-busy'], 'true');
    assert.equal(p.get('conversation-new').disabled, true);
    p.calls[2].resolve({ok: true, status: 200, json: async () => ({data: {id:5, deleted:true}})}); await settle();
    historyResponse(p.calls[3], []); await settle();
    assert.equal(JSON.parse(p.storage.get('helpdesk_active_support_session')).sessionId, null);
    assert.equal(p.get('conversation-report').hidden, true);
    assert.equal(userMessages(p).length, 0);
    assert.equal(p.get('conversation-history-list').children.length, 0);
    assert.match(p.get('conversation-status').textContent, /Đã xóa phiên/);
});

test('failed delete keeps history and allows retry; stale eligibility displays the server reason', async () => {
    const p = page(null, {withFeedback: true}); p.click('report-mode-ai');
    historyResponse(p.calls[0], [completedHistory]); await settle();
    const button = deleteButton(p); assert.ok(button); button.listeners.click();
    p.calls[1].reject(new TypeError('offline')); await settle();
    assert.equal(p.get('conversation-history-list').children.length, 1);
    assert.equal(button.disabled, false); assert.equal(button.attributes['aria-busy'], 'false');
    button.listeners.click();
    p.calls[2].resolve({ok: false, status: 422, json: async () => ({error: {details: {session: ['Phiếu đã được mở lại.']}}})}); await settle();
    assert.match(p.get('conversation-status').textContent, /Phiếu đã được mở lại/);
    assert.equal(p.get('conversation-history-list').children.length, 1);
});

test('deleting the last item on a page returns to the last available page and keeps another open session', async () => {
    const p = page({userId: 1, sessionId: 7, mode: 'ai'}, {withFeedback: true});
    historyResponse(p.calls[0], [completedHistory], 1, 2); respond(p.calls[1], data({session: {...data().session, id:7}})); await settle();
    p.click('conversation-next'); historyResponse(p.calls[2], [completedHistory], 2, 2); await settle();
    deleteButton(p).listeners.click();
    p.calls[3].resolve({ok:true, status:200, json:async()=>({data:{id:5,deleted:true}})}); await settle();
    historyResponse(p.calls[4], [], 2, 1); await settle();
    assert.equal(p.calls[5].url, '/api/support-sessions?page=1&per_page=10');
    historyResponse(p.calls[5], [{...completedHistory, id:7}]); await settle();
    assert.equal(p.get('conversation-page').textContent, 'Trang 1 / 1');
    assert.equal(JSON.parse(p.storage.get('helpdesk_active_support_session')).sessionId, 7);
    assert.equal(userMessages(p).length, 1);
});

test('logout while deletion is pending ignores the late response', async () => {
    const p = page(null, {withFeedback:true}); p.click('report-mode-ai');
    historyResponse(p.calls[0], [completedHistory]); await settle(); deleteButton(p).listeners.click();
    p.documentListeners.submit({target:{closest:()=>({})}});
    p.calls[1].resolve({ok:true,status:200,json:async()=>({data:{id:5,deleted:true}})}); await settle();
    assert.equal(p.calls.length, 2); assert.equal(p.storage.size, 0);
    assert.equal(p.get('conversation-history-list').children.length, 0);
    assert.equal(p.get('conversation-status').textContent, '');
});

test('history refresh blocks deletion until current eligibility has loaded', async () => {
    const p = page(null, {withFeedback:true}); p.click('report-mode-ai');
    historyResponse(p.calls[0], [completedHistory]); await settle();
    const button = deleteButton(p); p.click('conversation-refresh');
    assert.equal(button.disabled, true);
    button.listeners.click(); assert.equal(p.calls.length, 2);
    historyResponse(p.calls[1], [{...completedHistory, can_delete:false, is_completed:false, ticket_status:'reopened'}]); await settle();
    assert.equal(deleteButton(p), undefined);
});

test('shared feedback keeps chat statuses after five seconds so the next history page still loads', async () => {
    const p = page(null, {withFeedback: true}); p.click('report-mode-ai');
    const history = (current) => ({data: [{id: current, room_code: `A20${current}`}], meta: {pagination: {current_page: current, last_page: 2, total: 11}}});
    p.calls[0].resolve({ok: true, status: 200, json: async () => history(1)}); await settle();
    p.advanceFeedback(6000);
    assert.deepEqual([...p.removed], ['flash-status'], 'only temporary feedback should disappear');
    p.click('conversation-next');
    assert.equal(p.calls[1].url, '/api/support-sessions?page=2&per_page=10');
    p.calls[1].resolve({ok: true, status: 200, json: async () => history(2)}); await settle();
    assert.equal(p.get('conversation-page').textContent, 'Trang 2 / 2');
    assert.equal(p.get('conversation-refresh').attributes['aria-busy'], 'false');
    assert.equal(p.get('conversation-refresh').disabled, false);
    assert.equal(p.get('conversation-prev').disabled, false);
    assert.equal(p.get('conversation-next').disabled, true);
    p.click('conversation-prev');
    assert.equal(p.calls[2].url, '/api/support-sessions?page=1&per_page=10');
    p.calls[2].resolve({ok: true, status: 200, json: async () => history(1)}); await settle();
});

test('history failure after feedback timers expire releases loading and can be retried', async () => {
    const p = page(null, {withFeedback: true}); p.click('report-mode-ai');
    p.advanceFeedback(6000);
    p.calls[0].reject(new TypeError('offline')); await settle();
    assert.match(p.get('conversation-history-status').textContent, /Chưa tải được lịch sử/);
    assert.equal(p.get('conversation-refresh').disabled, false);
    assert.equal(p.get('conversation-refresh').attributes['aria-busy'], 'false');
    p.click('conversation-refresh');
    assert.equal(p.calls[1].url, '/api/support-sessions?page=1&per_page=10');
    p.calls[1].resolve({ok: true, status: 200, json: async () => ({data: [], meta: {pagination: {current_page: 1, last_page: 1, total: 0}}})});
    await settle();
    assert.equal(p.get('conversation-history-status').textContent, 'Chưa có hội thoại nào.');
    assert.equal(p.get('conversation-refresh').attributes['aria-busy'], 'false');
});

test('new AI reply reveals progressively with a complete accessible text and can be shown immediately', async () => {
    const p = page(); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    respond(p.calls[0]); await settle();
    const reply = p.get('conversation-log').children.at(-1);
    const visual = reply.children.find((node) => node.className === 'conversation-reply-visual');
    const accessible = reply.children.find((node) => node.className === 'conversation-sr-only');
    const full = data().messages.at(-1).content_text;
    assert.ok(visual); assert.notEqual(visual.textContent, full);
    assert.equal(visual.attributes['aria-hidden'], 'true');
    assert.equal(accessible.textContent, full);
    const before = visual.textContent.length; p.tick(); assert.ok(visual.textContent.length > before);
    reply.children.find((node) => node.className === 'conversation-reveal-skip').listeners.click();
    assert.equal(visual.textContent, full); assert.equal(p.animationJobs.size, 0);
});

test('reveal completes exactly and a new conversation cancels animation without resurrecting the reply', async () => {
    const p = page(); p.get('conversation-input').value = 'Sự cố'; p.submit(); respond(p.calls[0]); await settle();
    const reply = p.get('conversation-log').children.at(-1);
    const visual = reply.children.find((node) => node.className === 'conversation-reply-visual');
    assert.ok(visual);
    for (let i = 0; i < 100; i++) p.tick();
    assert.equal(visual.textContent, data().messages.at(-1).content_text);
    assert.equal(p.animationJobs.size, 0);
    p.get('conversation-input').value = 'Vẫn lỗi'; p.submit(); respond(p.calls[1]); await settle();
    assert.equal(p.animationJobs.size, 1);
    p.click('conversation-new'); assert.equal(p.animationJobs.size, 0);
    p.tick(); assert.equal(p.get('conversation-log').children.length, 1);
});

test('history and reduced motion display complete replies without a reveal timer', async () => {
    const p = page({userId: 1, sessionId: 5, mode: 'ai'});
    respond(p.calls[0]); await settle(); assert.equal(p.animationJobs.size, 0);
    assert.match(text(p.get('conversation-log')), /Hãy kiểm tra chế độ trình chiếu/);
    const reduced = page(null, {reducedMotion: true});
    reduced.get('conversation-input').value = 'Sự cố'; reduced.submit(); respond(reduced.calls[0]); await settle();
    assert.equal(reduced.animationJobs.size, 0);
    assert.match(text(reduced.get('conversation-log')), /Hãy kiểm tra chế độ trình chiếu/);
});

test('logout cancels reveal and animation does not force the scroll while reading older messages', async () => {
    const p = page(); p.get('conversation-input').value = 'Sự cố'; p.submit(); respond(p.calls[0]); await settle();
    const log = p.get('conversation-log'); log.scrollHeight = 500; log.clientHeight = 100; log.scrollTop = 0;
    p.tick(); assert.equal(log.scrollTop, 0);
    assert.equal(p.animationJobs.size, 1);
    p.documentListeners.submit({target:{closest:()=>({})}});
    assert.equal(p.animationJobs.size, 0); p.tick(); assert.equal(log.children.length, 0);
});

test('message appears immediately with a delivery label and animated thinking while response is pending', async () => {
    const p = page(); p.click('report-mode-ai');
    p.get('conversation-log').scrollHeight = 900;
    p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    assert.equal(userMessages(p).length, 1);
    assert.match(text(userMessages(p)[0]), /Máy chiếu không có hình.*Đang gửi/s);
    assert.equal(p.get('conversation-input').value, '');
    assert.equal(thinking(p).length, 1);
    assert.match(text(thinking(p)[0]), /AI đang suy nghĩ/);
    assert.equal(thinking(p)[0].children.at(-1).children.length, 3);
    assert.equal(p.get('conversation-log').scrollTop, 900);
    p.submit(); assert.equal(p.calls.length, 1);
    respond(p.calls[0], data(), 201); await settle();
    assert.equal(thinking(p).length, 0);
    assert.equal(userMessages(p).length, 1);
    assert.doesNotMatch(text(p.get('conversation-log')), /Đang gửi/);
});

test('network failure stops thinking and retry reuses the visible message without duplicates', async () => {
    const p = page(); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    p.calls[0].reject(new TypeError('offline')); await settle();
    assert.equal(thinking(p).length, 0);
    assert.equal(userMessages(p).length, 1);
    assert.match(text(userMessages(p)[0]), /Chưa xác nhận/);
    p.click('conversation-retry');
    assert.equal(userMessages(p).length, 1); assert.equal(thinking(p).length, 1);
    assert.equal(p.calls[0].options.body, p.calls[1].options.body);
    respond(p.calls[1], data(), 201); await settle();
    assert.equal(userMessages(p).length, 1); assert.equal(thinking(p).length, 0);
});

test('validation failure restores the draft and removes unaccepted optimistic content', async () => {
    const p = page(); p.get('conversation-input').value = 'Nội dung cần sửa'; p.submit();
    p.calls[0].resolve({ ok: false, status: 422, json: async () => ({ error: { message: 'Phòng không hợp lệ' } }) }); await settle();
    assert.equal(userMessages(p).length, 0); assert.equal(thinking(p).length, 0);
    assert.equal(p.get('conversation-input').value, 'Nội dung cần sửa');
    assert.equal(p.get('conversation-input').readOnly, false);
    assert.equal(p.get('conversation-retry').hidden, true);
});

test('logout removes optimistic content and thinking and ignores the send response', async () => {
    const p = page(); p.get('conversation-input').value = 'Sự cố'; p.submit();
    p.documentListeners.submit({ target: { closest: () => ({}) } });
    assert.equal(p.get('conversation-log').children.length, 0);
    respond(p.calls[0], data(), 201); await settle();
    assert.equal(p.get('conversation-log').children.length, 0);
});

test('report retries show the report action and reconcile persisted messages without duplicates', async () => {
    const p = page(); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    respond(p.calls[0], data(), 201); await settle();
    p.click('conversation-escalate');
    assert.match(text(thinking(p)[0]), /Đang gửi báo cáo/);
    const messages = [...data().messages, { role: 'user', content_text: 'Gửi báo cáo giúp tôi' }];
    respond(p.calls[1], data({ session: { ...data().session, status: 'handoff_pending' }, messages }), 503); await settle();
    assert.equal(thinking(p).length, 0); assert.equal(userMessages(p).length, 2);
    p.click('conversation-retry');
    assert.equal(userMessages(p).length, 2); assert.equal(thinking(p).length, 1);
    assert.equal(p.calls[1].options.body, p.calls[2].options.body);
});

test('a draft typed after an uncertain send is kept when retry succeeds', async () => {
    const p = page(); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    p.calls[0].reject(new TypeError('offline')); await settle();
    p.get('conversation-input').value = 'Thông tin bổ sung chưa gửi';
    p.click('conversation-retry'); respond(p.calls[1], data(), 201); await settle();
    assert.equal(p.get('conversation-input').value, 'Thông tin bổ sung chưa gửi');
});

test('send button shows processing and action buttons are disabled until the request ends', async () => {
    const p = page(); p.get('conversation-send').textContent = 'Gửi tin nhắn';
    p.get('conversation-input').value = 'Sự cố máy chiếu'; p.submit();
    assert.equal(p.get('conversation-send').textContent, 'Đang gửi…');
    assert.equal(p.get('conversation-send').attributes['aria-busy'], 'true');
    for (const id of ['conversation-send', 'conversation-new', 'conversation-escalate', 'conversation-resolve']) assert.equal(p.get(id).disabled, true);
    respond(p.calls[0], data(), 201); await settle();
    assert.equal(p.get('conversation-send').textContent, 'Gửi tin nhắn');
    assert.equal(p.get('conversation-send').attributes['aria-busy'], 'false');
    assert.equal(p.get('conversation-new').disabled, false);
});

test('new conversation confirms reset and cannot be replaced by an old restore response', async () => {
    const p = page({userId: 1, sessionId: 5, mode: 'ai'});
    p.click('conversation-new');
    assert.match(p.get('conversation-status').textContent, /Đã mở cuộc trò chuyện mới/);
    assert.equal(userMessages(p).length, 0);
    assert.equal(JSON.parse(p.storage.get('helpdesk_active_support_session')).sessionId, null);
    respond(p.calls[0], data({session: {...data().session, status: 'handed_off'}})); await settle();
    assert.equal(userMessages(p).length, 0);
    assert.match(p.get('conversation-context').textContent, /Cuộc trò chuyện mới/);
    p.get('conversation-input').value = 'Sự cố mới'; p.submit();
    assert.equal(p.calls[1].url, '/api/support-conversations');
});

test('new conversation clears completed report and stays empty after switching modes', async () => {
    const p = page({userId: 1, sessionId: 5, mode: 'ai'});
    respond(p.calls[0], data({session: {...data().session, status:'handed_off'}, report:{ticket_id:9,ticket_number:'HD-OLD'}})); await settle();
    p.click('conversation-new');
    assert.equal(p.get('conversation-report').children.length, 0);
    assert.equal(p.get('conversation-retry').hidden, true);
    assert.equal(p.get('conversation-input').disabled, false);
    p.click('report-mode-manual'); p.click('report-mode-ai');
    assert.equal(userMessages(p).length, 0);
    assert.equal(p.calls.length, 1);
});

test('two reporting modes preserve the manual form and selecting AI sends no provider request', async () => {
    const p = page(); p.get('title').value = 'Bản thủ công đang soạn';
    p.click('report-mode-ai'); await settle();
    assert.equal(p.get('report-manual-view').hidden, true); assert.equal(p.get('report-ai-view').hidden, false);
    assert.equal(p.calls.length, 0);
    p.click('report-mode-manual');
    assert.equal(p.get('report-manual-view').hidden, false); assert.equal(p.get('title').value, 'Bản thủ công đang soạn');
});

test('chat sends only the new user message and receives server history', async () => {
    const p = page(); p.click('report-mode-ai'); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    assert.equal(p.calls[0].url, '/api/support-conversations');
    assert.equal(JSON.parse(p.calls[0].options.body).messages, undefined);
    respond(p.calls[0], data(), 201); await settle();
    assert.equal(p.get('conversation-log').children.length, 2);
    assert.equal(p.get('conversation-input').value, '');
    assert.equal(JSON.parse(p.storage.get('helpdesk_active_support_session')).sessionId, 5);
});

test('report button creates the report inside chat without editing the manual form', async () => {
    const p = page(); p.click('report-mode-ai'); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    respond(p.calls[0], data(), 201); await settle(); p.click('conversation-escalate');
    assert.equal(p.calls[1].url, '/api/support-sessions/5/escalations');
    assert.equal(JSON.parse(p.calls[1].options.body).expected_version, 3);
    respond(p.calls[1], data({ session: { ...data().session, status: 'handed_off', version: 5 }, report: { ticket_id: 9, ticket_number: 'HD-TEST' } }), 201); await settle();
    assert.equal(p.get('conversation-report').hidden, false);
    assert.equal(p.get('conversation-report').children[1].href, '/customer/ticket-detail?ticket=9');
    assert.equal(p.get('title').value, ''); assert.equal(p.get('description').value, '');
    assert.equal(p.get('report-manual-view').hidden, true);
});

test('lost response retries exactly the same key and message; new content does not replace pending intent', async () => {
    const p = page(); p.get('conversation-input').value = 'Máy chiếu không có hình'; p.submit();
    p.calls[0].reject(new TypeError('offline')); await settle();
    assert.equal(p.get('conversation-retry').hidden, false);
    p.get('conversation-input').value = 'Nội dung khác'; p.submit(); assert.equal(p.calls.length, 1);
    p.click('conversation-retry');
    assert.equal(p.calls[1].options.body, p.calls[0].options.body);
    assert.equal(p.calls[1].options.headers['Idempotency-Key'], p.calls[0].options.headers['Idempotency-Key']);
});

test('reload reads history from server and logout ignores late responses', async () => {
    const p = page({ userId: 1, sessionId: 5, mode: 'ai' });
    assert.equal(p.calls[0].url, '/api/support-sessions/5/conversation');
    p.documentListeners.submit({ target: { closest: () => ({}) } });
    respond(p.calls[0]); await settle();
    assert.equal(p.storage.size, 0); assert.equal(p.get('conversation-log').children.length, 0);
    assert.equal(p.get('report-ai-view').hidden, true);
});

test('a different account cannot restore the previous conversation', () => {
    const p = page({ userId: 2, sessionId: 9, mode: 'ai' }); assert.equal(p.calls.length, 0); assert.equal(p.storage.size, 0);
});

test('handoff pending never displays successful creation and retains the retry key', async () => {
    const p = page(); p.get('conversation-input').value = 'Sự cố máy chiếu'; p.submit(); respond(p.calls[0], data(), 201); await settle();
    p.click('conversation-escalate');
    respond(p.calls[1], data({ session: { ...data().session, status: 'handoff_pending', version: 4 }, command: { status: 'handoff_pending' } }), 503); await settle();
    assert.equal(p.get('conversation-report').hidden, true); assert.equal(p.get('conversation-retry').hidden, false);
    p.click('conversation-retry');
    assert.equal(p.calls[2].options.headers['Idempotency-Key'], p.calls[1].options.headers['Idempotency-Key']);
});

test('report action keeps unsent incident details in the conversation', async () => {
    const p = page(); p.get('conversation-input').value = 'Sự cố máy chiếu'; p.submit(); respond(p.calls[0], data(), 201); await settle();
    p.get('conversation-input').value = 'Tôi đã thử đổi chế độ nhưng vẫn không có hình'; p.click('conversation-escalate');
    assert.equal(JSON.parse(p.calls[1].options.body).content, 'Tôi đã thử đổi chế độ nhưng vẫn không có hình');
    assert.equal(p.calls[1].url, '/api/support-sessions/5/messages');
});
