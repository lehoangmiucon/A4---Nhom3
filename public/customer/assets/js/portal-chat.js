(() => {
    const panel = document.getElementById("portal-chat");
    if (!panel) return;
    const embedded = panel.dataset.embedded === "true";
    const hostPanel = embedded ? document.getElementById("support-session-panel") : panel;
    const launch = document.getElementById(embedded ? "support-session-launch" : "portal-chat-launch");
    const close = document.getElementById("portal-chat-close");
    const form = document.getElementById("portal-chat-form");
    const input = document.getElementById("portal-chat-input");
    const send = form.querySelector("button[type=submit]");
    const log = document.getElementById("portal-chat-log");
    const status = document.getElementById("portal-chat-status");
    const draftButton = document.getElementById("portal-chat-draft");
    const reportFeedback = document.getElementById("report-chat-feedback");
    const title = document.getElementById("title");
    const description = document.getElementById("description");
    const category = document.getElementById("category_id");
    const building = document.getElementById("building");
    const room = document.getElementById("room");
    const roomId = document.getElementById("room-id");
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const nativePopover = typeof hostPanel.hidePopover === "function" && "popoverTargetElement" in launch;
    const scrollToLatest = () => { log.scrollTop = log.scrollHeight; };
    const isOpen = () => nativePopover ? hostPanel.matches(":popover-open") : hostPanel.classList.contains("is-open");
    const hideChat = () => {
        if (nativePopover) hostPanel.hidePopover();
        else hostPanel.classList.remove("is-open");
        launch.setAttribute("aria-expanded", "false");
    };

    if (!embedded) {
        close.addEventListener("click", (event) => {
            event.preventDefault();
            hideChat();
            launch.focus();
        });

        if (nativePopover) {
            panel.addEventListener("toggle", () => {
                launch.setAttribute("aria-expanded", String(isOpen()));
                if (isOpen()) scrollToLatest();
            });
        } else {
            panel.removeAttribute("popover");
            launch.addEventListener("click", () => {
                const open = panel.classList.toggle("is-open");
                launch.setAttribute("aria-expanded", String(open));
                if (open) {
                    input.focus();
                    scrollToLatest();
                }
            });
            document.addEventListener("keydown", (event) => {
                if (event.key !== "Escape" || !isOpen()) return;
                const focusWasInside = panel.contains(document.activeElement);
                hideChat();
                if (focusWasInside) launch.focus();
            });
            document.addEventListener("click", (event) => {
                if (isOpen() && !panel.contains(event.target) && !launch.contains(event.target)) hideChat();
            });
        }
        launch.setAttribute("aria-expanded", "false");
        launch.hidden = false;
    }

    let history = [];
    let latestDraft = null;
    const lastFilled = new Map();
    let loggedOut = false;
    let pendingRequest = null;
    document.addEventListener("submit", (event) => {
        if (!event.target.closest("form[data-logout-form]")) return;
        loggedOut = true;
        pendingRequest?.abort();
        history = [];
        latestDraft = null;
        lastFilled.clear();
        log.replaceChildren();
        input.value = "";
        status.textContent = "";
        draftButton.hidden = true;
    });

    const append = (role, content) => {
        const message = document.createElement("p");
        message.className = "portal-chat-message";
        message.classList.toggle("is-user", role === "user");
        const speaker = document.createElement("span");
        speaker.className = "portal-chat-speaker";
        speaker.textContent = role === "user" ? "Bạn: " : "Trợ lý: ";
        message.append(speaker, content);
        log.append(message);
        scrollToLatest();
        return message;
    };

    const setStatus = (text) => {
        status.textContent = text;
    };

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const content = input.value.trim();
        if (!content || send.disabled || loggedOut) return;

        const userMessage = append("user", content);
        const messages = [...history, { role: "user", content }];
        input.value = "";
        input.focus();
        input.readOnly = true;
        send.disabled = true;
        draftButton.disabled = true;
        setStatus("Trợ lý đang trả lời…");

        try {
            pendingRequest = new AbortController();
            const response = await fetch(panel.dataset.endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf },
                body: JSON.stringify({ messages, room_id: roomId.value || null }),
                signal: pendingRequest.signal,
            });
            if (loggedOut) return;

            if ([401, 403, 419].includes(response.status)) {
                setStatus("Phiên đăng nhập không còn hợp lệ. Hãy đăng nhập lại để tiếp tục chat.");
            } else if (response.status === 429) {
                setStatus("Bạn gửi tin nhắn hơi nhanh. Hãy chờ một lúc rồi thử lại.");
            } else {
                const result = await response.json();
                if (loggedOut) return;
                if (response.ok && typeof result.reply === "string") {
                    append("assistant", result.reply);
                    history = [...messages, { role: "assistant", content: result.reply }].slice(-8);
                    latestDraft = result.draft;
                    draftButton.hidden = !latestDraft;
                    setStatus("");
                    return;
                }
                setStatus(response.status === 503 && result.message ? result.message : "Gửi tin nhắn thất bại. Hãy thử lại.");
            }
        } catch {
            if (loggedOut) return;
            setStatus("Gửi tin nhắn thất bại. Hãy thử lại.");
        } finally {
            pendingRequest = null;
            input.readOnly = false;
            send.disabled = false;
            draftButton.disabled = false;
            scrollToLatest();
        }

        userMessage.remove();
        input.value = content;
    });

    const hasOption = (select, value) => Array.from(select.options).some((option) => option.value === value);
    const change = (field) => field.dispatchEvent(new Event("change", { bubbles: true }));

    const fillField = (field, value) => {
        if (!value || (field.value.trim() && field.value !== lastFilled.get(field))) return false;
        field.value = value;
        lastFilled.set(field, field.value);
        change(field);
        return true;
    };

    const fillRoom = (location) => {
        if (!location || roomId.value || building.disabled || !hasOption(building, location.building_code)) return false;
        const previousBuilding = building.value;
        building.value = location.building_code;
        change(building);

        if (hasOption(room, String(location.id))) {
            room.value = String(location.id);
            change(room);
            return true;
        }
        building.value = previousBuilding;
        change(building);
        return false;
    };

    draftButton.addEventListener("click", () => {
        if (!latestDraft) return;

        const titleFilled = fillField(title, latestDraft.title);
        const descriptionFilled = fillField(description, latestDraft.description);
        const categoryValue = String(latestDraft.category_id ?? "");
        const categoryFilled = hasOption(category, categoryValue) && fillField(category, categoryValue);
        const roomFilled = fillRoom(latestDraft.room);

        const missing = [];
        if (!roomId.value) missing.push("phòng học");
        if (!category.value) missing.push("danh mục");
        const updated = titleFilled || descriptionFilled || categoryFilled || roomFilled;
        const summary = updated ? "Đã điền bản nháp vào phiếu." : "Phiếu đang giữ các thông tin bạn đã điền.";
        const review = missing.length ? ` Hãy chọn thêm ${missing.join(" và ")} rồi kiểm tra lại trước khi gửi.` : " Bạn có thể sửa thông tin trước khi gửi.";
        reportFeedback.hidden = false;
        reportFeedback.textContent = summary + review;

        hideChat();
        title.focus();
    });
})();
