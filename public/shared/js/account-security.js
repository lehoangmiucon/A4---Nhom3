(() => {
  document.querySelectorAll('[data-password-code-form], [data-password-reset-form]').forEach(form => {
    let resendUntil = 0;
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      if (button.disabled || !form.reportValidity()) return;
      const feedback = form.querySelector('[data-account-feedback]');
      const reset = form.hasAttribute('data-password-reset-form');
      if (reset && form.elements.password.value !== form.elements.password_confirmation.value) {
        feedback.textContent = 'Mật khẩu xác nhận chưa khớp.';
        feedback.dataset.state = 'error'; feedback.hidden = false; return;
      }
      const label = button.textContent;
      button.disabled = true; button.textContent = reset ? 'Đang đổi mật khẩu…' : 'Đang gửi mã…';
      feedback.hidden = true;
      try {
        const response = await fetch(form.action, {
          method: 'POST', credentials: 'same-origin', body: new FormData(form),
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Không thể hoàn thành thao tác.');
        feedback.textContent = payload.message;
        feedback.dataset.state = 'success'; feedback.hidden = false;
        if (reset && payload.redirect) { window.location.assign(payload.redirect); return; }
        resendUntil = Date.now() + 60000;
        const tick = () => {
          const seconds = Math.max(0, Math.ceil((resendUntil - Date.now()) / 1000));
          button.textContent = seconds ? `Gửi lại mã sau ${seconds}s` : 'Gửi lại mã xác thực';
          button.disabled = seconds > 0;
          if (seconds) window.setTimeout(tick, 1000);
        };
        tick();
      } catch (error) {
        feedback.textContent = error.message || 'Không thể kết nối máy chủ.';
        feedback.dataset.state = 'error'; feedback.hidden = false;
      } finally {
        if (Date.now() >= resendUntil) { button.disabled = false; button.textContent = label; }
      }
    });
  });
})();
