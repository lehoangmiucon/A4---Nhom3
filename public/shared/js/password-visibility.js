(() => {
  const eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeSlash = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.8M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7c2.1 0 4-.7 5.5-1.7M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';

  const setup = () => {
    let nextId = 0;
    document.querySelectorAll('input[type="password"]').forEach((input) => {
      if (input.closest('.helpdesk-password-control, .password-input-wrap')) return;

      if (!input.id) {
        do { nextId++; } while (document.getElementById(`helpdesk-password-${nextId}`));
        input.id = `helpdesk-password-${nextId}`;
      }

      const control = document.createElement('span');
      control.className = 'helpdesk-password-control';
      input.before(control);
      control.append(input);

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'helpdesk-password-toggle';
      toggle.setAttribute('aria-controls', input.id);

      const setVisible = (visible) => {
        input.type = visible ? 'text' : 'password';
        const label = visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.title = label;
        toggle.innerHTML = visible ? eyeSlash : eye;
      };

      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        setVisible(input.type === 'password');
      });
      input.form?.addEventListener('reset', () => setVisible(false));
      setVisible(false);
      control.append(toggle);
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup, { once: true });
  else setup();
})();
