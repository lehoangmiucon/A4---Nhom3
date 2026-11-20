# CR-10 — phân trang hội thoại bị kẹt spinner

Ngày 20/11/2026. DONE_CODE, chưa xác nhận DEPLOYED/RELEASED. Truy vết AI-FR-002/014, AI-NFR-008; UI-03; TC-003/004/018. Giữ contract phân trang và quyền chủ phiên hiện có.

## Nguyên nhân và sửa

`public/shared/js/page-feedback.js` đặt timer 5000ms cho mọi role=status, sau đó fade và remove sau 250ms. Các trạng thái lâu dài trong chat cũng bị xóa. `loadHistory` bật busy trên nút Tải lại trước khi truy cập `conversation-history-status.textContent`; nếu node đã bị xóa thì lỗi trước cả fetch, nên spinner không được tắt. Cùng nguyên nhân còn ảnh hưởng trạng thái gửi tin/receipt/chờ AI.

Thêm marker `data-auto-dismiss="false"` trên section chat và guard `closest` trong autoDismiss, áp dụng cả node khởi tạo lẫn node động được MutationObserver phát hiện. Vẫn tự ẩn thông báo tạm ngoài section. Đổi version script feedback trong customer topbar để tải bản sửa. Không thay API hoặc dữ liệu.

## Evidence

- RED: `node --test tests/js/support-conversation.test.mjs`: 21 passed / 1 failed; test chạy đồng thời script feedback và hội thoại, fake clock tiến 6000ms; thất bại vì ba trạng thái chat đã bị remove.
- GREEN ban đầu: `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs`: 52 passed; test chuyển Sau/Trước sau 6 giây, kiểm tra số trang, nhãn/nút/busy, và toast ngoài chat vẫn bị xóa.
- `php artisan test --compact --filter=SupportSessionUiTest`: 7 passed / 49 assertions, 1.18s. SQLite :memory: theo phpunit.xml; không thay backend.
- Thêm guard lỗi mạng sau khi timer feedback hết hạn: nhả busy, hiện lỗi, Tải lại thành công. Kết quả cuối `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs`: **53 passed / 0 failed**. `git diff --check`: đạt, chỉ có cảnh báo CRLF/LF ở các thay đổi đã có.

Không gọi model/API trả phí, không migration/ghi database đang dùng. Không chạy toàn PHP suite vì chỉ thay JavaScript/Blade. Công cụ CUA báo native pipe unavailable, nên chưa xác nhận trực tiếp browser thật hoặc screen reader. Test VM dùng fake DOM/timer/API; không coi đây là UAT browser/staging.
