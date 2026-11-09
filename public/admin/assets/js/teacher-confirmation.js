(() => {
  document.querySelectorAll('[data-confirm-teacher]').forEach(button => button.addEventListener('click', async () => {
    const value = button.dataset.isTeacher !== '1';
    if (!confirm(`${value ? 'Xác nhận' : 'Bỏ xác nhận'} giảng viên cho ${button.dataset.teacherName}? Tài khoản sẽ cần đăng nhập lại.`)) return;
    button.disabled = true;
    try {
      const base = new URL(window.HELPDESK_CONFIG?.appUrl || '/', location.origin).pathname.replace(/\/+$/, '');
      const response = await fetch(`${base}/api/admin/users/${encodeURIComponent(button.dataset.confirmTeacher)}/teacher`, {
        method: 'PATCH', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify({ is_teacher: value })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Không thể cập nhật loại người dùng.');
      location.reload();
    } catch (error) {
      let feedback = button.parentElement.querySelector('[data-teacher-feedback]');
      if (!feedback) { feedback = document.createElement('p'); feedback.dataset.teacherFeedback = ''; feedback.setAttribute('role', 'alert'); button.after(feedback); }
      feedback.textContent = error.message || 'Không thể kết nối máy chủ.';
      button.disabled = false;
    }
  }));
})();
