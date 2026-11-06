(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const menu = document.querySelector('[data-notification-menu]');
    const trigger = menu?.querySelector('[data-notification-trigger]');
    const popover = menu?.querySelector('[data-notification-popover]');

    const closePopover = () => {
        if (!trigger || !popover) return;
        popover.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger?.addEventListener('click', () => {
        const opening = popover.hidden;
        popover.hidden = !opening;
        trigger.setAttribute('aria-expanded', String(opening));
    });

    document.addEventListener('click', (event) => {
        if (menu && !menu.contains(event.target)) closePopover();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePopover();
    });

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[data-notification-read-url]');
        if (!link || Number(link.dataset.notificationUnread || 0) < 1) return;

        event.preventDefault();
        if (link.closest('[data-notification-popover]')) closePopover();
        if (link.dataset.reading === 'true') return;
        link.dataset.reading = 'true';

        try {
            await fetch(link.dataset.notificationReadUrl, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
        } catch (_) {
            // Keep navigation available if the read receipt request fails.
        } finally {
            window.location.assign(link.href);
        }
    });
})();
