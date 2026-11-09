(() => {
  const eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeOff = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 5.1A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 4M6.2 6.2A21 21 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  const setup = () => {
    document.querySelectorAll('input[type="password"]').forEach((input, index) => {
      if (input.closest('.password-input-wrap')) return;
      if (!input.id) {
        let id = `password-visibility-${index}`;
        while (document.getElementById(id)) id += '-field';
        input.id = id;
      }
      const wrapper = document.createElement('span');
      wrapper.className = 'password-input-wrap';
      input.before(wrapper); wrapper.append(input);
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'password-toggle';
      button.setAttribute('aria-controls', input.id);
      const fieldName = input.labels?.[0]?.textContent.trim() || 'mật khẩu';
      const update = visible => {
        input.type = visible ? 'text' : 'password';
        button.innerHTML = visible ? eyeOff : eye;
        const label = `${visible ? 'Ẩn' : 'Hiện'} ${fieldName.toLocaleLowerCase('vi')}`;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', String(visible));
      };
      update(false);
      button.addEventListener('click', event => {
        event.preventDefault();
        const start = input.selectionStart, end = input.selectionEnd;
        update(input.type === 'password');
        input.focus({ preventScroll: true });
        if (start !== null && end !== null) input.setSelectionRange(start, end);
      });
      wrapper.append(button);
      input.form?.addEventListener('reset', () => update(false));
      input.closest('dialog')?.addEventListener('close', () => update(false));
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup, { once: true });
  else setup();
})();
