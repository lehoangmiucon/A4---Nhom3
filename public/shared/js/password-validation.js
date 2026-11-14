(() => {
    const setup = () => {
        let errorId = 0;
        document.querySelectorAll('form').forEach((form) => {
            const current = form.querySelector('[name="current_password"]');
            const password = form.querySelector('[name="password"]');
            const confirmation = form.querySelector('[name="password_confirmation"]');
            if (!password || (!current && !confirmation)) return;
            const fields = [current, password, confirmation].filter(Boolean);
            const errors = new Map();

            const messageFor = (field) => {
                if (field.required && !field.value.trim()) {
                    if (field === current) return 'Vui lòng nhập mật khẩu hiện tại.';
                    if (field === confirmation) return 'Vui lòng xác nhận mật khẩu mới.';
                    return 'Vui lòng nhập mật khẩu mới.';
                }
                if (field === password && field.value) {
                    if (field.minLength > 0 && field.value.length < field.minLength)
                        return `Mật khẩu mới phải có ít nhất ${field.minLength} ký tự.`;
                    if (current?.value && field.value === current.value)
                        return 'Mật khẩu mới không được trùng với mật khẩu hiện tại.';
                }
                if (field === confirmation && field.value && field.value !== password.value)
                    return 'Mật khẩu xác nhận chưa khớp.';
                return '';
            };

            const showError = (field, message) => {
                let error = errors.get(field);
                if (!error && message) {
                    error = document.createElement('small');
                    error.id = `helpdesk-password-error-${++errorId}`;
                    error.className = 'helpdesk-password-error';
                    error.setAttribute('role', 'alert');
                    (field.closest('.helpdesk-password-control, .password-input-wrap') || field).after(error);
                    errors.set(field, error);
                    const describedBy = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                    describedBy.add(error.id);
                    field.setAttribute('aria-describedby', [...describedBy].join(' '));
                }
                if (error) {
                    error.textContent = message;
                    error.hidden = !message;
                }
                if (message) field.setAttribute('aria-invalid', 'true');
                else field.removeAttribute('aria-invalid');
            };

            const sync = () => fields.forEach((field) => {
                const message = messageFor(field);
                field.setCustomValidity(message);
                if (errors.has(field)) showError(field, message);
            });

            fields.forEach((field) => {
                field.addEventListener('input', sync);
                field.addEventListener('change', sync);
                field.addEventListener('invalid', () => {
                    sync();
                    showError(field, messageFor(field));
                });
            });
            form.addEventListener('submit', (event) => {
                sync();
                // The auth screen already renders Vietnamese validation for OTP
                // and all its fields; preserve that screen's validation order.
                if (form.noValidate) return;
                const invalid = fields.find((field) => messageFor(field));
                if (!invalid) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                showError(invalid, messageFor(invalid));
                invalid.reportValidity();
                invalid.focus();
            }, true);
            form.addEventListener('reset', () => {
                fields.forEach((field) => { field.setCustomValidity(''); showError(field, ''); });
            });
            sync();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup, { once: true });
    else setup();
})();
