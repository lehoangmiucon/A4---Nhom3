(() => {
  let loading = false;

  async function loadDashboard(url, updateHistory = true) {
    if (loading) return;
    const currentPage = document.querySelector('.page-content');
    if (!currentPage) return;

    loading = true;
    currentPage.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
          Accept: 'text/html',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      if (!response.ok) throw new Error('Không thể tải số liệu.');

      const html = await response.text();
      const nextDocument = new DOMParser().parseFromString(html, 'text/html');
      const nextPage = nextDocument.querySelector('.page-content');
      if (!nextPage) throw new Error('Phản hồi dashboard không hợp lệ.');

      currentPage.innerHTML = nextPage.innerHTML;
      currentPage.className = nextPage.className;
      currentPage.removeAttribute('aria-busy');
      document.title = nextDocument.title;
      if (updateHistory) window.history.pushState({}, '', url);
      window.HelpdeskPeriodSelectInit?.();
    } catch (error) {
      currentPage.removeAttribute('aria-busy');
      window.location.assign(url);
    } finally {
      loading = false;
    }
  }

  document.addEventListener('change', (event) => {
    const select = event.target.closest('#dashboard-period');
    if (!select) return;

    const url = new URL(window.location.href);
    url.searchParams.set('period', select.value);
    loadDashboard(url);
  });

  window.addEventListener('popstate', () => loadDashboard(window.location.href, false));
})();
