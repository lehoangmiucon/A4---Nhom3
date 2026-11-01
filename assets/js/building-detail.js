(() => {
  const params = new URLSearchParams(window.location.search);
  const code = params.get('code') || 'A';
  const buildings = {
    A: {
      name: 'Khu giảng đường A', subtitle: 'Tòa nhà A · 4 tầng · Cơ sở chính', address: 'Cơ sở chính', floors: 4, rooms: 18, devices: 126, incidents: 3,
      description: 'Khu giảng đường phục vụ hoạt động giảng dạy và học tập của nhà trường.',
      entries: [
        ['A.101', 'Phòng học 101', 1, 8, 0], ['A.203', 'Phòng học 203', 2, 12, 1], ['A.204', 'Phòng học 204', 2, 10, 0], ['A.302', 'Phòng học 302', 3, 9, 1], ['A.401', 'Phòng học 401', 4, 7, 0]
      ]
    },
    B: {
      name: 'Khu giảng đường B', subtitle: 'Tòa nhà B · 5 tầng · Cơ sở chính', address: 'Cơ sở chính', floors: 5, rooms: 22, devices: 148, incidents: 2,
      description: 'Khu giảng đường với các phòng học và không gian phục vụ hoạt động đào tạo.',
      entries: [
        ['B.105', 'Phòng học 105', 1, 8, 1], ['B.201', 'Phòng học 201', 2, 9, 0], ['B.204', 'Phòng học 204', 2, 10, 1], ['B.301', 'Phòng học 301', 3, 7, 0], ['B.502', 'Phòng học 502', 5, 6, 0]
      ]
    },
    PRACTICE: {
      name: 'Khu thực hành', subtitle: 'Nhà thực hành · 3 tầng · Cơ sở chính', address: 'Cơ sở chính', floors: 3, rooms: 8, devices: 34, incidents: 1,
      description: 'Không gian thực hành với máy tính, thiết bị chuyên môn và dụng cụ học tập.',
      entries: [
        ['TH.01', 'Phòng thực hành 1', 1, 18, 0], ['TH.02', 'Phòng thực hành 2', 1, 24, 1], ['TH.03', 'Phòng thực hành điện', 2, 12, 0], ['TH.201', 'Phòng thực hành mạng', 2, 16, 0]
      ]
    },
    LIBRARY: {
      name: 'Thư viện', subtitle: 'Khối thư viện · 3 tầng · Cơ sở chính', address: 'Cơ sở chính', floors: 3, rooms: 12, devices: 18, incidents: 0,
      description: 'Khu thư viện gồm phòng đọc, không gian tự học và khu vực mượn trả tài liệu.',
      entries: [
        ['TV.01', 'Phòng đọc tầng 1', 1, 6, 0], ['TV.02', 'Không gian tự học', 2, 5, 0], ['TV.03', 'Phòng máy thư viện', 2, 8, 0]
      ]
    },
    ADMIN: {
      name: 'Khu hành chính', subtitle: 'Tòa nhà H · 2 tầng · Cơ sở chính', address: 'Cơ sở chính', floors: 2, rooms: 10, devices: 26, incidents: 0,
      description: 'Khu làm việc của các phòng ban và đơn vị hành chính trong trường.',
      entries: [
        ['H.101', 'Phòng tiếp nhận', 1, 5, 0], ['H.201', 'Phòng quản trị cơ sở vật chất', 2, 8, 0], ['H.202', 'Phòng họp', 2, 7, 0]
      ]
    }
  };
  const building = buildings[code] || {
    name: params.get('name') || `Khu nhà ${code}`, subtitle: `Mã khu nhà ${code}`, address: 'Cơ sở chính', floors: 1, rooms: 0, devices: 0, incidents: 0,
    description: 'Thông tin khu nhà mới được tạo trong danh mục cơ sở vật chất.', entries: []
  };
  const readStore = (key, fallback) => {
    try { return JSON.parse(localStorage.getItem(key)) || fallback; }
    catch { return fallback; }
  };
  const buildingEdits = readStore('helpdeskBuildingEdits', {});
  if (buildingEdits[code]) Object.assign(building, buildingEdits[code]);
  const removedRoomMap = readStore('helpdeskDeletedRooms', {});
  const editedRoomMap = readStore('helpdeskRoomEdits', {});
  const roomPrefix = { PRACTICE: 'TH', LIBRARY: 'TV', ADMIN: 'H' }[code] || code;
  const knownRoomCodes = new Set(building.entries.map((room) => room[0]));
  const floorSequences = new Map();
  let listedIncidents = building.entries.reduce((total, room) => total + room[4], 0);
  while (building.entries.length < building.rooms) {
    const floor = (building.entries.length % Math.max(building.floors, 1)) + 1;
    const sequence = (floorSequences.get(floor) || 0) + 1;
    floorSequences.set(floor, sequence);
    const generatedCode = `${roomPrefix}.${floor}${String(sequence).padStart(2, '0')}`;
    if (knownRoomCodes.has(generatedCode)) continue;
    knownRoomCodes.add(generatedCode);
    const incidentCount = listedIncidents < building.incidents && building.entries.length % 5 === 0 ? 1 : 0;
    listedIncidents += incidentCount;
    const generatedName = code === 'LIBRARY' ? `Không gian tầng ${floor} · ${sequence}` : `Phòng ${floor}${String(sequence).padStart(2, '0')}`;
    building.entries.push([generatedCode, generatedName, floor, 4 + ((floor + sequence) % 9), incidentCount]);
  }
  const removedRooms = new Set(removedRoomMap[code] || []);
  const savedRoomEdits = editedRoomMap[code] || {};
  building.entries.filter(([roomCode]) => removedRooms.has(roomCode)).forEach((room) => {
    building.devices -= room[3];
    building.incidents -= room[4];
  });
  building.entries = building.entries.filter(([roomCode]) => !removedRooms.has(roomCode));
  building.rooms = Math.max(0, building.rooms - removedRooms.size);
  building.entries.forEach((room) => {
    const edit = savedRoomEdits[room[0]];
    if (!edit) return;
    building.devices += Number(edit.devices) - room[3];
    building.incidents += Number(edit.incidents) - room[4];
    room[1] = edit.name;
    room[2] = Number(edit.floor);
    room[3] = Number(edit.devices);
    room[4] = Number(edit.incidents);
  });

  const setText = (selector, value) => {
    const element = document.querySelector(selector);
    if (element) element.textContent = value;
  };
  document.title = `${building.name} · Helpdesk trường học`;
  setText('#building-name', building.name);
  setText('#building-subtitle', building.subtitle);
  setText('#detail-building-name', building.name);
  setText('#detail-building-code', code);
  setText('#detail-building-address', building.address);
  setText('#detail-building-description', building.description);
  setText('#detail-room-count', building.rooms);
  setText('#detail-device-count', building.devices);
  setText('#detail-incident-count', building.incidents);
  setText('#detail-floor-count', building.floors);
  setText('#room-total-badge', `${building.rooms} phòng`);

  const addRoomLink = document.querySelector('#add-building-room');
  if (addRoomLink) {
    const createParams = new URLSearchParams({ create: 'room', building: code, building_name: building.name });
    addRoomLink.href = `locations.html?${createParams.toString()}`;
  }

  const roomList = document.querySelector('#building-room-list');
  const floorFilter = document.querySelector('#room-floor-filter');
  const floors = [...new Set(building.entries.map((room) => room[2]))].sort((a, b) => a - b);
  floors.forEach((floor) => {
    const option = document.createElement('option');
    option.value = floor;
    option.textContent = `Tầng ${floor}`;
    floorFilter.append(option);
  });

  building.entries.forEach(([roomCode, roomName, floor, deviceCount, issueCount]) => {
    const row = document.createElement('tr');
    row.dataset.floor = floor;
    row.dataset.search = `${roomCode} ${roomName}`.toLocaleLowerCase('vi');
    const codeCell = document.createElement('td');
    const codeLabel = document.createElement('strong');
    codeLabel.textContent = roomCode;
    codeCell.append(codeLabel);
    row.append(codeCell);
    [roomName, floor, deviceCount, issueCount].forEach((value) => {
      const cell = document.createElement('td');
      cell.textContent = value;
      row.append(cell);
    });
    const statusCell = document.createElement('td');
    const statusBadge = document.createElement('span');
    statusBadge.className = `status-badge ${issueCount > 0 ? 'status-wait' : 'status-done'}`;
    statusBadge.textContent = issueCount > 0 ? 'Có sự cố' : 'Đang sử dụng';
    statusCell.append(statusBadge);
    row.append(statusCell);
    const qrCell = document.createElement('td');
    const qrParams = new URLSearchParams({ building_code: code, building_name: building.name, room_code: roomCode, room_name: roomName });
    const qrLink = document.createElement('a');
    qrLink.className = 'text-link';
    qrLink.href = `../portal/index.html?${qrParams.toString()}`;
    qrLink.target = '_blank';
    qrLink.rel = 'noopener';
    qrLink.innerHTML = '<i class="fa-solid fa-qrcode" aria-hidden="true"></i> Mã QR ↗';
    qrCell.append(qrLink);
    row.append(qrCell);
    const actionCell = document.createElement('td');
    const actions = document.createElement('div');
    actions.className = 'room-actions';
    const editButton = document.createElement('button');
    editButton.className = 'room-action-button';
    editButton.type = 'button';
    editButton.title = 'Sửa phòng';
    editButton.setAttribute('aria-label', `Sửa ${roomCode}`);
    editButton.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i>';
    const deleteButton = document.createElement('button');
    deleteButton.className = 'room-action-button delete';
    deleteButton.type = 'button';
    deleteButton.title = 'Xóa phòng';
    deleteButton.setAttribute('aria-label', `Xóa ${roomCode}`);
    deleteButton.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
    editButton.addEventListener('click', () => openRoomEditor(row));
    deleteButton.addEventListener('click', () => deleteRoom(row));
    actions.append(editButton, deleteButton);
    actionCell.append(actions);
    row.append(actionCell);
    roomList.append(row);
  });

  const search = document.querySelector('#room-search');
  const emptyState = document.querySelector('#room-empty-state');
  function filterRooms() {
    const query = search.value.trim().toLocaleLowerCase('vi');
    let visibleCount = 0;
    roomList.querySelectorAll('tr').forEach((row) => {
      const matchesText = row.dataset.search.includes(query);
      const matchesFloor = !floorFilter.value || row.dataset.floor === floorFilter.value;
      row.hidden = !matchesText || !matchesFloor;
      if (!row.hidden) visibleCount += 1;
    });
    emptyState.hidden = visibleCount > 0;
  }
  search.addEventListener('input', filterRooms);
  floorFilter.addEventListener('change', filterRooms);

  const roomEditDialog = document.querySelector('#room-edit-dialog');
  const roomEditForm = document.querySelector('#room-edit-form');
  const buildingEditDialog = document.querySelector('#building-edit-dialog');
  const buildingEditForm = document.querySelector('#building-edit-form');
  let editingRow = null;

  function openRoomEditor(row) {
    editingRow = row;
    document.querySelector('#edit-room-code').value = row.cells[0].textContent.trim();
    document.querySelector('#edit-room-name').value = row.cells[1].textContent.trim();
    document.querySelector('#edit-room-floor').value = row.cells[2].textContent.trim();
    document.querySelector('#edit-room-devices').value = row.cells[3].textContent.trim();
    document.querySelector('#edit-room-incidents').value = row.cells[4].textContent.trim();
    updateRoomQrPreview(row);
    roomEditDialog.showModal();
  }

  function updateRoomQrPreview(row = editingRow) {
    if (!row) return;
    const roomCode = row.cells[0].textContent.trim();
    const roomName = document.querySelector('#edit-room-name').value.trim() || row.cells[1].textContent.trim();
    const qrUrl = new URL(row.cells[6].querySelector('a').href);
    qrUrl.searchParams.set('building_code', code);
    qrUrl.searchParams.set('building_name', building.name);
    qrUrl.searchParams.set('room_code', roomCode);
    qrUrl.searchParams.set('room_name', roomName);
    const imageParams = new URLSearchParams({ size: '190x190', margin: '8', data: qrUrl.href });
    document.querySelector('#edit-room-qr-image').src = `https://api.qrserver.com/v1/create-qr-code/?${imageParams.toString()}`;
    document.querySelector('#edit-room-qr-title').textContent = `Mã QR · ${roomCode}`;
    const link = document.querySelector('#edit-room-qr-link');
    link.href = qrUrl.href;
  }

  function deleteRoom(row) {
    const roomCode = row.cells[0].textContent.trim();
    if (!window.confirm(`Bạn có chắc muốn xóa phòng ${roomCode} khỏi ${building.name}?`)) return;
    const deleted = readStore('helpdeskDeletedRooms', {});
    deleted[code] = [...new Set([...(deleted[code] || []), roomCode])];
    localStorage.setItem('helpdeskDeletedRooms', JSON.stringify(deleted));
    const devices = Number(row.cells[3].textContent) || 0;
    const incidents = Number(row.cells[4].textContent) || 0;
    building.rooms = Math.max(0, building.rooms - 1);
    building.devices = Math.max(0, building.devices - devices);
    building.incidents = Math.max(0, building.incidents - incidents);
    setText('#detail-room-count', building.rooms);
    setText('#detail-device-count', building.devices);
    setText('#detail-incident-count', building.incidents);
    setText('#room-total-badge', `${building.rooms} phòng`);
    row.remove();
    filterRooms();
  }

  roomEditForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!editingRow) return;
    const roomCode = editingRow.cells[0].textContent.trim();
    const oldDevices = Number(editingRow.cells[3].textContent) || 0;
    const oldIncidents = Number(editingRow.cells[4].textContent) || 0;
    const edit = {
      name: document.querySelector('#edit-room-name').value.trim(),
      floor: Number(document.querySelector('#edit-room-floor').value),
      devices: Number(document.querySelector('#edit-room-devices').value),
      incidents: Number(document.querySelector('#edit-room-incidents').value)
    };
    editingRow.cells[1].textContent = edit.name;
    editingRow.cells[2].textContent = edit.floor;
    editingRow.cells[3].textContent = edit.devices;
    editingRow.cells[4].textContent = edit.incidents;
    editingRow.dataset.floor = String(edit.floor);
    editingRow.dataset.search = `${roomCode} ${edit.name}`.toLocaleLowerCase('vi');
    const statusBadge = editingRow.cells[5].querySelector('.status-badge');
    statusBadge.className = `status-badge ${edit.incidents > 0 ? 'status-wait' : 'status-done'}`;
    statusBadge.textContent = edit.incidents > 0 ? 'Có sự cố' : 'Đang sử dụng';
    const qrLink = editingRow.cells[6].querySelector('a');
    const qrUrl = new URL(qrLink.href);
    qrUrl.searchParams.set('room_name', edit.name);
    qrLink.href = qrUrl.href;
    const edits = readStore('helpdeskRoomEdits', {});
    edits[code] ||= {};
    edits[code][roomCode] = edit;
    localStorage.setItem('helpdeskRoomEdits', JSON.stringify(edits));
    building.devices += edit.devices - oldDevices;
    building.incidents += edit.incidents - oldIncidents;
    setText('#detail-device-count', building.devices);
    setText('#detail-incident-count', building.incidents);
    roomEditDialog.close();
    filterRooms();
  });
  document.querySelector('#edit-room-name').addEventListener('input', () => updateRoomQrPreview());
  const qrFeedback = document.querySelector('#room-qr-feedback');
  const qrImage = document.querySelector('#edit-room-qr-image');
  document.querySelector('[data-download-room-qr]').addEventListener('click', async () => {
    const roomCode = document.querySelector('#edit-room-code').value.trim();
    const safeCode = roomCode.replace(/[^a-z0-9_-]/gi, '-');
    try {
      const response = await fetch(qrImage.src, { mode: 'cors' });
      if (!response.ok) throw new Error('QR image could not be downloaded');
      const imageBlob = await response.blob();
      const objectUrl = URL.createObjectURL(imageBlob);
      const downloadLink = document.createElement('a');
      downloadLink.href = objectUrl;
      downloadLink.download = `Ma-QR-${safeCode}.png`;
      document.body.append(downloadLink);
      downloadLink.click();
      downloadLink.remove();
      setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
      qrFeedback.textContent = 'Đã tải mã QR xuống thiết bị.';
    } catch {
      window.open(qrImage.src, '_blank', 'noopener');
      qrFeedback.textContent = 'Ảnh QR đã mở ở tab mới. Bạn có thể lưu ảnh từ tab đó.';
    }
  });
  document.querySelector('[data-print-room-qr]').addEventListener('click', () => {
    const roomCode = document.querySelector('#edit-room-code').value.trim();
    const roomName = document.querySelector('#edit-room-name').value.trim();
    const printWindow = window.open('', '_blank', 'width=560,height=720');
    if (!printWindow) {
      qrFeedback.textContent = 'Trình duyệt đang chặn cửa sổ in. Hãy cho phép mở cửa sổ bật lên rồi thử lại.';
      return;
    }
    const printDocument = printWindow.document;
    printDocument.title = `Mã QR ${roomCode}`;
    const style = printDocument.createElement('style');
    style.textContent = 'body{margin:0;padding:36px;font:16px Arial,sans-serif;color:#18334d;text-align:center}main{max-width:440px;margin:0 auto;padding:28px;border:1px solid #dbe5ee;border-radius:18px}small{display:block;color:#61758a;letter-spacing:1px;font-weight:700}h1{font-size:24px;margin:12px 0 5px}p{color:#61758a;margin:0 0 22px}img{display:block;width:270px;height:270px;object-fit:contain;margin:0 auto 20px}.code{font-size:13px;color:#71839a}@page{margin:14mm}@media print{main{border:0;padding:0}}';
    printDocument.head.append(style);
    const printCard = printDocument.createElement('main');
    const kicker = printDocument.createElement('small');
    kicker.textContent = 'HELPDESK · TRƯỜNG HỌC';
    const title = printDocument.createElement('h1');
    title.textContent = roomName || roomCode;
    const description = printDocument.createElement('p');
    description.textContent = `${building.name} · Quét mã để báo sự cố tại phòng này`;
    const image = printDocument.createElement('img');
    image.src = qrImage.src;
    image.alt = `Mã QR phòng ${roomCode}`;
    const codeLabel = printDocument.createElement('span');
    codeLabel.className = 'code';
    codeLabel.textContent = `Mã phòng: ${roomCode}`;
    printCard.append(kicker, title, description, image, codeLabel);
    printDocument.body.append(printCard);
    image.addEventListener('load', () => {
      printWindow.focus();
      printWindow.print();
    });
    image.addEventListener('error', () => {
      qrFeedback.textContent = 'Chưa tải được ảnh QR. Hãy kiểm tra kết nối internet rồi thử lại.';
      printWindow.close();
    });
  });

  document.querySelector('[data-edit-building]').addEventListener('click', () => {
    document.querySelector('#edit-building-name').value = building.name;
    document.querySelector('#edit-building-address').value = building.address;
    document.querySelector('#edit-building-description').value = building.description;
    buildingEditDialog.showModal();
  });
  buildingEditForm.addEventListener('submit', (event) => {
    event.preventDefault();
    building.name = document.querySelector('#edit-building-name').value.trim();
    building.address = document.querySelector('#edit-building-address').value.trim() || 'Chưa cập nhật địa chỉ';
    building.description = document.querySelector('#edit-building-description').value.trim();
    const edits = readStore('helpdeskBuildingEdits', {});
    edits[code] = { name: building.name, address: building.address, description: building.description };
    localStorage.setItem('helpdeskBuildingEdits', JSON.stringify(edits));
    setText('#building-name', building.name);
    setText('#building-subtitle', `${building.subtitle.split(' · ').slice(0, 2).join(' · ')} · ${building.address}`);
    setText('#detail-building-name', building.name);
    setText('#detail-building-address', building.address);
    setText('#detail-building-description', building.description);
    document.title = `${building.name} · Helpdesk trường học`;
    const createParams = new URLSearchParams({ create: 'room', building: code, building_name: building.name });
    document.querySelector('#add-building-room').href = `locations.html?${createParams.toString()}`;
    roomList.querySelectorAll('tr').forEach((row) => {
      const qrUrl = new URL(row.cells[6].querySelector('a').href);
      qrUrl.searchParams.set('building_name', building.name);
      row.cells[6].querySelector('a').href = qrUrl.href;
    });
    buildingEditDialog.close();
  });

  document.querySelectorAll('[data-close-room-dialog]').forEach((button) => button.addEventListener('click', () => roomEditDialog.close()));
  document.querySelectorAll('[data-close-building-dialog]').forEach((button) => button.addEventListener('click', () => buildingEditDialog.close()));
  [roomEditDialog, buildingEditDialog].forEach((dialog) => dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  }));
  document.querySelector('[data-delete-building]').addEventListener('click', () => {
    if (!window.confirm(`Xóa ${building.name} và danh sách phòng thuộc khu nhà này?`)) return;
    const deleted = readStore('helpdeskDeletedBuildings', []);
    localStorage.setItem('helpdeskDeletedBuildings', JSON.stringify([...new Set([...deleted, code])]));
    window.location.href = 'locations.html';
  });
})();
