# CR-04 — danh mục hiện tại và mã nội bộ trong câu trả lời

Ngày 20/11/2026. DONE_CODE, chưa RELEASED. Task chính ở todo.md. Truy vết AI-FR-004/008, AI-NFR-003/008; UC-02/04, BR-40/41; phạm vi TC-007/012/021/022, không hoàn thành toàn bộ TC TARGET.

## Nguyên nhân

Ảnh người dùng cho thấy đổi sang Mạng / Wi-Fi trong cùng chat nhưng reply là “Reported: Không vào được mạng (U2).” CodeGraph xác nhận danh mục đã lưu trên session nhưng orchestrator chỉ đưa phòng vào HelpdeskAiService. Model nhận danh sách mọi danh mục, không biết danh mục đang chọn. Prompt có user_reports U1/U2 phục vụ reported_checks; reply trước đây chỉ kiểm tra không rỗng và cắt độ dài nên chuỗi nội bộ hiển thị nguyên văn. Đây là hai thiếu sót riêng; không kết luận chắc việc đổi danh mục trực tiếp gây model viết Reported.

## Thay đổi

- HelpdeskAiService nhận selectedCategory tùy chọn ở cuối chữ ký để giữ tương thích caller cũ. Orchestrator lấy category từ session server và truyền mỗi lượt, không lấy ID do model sinh. JSON ngữ cảnh có danh_muc_da_chon (id/name) hoặc null.
- Prompt luồng hỗ trợ yêu cầu ưu tiên phòng/danh mục hiện tại và lời báo mới nhất khi lịch sử đề cập danh mục khác; hỏi lại một câu nếu mâu thuẫn. Giữ toàn bộ lịch sử hiện có; không xóa, tạo phiên mới hoặc gộp vấn đề tự động khi đổi danh mục.
- Prompt tách reply cho người dùng và mã tham chiếu cho reported_checks. Backend chặn mẫu U[1-9][0-9]*, user_reports/handoff_brief hoặc tiền tố Reported ở reply của luồng hỗ trợ; thay bằng lời nhắn tiếng Việt xác định, không giả thành công gửi phiếu. Có nhắc tránh nguy hiểm điện/cháy để không làm mất đường cảnh báo khi model trả nội dung lỗi.
- Brief vẫn qua validator riêng và được giữ nếu hợp lệ; không gọi thêm model để sửa reply. Guard không áp dụng endpoint chat/draft cũ (prepareHandoff=false). Không đổi schema DB, endpoint, phân quyền hoặc provider.

## RED → GREEN

Baseline gần nhất: 192 passed / 2396 assertions.

Test mới: đổi danh mục trong cùng phiên rồi chat tiếp không gửi lại category_id; request model phải chứa danh mục mới ở cả hai lượt. Model local nhận ngữ cảnh đã chọn và reply bình thường giữ nguyên. Bốn ca reply lỗi kiểm tra thay thế tiếng Việt, giữ brief hợp lệ, chỉ một request provider.

`php artisan test --compact tests/Feature/LocalHelpdeskAiTest.php tests/Feature/Support/SupportConversationTest.php`:

- RED: 6 failed / 31 passed. Bốn ca reply giữ nguyên mã nội bộ; một ca thiếu tham số selectedCategory; một ca request thiếu danh mục đã chọn.
- GREEN: 37 passed / 301 assertions.

`php artisan test --compact`: **198 passed / 2427 assertions**, 10,77 giây. SQLite :memory:, HTTP fake; không tạo phiếu/notification trên DB local đang dùng, không gọi model cloud hoặc Ollama thật.

`php vendor/laravel/pint/builds/pint --test app/Services/HelpdeskAiService.php app/Services/Support/SupportConversationService.php tests/Feature/LocalHelpdeskAiTest.php tests/Feature/Support/SupportConversationTest.php`: passed sau sắp xếp import test. Pint ghi file bị chặn bởi kiểm tra is_writable trong sandbox nên chỉnh import bằng patch rồi chạy kiểm tra đạt.

Lần chạy PHPUnit trong sandbox bị chặn trước test: is_readable(vendor/autoload.php)=false dù require vẫn đọc được. Chạy lại cùng lệnh ngoài sandbox qua cơ chế xét duyệt tự động thành công; không đổi ACL hoặc sửa dependency để né lỗi.

## Review và giới hạn

Đã review đường truyền ngữ cảnh và output dùng chung hai provider, tương thích caller cũ, version/idempotency và quyền backend; suite có các ca brief, báo trùng và phiếu. Danh mục vẫn được lưu cùng lượt gửi tin, không tự gọi model ngay khi người dùng chỉ đổi select. Prompt hướng dẫn dùng ngữ cảnh hiện tại nhưng không bảo đảm model luôn suy luận đúng; mã nội bộ chưa biết hoặc sai ngôn ngữ ngoài mẫu vẫn cần eval. Chưa smoke model thật/browser ở lượt này. Lịch sử reply đã lưu không được viết lại; thay đổi có hiệu lực cho các lượt chat tiếp theo.
