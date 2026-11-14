<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#f3f7fb">
    <title>Gửi báo cáo sự cố · Helpdesk trường học</title>
    <link rel="stylesheet" href="{{ url('/customer/assets/css/customer.css?v=20261104-dashboard2') }}">
    <link rel="stylesheet" href="{{ url('/customer/assets/css/account.css?v=20261103-responsive1') }}">
    <link rel="stylesheet" href="{{ url('/customer/assets/css/portal.css?v=20261106-cleanup1') }}">
    <link rel="stylesheet" href="{{ url('/admin/assets/css/role-refresh.css?v=20261110-custom-select1') }}">
</head>
<body class="report-portal customer-role-page role-page">
    @include('customer.partials.topbar')

    <main class="report-main">
        <section class="report-hero">
            <div class="report-hero-copy">
                <span class="report-eyebrow"><i></i> HỖ TRỢ KỸ THUẬT NHÀ TRƯỜNG</span>
                <h1>Báo cáo sự cố <span>nhanh chóng</span></h1>
                <p>Cho đội ngũ kỹ thuật biết vấn đề đang xảy ra. Nhà trường sẽ tiếp nhận và cập nhật tiến độ để bạn theo dõi.</p>
            </div>
            <div class="report-flow" aria-label="Các bước gửi báo cáo">
                <div><span>01</span><strong>Mô tả sự cố</strong></div>
                <b aria-hidden="true"></b>
                <div><span>02</span><strong>Gửi báo cáo</strong></div>
                <b aria-hidden="true"></b>
                <div><span>03</span><strong>Theo dõi xử lý</strong></div>
            </div>
        </section>

        <section class="report-card">
            <div class="report-card-heading">
                <div class="report-heading-icon" aria-hidden="true">＋</div>
                <div>
                    <span class="report-step-label">BÁO CÁO MỚI</span>
                    <h2>Thông tin sự cố</h2>
                    <p>Điền các thông tin dưới đây để gửi phiếu đến quản lý.</p>
                </div>
                <span class="required-note"><b>*</b> Thông tin bắt buộc</span>
            </div>

            <form class="report-form" action="#" method="post" enctype="multipart/form-data" data-api-endpoint="/api/tickets" data-success-redirect="/customer/tickets" data-feedback-variant="success" data-feedback-position="top">
                <div class="report-fields">
                    <div class="report-field report-field-wide">
                        <label for="phone">Số điện thoại liên hệ <span>(không bắt buộc)</span></label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="Ví dụ: 0901 234 567">
                    </div>
                </div>

                <section class="report-location" aria-labelledby="location-heading">
                    <div class="report-location-heading">
                        <span class="location-mode-icon" aria-hidden="true">⌖</span>
                        <div>
                            <h3 id="location-heading">Chọn vị trí phòng học</h3>
                            <p>Quét mã QR tại phòng để điền vị trí tự động, hoặc chọn tòa nhà và phòng trong danh sách.</p>
                        </div>
                    </div>
                    <div class="qr-room-notice" id="qr-detected" role="status" hidden>
                        <span class="qr-notice-icon" aria-hidden="true">⌖</span>
                        <span><strong>Đã nhận diện phòng từ mã QR</strong><small id="qr-location-name"></small></span>
                        <span class="qr-verified">Đã xác nhận</span>
                    </div>
                    <p class="room-instruction" id="room-instruction">Quét mã QR tại phòng để tự điền vị trí. Nếu chưa quét mã, hãy chọn tòa nhà và phòng bên dưới.</p>
                    <div class="report-fields report-location-fields">
                        <div class="report-field">
                            <label for="building">Tòa nhà <b>*</b></label>
                            <select id="building" name="building" required disabled>
                                <option value="">Đang tải danh sách tòa nhà…</option>
                                @foreach($buildings as $building)
                                    <option value="{{ $building->code }}">{{ $building->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="building-code" name="building_code">
                        </div>
                        <div class="report-field">
                            <label for="room">Phòng học <b>*</b></label>
                            <select id="room" name="room" required disabled><option value="">Chọn tòa nhà trước</option></select>
                            <input type="hidden" id="room-id" name="room_id">
                            <input type="hidden" id="room-code" name="room_code">
                            <small id="room-context" class="room-context" hidden></small>
                        </div>
                    </div>
                </section>

                <div class="form-section-title report-section-spaced"><span>2</span><div><strong>Nội dung sự cố</strong><small>Chọn đúng danh mục và mô tả rõ dấu hiệu gặp phải.</small></div></div>
                <div class="report-fields report-issue-fields">
                    <div class="report-field report-field-wide">
                        <label for="category_id">Danh mục sự cố <b>*</b></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Chọn danh mục lỗi</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="report-field report-field-wide">
                        <label for="title">Tiêu đề sự cố <b>*</b></label>
                        <input id="title" name="title" maxlength="180" placeholder="Ví dụ: Máy chiếu không lên hình" required>
                    </div>
                    <div class="report-field report-field-wide">
                        <label for="description">Mô tả chi tiết <b>*</b></label>
                        <textarea id="description" name="description" maxlength="5000" placeholder="Mô tả dấu hiệu lỗi, thời điểm phát sinh và thông tin giúp nhân viên kỹ thuật kiểm tra…" required></textarea>
                        <small class="field-hint">Không nhập mật khẩu hoặc thông tin cá nhân nhạy cảm.</small>
                    </div>
                    <div class="report-field report-field-wide">
                        <label for="priority">Mức độ ưu tiên</label>
                        <select id="priority" name="priority">
                            <option value="low">Thấp — ảnh hưởng nhẹ</option>
                            <option value="normal" selected>Bình thường — ảnh hưởng một thiết bị</option>
                            <option value="high">Cao — ảnh hưởng cả lớp hoặc phòng</option>
                            <option value="urgent">Khẩn cấp — nguy cơ an toàn, cần xử lý sớm</option>
                        </select>
                    </div>
                    <div class="report-field report-field-wide">
                        <label for="attachment">Ảnh minh họa <span>(không bắt buộc, tối đa 5 ảnh)</span></label>
                        <input id="attachment" name="attachments[]" type="file" accept="image/*" multiple>
                        <small class="field-hint">Ảnh giúp nhân viên kỹ thuật xác định tình trạng nhanh hơn. Mỗi ảnh tối đa 5 MB.</small>
                        <p id="attachment-feedback" class="attachment-feedback" role="status" aria-live="polite" hidden></p>
                        <div id="attachment-preview" class="attachment-preview" aria-live="polite" hidden></div>
                    </div>
                </div>

                <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                <label class="report-consent"><input type="checkbox" required><span>Tôi xác nhận thông tin đã cung cấp là chính xác.</span></label>
                <div class="report-submit-row">
                    <p>Phiếu sẽ ở trạng thái <strong>Chờ tiếp nhận</strong> sau khi gửi.</p>
                    <button class="report-submit" type="submit"><span>Gửi báo cáo sự cố</span><b aria-hidden="true">→</b></button>
                </div>
            </form>
        </section>

        <footer class="report-footer">Helpdesk trường học <span>·</span> Hỗ trợ thiết bị và cơ sở vật chất</footer>
    </main>
    <script src="{{ url('/customer/assets/js/portal-qr.js?v=20261105-building-room2') }}"></script>
    <script src="{{ url('/customer/assets/js/portal-attachments.js?v=20261104-preview1') }}"></script>
    <script src="{{ url('/admin/assets/js/api-client.js?v=20261113-duplicate-red1') }}"></script>
</body>
</html>
