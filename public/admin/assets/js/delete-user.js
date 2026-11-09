(() => {
  const button = document.querySelector('[data-delete-user]');
  if (!button) return;
  button.addEventListener('click', async () => {
    if (!window.confirm(`Xóa tài khoản ${button.dataset.deleteName}? Tài khoản sẽ không thể đăng nhập; lịch sử phiếu vẫn được giữ lại.`)) return;
    button.disabled = true;
    const feedback = document.querySelector('[data-delete-feedback]');
    feedback.hidden = true;
    try {
      const config = window.HELPDESK_CONFIG || {};
      const base = new URL(config.appUrl || '/', location.origin).pathname.replace(/\/+$/, '');
      const response = await fetch(`${base}/api/admin/users/${encodeURIComponent(button.dataset.deleteUser)}`, {
        method: 'DELETE', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể xóa tài khoản.');
      window.location.assign(button.dataset.deleteReturn);
    } catch (error) {
      feedback.textContent = error.message || 'Không thể kết nối máy chủ.';
      feedback.hidden = false; button.disabled = false;
    }
  });
})();
