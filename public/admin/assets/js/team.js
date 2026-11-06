(() => {
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const api = (path) => `${appBase}/api${path}`;
  const modal = document.querySelector('#team-account-modal');
  const form = document.querySelector('#team-account-form');
  const feedback = form?.querySelector('[data-team-form-feedback]');
  const roleSelect = form?.querySelector('#team-account-role');
  const categoryPicker = form?.querySelector('[data-staff-category-picker]');
  const modalDescription = document.querySelector('#team-modal-description');
  const passwordInput = form?.elements.password;
  const emailInput = form?.elements.email;
  const phoneInput = form?.elements.phone;
  const emailLabel = form?.querySelector('#team-email-label');
  let editingStaffId = null;

  if (!modal || !form) return;

  function showFeedback(target, message, state = 'error') {
    if (!target) return;
    target.textContent = message;
    target.dataset.state = state;
    target.hidden = false;
  }

  function updateRoleFields() {
    const isStaff = roleSelect.value === 'staff';
    categoryPicker.hidden = !isStaff;
    categoryPicker.disabled = !isStaff;
    categoryPicker.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      input.disabled = !isStaff;
      if (!isStaff) input.checked = false;
    });
    if (!editingStaffId && modalDescription) {
      modalDescription.textContent = isStaff
        ? 'Nhân viên cần được gán ít nhất một danh mục sự cố phù hợp với chuyên môn.'
        : 'Tài khoản quản lý không cần gán danh mục công việc.';
    }
  }

  function openCreate() {
    editingStaffId = null;
    form.reset();
    feedback.hidden = true;
    roleSelect.disabled = false;
    form.querySelectorAll('[data-create-only]').forEach((field) => { field.hidden = false; });
    emailInput.readOnly = false;
    emailLabel.textContent = 'Email';
    passwordInput.required = true;
    passwordInput.value = '';
    form.querySelector('#team-password-label').textContent = 'Mật khẩu khởi tạo';
    document.querySelector('#team-modal-title').textContent = 'Tạo tài khoản quản lý hoặc nhân viên';
    document.querySelector('#team-modal-description').textContent = 'Nhân viên cần được gán ít nhất một danh mục sự cố phù hợp.';
    form.querySelector('[type="submit"]').textContent = 'Tạo tài khoản';
    updateRoleFields();
    modal.showModal();
  }

  function openEdit(button) {
    editingStaffId = button.dataset.userId;
    form.reset();
    feedback.hidden = true;
    roleSelect.value = 'staff';
    roleSelect.disabled = true;
    form.querySelectorAll('[data-create-only]').forEach((field) => { field.hidden = true; });
    form.elements.name.value = button.dataset.userName || '';
    emailInput.value = button.dataset.userEmail || '';
    phoneInput.value = button.dataset.userPhone || '';
    emailInput.readOnly = true;
    emailLabel.textContent = 'Email đăng nhập · chỉ xem';
    passwordInput.required = false;
    passwordInput.value = '';
    form.querySelector('#team-password-label').textContent = 'Mật khẩu mới (không bắt buộc)';
    document.querySelector('#team-modal-title').textContent = 'Chỉnh sửa nhân viên';
    document.querySelector('#team-modal-description').textContent = 'Để trống mật khẩu nếu không muốn đổi mật khẩu.';
    form.querySelector('[type="submit"]').textContent = 'Lưu thay đổi';

    let categoryIds = [];
    try { categoryIds = JSON.parse(button.dataset.userCategories || '[]').map(String); } catch (_) { /* Keep no category selected. */ }
    categoryPicker.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      input.checked = categoryIds.includes(input.value);
    });
    updateRoleFields();
    modal.showModal();
  }

  document.querySelector('[data-open-team-create]')?.addEventListener('click', openCreate);
  document.querySelectorAll('[data-close-team-modal]').forEach((button) => button.addEventListener('click', () => modal.close()));
  document.querySelectorAll('[data-edit-staff]').forEach((button) => button.addEventListener('click', () => openEdit(button)));
  roleSelect.addEventListener('change', updateRoleFields);

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const role = roleSelect.value;
    const categoryIds = role === 'staff'
      ? [...categoryPicker.querySelectorAll('input:checked')].map((input) => Number(input.value))
      : [];
    if (role === 'staff' && categoryIds.length === 0) {
      showFeedback(feedback, 'Hãy chọn ít nhất một danh mục sự cố cho nhân viên.');
      return;
    }

    const payload = {
      name: form.elements.name.value.trim(),
      phone: phoneInput.value.trim() || null,
      password: passwordInput.value || null,
    };
    const isEditing = Boolean(editingStaffId);
    if (isEditing || role === 'staff') payload.category_ids = categoryIds;
    if (!isEditing) {
      payload.email = emailInput.value.trim();
      payload.role = role;
    }

    const submitButton = form.querySelector('[type="submit"]');
    submitButton.disabled = true;
    feedback.hidden = true;
    try {
      const endpoint = isEditing ? `/admin/staff/${encodeURIComponent(editingStaffId)}` : '/admin/team';
      const response = await fetch(api(endpoint), {
        method: isEditing ? 'PUT' : 'POST',
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
      showFeedback(feedback, result.message, 'success');
      if (window.HelpdeskFeedback) window.HelpdeskFeedback.reload(result.message);
      else window.location.reload();
    } catch (error) {
      showFeedback(feedback, error.message || 'Không thể lưu tài khoản.');
    } finally {
      submitButton.disabled = false;
    }
  });

  document.querySelectorAll('[data-toggle-account]').forEach((button) => button.addEventListener('click', async () => {
    const currentlyLocked = button.dataset.locked === '1';
    const nextLocked = !currentlyLocked;
    const action = nextLocked ? 'khóa' : 'mở khóa';
    if (!window.confirm(`Bạn có chắc muốn ${action} tài khoản của ${button.dataset.userName}?`)) return;

    button.disabled = true;
    try {
      const response = await fetch(api(`/admin/users/${encodeURIComponent(button.dataset.userId)}/status`), {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ is_deleted: nextLocked }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể cập nhật trạng thái tài khoản.');
      window.location.reload();
    } catch (error) {
      showFeedback(document.querySelector('#team-feedback'), error.message || 'Không thể cập nhật tài khoản.');
      button.disabled = false;
    }
  }));

  updateRoleFields();

  const teamSearch = document.querySelector('#team-search');
  const roleFilter = document.querySelector('#team-role-filter');
  const statusFilter = document.querySelector('#team-status-filter');
  const categoryFilter = document.querySelector('#team-category-filter');
  const filterReset = document.querySelector('#team-filter-reset');
  let filterRequest = 0;

  async function filterTeamRows() {
    const requestId = ++filterRequest;
    const params = new URLSearchParams();
    if (teamSearch?.value.trim()) params.set('search', teamSearch.value.trim());
    if (roleFilter?.value) params.set('role', roleFilter.value);
    if (statusFilter?.value) params.set('status', statusFilter.value);
    if (categoryFilter?.value) params.set('category', categoryFilter.value);
    try {
      const response = await fetch(api(`/admin/team/filter?${params.toString()}`), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể lọc danh sách đội ngũ.');
      if (requestId !== filterRequest) return;
      const matchingIds = new Set((result.data?.ids || []).map(String));
      const visibleByRole = { admin: 0, staff: 0 };

      document.querySelectorAll('[data-team-row]').forEach((row) => {
        const detailUrl = row.querySelector('.team-detail-button')?.href;
        const userId = row.dataset.userId || (detailUrl ? new URL(detailUrl).searchParams.get('user') : '');
        const matches = matchingIds.has(String(userId));
        row.hidden = !matches;
        if (matches) visibleByRole[row.dataset.teamRole] += 1;
      });

      document.querySelectorAll('.team-management,.team-technicians').forEach((section) => {
        const tbody = section.querySelector('tbody');
        const roleName = section.classList.contains('team-management') ? 'admin' : 'staff';
        let emptyRow = tbody.querySelector('[data-team-filter-empty]');
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.dataset.teamFilterEmpty = '';
          emptyRow.innerHTML = '<td class="team-filter-empty" colspan="6">Không tìm thấy tài khoản phù hợp.</td>';
          tbody.append(emptyRow);
        }
        emptyRow.hidden = visibleByRole[roleName] > 0 || (!params.toString() && (!roleFilter?.value || roleFilter.value === roleName));
        const count = section.querySelector(`[data-team-count="${roleName}"]`);
        if (count) count.textContent = `${visibleByRole[roleName]} tài khoản`;
        section.hidden = Boolean(roleFilter?.value && roleFilter.value !== roleName);
      });
    } catch (error) {
      if (requestId === filterRequest) showFeedback(document.querySelector('#team-feedback'), error.message || 'Không thể kết nối máy chủ.');
    }
  }

  let searchTimer;
  teamSearch?.addEventListener('input', () => { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(filterTeamRows, 250); });
  [roleFilter, statusFilter, categoryFilter].forEach((control) => control?.addEventListener('change', filterTeamRows));
  filterReset?.addEventListener('click', () => {
    teamSearch.value = '';
    roleFilter.value = '';
    statusFilter.value = '';
    categoryFilter.value = '';
    filterTeamRows();
  });
  filterTeamRows();
})();
