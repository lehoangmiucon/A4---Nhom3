# CR-05 — gửi tin tức thời và ba chấm chờ AI

Ngày 20/11/2026. DONE_CODE, chưa RELEASED. Task chính ở todo.md. Phạm vi UI-03, AI-FR-002/014/017, AI-NFR-008; UC-02/12, TC-007/018/021/025. Không thay API, provider hoặc nghiệp vụ tạo phiếu.

## Thay đổi

- Tạo bong bóng tin người dùng ngay trước request, nhãn “Đang gửi…”, xóa composer và cuộn log xuống cuối. Nội dung dùng text node, không innerHTML.
- Thêm bong bóng trợ lý “AI đang suy nghĩ” với ba chấm CSS animation lệch nhịp. Report/resolve có nhãn tương ứng; role=status, chấm aria-hidden, prefers-reduced-motion tắt chuyển động.
- Dừng hiệu ứng khi nhận kết quả hoặc lỗi. Lịch sử từ server thay toàn bộ preview khi có snapshot. Khi chưa xác nhận được, giữ tin và đổi nhãn, cho retry cùng key/payload mà không nhân đôi. Snapshot 503/202 đã chứa tin được ghi nhận không bị thêm lại ở retry.
- Lỗi 409/422 phục hồi bản nháp, bỏ preview chưa nhận và tải lại phiên nếu đã có. Không xóa nội dung người dùng đã gõ sau lần lỗi khi retry cũ thành công. Logout xóa UI và phản hồi muộn không được khôi phục nội dung.
- Bump phiên bản CSS/JS trên portal sang 20261120-cr5.

## Kiểm chứng

RED: `node --test tests/js/support-conversation.test.mjs` sau thêm bốn ca đầu: **2 failed / 10 passed**, lỗi không có bong bóng trước khi server phản hồi và không có tin để gắn trạng thái khi mạng lỗi. Hai ca validation/logout ban đầu đã đạt nên chỉ là guard hồi quy.

GREEN sau triển khai: **12 passed** trên file support-conversation. Bổ sung ca report pending/retry với snapshot server và bảo toàn bản nháp gõ sau lỗi. Kết quả cuối `node --test tests/js/*.test.mjs`: **39 passed**, gồm 14 ca hội thoại.

`php artisan test --compact --filter=SupportSessionUiTest`: **7 passed / 49 assertions**, 1,26 giây. Chạy ngoài sandbox theo cơ chế xét duyệt do lỗi Windows is_readable đã ghi ở CR-04. Không đổi backend nên không chạy lại toàn PHP suite; baseline gần nhất 198 passed / 2427 assertions thuộc CR-04, không coi là một lần chạy mới.

`git diff --check`: đạt, có cảnh báo CRLF/LF từ thay đổi có sẵn.

Smoke Chrome headless bằng `node storage/framework/testing/cr05/browser-smoke.mjs`, fixture lấy markup partial hiện tại, CSS/JS hiện tại, chỉ fake API/lịch sử trong RAM. Ba lần chạy đạt:

| Kích thước cửa sổ yêu cầu | innerWidth thực | Reduced motion | Kết quả |
| --- | --- | --- | --- |
| 1440 × 1000 | 1422 | false | PASS |
| 360 × 1000 | 504 | false | PASS |
| 360 × 1000 | 504 | true | PASS |

Chrome headless giới hạn cửa sổ tối thiểu nên hai ca hẹp thực tế là 504px, **không tuyên bố đã test viewport 360px**. Các ca kiểm tra bong bóng tức thời, ba chấm/animation computed style, composer rỗng, phản hồi xóa typing, không trùng tin và không tràn ngang. Đã xem ảnh chụp desktop để kiểm tra bố cục. Ảnh/harness nằm trong storage/framework/testing/cr05 (ignored). Chạy Chrome trong sandbox bị chặn khởi động; chạy cùng harness ngoài sandbox được xét duyệt và đạt, không dùng profile/tài khoản cá nhân.

## Review và giới hạn

Client vẫn chỉ gửi tin mới và payload cũ; các node preview/chờ không được gửi lên API. Replay/idempotency giữ nguyên. Hiệu ứng dừng khi request thất bại, không chạy vô hạn ngoài deadline 35 giây đã có; lỗi không giả thành công. CSS chỉ dùng namespace conversation, form thủ công được giữ qua test. Không gọi model thật hoặc ghi DB local đang dùng. Chưa smoke đăng nhập/CSRF hoặc screen reader thực; chưa UAT/staging/production.
