(() => {
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const search = document.querySelector('#room-search');
  const floorFilter = document.querySelector('#room-floor-filter');
  const rows = [...document.querySelectorAll('#building-room-list tr')];
  const deleteButton = document.querySelector('[data-delete-building]');
  const feedback = document.querySelector('#building-detail-feedback');

  function filterRooms() {
    const query = (search?.value || '').trim().toLocaleLowerCase('vi');
    const floor = floorFilter?.value || '';
    rows.forEach((row) => {
      const matchesText = row.textContent.toLocaleLowerCase('vi').includes(query);
      const matchesFloor = !floor || row.cells[2]?.textContent.trim() === floor;
      row.hidden = !(matchesText && matchesFloor);
    });
  }

  search?.addEventListener('input', filterRooms);
  floorFilter?.addEventListener('change', filterRooms);

  deleteButton?.addEventListener('click', async () => {
    const buildingName = deleteButton.dataset.buildingName || 'khu nhà này';
    const confirmed = window.confirm(
      `Xóa "${buildingName}" và toàn bộ phòng thuộc khu nhà? Phiếu sự cố cũ vẫn được giữ nhưng sẽ mất liên kết với phòng. Thao tác này không thể hoàn tác.`,
    );
    if (!confirmed) return;

    deleteButton.disabled = true;
    if (feedback) feedback.hidden = true;
    try {
      const response = await fetch(`${appBase}/api/admin/buildings/${encodeURIComponent(deleteButton.dataset.buildingId)}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.message || 'Không thể xóa khu nhà.');
      window.location.assign('locations');
    } catch (error) {
      if (feedback) {
        feedback.textContent = error.message || 'Không thể kết nối máy chủ.';
        feedback.dataset.state = 'error';
        feedback.hidden = false;
      }
      deleteButton.disabled = false;
    }
  });
})();
