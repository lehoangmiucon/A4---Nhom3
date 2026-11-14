(() => {
    const config = window.HELPDESK_CONFIG || {};
    const apiBase = (config.apiBase || "/api").replace(/\/+$/, "");
    const appBase = new URL(
        config.appUrl || "/",
        window.location.origin,
    ).pathname.replace(/\/+$/, "");
    const apiEnabled = config.apiEnabled === true;

    const endpointUrl = (endpoint) => {
        const path = endpoint.startsWith("/")
            ? endpoint
            : `${apiBase}/${endpoint.replace(/^\/+/, "")}`;
        return new URL(
            `${appBase}/${path.replace(/^\/+/, "")}`,
            window.location.origin,
        );
    };

    const ticketEndpoint = (endpoint) => {
        const ticketId = new URLSearchParams(window.location.search).get(
            "ticket",
        );
        return endpoint.includes("{id}") && ticketId
            ? endpoint.replace("{id}", encodeURIComponent(ticketId))
            : endpoint;
    };

    const csrfToken = () => {
        const metaToken = document.querySelector(
            'meta[name="csrf-token"]',
        )?.content;
        if (metaToken) return metaToken;
        const cookie = document.cookie
            .split("; ")
            .find((item) => item.startsWith("XSRF-TOKEN="));
        return cookie
            ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length))
            : "";
    };

    const messageFrom = (payload) =>
        payload?.message ||
        Object.values(payload?.errors || {})
            .flat()
            .join(" ") ||
        "Không thể hoàn thành thao tác.";

    const showFeedback = (form, message, state = "success") => {
        const feedback =
            form?.querySelector("[data-api-feedback]") ||
            form?.querySelector('[role="status"]');
        if (!feedback) return;
        const icon = document.createElement("i");
        icon.className =
            state === "error"
                ? "fa-solid fa-circle-exclamation"
                : "fa-solid fa-circle-check";
        icon.setAttribute("aria-hidden", "true");
        const text = document.createElement("span");
        text.textContent = message;
        feedback.replaceChildren(icon, text);
        feedback.dataset.state = state;
        feedback.setAttribute("role", state === "error" ? "alert" : "status");
        feedback.hidden = false;
    };

    const fieldTargets = (input) =>
        input.name === "otp"
            ? [
                  input,
                  ...input
                      .closest(".role-field")
                      .querySelectorAll("[data-otp-digit]"),
              ]
            : [input];

    const focusField = (input) => {
        if (!input) return;
        if (input.name === "otp") {
            const digits = [
                ...input
                    .closest(".role-field")
                    .querySelectorAll("[data-otp-digit]"),
            ];
            (
                digits.find((digit) => !digit.value) ||
                digits[0] ||
                input
            ).focus();
        } else input.focus();
    };

    const clearFieldError = (input) => {
        const field = input.closest(".role-field");
        const error = field?.querySelector(".auth-field-error");
        field?.classList.remove("has-error");
        if (error) error.hidden = true;
        fieldTargets(input).forEach((target) => {
            target.removeAttribute("aria-invalid");
            const ids = (target.getAttribute("aria-describedby") || "")
                .split(/\s+/)
                .filter((id) => id && id !== error?.id);
            if (ids.length)
                target.setAttribute("aria-describedby", ids.join(" "));
            else target.removeAttribute("aria-describedby");
        });
    };

    const showFieldError = (input, message) => {
        const field = input.closest(".role-field");
        if (!field) return;
        let error = field.querySelector(".auth-field-error");
        if (!error) {
            error = document.createElement("span");
            error.className = "auth-field-error";
            error.id = `auth-error-${input.id || input.name}`;
            error.setAttribute("aria-live", "polite");
            const icon = document.createElement("i");
            icon.className = "fa-solid fa-circle-exclamation";
            icon.setAttribute("aria-hidden", "true");
            error.append(icon, document.createElement("span"));
            field.appendChild(error);
        }
        error.lastElementChild.textContent = message;
        error.hidden = false;
        field.classList.add("has-error");
        fieldTargets(input).forEach((target) => {
            target.setAttribute("aria-invalid", "true");
            const ids = new Set(
                (target.getAttribute("aria-describedby") || "")
                    .split(/\s+/)
                    .filter(Boolean),
            );
            ids.add(error.id);
            target.setAttribute("aria-describedby", [...ids].join(" "));
        });
    };

    const fieldMessage = (input) => {
        const label =
            input
                .closest(".role-field")
                ?.querySelector("label")
                ?.textContent.trim() || "thông tin";
        if (input.required && !input.value.trim())
            return `Vui lòng nhập ${label.toLocaleLowerCase("vi-VN")}.`;
        if (input.validity.typeMismatch)
            return "Địa chỉ email chưa đúng định dạng.";
        if (
            input.hasAttribute("data-school-email") &&
            input.value &&
            !/^[^@\s]+@ut\.edu\.vn$/i.test(input.value)
        ) {
            return "Chỉ chấp nhận email do trường cấp có đuôi @ut.edu.vn.";
        }
        if (input.name === "otp" && !/^[0-9]{6}$/.test(input.value))
            return "Mã OTP phải gồm đúng 6 chữ số.";
        if (input.minLength > 0 && input.value.length < input.minLength)
            return `Vui lòng nhập ít nhất ${input.minLength} ký tự.`;
        if (input.maxLength > 0 && input.value.length > input.maxLength)
            return `Vui lòng nhập tối đa ${input.maxLength} ký tự.`;
        if (
            input.name === "password_confirmation" &&
            input.value !== input.form?.elements.password?.value
        )
            return "Mật khẩu xác nhận chưa khớp.";
        if (!input.validity.valid)
            return "Thông tin chưa hợp lệ. Vui lòng kiểm tra lại.";
        return "";
    };

    const validateForm = (form) => {
        let firstInvalid;
        form.querySelectorAll(
            'input:not([type="hidden"]):not([data-otp-digit]):not([disabled]), input[name="otp"][type="hidden"]',
        ).forEach((input) => {
            clearFieldError(input);
            const message = fieldMessage(input);
            if (!message) return;
            showFieldError(input, message);
            firstInvalid ||= input;
        });
        focusField(firstInvalid);
        return !firstInvalid;
    };

    const showServerErrors = (form, errors) => {
        let firstInvalid;
        Object.entries(errors || {}).forEach(([name, messages]) => {
            const input = form.elements.namedItem(name);
            if (!input?.closest?.(".role-field")) return;
            showFieldError(
                input,
                Array.isArray(messages) ? messages[0] : String(messages),
            );
            firstInvalid ||= input;
        });
        focusField(firstInvalid);
        return Boolean(firstInvalid);
    };

    const otpStorageKey = (form) => `helpdesk:otp-clock:${form?.dataset.apiEndpoint?.includes("password") ? "password_reset" : "email_verification"}:${form?.elements.email?.value.trim().toLowerCase() || ""}`;

    const rememberOtpTiming = (form, payload) => {
        const serverTime = Date.parse(payload.server_time);
        const expires = Date.parse(payload.otp_expires_at);
        const resend = Date.parse(payload.otp_resend_available_at);
        if (![serverTime, expires, resend].every(Number.isFinite)) return null;
        const timing = {
            expires: Date.now() + expires - serverTime,
            resend: Date.now() + resend - serverTime,
        };
        try { sessionStorage.setItem(otpStorageKey(form), JSON.stringify(timing)); } catch (_) {}
        return timing;
    };

    const startResendCooldown = (button, restore = false, payload = null) => {
        if (!button) return;
        const label = "Gửi lại mã OTP";
        const form = button.form;
        const expiry = form?.querySelector("[data-otp-expiry]");
        let timing = payload ? rememberOtpTiming(form, payload) : null;
        try {
            if (!timing && restore) timing = JSON.parse(sessionStorage.getItem(otpStorageKey(form)));
        } catch (_) {}
        window.clearInterval(button.otpTimer);
        if (!timing || !Number.isFinite(timing.expires) || !Number.isFinite(timing.resend)) {
            if (expiry) expiry.hidden = true;
            delete button.dataset.cooldown;
            button.disabled = false;
            button.textContent = label;
            return;
        }
        const format = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
        const tick = () => {
            const remaining = Math.max(0, Math.ceil((timing.expires - Date.now()) / 1000));
            const wait = Math.max(0, Math.ceil((timing.resend - Date.now()) / 1000));
            if (expiry) {
                expiry.hidden = false;
                expiry.textContent = remaining > 0 ? `Mã OTP hết hạn sau ${format(remaining)}` : "Mã OTP đã hết hạn. Vui lòng gửi lại mã.";
                expiry.classList.toggle("otp-expired", remaining === 0);
            }
            if (wait > 0) {
                button.dataset.cooldown = "true";
                button.disabled = true;
                button.textContent = `Gửi lại sau ${format(wait)}`;
            } else {
                delete button.dataset.cooldown;
                button.disabled = false;
                button.textContent = label;
            }
            if (remaining === 0 && wait === 0) window.clearInterval(button.otpTimer);
        };
        button.otpTimer = window.setInterval(tick, 1000);
        tick();
    };

    const setupOtpInputs = () => {
        const group = document.querySelector("[data-otp-group]");
        if (!group) return;
        const digits = [...group.querySelectorAll("[data-otp-digit]")];
        const source = group.closest("form").elements.otp;
        const sync = () => {
            digits.forEach((input) =>
                input.classList.toggle("is-filled", Boolean(input.value)),
            );
            source.value = digits.map((input) => input.value).join("");
            if (source.hasAttribute("aria-invalid")) {
                clearFieldError(source);
                const message = fieldMessage(source);
                if (message) showFieldError(source, message);
            }
        };
        const fill = (text, index) => {
            const numbers = text.replace(/[^0-9]/g, "").slice(0, digits.length);
            if (!numbers) return;
            const start = numbers.length === digits.length ? 0 : index;
            [...numbers]
                .slice(0, digits.length - start)
                .forEach((number, offset) => {
                    digits[start + offset].value = number;
                });
            sync();
            digits[Math.min(start + numbers.length, digits.length - 1)].focus();
        };
        digits.forEach((input, index) => {
            input.addEventListener("focus", () => input.select());
            input.addEventListener("input", () => {
                const value = input.value.replace(/[^0-9]/g, "");
                if (value.length > 1) return fill(value, index);
                input.value = value;
                sync();
                if (value && index < digits.length - 1)
                    digits[index + 1].focus();
            });
            input.addEventListener("paste", (event) => {
                const text = event.clipboardData?.getData("text") || "";
                if (!/[0-9]/.test(text)) return;
                event.preventDefault();
                fill(text, index);
            });
            input.addEventListener("keydown", (event) => {
                if (event.key === "Backspace" && !input.value && index > 0) {
                    event.preventDefault();
                    digits[index - 1].value = "";
                    digits[index - 1].focus();
                    sync();
                } else if (event.key === "ArrowLeft" && index > 0) {
                    event.preventDefault();
                    digits[index - 1].focus();
                } else if (
                    event.key === "ArrowRight" &&
                    index < digits.length - 1
                ) {
                    event.preventDefault();
                    digits[index + 1].focus();
                }
            });
        });
        if (source.form.elements.email?.value) digits[0].focus();
    };

    document.addEventListener("DOMContentLoaded", () => {
        const email = new URLSearchParams(window.location.search).get("email");
        const emailInput = document.querySelector('input[name="email"]');
        if (email && emailInput && !emailInput.value) emailInput.value = email;
        const emailLabel = document.querySelector("[data-otp-email]");
        const verificationLink = document.querySelector(
            "[data-verification-link]",
        );
        const updateEmailLabel = () => {
            if (emailLabel)
                emailLabel.textContent = emailInput?.value || "email của bạn";
            if (verificationLink) {
                const url = new URL("verify-otp", document.baseURI);
                if (emailInput?.value)
                    url.searchParams.set("email", emailInput.value.trim());
                verificationLink.href = url.href;
            }
        };
        updateEmailLabel();
        emailInput?.addEventListener("input", updateEmailLabel);
        if (emailInput?.hasAttribute("data-school-email")) {
            emailInput.addEventListener("blur", () => {
                emailInput.value = emailInput.value.trim().toLowerCase();
            });
        }
        setupOtpInputs();
        document
            .querySelectorAll("[data-password-toggle]")
            .forEach((toggle) => {
                const input = document.getElementById(
                    toggle.getAttribute("aria-controls"),
                );
                if (!input) return;
                toggle.addEventListener("click", () => {
                    const visible = input.type === "password";
                    input.type = visible ? "text" : "password";
                    toggle.setAttribute("aria-pressed", String(visible));
                    toggle.setAttribute(
                        "aria-label",
                        visible ? "Ẩn mật khẩu" : "Hiện mật khẩu",
                    );
                    toggle.title = visible ? "Ẩn mật khẩu" : "Hiện mật khẩu";
                    toggle
                        .querySelector("i")
                        ?.classList.toggle("fa-eye", !visible);
                    toggle
                        .querySelector("i")
                        ?.classList.toggle("fa-eye-slash", visible);
                });
            });
        document.querySelectorAll("form[data-api-endpoint]").forEach((form) => {
            form.noValidate = true;
            form.addEventListener(
                "invalid",
                (event) => event.preventDefault(),
                true,
            );
            const revalidate = (event) => {
                const input = event.target;
                if (
                    !input.matches(
                        'input:not([type="hidden"]):not([data-otp-digit])',
                    )
                )
                    return;
                if (
                    event.type === "focusout"
                        ? input.value || input.hasAttribute("aria-invalid")
                        : input.hasAttribute("aria-invalid")
                ) {
                    clearFieldError(input);
                    const message = fieldMessage(input);
                    if (message) showFieldError(input, message);
                }
                if (input.name === "password") {
                    const confirmation = form.elements.password_confirmation;
                    if (confirmation?.value) {
                        clearFieldError(confirmation);
                        const message = fieldMessage(confirmation);
                        if (message) showFieldError(confirmation, message);
                    }
                }
            };
            form.addEventListener("input", revalidate);
            form.addEventListener("focusout", revalidate);
        });
        {
            startResendCooldown(
                document.querySelector(
                    '[data-api-action="/email/resend-otp"], [data-api-action="/password/forgot"]',
                ),
                true,
            );
        }
    });

    document.addEventListener("submit", async (event) => {
        const form = event.target.closest("form[data-api-endpoint]");
        if (!form) return;
        event.preventDefault();
        if (form.dataset.submitting === "true") return;
        const existingFeedback = form.querySelector("[data-api-feedback]");
        if (existingFeedback) existingFeedback.hidden = true;
        if (!validateForm(form)) return;
        if (!apiEnabled) {
            showFeedback(form, "API chưa được bật.", "error");
            return;
        }

        form.dataset.submitting = "true";
        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button?.textContent;
        if (button) {
            button.disabled = true;
            button.textContent = form.dataset.busyLabel || "Đang xử lý…";
        }
        const feedback = form.querySelector("[data-api-feedback]");
        if (feedback) feedback.hidden = true;

        try {
            const method = (
                form.dataset.apiMethod ||
                form.method ||
                "POST"
            ).toUpperCase();
            const headers = {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken(),
            };
            const options = { method, credentials: "same-origin", headers };
            if (method !== "GET" && method !== "HEAD")
                options.body = new FormData(form);

            const response = await fetch(
                endpointUrl(ticketEndpoint(form.dataset.apiEndpoint)),
                options,
            );
            const isJson = (
                response.headers.get("content-type") || ""
            ).includes("application/json");
            const payload = isJson
                ? await response.json()
                : { message: await response.text() };
            if (!response.ok) {
                if (showServerErrors(form, payload.errors)) return;
                throw new Error(messageFrom(payload));
            }

            if (
                form.dataset.apiEndpoint.endsWith("/login") &&
                payload.redirect
            ) {
                const redirectUrl = payload.redirect.startsWith("http")
                    ? payload.redirect
                    : `${appBase}/${payload.redirect.replace(/^\/+/, "")}`;
                window.location.assign(redirectUrl);
                return;
            }
            if (form.dataset.successRedirect) {
                rememberOtpTiming(form, payload);
                const destination = new URL(
                    form.dataset.successRedirect,
                    document.baseURI,
                );
                if (
                    form.dataset.apiEndpoint === "/password/forgot" &&
                    form.elements.email?.value
                ) {
                    destination.searchParams.set("step", "reset");
                    destination.searchParams.set(
                        "email",
                        form.elements.email.value,
                    );
                }
                if (
                    form.elements.email?.value &&
                    destination.pathname.endsWith("verify-otp")
                ) {
                    destination.searchParams.set(
                        "email",
                        form.elements.email.value,
                    );
                    if (form.dataset.apiEndpoint === "/register")
                        destination.searchParams.set("sent", "1");
                }
                window.location.assign(destination.href);
                return;
            }

            showFeedback(
                form,
                payload.message ||
                    form.dataset.successMessage ||
                    "Đã lưu thành công.",
            );
            if (form.dataset.apiEndpoint === "/password/reset") {
                window.clearInterval(form.querySelector('[data-api-action="/password/forgot"]')?.otpTimer);
                try { sessionStorage.removeItem(otpStorageKey(form)); } catch (_) {}
                form.reset();
                form.querySelectorAll("[data-reset-field]").forEach((field) => {
                    field.hidden = true;
                });
            }
            if (form.dataset.resetOnSuccess === "true") form.reset();
            if (
                /\/(assign|status|close|reopen)$/.test(form.dataset.apiEndpoint)
            ) {
                window.setTimeout(() => window.location.reload(), 700);
            }
        } catch (error) {
            showFeedback(
                form,
                error.message || "Không thể kết nối máy chủ.",
                "error",
            );
        } finally {
            delete form.dataset.submitting;
            if (button) {
                button.disabled = false;
                if (originalLabel) button.textContent = originalLabel;
            }
        }
    });

    document.addEventListener("click", async (event) => {
        const action = event.target.closest("[data-api-action]");
        if (!action) return;
        event.preventDefault();
        if (action.disabled || action.dataset.cooldown === "true") return;
        const form = action.closest("form");
        const emailInput = form?.querySelector('[name="email"]');
        if (!emailInput) return;
        clearFieldError(emailInput);
        const emailError = fieldMessage(emailInput);
        if (emailError) {
            showFieldError(emailInput, emailError);
            emailInput.focus();
            return;
        }
        const email = emailInput.value;

        const originalLabel = action.textContent;
        action.disabled = true;
        action.textContent = "Đang gửi mã OTP…";
        try {
            const response = await fetch(
                endpointUrl(action.dataset.apiAction),
                {
                    method: action.dataset.apiMethod || "POST",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                    body: JSON.stringify({ email }),
                },
            );
            const payload = await response.json();
            if (!response.ok) {
                if (showServerErrors(form, payload.errors)) return;
                throw new Error(messageFrom(payload));
            }
            showFeedback(form, payload.message || "Đã gửi lại mã xác thực.");
            if (
                ["/email/resend-otp", "/password/forgot"].includes(
                    action.dataset.apiAction,
                )
            )
                startResendCooldown(action, false, payload);
        } catch (error) {
            showFeedback(
                form,
                error.message || "Không thể gửi lại mã.",
                "error",
            );
        } finally {
            if (action.dataset.cooldown !== "true") {
                action.disabled = false;
                action.textContent = originalLabel;
            }
        }
    });
})();
