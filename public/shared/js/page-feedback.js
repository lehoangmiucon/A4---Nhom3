(() => {
  const key = 'helpdesk:page-feedback';
  const dismissTimers = new WeakMap();
  const autoDismiss = (element, delay = 5000) => {
    if (!element) return;
    window.clearTimeout(dismissTimers.get(element));
    dismissTimers.set(element, window.setTimeout(() => {
      element.style.transition = 'opacity .25s ease';
      element.style.opacity = '0';
      window.setTimeout(() => element.remove(), 250);
    }, delay));
  };

  document.querySelectorAll('[role="status"]:not([hidden])').forEach((element) => autoDismiss(element));
  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.type === 'attributes' && mutation.target.matches?.('[role="status"]')) {
        if (mutation.target.hidden) window.clearTimeout(dismissTimers.get(mutation.target));
        else autoDismiss(mutation.target);
      }
      mutation.addedNodes?.forEach((node) => {
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.matches?.('[role="status"]:not([hidden])')) autoDismiss(node);
        node.querySelectorAll?.('[role="status"]:not([hidden])').forEach((element) => autoDismiss(element));
      });
    });
  }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden'] });

  window.HelpdeskFeedback = {
    redirect(message, destination, options = {}) {
      const target = new URL(destination, document.baseURI);
      try {
        sessionStorage.setItem(key, JSON.stringify({
          message,
          path: target.pathname,
          time: Date.now(),
          variant: options.variant || 'success',
          position: options.position || 'top-right',
        }));
      } catch (_) { /* Keep the redirect available if browser storage is disabled. */ }
      window.location.assign(target.href);
    },
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
    toast.dataset.autoDismiss = 'true';
    const isWarning = notice.variant === 'warning';
    const icon = document.createElement('span');
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '\u2713';
    Object.assign(icon.style, {
      display: 'inline-grid', width: '24px', height: '24px', flex: '0 0 24px', placeItems: 'center',
      borderRadius: '50%', background: isWarning ? '#ffe4e6' : '#d1fae5', color: isWarning ? '#b42332' : '#087354', fontSize: '15px', fontWeight: '800',
    });
    toast.append(icon, document.createTextNode(notice.message));
    Object.assign(toast.style, {
      position: 'fixed', top: '96px', left: '50%', right: 'auto', transform: 'translateX(-50%)',
      zIndex: '10000', width: 'min(560px, calc(100vw - 36px))', display: 'flex', alignItems: 'center', gap: '10px',
      maxWidth: 'calc(100vw - 36px)', padding: '14px 18px', borderRadius: '12px', textAlign: 'left',
      background: isWarning ? '#fff1f2' : '#ecfdf5', color: isWarning ? '#b42332' : '#087354',
      border: `1px solid ${isWarning ? '#fecdd3' : '#a7e5cc'}`,
      boxShadow: '0 8px 28px #10233f20', fontSize: '14px', fontWeight: '600',
    });
    document.body.appendChild(toast);
    autoDismiss(toast);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showNotice, { once: true });
  else showNotice();
})();
