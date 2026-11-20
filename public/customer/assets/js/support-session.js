(() => {
    // Check required configs
    const config = window.HELPDESK_CONFIG || {};
    const apiBase = (config.apiBase || "/api").replace(/\/+$/, "");
    const currentUserId = config.currentUserId || null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

    const panel = document.getElementById("support-session-panel");
    const launch = document.getElementById("support-session-launch");
    const closeBtn = document.getElementById("support-session-close");

    if (!panel || !launch) return;

    // Navigation tabs
    const navList = document.getElementById("support-nav-list");
    const navCreate = document.getElementById("support-nav-create");
    const navChat = document.getElementById("support-nav-chat");
    const viewChat = document.getElementById("portal-chat");

    // Views
    const viewList = document.getElementById("support-view-list");
    const viewCreate = document.getElementById("support-view-create");
    const viewDetail = document.getElementById("support-view-detail");

    // Global status banner
    const globalStatus = document.getElementById("support-global-status");

    // UI-02: List elements
    const sessionCount = document.getElementById("support-session-count");
    const refreshListBtn = document.getElementById("support-btn-refresh-list");
    const listLoading = document.getElementById("support-list-loading");
    const listError = document.getElementById("support-list-error");
    const listErrorMsg = document.getElementById("support-list-error-msg");
    const listRetry = document.getElementById("support-list-retry");
    const listEmpty = document.getElementById("support-list-empty");
    const emptyCreateBtn = document.getElementById("support-empty-create-btn");
    const sessionItems = document.getElementById("support-session-items");
    const pagination = document.getElementById("support-pagination");
    const pagePrev = document.getElementById("support-page-prev");
    const pageNext = document.getElementById("support-page-next");
    const pageInfo = document.getElementById("support-page-info");

    // UI-01: Create elements
    const createForm = document.getElementById("support-create-form");
    const roomSelect = document.getElementById("support-room-select");
    const equipmentInput = document.getElementById("support-equipment-input");
    const descInput = document.getElementById("support-desc-input");
    const createError = document.getElementById("support-create-error");
    const createSubmit = document.getElementById("support-create-submit");
    const createCancel = document.getElementById("support-create-cancel");

    // UI-03: Detail elements
    const backToListBtn = document.getElementById("support-back-to-list");
    const directHelpBtn = document.getElementById("support-direct-help");
    const detailRoom = document.getElementById("support-detail-room");
    const detailStatusBadge = document.getElementById("support-detail-status-badge");
    const detailEquipment = document.getElementById("support-detail-equipment");
    const detailTime = document.getElementById("support-detail-time");
    const messagesLoading = document.getElementById("support-messages-loading");
    const messagesLog = document.getElementById("support-messages-log");

    // Form elements on portal page to sync with
    const portalRoomId = document.getElementById("room-id");
    const portalTitle = document.getElementById("title");
    const portalDescription = document.getElementById("description");
    const reportFeedback = document.getElementById("report-chat-feedback");

    // State
    const STORAGE_KEY = "helpdesk_active_support_session";
    let currentPage = 1;
    let totalPages = 1;
    let activeSession = null;
    let currentIdempotencyKey = null;
    let pendingPayload = null;
    let viewRevision = 0;
    let listRevision = 0;
    let loggedOut = false;
    let creating = false;
    let currentView = null;
    const clearStoredSession = () => {
        try { sessionStorage.removeItem(STORAGE_KEY); } catch {}
    };
    const syncEquipment = () => {
        equipmentInput.replaceChildren();
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "-- Chưa xác định thiết bị --";
        equipmentInput.appendChild(placeholder);
        const room = Array.from(roomSelect.options).find((option) => option.value === roomSelect.value);
        const equipment = JSON.parse(room?.dataset.equipment || "[]");
        equipment.forEach((item) => {
            const option = document.createElement("option");
            option.value = String(item.id);
            option.textContent = item.name;
            equipmentInput.appendChild(option);
        });
        equipmentInput.value = "";
    };
    roomSelect.addEventListener("change", syncEquipment);

    // Helper: generate UUID v4
    const generateUuid = () => {
        if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
            return crypto.randomUUID();
        }
        return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            const v = c === "x" ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    };

    // Helper: translate status code
    const formatStatus = (status) => {
        const labels = {
            collecting: "Đang thu thập",
            awaiting_result: "Chờ kết quả",
            handoff_pending: "Đang chuyển tiếp",
            handed_off: "Đã bàn giao",
            resolved: "Đã giải quyết",
            cancelled: "Đã hủy",
        };
        return labels[status] || status;
    };

    // Helper: format ISO date
    const formatDate = (isoString) => {
        if (!isoString) return "";
        try {
            const d = new Date(isoString);
            return d.toLocaleString("vi-VN", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            });
        } catch {
            return isoString;
        }
    };

    // Helper: API fetch wrapper
    const apiFetch = async (endpoint, options = {}) => {
        const url = `${apiBase}${endpoint.startsWith("/") ? "" : "/"}${endpoint}`;
        const headers = {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
            ...(options.headers || {}),
        };

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const res = await fetch(url, {
                ...options,
                credentials: "same-origin",
                headers,
                signal: controller.signal,
            });

            const data = await res.json().catch(() => null);
            return { ok: res.ok && data?.data != null, status: res.status, data };
        } catch {
            return { ok: false, status: 0, data: null };
        } finally {
            clearTimeout(timeout);
        }
    };

    // View Switcher
    const showView = (viewName) => {
        currentView = viewName;
        viewRevision++;
        listRevision++;
        activeSession = null;
        directHelpBtn.disabled = true;
        globalStatus.hidden = true;
        viewList.hidden = viewName !== "list";
        viewCreate.hidden = viewName !== "create";
        viewDetail.hidden = viewName !== "detail";
        if (viewChat) viewChat.hidden = viewName !== "chat";

        navList.classList.toggle("is-active", viewName === "list");
        navCreate.classList.toggle("is-active", viewName === "create");
        if (navChat) navChat.classList.toggle("is-active", viewName === "chat");
        navList.setAttribute("aria-pressed", String(viewName === "list"));
        navCreate.setAttribute("aria-pressed", String(viewName === "create"));
        navChat?.setAttribute("aria-pressed", String(viewName === "chat"));

        if (viewName === "list") {
            loadSessionList(currentPage);
        } else if (viewName === "create") {
            // Auto sync room from portal form if chosen
            if (portalRoomId && portalRoomId.value && roomSelect) {
                if (roomSelect.value !== portalRoomId.value) {
                    roomSelect.value = portalRoomId.value;
                    syncEquipment();
                }
            }
            createError.hidden = true;
            createError.textContent = "";
            descInput.focus();
        }
    };

    // UI-02: Load Session List
    const loadSessionList = async (page = 1) => {
        const revision = ++listRevision;
        listLoading.hidden = false;
        listError.hidden = true;
        listEmpty.hidden = true;
        sessionItems.replaceChildren();
        pagination.hidden = true;
        sessionCount.textContent = "Đang tải…";

        const res = await apiFetch(`/support-sessions?page=${page}&per_page=15`);
        if (loggedOut || revision !== listRevision) return;

        listLoading.hidden = true;

        if (!res.ok) {
            listError.hidden = false;
            listErrorMsg.textContent = res.data?.error?.message || "Không thể tải danh sách phiên. Vui lòng thử lại.";
            sessionCount.textContent = "Lỗi kết nối";
            return;
        }

        const items = res.data?.data || [];
        const meta = res.data?.meta?.pagination || {};
        currentPage = meta.current_page || page;
        totalPages = meta.last_page || 1;
        const totalItems = meta.total || items.length;

        sessionCount.textContent = `${totalItems} phiên`;

        if (items.length === 0) {
            listEmpty.hidden = false;
            return;
        }

        items.forEach((item) => {
            const li = document.createElement("li");
            li.className = "support-session-item";
            li.tabIndex = 0;
            li.setAttribute("role", "button");
            li.setAttribute("aria-label", `Xem phiên hỗ trợ tại phòng ${item.room_label || item.room_code}`);

            const head = document.createElement("div");
            head.className = "support-item-head";

            const roomSpan = document.createElement("span");
            roomSpan.className = "support-item-room";
            roomSpan.textContent = item.room_label ? `Phòng ${item.room_label}` : `Phòng ${item.room_code || "—"}`;

            const badge = document.createElement("span");
            badge.className = `support-badge status-${item.status || "collecting"}`;
            badge.textContent = formatStatus(item.status);

            head.append(roomSpan, badge);

            const equip = document.createElement("div");
            equip.className = "support-item-equipment";
            equip.textContent = item.equipment_label ? `Thiết bị: ${item.equipment_label}` : "Thiết bị: Chung";

            const desc = document.createElement("p");
            desc.className = "support-item-desc";
            desc.textContent = item.initial_description || "(Không có mô tả chi tiết)";

            const time = document.createElement("time");
            time.className = "support-item-time";
            time.textContent = `Tạo lúc: ${formatDate(item.created_at)}`;

            li.append(head, equip, desc, time);

            const selectItem = () => {
                openSessionDetail(item.id);
            };

            li.addEventListener("click", selectItem);
            li.addEventListener("keydown", (e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    selectItem();
                }
            });

            sessionItems.appendChild(li);
        });

        // Pagination
        if (totalPages > 1) {
            pagination.hidden = false;
            pageInfo.textContent = `Trang ${currentPage} / ${totalPages}`;
            pagePrev.disabled = currentPage <= 1;
            pageNext.disabled = currentPage >= totalPages;
        }
    };

    // UI-03: Open Session Detail
    const openSessionDetail = async (sessionId) => {
        showView("detail");
        const revision = viewRevision;
        globalStatus.hidden = true;
        messagesLog.replaceChildren();
        messagesLoading.hidden = false;
        detailRoom.textContent = "Đang tải…";
        detailEquipment.textContent = "Thiết bị: --";
        detailTime.textContent = "Thời gian: --";
        detailStatusBadge.textContent = "Đang tải…";

        // Save active session to storage (M1B-03)
        if (currentUserId) {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ userId: currentUserId, sessionId }));
            } catch {
                // Ignore storage access errors
            }
        }

        // Fetch session detail
        const sessionRes = await apiFetch(`/support-sessions/${sessionId}`);
        if (loggedOut || revision !== viewRevision) return;
        if (!sessionRes.ok) {
            messagesLoading.hidden = true;
            detailRoom.textContent = "Không tìm thấy phiên hỗ trợ";
            detailStatusBadge.textContent = "Lỗi";
            detailStatusBadge.className = "support-badge status-cancelled";
            const errP = document.createElement("p");
            errP.className = "support-inline-error";
            errP.textContent = sessionRes.status === 404
                ? "Phiên hỗ trợ này không tồn tại hoặc bạn không có quyền xem."
                : "Không thể tải phiên hỗ trợ. Vui lòng thử lại.";
            if ([401, 403, 404].includes(sessionRes.status)) clearStoredSession();
            messagesLog.appendChild(errP);
            appendDetailRetry(sessionId);
            return;
        }

        activeSession = sessionRes.data?.data || null;
        directHelpBtn.disabled = false;
        globalStatus.hidden = false;
        globalStatus.textContent = "Đã lưu phiên hỗ trợ";

        // Render meta
        detailRoom.textContent = activeSession.room_label ? `Phòng ${activeSession.room_label}` : `Phòng ${activeSession.room_code || "—"}`;
        detailEquipment.textContent = activeSession.equipment_label ? `Thiết bị: ${activeSession.equipment_label}` : "Thiết bị: Chưa xác định";
        detailStatusBadge.textContent = formatStatus(activeSession.status);
        detailStatusBadge.className = `support-badge status-${activeSession.status || "collecting"}`;
        detailTime.textContent = `Khởi tạo: ${formatDate(activeSession.created_at)}`;

        // Fetch messages
        const messages = [];
        let page = 1;
        let lastPage = 1;
        do {
            const msgRes = await apiFetch(`/support-sessions/${sessionId}/messages?page=${page}&per_page=50`);
            if (loggedOut || revision !== viewRevision) return;
            if (!msgRes.ok) {
                messagesLoading.hidden = true;
                const errP = document.createElement("p");
                errP.className = "support-inline-error";
                errP.textContent = "Không thể tải đầy đủ lịch sử tin nhắn. Vui lòng thử lại.";
                messagesLog.appendChild(errP);
                appendDetailRetry(sessionId);
                return;
            }
            messages.push(...msgRes.data.data);
            lastPage = msgRes.data.meta?.pagination?.last_page || 1;
            page++;
        } while (page <= lastPage);
        messagesLoading.hidden = true;
        if (messages.length === 0 && activeSession.initial_description) {
            // Fallback render initial description if no messages yet
            renderMessage({ role: "user", content_text: activeSession.initial_description, created_at: activeSession.created_at });
        } else {
            messages.forEach(renderMessage);
        }

        messagesLog.scrollTop = messagesLog.scrollHeight;
    };

    const appendDetailRetry = (sessionId) => {
        const retry = document.createElement("button");
        retry.type = "button";
        retry.className = "support-pill-btn";
        retry.textContent = "Thử lại";
        retry.addEventListener("click", () => openSessionDetail(sessionId));
        messagesLog.appendChild(retry);
    };

    const renderMessage = (msg) => {
        const bubble = document.createElement("div");
        const isUser = msg.role === "user";
        bubble.className = `support-message-bubble ${isUser ? "is-user" : "is-assistant"}`;

        const sender = document.createElement("span");
        sender.className = "support-bubble-sender";
        sender.textContent = isUser ? "Bạn" : "Trợ lý Helpdesk";

        const content = document.createElement("div");
        content.textContent = msg.content_text || "";

        bubble.append(sender, content);
        messagesLog.appendChild(bubble);
    };

    // UI-01: Handle Create Session Form Submit
    createForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (creating || loggedOut) return;

        const roomId = roomSelect.value;
        const description = descInput.value.trim();
        const equipmentId = equipmentInput.value;

        if (!roomId) {
            createError.hidden = false;
            createError.textContent = "Vui lòng chọn phòng học gặp sự cố.";
            roomSelect.focus();
            return;
        }

        if (!description) {
            createError.hidden = false;
            createError.textContent = "Vui lòng nhập mô tả sự cố ban đầu.";
            descInput.focus();
            return;
        }
        if ([...description].length > 1000) {
            createError.hidden = false;
            createError.textContent = "Mô tả tối đa 1000 ký tự.";
            return;
        }
        const payload = {
            room_id: parseInt(roomId, 10),
            equipment_id: equipmentId ? parseInt(equipmentId, 10) : null,
            description,
        };
        const serializedPayload = JSON.stringify(payload);
        if (pendingPayload && pendingPayload !== serializedPayload) {
            createError.hidden = false;
            createError.textContent = "Nội dung đã đổi trong khi lần gửi trước chưa được xác nhận. Khôi phục nội dung để thử lại; hoặc kiểm tra danh sách phiên rồi bấm Hủy để bắt đầu ý định mới.";
            return;
        }

        // Generate idempotency key if not already assigned for retry (M1B-02)
        if (!currentIdempotencyKey) {
            currentIdempotencyKey = generateUuid();
        }

        createError.hidden = true;
        creating = true;
        pendingPayload = serializedPayload;
        const revision = viewRevision;
        createSubmit.disabled = true;
        const originalBtnText = createSubmit.innerHTML;
        createSubmit.innerHTML = "<span>Đang lưu phiên…</span>";

        const res = await apiFetch("/support-sessions", {
            method: "POST",
            headers: {
                "Idempotency-Key": currentIdempotencyKey,
            },
            body: serializedPayload,
        });

        creating = false;
        createSubmit.disabled = false;
        createSubmit.innerHTML = originalBtnText;
        if (loggedOut) return;

        if (!res.ok) {
            let errorText = res.data?.error?.message || res.data?.message || "Chưa xác nhận được phiên đã lưu. Vui lòng thử lại với cùng nội dung.";
            const errors = res.data?.error?.details || res.data?.errors;
            if (errors) {
                const firstError = Object.values(errors)[0];
                if (firstError && firstError.length) errorText = firstError[0];
            }
            if (res.status === 422 && res.data?.error?.code !== "IDEMPOTENCY_KEY_REUSED") {
                currentIdempotencyKey = null;
                pendingPayload = null;
            }
            createError.hidden = false;
            createError.textContent = errorText;
            // Keep currentIdempotencyKey for retry!
            return;
        }

        // Success: clear key and inputs
        currentIdempotencyKey = null;
        pendingPayload = null;
        descInput.value = "";
        equipmentInput.value = "";

        const newSession = res.data?.data;
        if (revision !== viewRevision) return;
        if (newSession && newSession.id) {
            openSessionDetail(newSession.id);
        } else {
            showView("list");
        }
    });

    createCancel.addEventListener("click", () => {
        if (creating) return;
        currentIdempotencyKey = null;
        pendingPayload = null;
        showView("list");
    });

    backToListBtn.addEventListener("click", () => {
        showView("list");
    });

    // UI-03: Direct Help Button (Handoff to traditional form)
    directHelpBtn.addEventListener("click", () => {
        if (!activeSession) return;

        // Let the existing room selector synchronize its own hidden fields and QR UI.
        document.dispatchEvent(new CustomEvent("helpdesk:select-room", {
            detail: { roomId: activeSession.room_id },
        }));

        // Fill portal traditional form
        if (portalDescription && activeSession.initial_description) {
            portalDescription.value = activeSession.initial_description;
        }
        if (portalTitle) {
            portalTitle.value = activeSession.equipment_label
                ? `Sự cố ${activeSession.equipment_label} tại phòng ${activeSession.room_code || activeSession.room_label || ""}`
                : `Sự cố kỹ thuật tại phòng ${activeSession.room_code || activeSession.room_label || ""}`;
        }

        // Close support panel
        if (typeof panel.hidePopover === "function") {
            try { panel.hidePopover(); } catch { panel.classList.remove("is-open"); }
        } else {
            panel.classList.remove("is-open");
        }

        if (reportFeedback) {
            reportFeedback.hidden = false;
            reportFeedback.textContent = `Đã điền nội dung phiên hỗ trợ (Phòng ${activeSession.room_code || activeSession.room_label}) vào form. Hãy kiểm tra phòng, danh mục và bấm gửi báo cáo. Chưa tạo phiếu hoặc thông báo kỹ thuật viên.`;
        }

        if (portalTitle) portalTitle.focus();
    });

    // Pagination handlers
    pagePrev.addEventListener("click", () => {
        if (currentPage > 1) {
            loadSessionList(currentPage - 1);
        }
    });

    pageNext.addEventListener("click", () => {
        if (currentPage < totalPages) {
            loadSessionList(currentPage + 1);
        }
    });

    refreshListBtn.addEventListener("click", () => {
        loadSessionList(currentPage);
    });

    listRetry.addEventListener("click", () => {
        loadSessionList(currentPage);
    });

    emptyCreateBtn.addEventListener("click", () => {
        showView("create");
    });

    navList.addEventListener("click", () => {
        showView("list");
    });

    navCreate.addEventListener("click", () => {
        showView("create");
    });
    navChat?.addEventListener("click", () => {
        showView("chat");
        document.getElementById("portal-chat-input")?.focus();
    });

    // Panel open/close
    const nativePopover = typeof panel.hidePopover === "function" && "popoverTargetElement" in launch;

    const togglePanel = (open) => {
        launch.setAttribute("aria-expanded", String(open));
        if (open && !currentView) {
            // Restore active session or show list
            restoreActiveSession();
        }
    };

    if (nativePopover) {
        panel.addEventListener("toggle", (e) => {
            const isOpen = panel.matches(":popover-open");
            togglePanel(isOpen);
        });
    } else {
        panel.removeAttribute("popover");
        launch.addEventListener("click", () => {
            const open = panel.classList.toggle("is-open");
            togglePanel(open);
        });
        closeBtn.addEventListener("click", (e) => {
            e.preventDefault();
            panel.classList.remove("is-open");
            launch.setAttribute("aria-expanded", "false");
            launch.focus();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key !== "Escape" || !panel.classList.contains("is-open")) return;
            panel.classList.remove("is-open");
            launch.setAttribute("aria-expanded", "false");
            launch.focus();
        });
    }

    launch.hidden = false;

    // M1B-03: Restore session on reload & Cleanup on logout / account change
    const restoreActiveSession = () => {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                showView("list");
                return;
            }
            const data = JSON.parse(raw);
            if (data && data.userId === currentUserId && data.sessionId) {
                openSessionDetail(data.sessionId);
            } else {
                // Different user or invalid: clean up
                sessionStorage.removeItem(STORAGE_KEY);
                showView("list");
            }
        } catch {
            clearStoredSession();
            showView("list");
        }
    };

    // Account change check on page init (M1B-03)
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw) {
            const data = JSON.parse(raw);
            if (data && data.userId !== currentUserId) {
                // Account changed: clean up immediately
                sessionStorage.removeItem(STORAGE_KEY);
            }
        }
    } catch {
        clearStoredSession();
    }

    // Cleanup on logout form submit (M1B-03)
    document.addEventListener("submit", (e) => {
        const logoutForm = e.target.closest("form[data-logout-form]");
        if (logoutForm) {
            loggedOut = true;
            viewRevision++;
            listRevision++;
            activeSession = null;
            pendingPayload = null;
            currentIdempotencyKey = null;
            clearStoredSession();
            messagesLog.replaceChildren();
            sessionItems.replaceChildren();
            descInput.value = "";
            equipmentInput.value = "";
            roomSelect.value = "";
            detailRoom.textContent = "";
            detailEquipment.textContent = "";
            detailTime.textContent = "";
            createError.textContent = "";
            globalStatus.hidden = true;
            directHelpBtn.disabled = true;
            panel.classList.remove("is-open");
            if (nativePopover) { try { panel.hidePopover(); } catch {} }
        }
    });

    // If page reloaded and had active session, restore it automatically
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw) {
            const data = JSON.parse(raw);
            if (data && data.userId === currentUserId && data.sessionId) {
                // Preload active session
                openSessionDetail(data.sessionId);
            }
        }
    } catch {}
})();
