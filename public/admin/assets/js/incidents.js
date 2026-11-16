(() => {
  const filterBar = document.querySelector('.filter-bar');
  const tableBody = document.querySelector('.table-panel tbody');
  if (!filterBar || !tableBody) return;

  const searchInput = filterBar.querySelector('.search-field');
  const [statusSelect, categorySelect, buildingSelect, reporterRoleSelect, periodSelect] = filterBar.querySelectorAll('.select-field');
  const rows = [...tableBody.querySelectorAll('[data-incident-row]')];
  const originalEmptyRow = tableBody.querySelector('tr:not([data-incident-row]) td[colspan]')?.closest('tr');
  const resultCount = document.querySelector('.pagination span');
  const pagination = document.querySelector('.pagination');
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  let requestSequence = 0;
  let noResultsRow = null;
  let currentPage = 1;
  let matchingRows = rows;
  const pageSize = 15;
  const rowsById = new Map(rows.map((row) => {
    const link = row.querySelector('.ticket-code');
    const id = row.dataset.ticketId || (link ? new URL(link.href).searchParams.get('ticket') : '');
    return [String(id), row];
  }));

  function renderPage() {
    const pageCount = Math.max(1, Math.ceil(matchingRows.length / pageSize));
    currentPage = Math.min(currentPage, pageCount);
    const start = (currentPage - 1) * pageSize;
    const visibleRows = new Set(matchingRows.slice(start, start + pageSize));
    rows.forEach((row) => { row.hidden = !visibleRows.has(row); });
    if (resultCount) resultCount.textContent = matchingRows.length
      ? `Hiển thị ${start + 1}–${Math.min(start + pageSize, matchingRows.length)} / ${matchingRows.length} phiếu sự cố`
      : 'Hiển thị 0 phiếu sự cố';
    let nav = pagination?.querySelector('.page-numbers');
    if (pagination && !nav) {
      nav = document.createElement('nav');
      nav.className = 'page-numbers';
      nav.setAttribute('aria-label', 'Phân trang phiếu sự cố');
      pagination.append(nav);
    }
    if (!nav) return;
    nav.replaceChildren();
    if (pageCount <= 1) return;
    const addButton = (label, page, disabled = false, active = false) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.disabled = disabled;
      button.className = active ? 'current' : '';
      if (active) button.setAttribute('aria-current', 'page');
      button.addEventListener('click', () => { currentPage = page; renderPage(); });
      nav.append(button);
    };
    addButton('‹', currentPage - 1, currentPage === 1);
    for (let page = Math.max(1, currentPage - 2); page <= Math.min(pageCount, currentPage + 2); page++) {
      addButton(String(page), page, false, page === currentPage);
    }
    addButton('›', currentPage + 1, currentPage === pageCount);
  }

  const initialFilters = new URLSearchParams(window.location.search);
  const filterFields = [
    [statusSelect, 'status'], [categorySelect, 'category'], [buildingSelect, 'building'],
    [reporterRoleSelect, 'reporter_role'], [periodSelect, 'period'],
  ];
  if (searchInput && initialFilters.has('search')) searchInput.value = initialFilters.get('search');
  filterFields.forEach(([select, key]) => {
    const value = initialFilters.get(key);
    if (select && value && [...select.options].some((option) => option.value === value)) {
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  if (rows.length) {
    noResultsRow = document.createElement('tr');
    noResultsRow.hidden = true;
    noResultsRow.innerHTML = '<td colspan="8" style="padding:24px;text-align:center;color:#718096">Không tìm thấy phiếu phù hợp với bộ lọc.</td>';
    tableBody.append(noResultsRow);
    if (originalEmptyRow) originalEmptyRow.hidden = true;
  }

  async function applyFilters() {
    const sequence = ++requestSequence;
    const params = new URLSearchParams();
    if (searchInput?.value.trim()) params.set('search', searchInput.value.trim());
    if (statusSelect?.value) params.set('status', statusSelect.value);
    if (categorySelect?.value) params.set('category', categorySelect.value);
    if (buildingSelect?.value) params.set('building', buildingSelect.value);
    if (reporterRoleSelect?.value) params.set('reporter_role', reporterRoleSelect.value);
    if (periodSelect?.value && periodSelect.value !== 'all') params.set('period', periodSelect.value);

    try {
      const response = await fetch(`${appBase}/api/admin/tickets/filter?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể lọc phiếu sự cố.');
      if (sequence !== requestSequence) return;
      matchingRows = (result.data?.ids || []).map((id) => rowsById.get(String(id))).filter(Boolean);
      matchingRows.forEach((row) => tableBody.append(row));
      currentPage = 1;
      if (noResultsRow) noResultsRow.hidden = matchingRows.length > 0;
      renderPage();
      delete filterBar.dataset.filterError;
      filterBar.removeAttribute('aria-description');
    } catch (error) {
      if (sequence === requestSequence) {
        filterBar.dataset.filterError = error.message || 'Không thể kết nối máy chủ.';
        filterBar.setAttribute('aria-description', filterBar.dataset.filterError);
      }
    }
  }

  let searchTimer;
  searchInput?.addEventListener('input', () => {
    currentPage = 1;
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 250);
  });
  [statusSelect, categorySelect, buildingSelect, reporterRoleSelect]
    .forEach((select) => select?.addEventListener('change', () => { currentPage = 1; applyFilters(); }));
  periodSelect?.addEventListener('change', () => {
    const destination = new URL(window.location.href);
    destination.searchParams.delete('page');
    filterFields.forEach(([select, key]) => {
      if (select?.value) destination.searchParams.set(key, select.value);
      else destination.searchParams.delete(key);
    });
    if (searchInput?.value.trim()) destination.searchParams.set('search', searchInput.value.trim());
    else destination.searchParams.delete('search');
    window.location.assign(destination.href);
  });
  applyFilters();
})();
