# CR-07 — kéo dài phiên chat AI

Ngày 20/11/2026. DONE_CODE, chưa RELEASED. Task chính ở todo.md. Phạm vi AI-FR-002/004/017, AI-NFR-005/008; UC-02/12, TC-007/021/027 trong phạm vi giới hạn hiện có, chưa thay quota phân tán TARGET M4-02.

## Trước và sau

SupportConversationService trước sửa có trần cứng 5 command chat/phiên và 5/phút/tài khoản, 100 message/phiên; model chỉ nhận 9 message cuối. Đây là lý do phiên #7 đã dừng AI ở lượt thứ 6. Không có giới hạn thời gian mở tab gây ra hiện tượng đó.

Sau sửa, services.helpdesk_ai đọc ba env với mặc định 40 command chat/phiên, 12/phút/tài khoản và 200 tin (user+assistant). Clamp hiệu lực tối thiểu 1. .env.example có ba biến mới; không thay .env đang dùng vì mặc định đã đáp ứng. Đã bootstrap app local và xác nhận giá trị hiệu lực là 40/12/200; bootstrap/cache/config.php không tồn tại nên không cần xóa cache.

Tách thông báo giới hạn theo phút: chờ khoảng một phút rồi tiếp tục hoặc gửi báo cáo ngay. Giữ đường gửi report bypass giới hạn AI/trần tin, không gọi model khi report. Không đổi throttle HTTP endpoint hiện có hoặc thêm API trả phí.

History gửi model gồm hai user message đầu để giữ ngữ cảnh ban đầu và tối đa 9 message cuối. Unique theo id, thứ tự id tăng dần. Nếu tổng content vượt 6000 ký tự, loại các tin cũ ở giữa trước, bảo vệ hai lời báo đầu và tin hiện tại. Tối đa 11 message; không cắt từ bên trong nội dung hoặc tự viết lại history. Raw transcript vẫn lưu đầy đủ và formatter bàn giao vẫn dùng facts server. Đây là giới hạn ký tự; chưa đo token thực hoặc tăng num_ctx của Ollama.

## Kiểm chứng

- Baseline gần nhất: 201 passed / 2457 assertions.
- Thêm test default 40 lượt, hồi phục giới hạn theo phút và history dài. RED: 3 failed / 28 passed. Test 40 lượt ban đầu chạm HTTP throttle 30/phút, đã chỉnh fixture dùng fake clock tiến 61 giây sau mỗi 10 lượt để kiểm tra trần phiên độc lập với throttle. Test history so sánh với trim đầu vào vì middleware Laravel trim, không đổi expectation nghiệp vụ.
- Các test quota cũ và test fallback CR-06 khai báo cấu hình nhỏ 5 lượt rõ ràng để giữ kiểm chứng fallback, không phụ thuộc default mới. Test cap cũ khai báo 100 rõ ràng.
- GREEN: `php artisan test --compact tests/Feature/Support/SupportConversationTest.php tests/Feature/Support/SupportHandoffBriefTest.php`: 52 passed / 471 assertions ở checkpoint đầu.
- Tăng độ dài message của ca history để thực sự chạm budget 6000 và yêu cầu số message nhỏ hơn 11. `php artisan test --compact`: **204 passed / 2524 assertions**, 12,20 giây.
- Thêm test mặc định cap 200: chat vẫn được tại 150 tin, chạm 200 thì 422 không ghi thêm, report vẫn tạo được. Focused cuối `php artisan test --compact tests/Feature/Support/SupportConversationTest.php`: **32 passed / 343 assertions**, 4,36 giây. Không gọi 205 tests là một lần toàn-suite mới.
- Pint --test trên SupportConversationService, config/services và hai file test: passed.

Các test dùng SQLite :memory:, fake HTTP/notification; fake clock không phải chờ thật. PHPUnit chạy ngoài sandbox qua cơ chế xét duyệt do lỗi is_readable trên Windows đã biết. Không migrate, không sửa phiếu cũ, không gọi OpenAI/Ollama thật hoặc gửi thông báo thật trong lượt này.

## Giới hạn/review

Review xác nhận provider request vẫn ngoài transaction, version/idempotency và quyền hiện có không đổi. Tăng default có chủ đích theo yêu cầu người dùng; không tự mở quota vô hạn. Command chat bị fallback/thiếu cấu hình vẫn được tính vào giới hạn phiên như trước, không tuyên bố mọi command tương ứng một request provider. UI trạng thái chờ CR-05 giữ nguyên.

Chưa smoke model thật ở phiên 40 lượt hoặc eval việc model nhớ thông tin qua history mới. Hai lời báo đầu có thể chỉ là lời chào; mô tả xuất hiện muộn hơn vẫn chỉ giữ theo cửa sổ gần nhất. NFR quota phân tán, token thực, MySQL concurrency và staging/production chưa được nghiệm thu.
