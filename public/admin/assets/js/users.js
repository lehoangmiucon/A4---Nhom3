(() => {
  const config = window.HELPDESK_CONFIG || {};
  const base = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const rows = [...document.querySelectorAll('[data-user-row]')];
  const search = document.querySelector('#users-search');
  const roleFilter = document.querySelector('#users-role-filter');
  const statusFilter = document.querySelector('#users-status-filter');
  const feedback = document.querySelector('#users-feedback');

  function filterRows() {
    const phrase = (search?.value || '').trim().toLocaleLowerCase();
    rows.forEach((row) => {
      const matchesSearch = (row.dataset.userSearch || '').includes(phrase);
      const matchesRole = !roleFilter?.value || row.dataset.userRole === roleFilter.value;
      const matchesStatus = !statusFilter?.value || row.dataset.userStatus === statusFilter.value;
      row.hidden = !(matchesSearch && matchesRole && matchesStatus);
    });
  }

  function showFeedback(message, error = false) {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.dataset.state = error ? 'error' : 'success';
    feedback.hidden = false;
  }

  [search, roleFilter, statusFilter].forEach((control) => {
    control?.addEventListener('input', filterRows);
    control?.addEventListener('change', filterRows);
  });

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

  const roleForm = document.querySelector('[data-role-form]');
  roleForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = roleForm.querySelector('[type="submit"]');
    const currentRole = roleForm.dataset.userRole;
    const nextRole = currentRole === 'teacher' ? 'student' : 'teacher';
    const userName = roleForm.dataset.userName || 'tài khoản này';
    const confirmation = nextRole === 'teacher'
      ? `Cấp vai trò giáo viên cho ${userName}?\n\nTài khoản vẫn dùng cổng sinh viên. Phiếu mới sẽ được tự động xếp ưu tiên cao.`
      : `Gỡ quyền giáo viên của ${userName} và chuyển tài khoản về sinh viên?\n\nCác phiếu đã gửi sẽ giữ nguyên mức ưu tiên hiện tại.`;
    if (!window.confirm(confirmation)) return;

    button.disabled = true;
    try {
      const response = await fetch(`${base}/api/admin/users/${encodeURIComponent(roleForm.dataset.userId)}/role`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ role: nextRole }),
      });
      const payload = await response.json();
      if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {}).flat().join(' ');
        throw new Error(validationMessage || payload.message || 'Không thể cập nhật vai trò tài khoản.');
      }
      const message = nextRole === 'teacher'
        ? 'Đã chuyển tài khoản thành giáo viên. Các phiếu ưu tiên cao đã được cập nhật.'
        : 'Đã cập nhật vai trò thành sinh viên.';
      if (window.HelpdeskFeedback) window.HelpdeskFeedback.reload(message);
      else window.location.reload();
    } catch (error) {
      showFeedback(error.message || 'Không thể kết nối máy chủ.', true);
      button.disabled = false;
    }
  });
})();
