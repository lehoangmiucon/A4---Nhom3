(() => {
  const config = window.HELPDESK_CONFIG || {};
  const base = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const rows = [...document.querySelectorAll('[data-user-row]')];
  const search = document.querySelector('#users-search');
  const statusFilter = document.querySelector('#users-status-filter');
  const feedback = document.querySelector('#users-feedback');

  function filterRows() {
    const phrase = (search?.value || '').trim().toLocaleLowerCase();
    rows.forEach((row) => {
      const matchesSearch = (row.dataset.userSearch || '').includes(phrase);
      const matchesStatus = !statusFilter?.value || row.dataset.userStatus === statusFilter.value;
      row.hidden = !(matchesSearch && matchesStatus);
    });
    const empty = document.querySelector("[data-users-empty]");
    if (empty) empty.hidden = !rows.length || rows.some(row => !row.hidden);
  }

  function showFeedback(message, error = false) {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.dataset.state = error ? 'error' : 'success';
    feedback.hidden = false;
  }

  [search, statusFilter].forEach((control) => control?.addEventListener('input', filterRows));
  statusFilter?.addEventListener('change', filterRows);

  document.querySelectorAll('[data-account-toggle]').forEach((button) => {
    button.addEventListener('click', async () => {
      const isLocked = button.dataset.locked === '1';
      const nextState = !isLocked;
      const action = nextState ? 'khóa' : 'mở khóa';
      const name = button.dataset.userName || 'tài khoản này';
      if (!window.confirm(`Bạn có chắc muốn ${action} tài khoản ${name}?`)) return;

      button.disabled = true;
      try {
        const response = await fetch(`${base}/api/admin/users/${encodeURIComponent(button.dataset.userId)}/status`, {
          method: 'PATCH',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ is_deleted: nextState ? 1 : 0 }),
        });
        const payload = await response.json();
        if (!response.ok) {
          const validationMessage = Object.values(payload.errors || {}).flat().join(' ');
          throw new Error(validationMessage || payload.message || 'Không thể cập nhật trạng thái tài khoản.');
        }
        showFeedback(payload.message || 'Đã cập nhật tài khoản.');
        if (window.HelpdeskFeedback) window.HelpdeskFeedback.reload(payload.message);
        else window.location.reload();
      } catch (error) {
        showFeedback(error.message || 'Không thể kết nối máy chủ.', true);
        button.disabled = false;
      }
    });
  });
})();
