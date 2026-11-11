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
        feedback.textContent = message;
        feedback.dataset.state = state;
        feedback.hidden = false;
    };

    document.addEventListener("DOMContentLoaded", () => {
        const email = new URLSearchParams(window.location.search).get("email");
        const emailInput = document.querySelector('input[name="email"]');
        if (email && emailInput && !emailInput.value) emailInput.value = email;

        document
            .querySelectorAll('form[data-custom-validation="staff-progress"]')
            .forEach((form) => {
                form.noValidate = true;
                const note = form.querySelector('[name="note"]');
                const error = form.querySelector("[data-note-error]");
                note?.addEventListener("input", () => {
                    if (note.value.trim()) {
                        note.removeAttribute("aria-invalid");
                        if (error) error.hidden = true;
                    }
                });
            });
    });

    document.addEventListener("submit", async (event) => {
        const form = event.target.closest("form[data-api-endpoint]");
        if (!form) return;
        event.preventDefault();
        if (form.dataset.customValidation === "staff-progress") {
            const note = form.querySelector('[name="note"]');
            const error = form.querySelector("[data-note-error]");
            if (!note?.value.trim()) {
                const selectedImages =
                    form.querySelector('[name="attachments[]"]')?.files
                        ?.length || 0;
                if (error) {
                    error.textContent = selectedImages
                        ? `Bạn đã chọn ${selectedImages} ảnh minh chứng. Vui lòng nhập ghi chú tiến độ trước khi lưu.`
                        : "Vui lòng nhập ghi chú tiến độ trước khi lưu.";
                    error.hidden = false;
                }
                note?.setAttribute("aria-invalid", "true");
                note?.focus();
                return;
            }
            note.removeAttribute("aria-invalid");
            if (error) error.hidden = true;
        } else if (!form.reportValidity()) {
            return;
        }
        if (!apiEnabled) {
            showFeedback(form, "API chưa được bật.", "error");
            return;
        }

        const password = form.querySelector('[name="password"]');
        const confirmation = form.querySelector(
            '[name="password_confirmation"]',
        );
        if (password && confirmation && password.value !== confirmation.value) {
            confirmation.setCustomValidity("Mật khẩu xác nhận chưa khớp.");
            confirmation.reportValidity();
            confirmation.addEventListener(
                "input",
                () => confirmation.setCustomValidity(""),
                { once: true },
            );
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button?.textContent;
        if (button) button.disabled = true;
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
            if (!response.ok) throw new Error(messageFrom(payload));

            if (
                form.dataset.apiEndpoint.endsWith("/login") &&
                payload.redirect
            ) {
                window.location.assign(payload.redirect);
                return;
            }
            if (form.dataset.successRedirect) {
                const destination = new URL(
                    form.dataset.successRedirect,
                    document.baseURI,
                );
                if (
                    form.elements.email?.value &&
                    destination.pathname.endsWith("verify-otp")
                ) {
                    destination.searchParams.set(
                        "email",
                        form.elements.email.value,
                    );
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
            if (form.dataset.resetOnSuccess === "true") form.reset();
            if (
                form.dataset.apiEndpoint.includes("/staff/tickets/") &&
                payload?.data?.status === "resolved"
            ) {
                const updateCard = form.closest(".staff-ticket-update-card");
                if (updateCard) {
                    updateCard.classList.remove("staff-ticket-update-card");
                    updateCard.classList.add("staff-ticket-readonly-note");
                    updateCard.innerHTML =
                        "<strong>Đã gửi kết quả xử lý</strong><p>Phiếu đang chờ quản lý kiểm tra và xác nhận.</p>";
                }
            }
            if (
                /\/(assign|status|close|reopen)$/.test(form.dataset.apiEndpoint)
            ) {
                if (window.HelpdeskFeedback)
                    window.HelpdeskFeedback.reload(
                        payload.message || form.dataset.successMessage,
                    );
                else window.location.reload();
            }
        } catch (error) {
            showFeedback(
                form,
                error.message || "Không thể kết nối máy chủ.",
                "error",
            );
        } finally {
            if (button) {
                button.disabled = false;
                if (originalLabel) button.textContent = originalLabel;
            }
        }
    });

    document.addEventListener("click", async (event) => {
        const progressButton = event.target.closest("[data-submit-progress]");
        if (progressButton) {
            const form = progressButton.closest("form");
            const status = form?.querySelector('[name="status"]');
            if (status) status.value = "in_progress";
        }

        const acknowledgeButton = event.target.closest(
            "[data-acknowledge-schedule]",
        );
        if (acknowledgeButton) {
            event.preventDefault();
            const feedback = acknowledgeButton
                .closest("aside")
                ?.querySelector("[data-schedule-ack-feedback]");
            acknowledgeButton.disabled = true;
            try {
                const scheduleId = encodeURIComponent(
                    acknowledgeButton.dataset.acknowledgeSchedule,
                );
                const acknowledgeUrl =
                    acknowledgeButton.dataset.acknowledgeUrl ||
                    endpointUrl(`staff/schedules/${scheduleId}/acknowledge`);
                const response = await fetch(acknowledgeUrl, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                    body: JSON.stringify({}),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(messageFrom(payload));
                if (feedback) {
                    feedback.textContent =
                        payload.message || "Đã xác nhận lịch xử lý.";
                    feedback.dataset.state = "success";
                    feedback.hidden = false;
                }
                if (window.HelpdeskFeedback)
                    window.HelpdeskFeedback.reload(payload.message);
                else window.location.reload();
            } catch (error) {
                if (feedback) {
                    feedback.textContent =
                        error.message || "Không thể xác nhận lịch xử lý.";
                    feedback.dataset.state = "error";
                    feedback.hidden = false;
                }
                acknowledgeButton.disabled = false;
            }
            return;
        }

        const action = event.target.closest("[data-api-action]");
        if (!action) return;
        event.preventDefault();
        const form = action.closest("form");
        const email = form?.querySelector('[name="email"]')?.value;
        if (!email) {
            form?.querySelector('[name="email"]')?.reportValidity();
            return;
        }

        const originalLabel = action.textContent;
        action.disabled = true;
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
            if (!response.ok) throw new Error(messageFrom(payload));
            showFeedback(form, payload.message || "Đã gửi lại mã xác thực.");
        } catch (error) {
            showFeedback(
                form,
                error.message || "Không thể gửi lại mã.",
                "error",
            );
        } finally {
            action.disabled = false;
            action.textContent = originalLabel;
        }
    });
})();
