(() => {
    if (window.helpdeskLogoutReady) return;
    window.helpdeskLogoutReady = true;

    const csrfCookie = () => {
        const cookie = document.cookie
            .split("; ")
            .find((item) => item.startsWith("XSRF-TOKEN="));
        return cookie
            ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length))
            : "";
    };

    document.addEventListener("submit", async (event) => {
        const form = event.target.closest("form[data-logout-form]");
        if (!form) return;
        event.preventDefault();
        if (form.dataset.submitting === "true") return;
        form.dataset.submitting = "true";
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        let feedback = form.querySelector("[data-logout-feedback]");
        if (feedback) feedback.hidden = true;

        try {
            let response;
            for (let attempt = 0; attempt < 2; attempt++) {
                const refresh = await fetch(
                    new URL("sanctum/csrf-cookie", form.action),
                    {
                        credentials: "same-origin",
                        cache: "no-store",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                    },
                );
                if (!refresh.ok)
                    throw new Error(
                        "Không thể làm mới phiên đăng xuất. Vui lòng thử lại.",
                    );
                const token = csrfCookie();
                if (!token)
                    throw new Error(
                        "Không đọc được phiên đăng nhập. Vui lòng tải lại trang.",
                    );
                response = await fetch(form.action, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-XSRF-TOKEN": token,
                    },
                });
                if (response.status !== 419) break;
            }
            if (!response.ok && response.status !== 401) {
                throw new Error("Không thể đăng xuất. Vui lòng thử lại.");
            }
            window.location.replace(new URL("auth/login", form.action).href);
        } catch (error) {
            if (!feedback) {
                feedback = document.createElement("p");
                feedback.className = "api-feedback";
                feedback.dataset.logoutFeedback = "";
                feedback.setAttribute("role", "alert");
                form.appendChild(feedback);
            }
            feedback.textContent =
                error.message || "Không thể kết nối máy chủ. Vui lòng thử lại.";
            feedback.dataset.state = "error";
            feedback.hidden = false;
            form.dataset.submitting = "false";
            if (button) button.disabled = false;
        }
    });
})();
