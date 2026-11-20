<section id="report-ai-view" class="report-card conversation-card" aria-labelledby="conversation-heading" data-auto-dismiss="false" hidden>
    <div class="report-card-heading">
        <div>
            <span class="report-step-label">NHỜ AI HỖ TRỢ</span>
            <h2 id="conversation-heading">Cùng tìm cách khắc phục sự cố</h2>
            <p>Cho biết phòng và công việc cần hỗ trợ trước khi bắt đầu. Nếu cần kỹ thuật viên, nói “Gửi báo cáo giúp tôi” ngay tại đây.</p>
        </div>
        <button id="conversation-new" class="conversation-secondary" type="button">Cuộc trò chuyện mới</button>
    </div>

    <div class="conversation-layout">
        <aside class="conversation-history" aria-label="Hội thoại đã lưu">
            <div class="conversation-history-heading"><h3>Hội thoại của bạn</h3><button id="conversation-refresh" class="conversation-secondary" type="button">Tải lại</button></div>
            <p id="conversation-history-status" role="status"></p>
            <ul id="conversation-history-list"></ul>
            <div class="conversation-history-pages">
                <button id="conversation-prev" class="conversation-secondary" type="button" disabled>Trước</button>
                <span id="conversation-page">Trang 1</span>
                <button id="conversation-next" class="conversation-secondary" type="button" disabled>Sau</button>
            </div>
        </aside>

        <div class="conversation-main">
            <p id="conversation-context" class="conversation-context">Cần biết phòng, loại công việc hoặc thiết bị và mô tả vấn đề trước khi hướng dẫn.</p>
            <div id="conversation-log" class="conversation-log" role="log" aria-label="Nội dung trao đổi" tabindex="0"></div>
            <div id="conversation-report" class="conversation-receipt" role="status" hidden></div>
            <p id="conversation-status" class="conversation-status" role="status" aria-live="polite"></p>
            <button id="conversation-retry" class="conversation-secondary" type="button" hidden>Thử lại lần gửi trước</button>

            <details id="conversation-context-picker" class="conversation-context-picker" open>
                <summary>Phòng và công việc cần hỗ trợ</summary>
                <p>Chọn phòng, loại công việc hoặc thiết bị rồi mô tả vấn đề trong tin nhắn. Bạn cũng có thể nêu trực tiếp, ví dụ: “Máy chiếu phòng A203 không có hình”. Nếu còn thiếu, trợ lý sẽ hỏi thêm trước khi hướng dẫn.</p>
                <div class="report-fields">
                    <div class="report-field"><label for="conversation-room">Phòng học</label>
                        <select id="conversation-room"><option value="">Chưa chọn phòng</option>
                            @foreach($buildings as $building)
                                <optgroup label="{{ $building->name }}">
                                    @foreach($building->rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->code }} · {{ $room->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="report-field"><label for="conversation-category">Loại công việc / thiết bị</label>
                        <select id="conversation-category"><option value="">Chưa chọn danh mục</option>
                            @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </details>

            <form id="conversation-form" class="conversation-form">
                <label for="conversation-input">Tin nhắn của bạn</label>
                <textarea id="conversation-input" maxlength="1000" rows="3" placeholder="Ví dụ: Máy chiếu phòng A203 không có hình. Tôi cần hỗ trợ trình chiếu…" required aria-describedby="conversation-privacy"></textarea>
                <div class="conversation-actions">
                    <button id="conversation-send" class="conversation-primary" type="submit">Gửi tin nhắn</button>
                    <button id="conversation-escalate" class="conversation-secondary" type="button">Gửi báo cáo giúp tôi</button>
                    <button id="conversation-resolve" class="conversation-secondary" type="button">Đã khắc phục được</button>
                </div>
            </form>
            <p id="conversation-privacy" class="conversation-privacy">AI có thể nhầm. Chỉ thực hiện kiểm tra an toàn; không tháo hoặc tự sửa thiết bị. Hội thoại được lưu để hỗ trợ xử lý và có thể gửi tới dịch vụ AI. Không nhập mật khẩu, OTP hoặc thông tin riêng tư.</p>
        </div>
    </div>
</section>
