(() => {
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const appPath = (path) => `${appBase}/${path.replace(/^\/+/, '')}`;
  const buildingSearch = document.querySelector('#building-search');
  const buildingCards = [...document.querySelectorAll('[data-building-card]')];
  const buildingResults = document.querySelector('#building-results');
  const searchEmpty = document.querySelector('[data-building-search-empty]');

  function normalizeSearch(value) {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLocaleLowerCase('vi');
  }

  function filterBuildings() {
    const query = normalizeSearch(buildingSearch?.value.trim() || '');
    let visible = 0;
    buildingCards.forEach((card) => {
      const matches = normalizeSearch(card.dataset.search || '').includes(query);
      card.hidden = !matches;
      if (matches) visible += 1;
    });
    if (buildingResults) buildingResults.textContent = `${visible} khu nhà`;
    if (searchEmpty) searchEmpty.hidden = !query || visible > 0;
    document.querySelectorAll('[data-clear-building-search]').forEach((button) => {
      if (button.tagName === 'BUTTON' && button.closest('.location-search-control')) button.hidden = !query;
    });
  }

  buildingSearch?.addEventListener('input', filterBuildings);
  document.querySelectorAll('[data-clear-building-search]').forEach((button) => button.addEventListener('click', () => {
    if (buildingSearch) {
      buildingSearch.value = '';
      filterBuildings();
      buildingSearch.focus();
    }
  }));

  const dialog = document.querySelector('#location-dialog');
  const form = document.querySelector('#location-form');
  if (!dialog || !form) return;

  const feedback = document.querySelector('#location-feedback');
  const roomBuilding = document.querySelector('#new-room-building');
  const roomCode = document.querySelector('#new-room-code');
  let mode = 'building';

  function setMode(nextMode) {
    mode = nextMode;
    const isBuilding = mode === 'building';
    document.querySelectorAll('[data-location-mode]').forEach((button) => {
      const active = button.dataset.locationMode === mode;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', String(active));
    });
    document.querySelectorAll('[data-building-field]').forEach((field) => {
      field.hidden = !isBuilding;
      field.querySelectorAll('input').forEach((input) => { input.disabled = !isBuilding; });
    });
    document.querySelectorAll('[data-room-field]').forEach((field) => {
      field.hidden = isBuilding;
      field.querySelectorAll('input,select').forEach((input) => { input.disabled = isBuilding; });
    });
    document.querySelector('#location-dialog-title').textContent = isBuilding ? 'Tạo khu nhà' : 'Tạo phòng / không gian';
    document.querySelector('#location-submit').textContent = isBuilding ? 'Tạo khu nhà' : 'Tạo phòng';
  }

  document.querySelectorAll('[data-open-location]').forEach((button) => button.addEventListener('click', () => {
    form.reset();
    feedback.hidden = true;
    setMode(button.dataset.openLocation || 'building');
    dialog.showModal();
  }));
  document.querySelectorAll('[data-location-mode]').forEach((button) => button.addEventListener('click', () => setMode(button.dataset.locationMode)));
  [roomBuilding, roomCode, document.querySelector('#new-room-name')].forEach((input) => input?.addEventListener('input', () => {
    const code = roomCode?.value.trim() || '';
    document.querySelector('#room-qr-link').textContent = code ? `${window.location.origin}${appPath(`/r/${encodeURIComponent(code)}`)}` : 'Chọn khu nhà và nhập mã phòng';
  }));
  document.querySelectorAll('[data-close-location]').forEach((button) => button.addEventListener('click', () => dialog.close()));

  const params = new URLSearchParams(window.location.search);
  if (params.get('create') === 'room') {
    form.reset();
    feedback.hidden = true;
    setMode('room');
    const building = params.get('building');
    const option = [...(roomBuilding?.options || [])].find((item) =>
      item.value === building || item.dataset.code === building,
    );
    if (option) roomBuilding.value = option.value;
    dialog.showModal();
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const buildingOption = roomBuilding?.selectedOptions[0];
    const endpoint = mode === 'building'
      ? '/api/admin/buildings'
      : `/api/admin/buildings/${encodeURIComponent(roomBuilding.value)}/rooms`;
    const payload = mode === 'building'
      ? {
          name: document.querySelector('#new-building-name').value.trim(),
          code: document.querySelector('#new-building-code').value.trim().toUpperCase(),
          description: document.querySelector('#new-building-address').value.trim(),
        }
      : {
          code: document.querySelector('#new-room-code').value.trim(),
          name: document.querySelector('#new-room-name').value.trim(),
        };

    const button = document.querySelector('#location-submit');
    button.disabled = true;
    feedback.hidden = true;
    try {
      const response = await fetch(appPath(endpoint), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
      window.location.reload();
    } catch (error) {
      feedback.textContent = error.message || 'Không thể lưu dữ liệu.';
      feedback.hidden = false;
    } finally {
      button.disabled = false;
    }
  });
})();
