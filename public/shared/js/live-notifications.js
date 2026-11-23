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
        const box = document.createElement('aside');
        box.setAttribute('role', 'status');
        Object.assign(box.style, {
            position: 'fixed', top: '76px', right: '18px', zIndex: '9999',
            width: 'min(340px, calc(100vw - 36px))', padding: '15px 18px',
            borderRadius: '14px', border: '1px solid #cfe4f5', background: '#fff',
            color: '#183754', boxShadow: '0 14px 36px #123b7030',
            fontFamily: 'Be Vietnam Pro, Arial, sans-serif', fontSize: '13px',
            lineHeight: '1.5', cursor: 'pointer',
        });
        const heading = document.createElement('strong');
        heading.style.display = 'block';
        heading.textContent = item.title || 'Thông báo Helpdesk';
        const content = document.createElement('span');
        content.textContent = item.body || '';
        box.append(heading, content);
        box.addEventListener('click', () => {
            const root = endpoint.replace(/\/api\/notifications.*$/, '');
            window.location.href = item.payload?.type === 'chat'
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
