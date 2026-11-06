(() => {
  const config = window.HELPDESK_CONFIG || {};
  const apiBase = (config.apiBase || '/api').replace(/\/+$/, '');
  const appBase = new URL(config.appUrl || '/', window.location.origin).pathname.replace(/\/+$/, '');
  const apiEnabled = config.apiEnabled === true;

  const endpointUrl = (endpoint) => {
    const path = endpoint.startsWith('/') ? endpoint : `${apiBase}/${endpoint.replace(/^\/+/, '')}`;
    return new URL(`${appBase}/${path.replace(/^\/+/, '')}`, window.location.origin);
  };

  const ticketEndpoint = (endpoint) => {
    const ticketId = new URLSearchParams(window.location.search).get('ticket');
    return endpoint.includes('{id}') && ticketId
      ? endpoint.replace('{id}', encodeURIComponent(ticketId))
      : endpoint;
  };

  const csrfToken = () => {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (metaToken) return metaToken;
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
  };

  const messageFrom = (payload) => Object.values(payload?.errors || {}).flat().join(' ')
    || payload?.message
    || 'Không thể hoàn thành thao tác.';

  const showFeedback = (form, message, state = 'success') => {
    const feedback = form?.querySelector('[data-api-feedback]') || form?.querySelector('[role="status"]');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.dataset.state = state;
    feedback.hidden = false;
  };

  document.addEventListener('DOMContentLoaded', () => {
    const email = new URLSearchParams(window.location.search).get('email');
    const emailInput = document.querySelector('input[name="email"]');
    if (email && emailInput && !emailInput.value) emailInput.value = email;
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    const otp = fragment.get('otp');
    const otpInput = document.querySelector('input[name="otp"]');
    if (otpInput && otp !== null) {
      if (/^[0-9]{6}$/.test(otp)) otpInput.value = otp;
      // Remove the code from the address bar after filling the form.
      fragment.delete('otp');
      const remaining = fragment.toString();
      window.history.replaceState(null, '', window.location.pathname + window.location.search + (remaining ? `#${remaining}` : ''));
      if (otpInput.value) document.querySelector('button[type="submit"]')?.focus();
    }
    const recipient = document.querySelector('[data-otp-recipient]');
    const updateRecipient = () => {
      if (recipient) recipient.textContent = emailInput?.value.trim() || 'email @ut.edu.vn của bạn';
    };
    updateRecipient();
    emailInput?.addEventListener('input', updateRecipient);
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-api-endpoint]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.redirectPending === 'true') return;
    if (!form.reportValidity()) return;
    if (!apiEnabled) {
      showFeedback(form, 'API chưa được bật.', 'error');
      return;
    }

    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="password_confirmation"]');
    if (password && confirmation && password.value !== confirmation.value) {
      confirmation.setCustomValidity('Mật khẩu xác nhận chưa khớp.');
      confirmation.reportValidity();
      confirmation.addEventListener('input', () => confirmation.setCustomValidity(''), { once: true });
      return;
    }

    const button = form.querySelector('button[type="submit"]');
    const originalLabel = button?.textContent;
    if (button) button.disabled = true;
    if (button && form.dataset.apiEndpoint === '/register') button.textContent = 'Đang gửi OTP...';
    const feedback = form.querySelector('[data-api-feedback]');
    if (feedback) feedback.hidden = true;

    try {
      const method = (form.dataset.apiMethod || form.method || 'POST').toUpperCase();
      const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      };
      const options = { method, credentials: 'same-origin', headers };
      if (method !== 'GET' && method !== 'HEAD') options.body = new FormData(form);

      const response = await fetch(endpointUrl(ticketEndpoint(form.dataset.apiEndpoint)), options);
      const isJson = (response.headers.get('content-type') || '').includes('application/json');
      const payload = isJson ? await response.json() : { message: await response.text() };
      if (!response.ok) throw new Error(messageFrom(payload));

      if (form.dataset.apiEndpoint.endsWith('/login') && payload.redirect) {
        const redirectUrl = payload.redirect.startsWith('http')
          ? payload.redirect
          : `${appBase}/${payload.redirect.replace(/^\/+/, '')}`;
        window.location.assign(redirectUrl);
        return;
      }
      if (form.dataset.successRedirect) {
        const destination = new URL(form.dataset.successRedirect, document.baseURI);
        if (form.elements.email?.value && destination.pathname.endsWith('verify-otp')) {
          destination.searchParams.set('email', payload.email || form.elements.email.value.trim().toLowerCase());
        }
        const delay = Number(form.dataset.successDelay || 0);
        if (delay > 0) {
          showFeedback(form, form.dataset.successMessage || payload.message || 'Đã lưu thành công.');
          form.dataset.redirectPending = 'true';
          form.querySelectorAll('button').forEach((control) => { control.disabled = true; });
          await new Promise((resolve) => window.setTimeout(resolve, delay));
        }
        window.location.assign(destination.href);
        return;
      }

      showFeedback(form, payload.message || form.dataset.successMessage || 'Đã lưu thành công.');
      if (form.dataset.resetOnSuccess === 'true') form.reset();
      if (/\/(assign|status|close|reopen)$/.test(form.dataset.apiEndpoint)) {
        window.setTimeout(() => window.location.reload(), 700);
      }
    } catch (error) {
      showFeedback(form, error.message || 'Không thể kết nối máy chủ.', 'error');
    } finally {
      if (button) {
        button.disabled = form.dataset.redirectPending === 'true';
        if (originalLabel) button.textContent = originalLabel;
      }
    }
  });

  document.addEventListener('click', async (event) => {
    const action = event.target.closest('[data-api-action]');
    if (!action) return;
    event.preventDefault();
    const form = action.closest('form');
    const emailInput = form?.querySelector('[name="email"]');
    const email = emailInput?.value.trim().toLowerCase();
    if (!emailInput?.reportValidity()) {
      return;
    }

    const originalLabel = action.textContent;
    action.disabled = true;
    action.textContent = 'Đang gửi OTP...';
    try {
      const response = await fetch(endpointUrl(action.dataset.apiAction), {
        method: action.dataset.apiMethod || 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ email }),
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(messageFrom(payload));
      showFeedback(form, payload.message || 'Đã gửi lại mã xác thực.');
    } catch (error) {
      showFeedback(form, error.message || 'Không thể gửi lại mã.', 'error');
    } finally {
      action.disabled = false;
      action.textContent = originalLabel;
    }
  });
})();
