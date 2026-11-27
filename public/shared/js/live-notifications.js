(() => {
    const script = document.currentScript;
    const endpoint = script?.dataset.api;
    if (!endpoint) return;
    let latestId = null;
    let initialized = false;
    let busy = false;
    let soundContext = null;
    let soundEnabled = false;

    const unlockSound = () => {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        soundContext ||= new AudioContextClass();
        soundContext.resume().then(() => { soundEnabled = true; }).catch(() => {});
    };
    document.addEventListener('pointerdown', unlockSound, { once: true, passive: true });
    document.addEventListener('keydown', unlockSound, { once: true });

    const playNotificationSound = () => {
        if (!soundEnabled || !soundContext || soundContext.state !== 'running') return;
        const start = soundContext.currentTime;
        [[740, 0], [990, 0.13]].forEach(([frequency, offset]) => {
            const oscillator = soundContext.createOscillator();
            const volume = soundContext.createGain();
            const at = start + offset;
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(frequency, at);
            volume.gain.setValueAtTime(0.0001, at);
            volume.gain.exponentialRampToValueAtTime(0.12, at + 0.018);
            volume.gain.exponentialRampToValueAtTime(0.0001, at + 0.12);
            oscillator.connect(volume);
            volume.connect(soundContext.destination);
            oscillator.start(at);
            oscillator.stop(at + 0.13);
        });
    };

    const notify = (item) => {
        playNotificationSound();
        const payload = item.payload || {};
        const isChat = payload.type === 'chat';
        const isTicket = !isChat && (payload.type === 'ticket' || item.ticket_id || payload.ticket_id);
        const accent = isChat ? '#78d5ff' : isTicket ? '#ffd08a' : '#b8cee2';
        const background = isChat
            ? 'linear-gradient(135deg,#12486e,#102f4b)'
            : isTicket
                ? 'linear-gradient(135deg,#73501f,#3c2d1b)'
                : 'linear-gradient(135deg,#294765,#1a3047)';
        const box = document.createElement('aside');
        box.className = `helpdesk-notification-toast${isChat ? ' is-chat' : isTicket ? ' is-ticket' : ''}`;
        box.setAttribute('role', 'alert');
        Object.assign(box.style, {
            position: 'fixed', top: '76px', right: '18px', zIndex: '9999',
            width: 'min(370px, calc(100vw - 36px))', padding: '14px 16px',
            borderRadius: '16px', border: `1px solid ${accent}70`, background,
            color: '#fff', boxShadow: `0 16px 38px ${accent}24`,
            fontFamily: 'Be Vietnam Pro, Arial, sans-serif', fontSize: '13px',
            lineHeight: '1.5', cursor: 'pointer',
        });
        const icon = document.createElement('span');
        icon.textContent = isChat ? '✉' : isTicket ? '▣' : '•';
        Object.assign(icon.style, {
            width: '42px', height: '42px', flex: 'none', display: 'grid',
            placeItems: 'center', marginRight: '12px', borderRadius: '13px',
            color: accent, background: `${accent}20`, fontSize: '20px', fontWeight: '800',
        });
        const copy = document.createElement('span');
        Object.assign(copy.style, { minWidth: '0', flex: '1', display: 'block' });
        const badge = document.createElement('small');
        badge.textContent = isChat ? 'TIN NHẮN MỚI' : isTicket ? 'PHIẾU SỰ CỐ' : 'THÔNG BÁO HELPDESK';
        Object.assign(badge.style, { display: 'block', marginBottom: '2px', color: accent, fontSize: '9px', fontWeight: '800', letterSpacing: '.8px' });
        const heading = document.createElement('strong');
        heading.style.display = 'block';
        heading.textContent = item.title || (isChat ? 'Bạn có tin nhắn mới' : isTicket ? 'Có cập nhật phiếu sự cố' : 'Thông báo Helpdesk');
        const content = document.createElement('span');
        content.textContent = item.body || '';
        Object.assign(content.style, { display: 'block', marginTop: '2px', color: '#d8e5f0', fontSize: '11px' });
        copy.append(badge, heading, content);
        const arrow = document.createElement('span');
        arrow.textContent = '›';
        Object.assign(arrow.style, { marginLeft: '8px', color: accent, fontSize: '25px', lineHeight: '1' });
        Object.assign(box.style, { display: 'flex', alignItems: 'center' });
        box.append(icon, copy, arrow);
        box.addEventListener('click', () => {
            const root = endpoint.replace(/\/api\/notifications.*$/, '');
            if (payload.type === 'chat' && payload.conversation_id) {
                window.location.href = `${root}/messages?conversation_id=${encodeURIComponent(payload.conversation_id)}`;
                return;
            }
            window.location.href = payload.type === 'chat'
                ? `${root}/messages`
                : script.dataset.notificationsPage;
        });
        document.body.append(box);
        window.setTimeout(() => box.remove(), 7000);
    };

    const refresh = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(`${endpoint}?per_page=5`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            const rows = payload.data || [];
            if (initialized && rows.length) {
                const found = rows.findIndex((item) => item.id === latestId);
                const newer = rows.slice(0, found < 0 ? rows.length : found);
                newer.reverse().forEach(notify);
            }
            if (rows.length) latestId = rows[0].id;
            initialized = true;
            document.querySelectorAll('.notification-count,.role-nav-notification-badge,.customer-notification-badge').forEach((badge) => {
                badge.textContent = Math.min(payload.unread_count || 0, 99);
                badge.hidden = !payload.unread_count;
            });
        } catch (_) {
            // Keep the page usable if the connection is temporarily unavailable.
        } finally {
            busy = false;
        }
    };
    refresh();
    window.setInterval(refresh, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
