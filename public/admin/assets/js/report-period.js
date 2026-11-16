(() => {
  let loading = false;
  let restoringHistory = false;
  const form = () => document.querySelector('[data-report-filter]');

  function setText(selector, value) {
    const element = document.querySelector(selector);
    if (element) element.textContent = value;
  }

  function renderCategories(items, maxCount) {
    const container = document.querySelector('[data-report-categories]');
    if (!container) return;
    container.replaceChildren();
    if (!items.length) {
      const empty = document.createElement('p');
      empty.textContent = 'Chưa có dữ liệu phân loại sự cố.';
      container.append(empty);
      return;
    }

    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'report-bar-row';
      const name = document.createElement('span');
      name.textContent = item.name;
      const bar = document.createElement('div');
      bar.className = 'report-bar';
      const fill = document.createElement('i');
      fill.style.width = `${maxCount ? Math.round(item.tickets_count / maxCount * 100) : 0}%`;
      fill.style.background = /^#[\da-f]{6}$/i.test(item.color || '') ? item.color : '#64748B';
      bar.append(fill);
      const count = document.createElement('strong');
      count.textContent = item.tickets_count;
      row.append(name, bar, count);
      container.append(row);
    });
  }

  function renderStatuses(counts, total) {
    const container = document.querySelector('[data-report-statuses]');
    if (!container) return;
    container.dataset.total = total;
    container.querySelectorAll('[data-status]').forEach((row) => {
      const count = Number(counts[row.dataset.status] || 0);
      const fill = row.querySelector('.report-bar i');
      const value = row.querySelector('strong');
      if (fill) fill.style.width = `${total ? Math.round(count / total * 100) : 0}%`;
      if (value) value.textContent = count;
    });
  }

  async function loadSummary(period, updateHistory = true) {
    const filter = form();
    const content = document.querySelector('.page-content');
    if (!filter || !content || loading) return;
    loading = true;
    content.setAttribute('aria-busy', 'true');
    try {
      const endpoint = new URL(filter.dataset.summaryUrl, window.location.origin);
      endpoint.searchParams.set('period', period);
      const response = await fetch(endpoint, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!response.ok) throw new Error('Không thể tải thống kê.');
      const data = await response.json();
      setText('[data-report-period-title]', data.period_title);
      setText('[data-report-total]', data.total);
      setText('[data-report-closed]', data.closed);
      setText('[data-report-average]', data.average_hours);
      renderCategories(data.categories || [], data.max_category_count || 0);
      renderStatuses(data.counts || {}, data.total || 0);
      if (updateHistory) {
        const url = new URL(window.location.href);
        url.searchParams.set('period', data.period);
        window.history.pushState({}, '', `${url.pathname}${url.search}${url.hash}`);
      }
    } catch (error) {
      const feedback = document.querySelector('[data-report-feedback]');
      if (feedback) feedback.textContent = error.message;
    } finally {
      content.removeAttribute('aria-busy');
      loading = false;
    }
  }

  document.addEventListener('change', (event) => {
    if (event.target.matches('#report-period')) loadSummary(event.target.value, !restoringHistory);
  });
  window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    const select = document.querySelector('#report-period');
    if (!select) return;
    select.value = url.searchParams.get('period') || 'month';
    restoringHistory = true;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    restoringHistory = false;
  });
})();
