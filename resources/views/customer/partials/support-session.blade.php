<button id="support-session-launch" class="portal-chat-launch support-session-launch" type="button" popovertarget="support-session-panel" aria-controls="support-session-panel" aria-expanded="false" hidden>
    <span class="portal-chat-icon" aria-hidden="true">···</span> Hỗ trợ
</button>

<section id="support-session-panel" class="portal-chat support-session-panel" popover="auto" aria-labelledby="support-session-title">
    <header class="support-session-header">
        <div class="support-header-titles">
            <span class="support-header-kicker">HỖ TRỢ KỸ THUẬT</span>
            <h2 id="support-session-title">Hỗ trợ kỹ thuật</h2>
        </div>
        <button id="support-session-close" type="button" popovertarget="support-session-panel" popovertargetaction="hide" aria-label="Đóng bảng hỗ trợ">×</button>
    </header>

    <nav class="support-panel-nav" aria-label="Chuyển chế độ xem">
        <button id="support-nav-list" type="button" class="support-nav-tab is-active" aria-pressed="true">Phiên đã lưu</button>
        <button id="support-nav-create" type="button" class="support-nav-tab" aria-pressed="false">＋ Tạo phiên</button>
        <button id="support-nav-chat" type="button" class="support-nav-tab" aria-pressed="false">Hỏi AI</button>
    </nav>

    <div id="support-global-status" class="support-status-bar" role="status" aria-live="polite" hidden></div>

    <!-- View 1: Danh sách phiên (UI-02) -->
    <div id="support-view-list" class="support-view">
        <div class="support-view-heading">
            <span id="support-session-count" class="support-count-badge">Đang tải…</span>
            <button id="support-btn-refresh-list" type="button" class="support-icon-button" aria-label="Làm mới danh sách">↻</button>
        </div>
        <div id="support-list-loading" class="support-state-box" role="status" hidden>
            <p>Đang tải danh sách phiên hỗ trợ…</p>
        </div>
        <div id="support-list-error" class="support-state-box is-error" hidden>
            <p id="support-list-error-msg">Không thể tải danh sách phiên hỗ trợ.</p>
            <button id="support-list-retry" type="button" class="support-pill-btn">Thử lại</button>
        </div>
        <div id="support-list-empty" class="support-state-box is-empty" hidden>
            <strong>Bạn chưa có phiên hỗ trợ nào</strong>
            <p>Bắt đầu phiên mới để lưu và nhận hỗ trợ kỹ thuật phòng học.</p>
            <button id="support-empty-create-btn" type="button" class="support-pill-btn">Bắt đầu phiên đầu tiên</button>
        </div>
        <ul id="support-session-items" class="support-session-list" role="list"></ul>
        <div id="support-pagination" class="support-pagination" hidden>
            <button id="support-page-prev" type="button" class="support-page-nav" disabled aria-label="Trang trước">← Trước</button>
            <span id="support-page-info" class="support-page-label">Trang 1 / 1</span>
            <button id="support-page-next" type="button" class="support-page-nav" disabled aria-label="Trang sau">Sau →</button>
        </div>
    </div>

    <!-- View 2: Tạo phiên mới (UI-01) -->
    <div id="support-view-create" class="support-view" hidden>
        <form id="support-create-form" class="support-form" novalidate>
            <div class="support-form-field">
                <label for="support-room-select">Phòng học <b>*</b></label>
                <select id="support-room-select" name="room_id" required>
                    <option value="">-- Chọn phòng học gặp sự cố --</option>
                    @foreach($buildings as $b)
                        <optgroup label="Tòa {{ $b->name }} ({{ $b->code }})">
                            @foreach($b->rooms as $r)
                                <option value="{{ $r->id }}" data-equipment="{{ $r->equipment->map->only(['id', 'name'])->values()->toJson(JSON_UNESCAPED_UNICODE) }}" data-room-code="{{ $r->code }}" data-building-code="{{ $b->code }}">{{ $r->name }} ({{ $r->code }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="support-form-field">
                <label for="support-equipment-input">Thiết bị <span>(không bắt buộc)</span></label>
                <select id="support-equipment-input" name="equipment_id">
                    <option value="">-- Chưa xác định thiết bị --</option>
                </select>
            </div>

            <div class="support-form-field">
                <label for="support-desc-input">Mô tả sự cố ban đầu <b>*</b></label>
                <textarea id="support-desc-input" name="description" rows="4" maxlength="1000" required placeholder="Mô tả cụ thể dấu hiệu hư hỏng bạn quan sát được (ví dụ: máy chiếu bật lên nhưng không có tín hiệu hình ảnh)…"></textarea>
                <small class="support-field-hint">Tối đa 1000 ký tự. Tránh nhập mật khẩu, OTP hoặc thông tin riêng tư.</small>
            </div>

            <div id="support-create-error" class="support-inline-error" role="alert" hidden></div>

            <div class="support-form-footer">
                <button id="support-create-submit" type="submit" class="support-submit-btn">
                    <span>Bắt đầu phiên hỗ trợ</span>
                </button>
                <button id="support-create-cancel" type="button" class="support-cancel-btn">Hủy</button>
            </div>
        </form>
    </div>

    <!-- View 3: Chi tiết phiên hỗ trợ (UI-03) -->
    <div id="support-view-detail" class="support-view" hidden>
        <div class="support-detail-bar">
            <button id="support-back-to-list" type="button" class="support-text-btn">← Danh sách phiên</button>
            <button id="support-direct-help" type="button" class="support-handoff-btn" title="Điền thông tin sự cố này vào form báo cáo của nhà trường" disabled>Hỗ trợ trực tiếp (Điền form)</button>
        </div>

        <article class="support-session-summary-card">
            <div class="support-summary-header">
                <h3 id="support-detail-room" class="support-summary-title">Phòng: --</h3>
                <span id="support-detail-status-badge" class="support-badge status-collecting">Đang thu thập</span>
            </div>
            <p id="support-detail-equipment" class="support-summary-sub">Thiết bị: Chưa xác định</p>
            <time id="support-detail-time" class="support-summary-time">Thời gian: --</time>
        </article>

        <div id="support-messages-loading" class="support-state-box" role="status" hidden>
            <p>Đang tải lịch sử phiên…</p>
        </div>

        <div id="support-messages-log" class="support-messages-scroll" role="log" tabindex="0" aria-label="Lịch sử tin nhắn">
            <!-- Messages rendered by JS -->
        </div>

        <section class="support-stage-card" aria-label="Thông báo tính năng">
            <span class="support-stage-icon" aria-hidden="true">ℹ️</span>
            <div class="support-stage-copy">
                <strong>Phiên hỗ trợ và báo cáo sự cố</strong>
                <p>Trợ lý AI đang được tích hợp theo lộ trình. Nút <b>Hỗ trợ trực tiếp</b> điền thông tin vào form báo sự cố; bạn cần kiểm tra và gửi form để tạo phiếu.</p>
            </div>
        </section>
    </div>
    @include('customer.partials.portal-chat', ['embedded' => true])
</section>
