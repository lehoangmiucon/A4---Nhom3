(() => {
    let requestNumber = 0;

    async function loadCalendar(url, updateHistory = true) {
        const current = document.getElementById('staff-calendar-panel');
        if (!current) return;
        const requestId = ++requestNumber;
        current.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('Không thể tải lịch.');

            const html = await response.text();
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const next = nextDocument.getElementById('staff-calendar-panel');
            if (!next) throw new Error('Phản hồi lịch không hợp lệ.');
            if (requestId !== requestNumber) return;

            current.replaceWith(next);
            if (updateHistory) window.history.pushState({}, '', url);
        } catch (error) {
            if (requestId === requestNumber) window.location.assign(url);
        }
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest(
            '#staff-calendar-panel .staff-calendar-day, #staff-calendar-panel .staff-calendar-toolbar a, #staff-calendar-panel .staff-calendar-period a, #staff-calendar-panel .staff-today-link',
        );
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        loadCalendar(link.href);
    });

    window.addEventListener('popstate', () => loadCalendar(window.location.href, false));
})();
