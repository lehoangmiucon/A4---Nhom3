(() => {
    const get = (id) => document.getElementById(id);
    const root = get('report-ai-view');
    if (!root) return;
    const config = window.HELPDESK_CONFIG || {};
    const api = (config.apiBase || '/api').replace(/\/$/, '');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const userId = config.currentUserId;
    const storageKey = 'helpdesk_active_support_session';
    const log = get('conversation-log');
    const status = get('conversation-status');
    const input = get('conversation-input');
    const room = get('conversation-room');
    const category = get('conversation-category');
    const retry = get('conversation-retry');
    let session = null;
    let pending = null;
    let busy = false;
    let loadingConversation = false;
    let revision = 0;
    let listRevision = 0;
    let page = 1;
    let lastPage = 1;
    let initialized = false;
    let loggedOut = false;
    let saved = null;
    let reveal = null;
    let deleting = false;
    let loadingHistory = false;
    let historyDeleteButtons = [];

    const removeStorage = () => { try { sessionStorage.removeItem(storageKey); } catch {} };
    try {
        saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (saved?.userId !== userId) { saved = null; removeStorage(); }
    } catch { removeStorage(); }
    const remember = () => {
        try { sessionStorage.setItem(storageKey, JSON.stringify({ userId, sessionId: session?.id || null, mode: root.hidden ? 'manual' : 'ai' })); } catch {}
    };
    const say = (text, error = false) => { status.textContent = text; status.classList.toggle('is-error', error); };
    const buttonLabels = new WeakMap();
    const setButtonBusy = (button, active, label = 'Đang tải…') => {
        if (!buttonLabels.has(button)) buttonLabels.set(button, button.textContent);
        button.textContent = active ? label : buttonLabels.get(button);
        button.setAttribute('aria-busy', String(active));
    };
    const syncActions = () => {
        const blocked = busy || loadingConversation || session?.status === 'handed_off';
        get('conversation-send').disabled = blocked;
        get('conversation-escalate').disabled = blocked;
        get('conversation-resolve').disabled = blocked || !session;
        get('conversation-new').disabled = busy;
        retry.disabled = busy;
        input.disabled = loadingConversation || session?.status === 'handed_off';
        historyDeleteButtons.forEach((button) => { button.disabled = busy || loadingConversation || loadingHistory || !!pending; });
    };
    const request = async (path, options = {}) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 35000);
        try {
            const response = await fetch(api + path, {
                ...options, credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
            });
            const body = await response.json().catch(() => null);
            return { ok: response.ok, status: response.status, body };
        } catch { return { ok: false, status: 0, body: null }; }
        finally { clearTimeout(timer); }
    };
    const bubble = (role, text) => {
        const message = document.createElement('p');
        message.className = 'conversation-message' + (role === 'user' ? ' is-user' : '');
        const sender = document.createElement('strong');
        sender.textContent = role === 'user' ? 'Bạn' : 'Trợ lý hỗ trợ';
        message.append(sender, document.createTextNode(text));
        log.appendChild(message);
        return message;
    };
    const finishReveal = () => {
        if (!reveal) return;
        clearTimeout(reveal.timer);
        reveal.visual.textContent = reveal.text;
        reveal.message.classList.toggle('is-revealing', false);
        reveal.skip.remove();
        reveal = null;
    };
    const revealReply = (text) => {
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return bubble('assistant', text);
        const message = bubble('assistant', '');
        const visual = document.createElement('span');
        visual.className = 'conversation-reply-visual'; visual.setAttribute('aria-hidden', 'true');
        // Announce the complete reply once rather than every animation update.
        const accessible = document.createElement('span');
        accessible.className = 'conversation-sr-only'; accessible.textContent = text;
        const skip = document.createElement('button');
        skip.type = 'button'; skip.className = 'conversation-reveal-skip'; skip.textContent = 'Hiện toàn bộ';
        skip.addEventListener('click', finishReveal);
        message.append(visual, accessible, skip);
        message.classList.toggle('is-revealing', true);
        const characters = Array.from(text);
        const step = Math.max(1, Math.ceil(characters.length / 80));
        const current = {message, visual, skip, text, timer: null};
        reveal = current;
        let offset = 0;
        const tick = () => {
            if (loggedOut || reveal !== current) return;
            const follow = log.scrollHeight - log.scrollTop - log.clientHeight < 64;
            offset += step; visual.textContent = characters.slice(0, offset).join('');
            if (follow) log.scrollTop = log.scrollHeight;
            if (offset >= characters.length) finishReveal();
            else current.timer = setTimeout(tick, 20);
        };
        tick();
        return message;
    };
    const showWaiting = (intent) => {
        finishReveal();
        if (!intent.message && !intent.confirmed) {
            intent.message = bubble('user', intent.body.content);
            intent.delivery = document.createElement('small');
            intent.delivery.className = 'conversation-delivery';
            intent.message.appendChild(intent.delivery);
        }
        if (intent.delivery) intent.delivery.textContent = 'Đang gửi…';
        const label = intent.action === 'report' ? 'Đang gửi báo cáo' : intent.action === 'resolve' ? 'Đang cập nhật' : 'AI đang suy nghĩ';
        const indicator = bubble('assistant', label);
        indicator.className += ' is-thinking';
        indicator.setAttribute('role', 'status');
        const dots = document.createElement('span');
        dots.className = 'conversation-thinking-dots';
        dots.setAttribute('aria-hidden', 'true');
        for (let i = 0; i < 3; i++) dots.appendChild(document.createElement('span'));
        indicator.appendChild(dots);
        log.scrollTop = log.scrollHeight;
        return indicator;
    };
    const emptyChat = () => {
        finishReveal();
        log.replaceChildren();
        get('conversation-context-picker').open = true;
        bubble('assistant', room.value
            ? 'Mình đã nhận phòng bạn chọn. Bạn cần hỗ trợ công việc hoặc thiết bị gì? Hãy chọn danh mục và mô tả vấn đề cụ thể để mình bắt đầu hỗ trợ.'
            : 'Trước khi bắt đầu, bạn đang ở phòng nào và cần hỗ trợ công việc hoặc thiết bị gì? Bạn có thể chọn phòng, danh mục bên dưới hoặc nêu trong tin nhắn, kèm mô tả vấn đề cụ thể.');
    };
    const render = (data, animateLatest = false) => {
        finishReveal();
        session = data.session;
        log.replaceChildren();
        data.messages.forEach((message, index) => {
            if (animateLatest && index === data.messages.length - 1 && message.role === 'assistant') revealReply(message.content_text);
            else bubble(message.role, message.content_text);
        });
        log.scrollTop = log.scrollHeight;
        room.value = session.room_id ? String(session.room_id) : '';
        category.value = session.category_id ? String(session.category_id) : '';
        get('conversation-context').textContent = `${session.room_code || 'Chưa xác định phòng'} · ${data.category_label || 'Chưa xác định danh mục'} · ${session.status === 'handed_off' ? 'Đã gửi báo cáo' : session.status === 'resolved' ? 'Bạn đã xác nhận khắc phục được' : 'Hội thoại đã lưu'}`;
        get('conversation-context-picker').open = !!(data.needs_context || data.needs_description) && !['handed_off', 'resolved'].includes(session.status);
        syncActions();
        const receipt = get('conversation-report');
        receipt.replaceChildren(); receipt.hidden = !data.report;
        if (data.report) {
            const text = document.createElement('p');
            text.textContent = `Đã gửi báo cáo ${data.report.ticket_number}. Phiếu đang chờ quản lý tiếp nhận.`;
            const link = document.createElement('a');
            link.href = `/customer/ticket-detail?ticket=${encodeURIComponent(data.report.ticket_id)}`;
            link.textContent = 'Xem phiếu và theo dõi xử lý';
            receipt.append(text, link);
        }
        remember();
    };
    const loadConversation = async (id) => {
        if (busy || pending) { say('Lần gửi trước chưa được xác nhận. Hãy bấm Thử lại trước khi chuyển hội thoại.', true); return; }
        const current = ++revision;
        finishReveal();
        loadingConversation = true; syncActions();
        say('Đang đọc hội thoại đã lưu…');
        const response = await request(`/support-sessions/${encodeURIComponent(id)}/conversation`);
        if (loggedOut || current !== revision) return;
        loadingConversation = false; syncActions();
        if (!response.ok || !response.body?.data?.session) {
            say(response.body?.error?.message || 'Không thể đọc hội thoại. Bấm Tải lại để thử lại.', true);
            if ([401, 403, 404].includes(response.status)) { removeStorage(); session = null; emptyChat(); }
            return;
        }
        render(response.body.data); say('');
    };
    const loadHistory = async (requestedPage = page) => {
        if (deleting || loggedOut) return;
        const current = ++listRevision;
        loadingHistory = true; syncActions();
        setButtonBusy(get('conversation-refresh'), true);
        get('conversation-refresh').disabled = true;
        get('conversation-prev').disabled = true; get('conversation-next').disabled = true;
        get('conversation-history-status').textContent = 'Đang tải…';
        const response = await request(`/support-sessions?page=${requestedPage}&per_page=10`);
        if (loggedOut || current !== listRevision) return;
        loadingHistory = false; syncActions();
        setButtonBusy(get('conversation-refresh'), false);
        get('conversation-refresh').disabled = false;
        get('conversation-prev').disabled = page <= 1; get('conversation-next').disabled = page >= lastPage;
        if (!response.ok || !Array.isArray(response.body?.data)) {
            get('conversation-history-status').textContent = 'Chưa tải được lịch sử. Bấm Tải lại để thử lại.'; return;
        }
        const pagination = response.body.meta?.pagination || {};
        if (requestedPage > (pagination.last_page || 1) && response.body.data.length === 0) {
            return loadHistory(pagination.last_page || 1);
        }
        page = pagination.current_page || requestedPage; lastPage = pagination.last_page || 1;
        const list = get('conversation-history-list'); list.replaceChildren();
        historyDeleteButtons = [];
        response.body.data.forEach((item) => {
            const li = document.createElement('li'); const button = document.createElement('button');
            button.type = 'button'; button.className = 'conversation-secondary';
            button.textContent = item.room_code || 'Chưa xác định phòng';
            const preview = document.createElement('small'); preview.textContent = item.initial_description || 'Hội thoại hỗ trợ';
            button.appendChild(preview); button.addEventListener('click', () => loadConversation(item.id)); li.appendChild(button); list.appendChild(li);
            const badge = document.createElement('span');
            badge.className = 'conversation-outcome' + (item.is_completed ? ' is-completed' : '');
            badge.textContent = item.is_completed ? 'Đã xử lý xong' : item.ticket_status === 'resolved' ? 'Chờ xác nhận hoàn thành'
                : item.ticket_status === 'reopened' ? 'Sự cố cần xử lý lại' : item.status === 'handed_off' ? 'Đã báo cáo · Chưa hoàn tất'
                : item.status === 'resolved' ? 'Bạn đã xác nhận khắc phục' : 'Chưa khắc phục';
            li.appendChild(badge);
            if (item.can_delete === true) {
                const remove = document.createElement('button');
                remove.type = 'button'; remove.className = 'conversation-secondary conversation-delete';
                remove.textContent = 'Xóa phiên';
                remove.setAttribute('aria-label', `Xóa phiên ${item.room_code || item.id} đã xử lý xong`);
                remove.addEventListener('click', () => deleteConversation(item, remove));
                historyDeleteButtons.push(remove); li.appendChild(remove);
            }
        });
        get('conversation-history-status').textContent = response.body.data.length ? `${pagination.total || response.body.data.length} hội thoại` : 'Chưa có hội thoại nào.';
        get('conversation-page').textContent = `Trang ${page} / ${lastPage}`;
        get('conversation-prev').disabled = page <= 1; get('conversation-next').disabled = page >= lastPage;
        syncActions();
    };
    const deleteConversation = async (item, button) => {
        if (busy || pending || loadingConversation || loadingHistory || loggedOut) return;
        if (!window.confirm('Xóa phiên hội thoại đã xử lý xong? Phiếu sự cố và kết quả xử lý vẫn được giữ lại.')) return;
        deleting = true; busy = true; listRevision++;
        const current = revision;
        syncActions(); setButtonBusy(button, true, 'Đang xóa…');
        get('conversation-refresh').disabled = true;
        get('conversation-prev').disabled = true; get('conversation-next').disabled = true;
        say('Đang xóa phiên…');
        const response = await request(`/support-sessions/${encodeURIComponent(item.id)}`, {method: 'DELETE'});
        if (loggedOut || current !== revision) return;
        deleting = false; busy = false;
        setButtonBusy(button, false); syncActions();
        setButtonBusy(get('conversation-refresh'), false);
        get('conversation-refresh').disabled = false;
        get('conversation-prev').disabled = page <= 1; get('conversation-next').disabled = page >= lastPage;
        if (!response.ok || response.body?.data?.deleted !== true) {
            const detail = response.body?.error?.details?.session?.[0];
            say(detail || response.body?.error?.message || 'Chưa xác nhận được việc xóa phiên. Hãy thử lại.', true);
            return;
        }
        if (session?.id === item.id || saved?.sessionId === item.id) resetConversation();
        await loadHistory(page);
        if (loggedOut) return;
        say('Đã xóa phiên hội thoại. Phiếu sự cố và kết quả xử lý vẫn được giữ lại.');
        get('conversation-refresh').focus();
    };
    const selectMode = (ai) => {
        if (!ai) finishReveal();
        root.hidden = !ai; get('report-manual-view').hidden = ai;
        get('report-mode-ai').setAttribute('aria-pressed', String(ai));
        get('report-mode-manual').setAttribute('aria-pressed', String(!ai));
        if (ai && !initialized) {
            initialized = true; loadHistory();
            if (saved?.sessionId) loadConversation(saved.sessionId);
            else {
                const selectedRoom = get('room-id')?.value;
                if (selectedRoom) room.value = selectedRoom;
                emptyChat();
            }
        }
        if (!saved?.sessionId || session) remember();
    };
    const sendPending = async () => {
        if (!pending || busy || loggedOut) return;
        busy = true; const current = revision; const intent = pending;
        const actionButton = get(intent.action === 'report' ? 'conversation-escalate' : intent.action === 'resolve' ? 'conversation-resolve' : 'conversation-send');
        setButtonBusy(actionButton, true, intent.action === 'report' ? 'Đang gửi báo cáo…' : intent.action === 'resolve' ? 'Đang cập nhật…' : 'Đang gửi…');
        syncActions();
        get('conversation-send').disabled = true; input.readOnly = true; retry.hidden = true;
        const indicator = showWaiting(intent);
        say(intent.action === 'report' ? 'Đang gửi báo cáo…' : 'Đang lưu và xử lý tin nhắn…');
        const response = await request(intent.path, { method: 'POST', headers: { 'Idempotency-Key': intent.key }, body: JSON.stringify(intent.body) });
        busy = false;
        if (loggedOut || current !== revision) return;
        setButtonBusy(actionButton, false);
        indicator.remove();
        input.readOnly = false;
        if (response.body?.data?.session) {
            render(response.body.data, response.ok && response.status !== 202 && intent.action === 'chat');
            intent.confirmed = true; intent.message = null; intent.delivery = null;
        }
        syncActions();
        if (!response.ok || response.status === 202 || !response.body?.data?.session) {
            if (intent.delivery) intent.delivery.textContent = 'Chưa xác nhận • Bấm Thử lại';
            retry.hidden = false;
            const details = response.body?.error?.details;
            const fieldError = details && Object.values(details).flat()[0];
            say(fieldError || response.body?.error?.message || (response.status === 503 ? 'Yêu cầu gửi đã được lưu nhưng chưa tạo xong phiếu. Bấm Thử lại; không tạo yêu cầu mới.' : 'Chưa xác nhận được lần gửi trước. Bấm Thử lại để gửi lại cùng yêu cầu.'), true);
            if ([409, 422].includes(response.status)) {
                intent.message?.remove();
                if (!input.value) input.value = intent.draft;
                pending = null; retry.hidden = true;
                if (session?.id) loadConversation(session.id);
            }
            return;
        }
        pending = null; retry.hidden = true;
        say(response.body.data.report ? 'Báo cáo đã gửi thành công.' : 'Hội thoại đã được lưu.');
        loadHistory(1);
    };
    const send = (action = 'chat') => {
        if (loggedOut || busy || loadingConversation) return;
        if (pending) { say('Hãy thử lại lần gửi chưa được xác nhận trước khi gửi nội dung mới.', true); retry.hidden = false; return; }
        let content = input.value.trim();
        const escalationOnly = action === 'report' && session && !content;
        if (escalationOnly) content = 'Gửi báo cáo giúp tôi';
        if (action === 'resolve') content = 'Đã khắc phục được';
        if (!content || [...content].length > 1000) { say('Hãy mô tả sự cố trước khi gửi (tối đa 1000 ký tự).', true); input.focus(); return; }
        const body = { content, action };
        if (room.value) body.room_id = Number(room.value);
        if (category.value) body.category_id = Number(category.value);
        if (session) body.expected_version = session.version;
        pending = { action, body, draft: input.value, key: crypto.randomUUID(), path: session ? `/support-sessions/${session.id}/${escalationOnly ? 'escalations' : 'messages'}` : '/support-conversations' };
        input.value = '';
        sendPending();
    };
    get('report-mode-ai').addEventListener('click', () => selectMode(true));
    get('report-mode-manual').addEventListener('click', () => selectMode(false));
    get('conversation-form').addEventListener('submit', (event) => { event.preventDefault(); send(); });
    get('conversation-escalate').addEventListener('click', () => send('report'));
    get('conversation-resolve').addEventListener('click', () => { if (session) send('resolve'); });
    retry.addEventListener('click', sendPending);
    get('conversation-refresh').addEventListener('click', () => { if (deleting) return; loadHistory(); if (session && !pending) loadConversation(session.id); });
    get('conversation-prev').addEventListener('click', () => { if (page > 1) loadHistory(page - 1); });
    get('conversation-next').addEventListener('click', () => { if (page < lastPage) loadHistory(page + 1); });
    const resetConversation = () => {
        if (busy || pending) { say('Hãy thử lại lần gửi chưa được xác nhận trước khi bắt đầu hội thoại mới.', true); return; }
        revision++; session = null; saved = null; initialized = true; loadingConversation = false;
        room.value = get('room-id')?.value || ''; category.value = ''; input.value = ''; input.readOnly = false;
        retry.hidden = true; syncActions();
        get('conversation-report').replaceChildren(); get('conversation-report').hidden = true; get('conversation-context-picker').open = false;
        get('conversation-context').textContent = 'Cuộc trò chuyện mới. Xác định phòng và công việc cần hỗ trợ trước khi bắt đầu.';
        emptyChat(); log.scrollTop = 0; say('Đã mở cuộc trò chuyện mới. Bạn có thể nhập tin nhắn.'); remember(); input.focus();
    };
    get('conversation-new').addEventListener('click', resetConversation);
    const clickTimers = new WeakMap();
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.conversation-card button, .report-mode-picker button');
        if (!button || button.disabled) return;
        clearTimeout(clickTimers.get(button));
        button.classList.toggle('is-clicked', true);
        clickTimers.set(button, setTimeout(() => button.classList.toggle('is-clicked', false), 320));
    });
    document.addEventListener('submit', (event) => {
        if (!event.target.closest('form[data-logout-form]')) return;
        finishReveal();
        loggedOut = true; revision++; listRevision++; pending = null; session = null; saved = null;
        removeStorage(); log.replaceChildren(); get('conversation-history-list').replaceChildren();
        get('conversation-report').replaceChildren(); input.value = ''; room.value = ''; category.value = ''; say('');
        get('conversation-context').textContent = ''; root.hidden = true;
    });
    syncActions();
    if (saved?.mode === 'ai') selectMode(true);
})();
