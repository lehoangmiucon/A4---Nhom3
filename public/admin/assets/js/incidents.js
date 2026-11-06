(() => {
  const filterBar = document.querySelector('.filter-bar');
  const tableBody = document.querySelector('.table-panel tbody');
  if (!filterBar || !tableBody) return;

  const searchInput = filterBar.querySelector('.search-field');
  const [statusSelect, categorySelect, buildingSelect] = filterBar.querySelectorAll('.select-field');
  const rows = [...tableBody.querySelectorAll('[data-incident-row]')];
  const originalEmptyRow = tableBody.querySelector('tr:not([data-incident-row]) td[colspan]')?.closest('tr');
  const resultCount = document.querySelector('.pagination span');
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  let requestSequence = 0;
  let filterController = null;
  let noResultsRow = null;

  if (rows.length) {
    noResultsRow = document.createElement('tr');
    noResultsRow.hidden = true;
    noResultsRow.innerHTML = '<td colspan="8" style="padding:24px;text-align:center;color:#718096">Không tìm thấy phiếu phù hợp với bộ lọc.</td>';
    tableBody.append(noResultsRow);
    if (originalEmptyRow) originalEmptyRow.hidden = true;
  }

  function renderRows(matchingIds) {
    let visibleCount = 0;

    rows.forEach((row) => {
      const detailLink = row.querySelector('.ticket-code');
      const ticketId = detailLink ? new URL(detailLink.href).searchParams.get('ticket') : '';
      const matches = !matchingIds || matchingIds.has(String(ticketId));
      row.hidden = !matches;
      if (matches) visibleCount += 1;
    });

    if (noResultsRow) noResultsRow.hidden = visibleCount > 0;
    if (resultCount) resultCount.textContent = rows.length
      ? `Hiển thị ${visibleCount} / ${rows.length} phiếu sự cố`
      : 'Hiển thị 0 phiếu sự cố';
    delete filterBar.dataset.filterError;
    filterBar.removeAttribute('aria-description');
  }

  async function applyFilters() {
    const sequence = ++requestSequence;
    filterController?.abort();
    const params = new URLSearchParams();
    if (searchInput?.value.trim()) params.set('search', searchInput.value.trim());
    if (statusSelect?.value) params.set('status', statusSelect.value);
    if (categorySelect?.value) params.set('category', categorySelect.value);
    if (buildingSelect?.value) params.set('building', buildingSelect.value);

    if (!params.toString()) {
      renderRows(null);
      return;
    }
    filterController = new AbortController();
    try {
      const response = await fetch(`${appBase}/api/admin/tickets/filter?${params.toString()}`, {
        credentials: 'same-origin',
        signal: filterController.signal,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể lọc phiếu sự cố.');
      if (sequence !== requestSequence) return;
      renderRows(new Set((result.data?.ids || []).map(String)));
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (sequence === requestSequence) {
        filterBar.dataset.filterError = error.message || 'Không thể kết nối máy chủ.';
        filterBar.setAttribute('aria-description', filterBar.dataset.filterError);
      }
    }
  }

  let searchTimer;
  searchInput?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 250);
  });
  [statusSelect, categorySelect, buildingSelect].forEach((select) => select?.addEventListener('change', applyFilters));
  applyFilters();
})();
