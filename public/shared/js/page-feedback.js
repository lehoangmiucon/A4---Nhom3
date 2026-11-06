(() => {
  const key = 'helpdesk:page-feedback';
  window.HelpdeskFeedback = {
    reload(message = 'Đã lưu thành công.') {
      try {
        sessionStorage.setItem(key, JSON.stringify({ message, path: location.pathname, time: Date.now() }));
      } catch (_) { /* Navigation remains available when storage is disabled. */ }
      window.location.reload();
    },
  };

  const showNotice = () => {
    let notice;
    try {
      notice = JSON.parse(sessionStorage.getItem(key) || 'null');
      sessionStorage.removeItem(key);
    } catch (_) { return; }
    if (!notice || notice.path !== location.pathname || Date.now() - notice.time > 30000) return;
    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.textContent = notice.message;
    Object.assign(toast.style, {
      position: 'fixed', top: '96px', right: '20px', zIndex: '10000',
      maxWidth: 'calc(100vw - 40px)', padding: '14px 20px', borderRadius: '12px',
      background: '#ecfdf5', color: '#087354', border: '1px solid #a7e5cc',
      boxShadow: '0 8px 28px #10233f20', fontSize: '14px', fontWeight: '600',
    });
    document.body.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3000);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showNotice, { once: true });
  else showNotice();
})();
