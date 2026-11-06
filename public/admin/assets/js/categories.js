(() => {
  const config = window.HELPDESK_CONFIG || {};
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const endpoint = `${appBase}/api/admin/categories`;
  const feedback = document.querySelector('#category-feedback');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function showMessage(message, isError = false) {
    feedback.textContent = message;
    feedback.dataset.state = isError ? 'error' : 'success';
    feedback.hidden = false;
  }

  async function send(url, method, payload) {
    const response = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: payload ? JSON.stringify(payload) : undefined,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
    return result;
  }

  document.querySelector('#category-create-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form));
    try {
      const result = await send(endpoint, 'POST', values);
      showMessage(result.message);
      window.location.reload();
    } catch (error) {
      showMessage(error.message || 'Không thể tạo danh mục.', true);
    }
  });

  document.querySelectorAll('.category-edit-form').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(form));
    try {
      const result = await send(`${endpoint}/${encodeURIComponent(form.dataset.categoryId)}`, 'PUT', values);
      showMessage(result.message);
      window.location.reload();
    } catch (error) {
      showMessage(error.message || 'Không thể cập nhật danh mục.', true);
    }
  }));

  document.querySelectorAll('[data-category-delete]').forEach((button) => button.addEventListener('click', async () => {
    if (!window.confirm('Xóa danh mục này?')) return;
    try {
      const result = await send(`${endpoint}/${encodeURIComponent(button.dataset.categoryDelete)}`, 'DELETE');
      showMessage(result.message);
      window.location.reload();
    } catch (error) {
      showMessage(error.message || 'Không thể xóa danh mục.', true);
    }
  }));
})();
