(() => {
    const params = new URLSearchParams(window.location.search);
    const config = window.HELPDESK_CONFIG || {};
    const appUrl = (config.appUrl || "/").replace(/\/+$/, "");
    const buildingSelect = document.querySelector("#building");
    const roomSelect = document.querySelector("#room");
    const buildingCode = document.querySelector("#building-code");
    const roomId = document.querySelector("#room-id");
    const roomCode = document.querySelector("#room-code");
    const roomContext = document.querySelector("#room-context");
    const banner = document.querySelector("#qr-detected");
    const locationName = document.querySelector("#qr-location-name");
    const manualNotice = document.querySelector("#manual-location-notice");
    const roomInstruction = document.querySelector("#room-instruction");
    const locationCard = document.querySelector(".report-location");
    const form = document.querySelector(".report-form");

    if (!buildingSelect || !roomSelect || !banner || !locationCard || !form)
        return;

    const addOption = (select, value, label) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = label;
        select.append(option);
    };

    const setPlaceholder = (select, text) => {
        select.replaceChildren();
        addOption(select, "", text);
    };

    const showRoomMessage = (message, isError = false) => {
        roomContext.textContent = message;
        roomContext.hidden = !message;
        roomContext.classList.toggle("is-error", isError);
    };

    const updateManualNotice = (title, detail) => {
        if (!manualNotice) return;
        manualNotice.querySelector("strong").textContent = title;
        manualNotice.querySelector("small").textContent = detail;
    };

    let rooms = [];
    const roomsUrl = new URL(`${appUrl}/api/rooms`, window.location.origin);

    const fillRooms = (buildingCodeValue, selectedRoomId = null) => {
        setPlaceholder(roomSelect, "Chọn phòng học");
        roomId.value = "";
        roomCode.value = "";

        const matches = rooms.filter(
            (room) => room.building?.code === buildingCodeValue,
        );
        matches.forEach((room) =>
            addOption(
                roomSelect,
                String(room.id),
                `${room.code} · ${room.name}`,
            ),
        );
        roomSelect.disabled = matches.length === 0;

        if (!matches.length) {
            setPlaceholder(roomSelect, "Tòa nhà chưa có phòng");
            showRoomMessage(
                "Tòa nhà này chưa có phòng học trong hệ thống.",
                true,
            );
            return;
        }

        showRoomMessage("");
        if (
            selectedRoomId !== null &&
            matches.some((room) => String(room.id) === String(selectedRoomId))
        ) {
            roomSelect.value = String(selectedRoomId);
            syncSelectedRoom();
        }
    };

    const syncSelectedRoom = () => {
        const selectedRoom = rooms.find(
            (room) => String(room.id) === roomSelect.value,
        );
        roomId.value = selectedRoom ? String(selectedRoom.id) : "";
        roomCode.value = selectedRoom
            ? selectedRoom.qr_code || selectedRoom.code
            : "";
        showRoomMessage(selectedRoom ? `Mã phòng: ${selectedRoom.code}` : "");
        updateManualNotice(
            selectedRoom
                ? "Đã chọn vị trí báo cáo"
                : "Đang chọn vị trí thủ công",
            selectedRoom
                ? `${selectedRoom.building?.name || ""} · ${selectedRoom.name || selectedRoom.code}`
                : "Chọn tòa nhà rồi chọn phòng học để tiếp tục.",
        );
    };

    buildingSelect.addEventListener("change", () => {
        buildingCode.value = buildingSelect.value;
        updateManualNotice(
            "Đang chọn vị trí thủ công",
            "Chọn phòng học thuộc tòa nhà này.",
        );
        fillRooms(buildingSelect.value);
    });

    roomSelect.addEventListener("change", syncSelectedRoom);

    const lockQrLocation = (room) => {
        buildingSelect.value = room.building.code;
        buildingCode.value = room.building.code;
        fillRooms(room.building.code, room.id);
        buildingSelect.disabled = true;
        roomSelect.disabled = true;
        buildingSelect.classList.add("qr-prefilled");
        roomSelect.classList.add("qr-prefilled");

        const roomName = room.name || room.code;
        locationName.textContent = `${room.building.name} · ${roomName}`;
        showRoomMessage(
            `Mã phòng ${room.code} · Vị trí được nhận diện từ mã QR`,
        );
        banner.hidden = false;
        if (manualNotice) manualNotice.hidden = true;
        locationCard.classList.add("is-qr");
        locationCard.querySelector("h3").textContent =
            "Vị trí nhận diện từ mã QR";
        locationCard.querySelector(".report-location-heading p").textContent =
            "Tòa nhà và phòng đã được điền tự động.";
        if (roomInstruction)
            roomInstruction.textContent =
                "Vị trí đã được điền và xác nhận từ mã QR.";
    };

    fetch(roomsUrl, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
    })
        .then((response) => {
            if (!response.ok)
                throw new Error("Không tải được danh sách phòng.");
            return response.json();
        })
        .then((payload) => {
            rooms = Array.isArray(payload.data) ? payload.data : [];
            const roomValue =
                params.get("room_code") ||
                params.get("room") ||
                params.get("room_id");
            const qrRoom =
                roomValue &&
                rooms.find(
                    (room) =>
                        String(room.id) === roomValue ||
                        room.code === roomValue ||
                        room.qr_code === roomValue,
                );

            if (qrRoom?.building) {
                lockQrLocation(qrRoom);
                return;
            }

            buildingSelect.disabled = false;
            roomSelect.disabled = true;
            if (manualNotice) manualNotice.hidden = false;
            if (roomValue) {
                showRoomMessage(
                    "Không tìm thấy phòng từ mã QR. Vui lòng chọn lại vị trí trong danh sách.",
                    true,
                );
                updateManualNotice(
                    "Không nhận diện được mã QR",
                    "Hãy chọn tòa nhà và phòng từ danh sách.",
                );
                if (roomInstruction)
                    roomInstruction.textContent =
                        "Mã QR không khớp với phòng nào. Bạn có thể tiếp tục bằng cách chọn vị trí bên dưới.";
            } else {
                updateManualNotice(
                    "Đang chọn vị trí thủ công",
                    "Chọn tòa nhà rồi chọn phòng học.",
                );
                if (roomInstruction)
                    roomInstruction.textContent =
                        "Nếu mở biểu mẫu từ mã QR của phòng, tòa nhà và phòng sẽ tự điền.";
            }
        })
        .catch(() => {
            buildingSelect.disabled = true;
            roomSelect.disabled = true;
            showRoomMessage(
                "Không tải được danh sách phòng. Vui lòng tải lại trang hoặc báo quản trị viên.",
                true,
            );
            updateManualNotice(
                "Chưa tải được danh sách phòng",
                "Hãy tải lại trang để chọn vị trí báo cáo.",
            );
        });
})();
