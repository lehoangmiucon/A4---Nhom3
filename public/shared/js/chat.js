(() => {
    const config = window.HELPDESK_CHAT_CONFIG || {};
    const apiBase = (config.apiBase || '/api/chat').replace(/\/+$/, '');
    const currentUserId = Number(config.currentUserId || 0);
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
    let conversations = [];
    let current = null;
    let lastId = 0;
    let pollTimer = null;
    let searchTimer = null;

    const request = async (path, options = {}) => {
        const response = await fetch(`${apiBase}${path}`, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...(options.headers || {}),
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Không thể thực hiện thao tác.';
            throw new Error(message);
        }
        return payload;
    };

    const showNotice = (message) => {
        notice.textContent = message;
        notice.hidden = !message;
    };

    const conversationName = (conversation) => conversation.title || 'Cuộc trò chuyện';

    const renderConversations = () => {
        count.textContent = conversations.length;
        list.replaceChildren();
        if (!conversations.length) {
            const hint = document.createElement('p');
            hint.className = 'chat-empty-list';
            hint.textContent = 'Chưa có tin nhắn. Tìm người ở phía trên để bắt đầu.';
            list.append(hint);
            return;
        }
        conversations.forEach((conversation) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `chat-conversation${current?.id === conversation.id ? ' active' : ''}`;
            const face = document.createElement('span');
            face.className = `chat-avatar${conversation.type === 'assistant' ? ' assistant' : ''}`;
            face.textContent = conversation.type === 'assistant' ? '✦' : conversationName(conversation).slice(0, 1).toUpperCase();
            const copy = document.createElement('span');
            copy.className = 'chat-conversation-copy';
            const name = document.createElement('strong');
            name.textContent = conversationName(conversation);
            const preview = document.createElement('small');
            preview.textContent = conversation.last_message?.body || conversation.ticket?.title || 'Bắt đầu trò chuyện';
            copy.append(name, preview);
            button.append(face, copy);
            button.addEventListener('click', () => openConversation(conversation));
            list.append(button);
        });
    };

    const renderMessage = (message) => {
        if (messages.querySelector(`[data-message-id="${message.id}"]`)) return;
        const item = document.createElement('article');
        item.className = `chat-message${message.sender_type === 'assistant' ? ' assistant' : ''}${message.sender_type === 'system' ? ' system' : ''}${message.user_id === currentUserId ? ' mine' : ''}`;
        item.dataset.messageId = message.id;
        item.append(document.createTextNode(message.body || ''));
        if (message.sender_type !== 'system' && message.created_at) {
            const time = document.createElement('time');
            const date = new Date(message.created_at);
            time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            item.append(time);
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
        messages.replaceChildren();
        empty.hidden = true;
        active.hidden = false;
        frame.classList.add('thread-open');
        title.textContent = conversationName(conversation);
        avatar.textContent = conversation.type === 'assistant' ? '✦' : conversationName(conversation).slice(0, 1).toUpperCase();
        avatar.classList.toggle('assistant', conversation.type === 'assistant');
        subtitle.textContent = conversation.type === 'assistant'
            ? 'Hỏi về quy trình và cách sử dụng Helpdesk'
            : (conversation.participants || []).map((person) => person.role).filter(Boolean).join(' · ');
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
            pollTimer = window.setInterval(() => refreshMessages().catch(() => {}), 4000);
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
            renderConversations();
        }
    };

    const openWidget = async () => {
        if (!widget) return;
        widget.hidden = false;
        if (current) {
            await refreshMessages(true).catch((error) => showNotice(error.message));
            if (pollTimer) window.clearInterval(pollTimer);
            pollTimer = window.setInterval(() => refreshMessages().catch(() => {}), 4000);
        } else {
            frame.setAttribute('aria-busy', 'true');
            try {
                await refreshConversations();
                if (conversations.length) {
                    await openConversation(conversations[0]);
                } else {
                    const payload = await request('/assistant', { method: 'POST' });
                    await refreshConversations();
                    await openConversation(payload.data);
                }
            } catch (error) {
                showNotice(error.message);
            } finally {
                frame.removeAttribute('aria-busy');
            }
        }
    };

    const closeWidget = () => {
        if (widget) widget.hidden = true;
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = null;
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
            face.textContent = (person.name || '?').slice(0, 1).toUpperCase();
            const copy = document.createElement('span');
            copy.className = 'chat-contact-copy';
            const name = document.createElement('strong');
            name.textContent = person.name || 'Người dùng';
            const detail = document.createElement('small');
            detail.textContent = [person.email, person.phone].filter(Boolean).join(' · ');
            copy.append(name, detail);
            const role = document.createElement('span');
            role.className = 'chat-role';
            role.textContent = person.role || '';
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

    search.addEventListener('input', () => {
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
    document.getElementById('chat-refresh').addEventListener('click', () => refreshConversations().catch((error) => showNotice(error.message)));
    document.getElementById('chat-mobile-back').addEventListener('click', () => frame.classList.remove('thread-open'));
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

    composer.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = input.value.trim();
        if (!body || !current) return;
        send.disabled = true;
        showNotice('');
        try {
            const payload = await request(`/conversations/${current.id}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
            });
            renderMessage(payload.data);
            if (payload.assistant) renderMessage(payload.assistant);
            input.value = '';
            messages.scrollTop = messages.scrollHeight;
            await refreshConversations();
        } catch (error) {
            showNotice(error.message);
        } finally {
            send.disabled = false;
            input.focus();
        }
    });

    if (new URLSearchParams(window.location.search).get('open_chat') === '1') {
        openWidget();
        const url = new URL(window.location.href);
        url.searchParams.delete('open_chat');
        window.history.replaceState({}, '', url);
    } else {
        refreshConversations().catch((error) => showNotice(error.message));
    }
})();
