(() => {
  const dialog = document.querySelector('#location-dialog');
  const form = document.querySelector('#location-form');
  if (!dialog || !form) return;

  const grid = document.querySelector('.info-grid');
  const roomTable = document.querySelector('#room-table-body');
  const roomBuilding = document.querySelector('#new-room-building');
  const roomCode = document.querySelector('#new-room-code');
  const roomName = document.querySelector('#new-room-name');
  const qrPreview = document.querySelector('#room-qr-link');
  let currentMode = 'building';

  const fieldGroups = {
    building: [...form.querySelectorAll('[data-building-field]')],
    room: [...form.querySelectorAll('[data-room-field]')]
  };
  const buildingCodesByName = new Map([
    ['Khu giảng đường A', 'A'], ['Khu giảng đường B', 'B'], ['Khu thực hành', 'PRACTICE'], ['Thư viện', 'LIBRARY'], ['Khu hành chính', 'ADMIN']
  ]);
  const buildingTotals = { A: 18, B: 22, PRACTICE: 8, LIBRARY: 12, ADMIN: 10 };
  function readStored(key, fallback) {
    try { return JSON.parse(localStorage.getItem(key)) || fallback; }
    catch { return fallback; }
  }
  const deletedBuildings = new Set(readStored('helpdeskDeletedBuildings', []));
  const deletedRooms = readStored('helpdeskDeletedRooms', {});
  const roomEdits = readStored('helpdeskRoomEdits', {});
  const buildingEdits = readStored('helpdeskBuildingEdits', {});
  grid.querySelectorAll('.info-card').forEach((card) => {
    const heading = card.querySelector('h3')?.textContent.trim();
    const code = buildingCodesByName.get(heading);
    if (!code) return;
    if (deletedBuildings.has(code)) {
      card.remove();
      return;
    }
    const edit = buildingEdits[code];
    if (edit) {
      card.querySelector('h3').textContent = edit.name;
      const address = card.querySelector('.info-meta span');
      if (address) address.textContent = `Địa chỉ: ${edit.address}`;
    }
    const roomTag = card.querySelector('.device-tag');
    const roomCountForBuilding = Math.max(0, (buildingTotals[code] || 0) - (deletedRooms[code] || []).length);
    if (roomTag && deletedRooms[code]?.length) roomTag.textContent = `${roomCountForBuilding} phòng`;
  });
  roomTable.querySelectorAll('tr[data-room-row]').forEach((row) => {
    const code = buildingCodesByName.get(row.cells[2].textContent.trim());
    if (!code) return;
    const roomCode = row.cells[0].textContent.trim();
    if (deletedBuildings.has(code) || (deletedRooms[code] || []).includes(roomCode)) {
      row.remove();
      return;
    }
    const edit = roomEdits[code]?.[roomCode];
    if (!edit) return;
    row.cells[1].textContent = edit.name;
    row.cells[3].textContent = edit.floor;
    row.cells[4].textContent = edit.devices;
    const statusBadge = row.cells[5].querySelector('.status-badge');
    statusBadge.className = `status-badge ${edit.incidents > 0 ? 'status-wait' : 'status-done'}`;
    statusBadge.textContent = edit.incidents > 0 ? 'Có sự cố' : 'Đang sử dụng';
    const qr = row.cells[6].querySelector('a');
    const qrUrl = new URL(qr.href);
    qrUrl.searchParams.set('room_name', edit.name);
    qrUrl.searchParams.set('building_name', buildingEdits[code]?.name || row.cells[2].textContent.trim());
    qr.href = qrUrl.href;
  });
  const removedBuildingRooms = [...deletedBuildings].reduce((total, code) => total + (buildingTotals[code] || 0), 0);
  const removedRoomCount = Object.entries(deletedRooms).reduce((total, [code, roomCodes]) => total + (deletedBuildings.has(code) ? 0 : roomCodes.length), 0);
  const buildingCount = document.querySelector('#building-count');
  const roomCount = document.querySelector('#room-count');
  if (buildingCount) buildingCount.textContent = String(Math.max(0, 5 - deletedBuildings.size)).padStart(2, '0');
  if (roomCount) roomCount.textContent = String(Math.max(0, 48 - removedBuildingRooms - removedRoomCount));

  function setMode(mode) {
    currentMode = mode;
    const isBuilding = mode === 'building';
    document.querySelectorAll('[data-location-mode]').forEach((button) => {
      const active = button.dataset.locationMode === mode;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', String(active));
    });
    fieldGroups.building.forEach((field) => {
      field.hidden = !isBuilding;
      field.querySelectorAll('input').forEach((input) => { input.disabled = !isBuilding; });
    });
    fieldGroups.room.forEach((field) => {
      field.hidden = isBuilding;
      field.querySelectorAll('input,select').forEach((input) => { input.disabled = isBuilding; });
    });
    document.querySelector('#location-dialog-title').textContent = isBuilding ? 'Tạo khu nhà' : 'Tạo phòng / không gian';
    document.querySelector('#location-submit').textContent = isBuilding ? 'Tạo khu nhà' : 'Tạo phòng';
    document.querySelector('.location-dialog-icon i').className = isBuilding
      ? 'fa-solid fa-building-circle-check'
      : 'fa-solid fa-door-open';
    updateQrPreview();
  }

  function openDialog(mode) {
    form.reset();
    setMode(mode || 'building');
    dialog.showModal();
  }

  document.querySelectorAll('[data-open-location]').forEach((button) => {
    button.addEventListener('click', () => openDialog(button.dataset.openLocation));
  });
  document.querySelectorAll('[data-location-mode]').forEach((button) => {
    button.addEventListener('click', () => setMode(button.dataset.locationMode));
  });
  document.querySelectorAll('[data-close-location]').forEach((button) => {
    button.addEventListener('click', () => dialog.close());
  });
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  function updateQrPreview() {
    if (!qrPreview || !roomBuilding || !roomCode || !roomName) return;
    const code = roomBuilding.value;
    const selected = roomBuilding.selectedOptions[0];
    if (!code || !roomCode.value.trim()) {
      qrPreview.textContent = 'Chọn khu nhà và nhập mã phòng';
      return;
    }
    const params = new URLSearchParams({
      building_code: code,
      building_name: selected?.dataset.buildingLabel || selected?.textContent.trim() || code,
      room_code: roomCode.value.trim(),
      room_name: roomName.value.trim() || roomCode.value.trim()
    });
    qrPreview.textContent = `../portal/index.html?${params.toString()}`;
  }

  [roomBuilding, roomCode, roomName].forEach((input) => input?.addEventListener('input', updateQrPreview));
  roomBuilding?.addEventListener('change', updateQrPreview);

  function addTextCell(row, value, className = '') {
    const cell = document.createElement('td');
    if (className) cell.className = className;
    cell.textContent = value;
    row.append(cell);
    return cell;
  }

  function addBuilding(name, code, address) {
    if ([...roomBuilding.options].some((option) => option.value.toLowerCase() === code.toLowerCase())) {
      const input = document.querySelector('#new-building-code');
      input.setCustomValidity('Mã khu nhà này đã tồn tại.');
      input.reportValidity();
      input.addEventListener('input', () => input.setCustomValidity(''), { once: true });
      return false;
    }
    const option = document.createElement('option');
    option.value = code;
    option.dataset.buildingLabel = name;
    option.textContent = name;
    roomBuilding.append(option);

    const card = document.createElement('article');
    card.className = 'info-card';
    card.dataset.buildingCode = code;
    const heading = document.createElement('div');
    heading.className = 'building-card-heading';
    const icon = document.createElement('span');
    icon.className = 'building-icon';
    icon.innerHTML = '<i class="fa-solid fa-building" aria-hidden="true"></i>';
    const text = document.createElement('div');
    const title = document.createElement('h3');
    title.textContent = name;
    const subtitle = document.createElement('p');
    subtitle.textContent = `Mã khu nhà ${code}`;
    text.append(title, subtitle);
    heading.append(icon, text);
    const tags = document.createElement('div');
    tags.className = 'device-list';
    const roomTag = document.createElement('span');
    roomTag.className = 'device-tag building-room-count';
    roomTag.textContent = '0 phòng';
    const addressTag = document.createElement('span');
    addressTag.className = 'device-tag';
    addressTag.textContent = address || 'Chưa cập nhật địa chỉ';
    tags.append(roomTag, addressTag);
    const meta = document.createElement('div');
    meta.className = 'info-meta';
    const qrText = document.createElement('span');
    qrText.textContent = 'Tạo phòng và mã QR theo vị trí';
    const detailParams = new URLSearchParams({ code, name });
    const detailLink = document.createElement('a');
    detailLink.className = 'text-link';
    detailLink.href = `building-detail.html?${detailParams.toString()}`;
    detailLink.textContent = 'Chi tiết →';
    const addRoom = document.createElement('button');
    addRoom.className = 'text-link location-add-link';
    addRoom.type = 'button';
    addRoom.dataset.openLocation = 'room';
    addRoom.textContent = 'Tạo phòng →';
    addRoom.addEventListener('click', () => {
      openDialog('room');
      roomBuilding.value = code;
      updateQrPreview();
    });
    meta.append(qrText, detailLink, addRoom);
    card.append(heading, tags, meta);
    const addCard = grid.querySelector('[data-open-location="room"]')?.closest('.info-card');
    grid.insertBefore(card, addCard || null);
    const count = document.querySelector('#building-count');
    if (count) count.textContent = String(Number(count.textContent) + 1).padStart(2, '0');
    return true;
  }

  function updateRoomCount(buildingName) {
    const card = [...grid.querySelectorAll('.info-card')].find((item) => item.querySelector('h3')?.textContent.trim() === buildingName);
    const tag = card?.querySelector('.building-room-count') || card?.querySelector('.device-tag');
    if (!tag) return;
    const match = tag.textContent.match(/\d+/);
    tag.textContent = `${Number(match?.[0] || 0) + 1} phòng`;
  }

  function addRoom() {
    const code = roomCode.value.trim();
    if ([...roomTable.querySelectorAll('tr[data-room-row] td:first-child')].some((cell) => cell.textContent.trim().toLowerCase() === code.toLowerCase())) {
      roomCode.setCustomValidity('Mã phòng này đã tồn tại.');
      roomCode.reportValidity();
      roomCode.addEventListener('input', () => roomCode.setCustomValidity(''), { once: true });
      return false;
    }
    const buildingOption = roomBuilding.selectedOptions[0];
    const buildingCode = roomBuilding.value;
    const buildingName = buildingOption?.dataset.buildingLabel || buildingOption?.textContent.trim() || '';
    const name = roomName.value.trim();
    const floor = document.querySelector('#new-room-floor').value || '—';
    const params = new URLSearchParams({ building_code: buildingCode, building_name: buildingName, room_code: code, room_name: name });
    const row = document.createElement('tr');
    row.dataset.roomRow = '';
    const codeCell = document.createElement('td');
    const codeLabel = document.createElement('strong');
    codeLabel.textContent = code;
    codeCell.append(codeLabel);
    row.append(codeCell);
    addTextCell(row, name);
    addTextCell(row, buildingName);
    addTextCell(row, floor);
    addTextCell(row, '0');
    const status = document.createElement('td');
    const badge = document.createElement('span');
    badge.className = 'status-badge status-done';
    badge.textContent = 'Đang sử dụng';
    status.append(badge);
    row.append(status);
    const qrCell = document.createElement('td');
    const qrLink = document.createElement('a');
    qrLink.className = 'text-link';
    qrLink.href = `../portal/index.html?${params.toString()}`;
    qrLink.target = '_blank';
    qrLink.rel = 'noopener';
    qrLink.textContent = 'Mở liên kết QR ↗';
    qrCell.append(qrLink);
    row.append(qrCell);
    roomTable.append(row);
    updateRoomCount(buildingName);
    const count = document.querySelector('#room-count');
    if (count) count.textContent = String(Number(count.textContent) + 1);
    return true;
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const saved = currentMode === 'building'
      ? addBuilding(
        document.querySelector('#new-building-name').value.trim(),
        document.querySelector('#new-building-code').value.trim().toUpperCase(),
        document.querySelector('#new-building-address').value.trim()
      )
      : addRoom();
    if (saved) dialog.close();
  });

  const query = new URLSearchParams(window.location.search);
  if (query.get('create') === 'room') {
    openDialog('room');
    const buildingCode = query.get('building') || query.get('building_code');
    if (buildingCode && ![...roomBuilding.options].some((option) => option.value === buildingCode)) {
      const option = document.createElement('option');
      option.value = buildingCode;
      option.dataset.buildingLabel = query.get('building_name') || buildingCode;
      option.textContent = option.dataset.buildingLabel;
      roomBuilding.append(option);
    }
    if (buildingCode) roomBuilding.value = buildingCode;
    updateQrPreview();
  }
})();
