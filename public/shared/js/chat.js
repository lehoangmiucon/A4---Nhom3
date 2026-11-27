(() => {
    const config = window.HELPDESK_CHAT_CONFIG || {};
    const apiBase = (config.apiBase || '/api/chat').replace(/\/+$/, '');
    const currentUserId = Number(config.currentUserId || 0);
    const currentUserRole = config.currentUserRole || 'student';
    const appRoot = apiBase.replace(/\/api\/chat\/?$/, '');
    const widget = document.getElementById('chat-widget-window');
    const frame = document.querySelector('.chat-frame');
    const list = document.getElementById('chat-conversation-list');
    const count = document.getElementById('conversation-count');
    const search = document.getElementById('chat-search');
    const contacts = document.getElementById('chat-contact-results');
    const empty = document.getElementById('chat-empty');
    const active = document.getElementById('chat-active');
    const title = document.getElementById('chat-title');
    const subtitle = document.getElementById('chat-subtitle');
    const avatar = document.getElementById('chat-avatar');
    const ticketContext = document.getElementById('chat-ticket-context');
    const messages = document.getElementById('chat-messages');
    const composer = document.getElementById('chat-composer');
    const input = document.getElementById('chat-message-input');
    const send = document.getElementById('chat-send');
    const notice = document.getElementById('chat-notice');
    const emptyComposer = document.getElementById('chat-empty-composer');
    const emptyInput = document.getElementById('chat-empty-message-input');
    const emptySend = document.getElementById('chat-empty-send');
    const emptyNotice = document.getElementById('chat-empty-notice');
    let conversations = [];
    let current = null;
    let lastId = 0;
    let lastMessageDay = null;
    let lastMessageAt = null;
    let pollTimer = null;
    let conversationPollTimer = null;
    let searchTimer = null;

    const localizeError = (message, status) => {
        const normalized = String(message || '').trim().toLowerCase();
        if (normalized.includes('csrf token mismatch') || status === 419) {
            return 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
        }
        if (normalized.includes('unauthenticated') || status === 401) {
            return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
        }
        if (normalized.includes('unauthorized') || status === 403) {
            return 'Bạn không có quyền thực hiện thao tác này.';
        }
        if (status === 404) return 'Không tìm thấy dữ liệu yêu cầu.';
        if (status === 429) return 'Bạn thao tác quá nhanh. Vui lòng chờ một lúc rồi thử lại.';
        if (status >= 500) return 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
        if (normalized.includes('invalid') || status === 422) {
            return 'Dữ liệu gửi lên chưa hợp lệ. Vui lòng kiểm tra lại.';
        }
        return /[À-ỹ]/.test(message) && !/<(?:!doctype|html)/i.test(message)
            ? message
            : 'Không thể thực hiện thao tác. Vui lòng kiểm tra lại và thử lại.';
    };

    const request = async (path, options = {}) => {
        let response;
        try {
            response = await fetch(`${apiBase}${path}`, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(options.headers || {}),
                },
            });
        } catch (_) {
            throw new Error('Không thể kết nối máy chủ. Vui lòng kiểm tra mạng và thử lại.');
        }
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const rawMessage = payload.message || Object.values(payload.errors || {}).flat().join(' ');
            const message = localizeError(rawMessage, response.status);
            throw new Error(message);
        }
        return payload;
    };

    const showNotice = (message) => {
        notice.textContent = message;
        notice.hidden = !message;
        emptyNotice.textContent = message;
        emptyNotice.hidden = !message;
    };

    const conversationName = (conversation) => conversation.title || 'Cuộc trò chuyện';
    const roleLabel = (role) => ({student: 'Sinh viên', teacher: 'Giảng viên', staff: 'Kỹ thuật viên', admin: 'Quản trị viên'})[role] || '';

    const renderAvatar = (element, person, fallback) => {
        const source = person?.avatar_url || '';
        // Keep the existing image while polling the same profile.
        if (source && element.querySelector('img')?.getAttribute('src') === source) return;
        element.textContent = fallback;
        if (!source) return;
        const image = document.createElement('img');
        image.alt = '';
        image.width = 40;
        image.height = 40;
        image.addEventListener('error', () => {
            if (element.contains(image)) element.textContent = fallback;
        }, { once: true });
        image.src = source;
        element.replaceChildren(image);
    };

    const renderConversationAvatar = (element, conversation) => {
        const assistant = conversation.type === 'assistant';
        element.classList.toggle('assistant', assistant);
        renderAvatar(element, assistant ? null : conversation.participants?.[0],
            assistant ? '✦' : conversationName(conversation).slice(0, 1).toUpperCase());
    };

    const renderConversationHeader = () => {
        title.textContent = conversationName(current);
        renderConversationAvatar(avatar, current);
        subtitle.textContent = current.type === 'assistant'
            ? 'Hỏi về quy trình và cách sử dụng Helpdesk'
            : (current.participants || []).map((person) => roleLabel(person.role)).filter(Boolean).join(' · ');
    };

    const messageDateInfo = (value) => {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return null;
        const key = [date.getFullYear(), date.getMonth() + 1, date.getDate()].join('-');
        const today = new Date();
        const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const dayOffset = Math.round((todayOnly - dateOnly) / 86400000);
        const time = date.toLocaleTimeString('vi-VN', {
            hour: '2-digit', minute: '2-digit', hour12: false,
        });
        const weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
        const weekday = weekdays[(date.getDay() + 6) % 7];
        const dateText = [
            String(date.getDate()).padStart(2, '0'),
            String(date.getMonth() + 1).padStart(2, '0'),
            date.getFullYear(),
        ].join('/');
        const label = dayOffset === 0
            ? time
            : dayOffset === 1
                ? time + ' Hôm qua'
                : dayOffset < 7
                    ? time + ' ' + weekday
                    : time + ' ' + weekday + ' ' + dateText;
        return { key, label, date };
    };
    const renderConversations = () => {
        const query = !config.canSearchContacts ? (search?.value || '').trim().toLocaleLowerCase('vi') : '';
        const visibleConversations = query ? conversations.filter((conversation) => {
            const searchable = [conversation.title, conversation.last_message?.body, conversation.ticket?.title,
                conversation.ticket?.ticket_number, ...(conversation.participants || []).map((person) => `${person.name || ''} ${person.email || ''}`)]
                .filter(Boolean).join(' ').toLocaleLowerCase('vi');
            return searchable.includes(query);
        }) : conversations;
        const unreadTotal = visibleConversations.reduce((total, conversation) => total + Number(conversation.unread_count || 0), 0);
        count.textContent = unreadTotal > 99 ? '99+' : String(unreadTotal);
        count.hidden = unreadTotal === 0;
        count.setAttribute('aria-label', `${unreadTotal} tin nhắn chưa đọc`);
        list.replaceChildren();
        if (!visibleConversations.length) {
            const hint = document.createElement('p');
            hint.className = 'chat-empty-list';
            hint.textContent = query ? 'Không tìm thấy cuộc trò chuyện phù hợp.' : 'Chưa có tin nhắn.';
            list.append(hint);
            return;
        }
        visibleConversations.forEach((conversation) => {
            const button = document.createElement('button');
            button.type = 'button';
            const unreadCount = Number(conversation.unread_count || 0);
            button.className = `chat-conversation${current?.id === conversation.id ? ' active' : ''}${unreadCount > 0 ? ' unread' : ''}`;
            const face = document.createElement('span');
            face.className = `chat-avatar${conversation.type === 'assistant' ? ' assistant' : ''}`;
            renderConversationAvatar(face, conversation);
            const copy = document.createElement('span');
            copy.className = 'chat-conversation-copy';
            const name = document.createElement('strong');
            name.textContent = conversationName(conversation);
            const preview = document.createElement('small');
            preview.textContent = conversation.last_message?.body || conversation.ticket?.title || 'Bắt đầu trò chuyện';
            copy.append(name, preview);
            button.append(face, copy);
            if (unreadCount > 0) {
                const unread = document.createElement('span');
                unread.className = 'chat-unread-count';
                unread.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                unread.setAttribute('aria-label', `${unreadCount} tin nhắn chưa đọc`);
                button.append(unread);
            }
            button.addEventListener('click', () => openConversation(conversation));
            list.append(button);
        });
    };

    const renderMessage = (message) => {
        const day = messageDateInfo(message.created_at);
        if (messages.querySelector(`[data-message-id="${message.id}"]`)) return;
        const showDateDivider = day && (
            day.key !== lastMessageDay
            || (lastMessageAt && day.date.getTime() - lastMessageAt.getTime() >= 300000)
        );
        const item = document.createElement('article');
        item.className = `chat-message${message.sender_type === 'assistant' ? ' assistant' : ''}${message.sender_type === 'system' ? ' system' : ''}${message.user_id === currentUserId ? ' mine' : ''}`;
        item.dataset.messageId = message.id;
        item.append(document.createTextNode(message.body || ''));
        const metadata = message.metadata || {};
        if (metadata.ticket_id) {
            const ticketLink = document.createElement('a');
            ticketLink.className = 'chat-ticket-link';
            const ticketPage = currentUserRole === 'admin'
                ? '/admin/pages/ticket-detail'
                : currentUserRole === 'staff'
                    ? '/staff/ticket-detail'
                    : '/customer/ticket-detail';
            ticketLink.href = `${appRoot}${ticketPage}?ticket=${encodeURIComponent(metadata.ticket_id)}`;
            ticketLink.textContent = `Mở phiếu ${metadata.ticket_number || metadata.ticket_id}`;
            item.append(ticketLink);
        }
        if (message.sender_type !== 'system' && message.created_at) {
            const time = document.createElement('time');
            const date = new Date(message.created_at);
            time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            item.append(time);
        }
        if (day) {
            item.dataset.messageDay = day.key;
            if (showDateDivider) {
                const divider = document.createElement('div');
                divider.className = 'chat-date-separator';
                const label = document.createElement('span');
                label.textContent = day.label;
                divider.append(label);
                messages.append(divider);
            }
            lastMessageDay = day.key;
            lastMessageAt = day.date;
        }
        messages.append(item);
        lastId = Math.max(lastId, Number(message.id || 0));
    };

    const refreshMessages = async (initial = false) => {
        if (!current) return;
        const query = initial ? '' : `?after_id=${lastId}`;
        const payload = await request(`/conversations/${current.id}/messages${query}`);
        (payload.data || []).forEach(renderMessage);
        if (initial || payload.data?.length) messages.scrollTop = messages.scrollHeight;
    };

    const openConversation = async (conversation) => {
        current = conversation;
        lastId = 0;
        lastMessageDay = null;
        lastMessageAt = null;
        messages.replaceChildren();
        empty.hidden = true;
        active.hidden = false;
        frame.classList.add('thread-open');
        renderConversationHeader();
        if (conversation.ticket) {
            ticketContext.textContent = `${conversation.ticket.ticket_number} · ${conversation.ticket.title}`;
            ticketContext.hidden = false;
        } else {
            ticketContext.hidden = true;
        }
        renderConversations();
        showNotice('');
        if (pollTimer) window.clearInterval(pollTimer);
        try {
            await refreshMessages(true);
            pollTimer = window.setInterval(() => refreshMessages().catch(() => {}), 2000);
            await refreshConversations().catch(() => {});
        } catch (error) {
            showNotice(error.message);
        }
    };

    const refreshConversations = async () => {
        const payload = await request('/conversations');
        conversations = payload.data || [];
        renderConversations();
        if (current) {
            current = conversations.find((item) => item.id === current.id) || current;
            renderConversationHeader();
            renderConversations();
        }
    };

    const openWidget = async () => {
        if (!widget) return;
        widget.hidden = false;
        frame.classList.remove('thread-open');
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = null;
        frame.setAttribute('aria-busy', 'true');
        try {
            await refreshConversations();
            if (conversationPollTimer) window.clearInterval(conversationPollTimer);
            conversationPollTimer = window.setInterval(() => {
                if (!widget?.hidden && !document.hidden) refreshConversations().catch(() => {});
            }, 2500);
            showNotice('');
        } catch (error) {
            showNotice(error.message);
        } finally {
            frame.removeAttribute('aria-busy');
        }
    };

    const closeWidget = () => {
        if (widget) widget.hidden = true;
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = null;
        if (conversationPollTimer) window.clearInterval(conversationPollTimer);
        conversationPollTimer = null;
    };

    const renderContacts = (rows) => {
        contacts.replaceChildren();
        if (!rows.length) {
            const none = document.createElement('div');
            none.className = 'chat-empty-list';
            none.textContent = 'Không tìm thấy tài khoản phù hợp.';
            contacts.append(none);
        }
        rows.forEach((person) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'chat-contact';
            const face = document.createElement('span');
            face.className = 'chat-avatar';
            renderAvatar(face, person, (person.name || '?').slice(0, 1).toUpperCase());
            const copy = document.createElement('span');
            copy.className = 'chat-contact-copy';
            const name = document.createElement('strong');
            name.textContent = person.name || 'Người dùng';
            const detail = document.createElement('small');
            detail.textContent = [person.email, person.phone].filter(Boolean).join(' · ');
            copy.append(name, detail);
            const role = document.createElement('span');
            role.className = 'chat-role';
            role.textContent = roleLabel(person.role);
            button.append(face, copy, role);
            button.addEventListener('click', async () => {
                try {
                    const payload = await request('/conversations', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: person.id }),
                    });
                    await refreshConversations();
                    await openConversation(payload.data);
                    search.value = '';
                    contacts.hidden = true;
                } catch (error) { showNotice(error.message); }
            });
            contacts.append(button);
        });
        contacts.hidden = false;
    };

    search?.addEventListener('input', () => {
        if (!config.canSearchContacts) {
            contacts?.replaceChildren();
            if (contacts) contacts.hidden = true;
            renderConversations();
            return;
        }
        window.clearTimeout(searchTimer);
        const value = search.value.trim();
        if (value.length < 2) { contacts.hidden = true; return; }
        searchTimer = window.setTimeout(async () => {
            try {
                const payload = await request(`/contacts?q=${encodeURIComponent(value)}`);
                renderContacts(payload.data || []);
            } catch (error) { showNotice(error.message); }
        }, 300);
    });

    document.getElementById('chat-ai').addEventListener('click', async () => {
        try {
            const payload = await request('/assistant', { method: 'POST' });
            await refreshConversations();
            await openConversation(payload.data);
        } catch (error) { showNotice(error.message); }
    });
    document.getElementById('chat-support')?.addEventListener('click', async () => {
        try {
            const payload = await request('/support', { method: 'POST' });
            await refreshConversations();
            await openConversation(payload.data);
        } catch (error) { showNotice(error.message); }
    });
    document.getElementById('chat-refresh').addEventListener('click', () => refreshConversations().catch((error) => showNotice(error.message)));
    document.getElementById('chat-mobile-back').addEventListener('click', () => frame.classList.remove('thread-open'));
    document.getElementById('chat-empty-browse').addEventListener('click', () => frame.classList.remove('thread-open'));
    document.querySelectorAll('[data-open-chat-widget]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openWidget();
        });
    });
    document.querySelector('[data-close-chat-widget]')?.addEventListener('click', closeWidget);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && widget && !widget.hidden) closeWidget();
    });

    const postMessage = async (body) => {
        const payload = await request(`/conversations/${current.id}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
        });
        renderMessage(payload.data);
        if (payload.assistant) renderMessage(payload.assistant);
        messages.scrollTop = messages.scrollHeight;
        await refreshConversations().catch(() => {});
    };

    emptyComposer.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = emptyInput.value.trim();
        if (!body) { showNotice('Vui lòng nhập nội dung tin nhắn.'); return; }
        emptySend.disabled = true;
        showNotice('');
        try {
            const payload = await request('/assistant', { method: 'POST' });
            await openConversation(payload.data);
            await postMessage(body);
            emptyInput.value = '';
            input.focus();
        } catch (error) {
            showNotice(error.message);
        } finally {
            emptySend.disabled = false;
        }
    });

    composer.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = input.value.trim();
        if (!body) { showNotice('Vui lòng nhập nội dung tin nhắn.'); return; }
        if (!current) return;
        send.disabled = true;
        showNotice('');
        try {
            await postMessage(body);
            input.value = '';
        } catch (error) {
            showNotice(error.message);
        } finally {
            send.disabled = false;
            input.focus();
        }
    });

    const sendOnEnter = (textarea, form) => textarea?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
        event.preventDefault();
        form.requestSubmit();
    });
    sendOnEnter(emptyInput, emptyComposer);
    sendOnEnter(input, composer);

    const queryParams = new URLSearchParams(window.location.search);
    const requestedConversationId = queryParams.get('conversation_id');
    if (requestedConversationId) {
        queryParams.delete('conversation_id');
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.delete('conversation_id');
        window.history.replaceState({}, '', nextUrl);
        refreshConversations().then(() => {
            const conversation = conversations.find((item) => String(item.id) === requestedConversationId);
            if (conversation) return openConversation(conversation);
            showNotice('Không tìm thấy cuộc trò chuyện được thông báo.');
        }).catch((error) => showNotice(error.message));
    } else if (queryParams.get('open_chat') === '1') {
        openWidget();
        const url = new URL(window.location.href);
        url.searchParams.delete('open_chat');
        window.history.replaceState({}, '', url);
    } else {
        refreshConversations().catch((error) => showNotice(error.message));
    }
})();
