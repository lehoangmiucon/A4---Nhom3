# CR-12 — biết phòng và công việc trước khi hướng dẫn

Ngày 20/11/2026. DONE_CODE; chưa UAT/staging/RELEASED. Task chính todo.md. Yêu cầu người dùng: AI phải biết phòng ở đâu và hỗ trợ công việc gì trước khi bắt đầu hỗ trợ. Truy vết AI-FR-001/004/014, AI-NFR-003/005/008, UC-02, BR-44, TC-007/018/034.

## Baseline và thay đổi

Trước CR-12, SupportConversationService chỉ đòi đủ phòng/danh mục/dữ kiện trước gửi report, còn lượt chat vẫn gọi model khi thiếu. Lời chào có thể lọt vào incidentFacts. Nay backend yêu cầu room_id hợp lệ, category_id và mô tả vấn đề trước gọi model; thông tin được chọn hoặc suy ra duy nhất từ chat. Hỏi phần còn thiếu bằng câu xác định, giữ tin/receipt/replay, không dùng model để hỏi thông tin cơ bản. operation=clarify không tiêu quota chat; throttle và trần tin hiện tại giữ nguyên. Gửi report không cần AI, nhưng phải có mô tả thực.

UI mở mục Phòng và công việc cần hỗ trợ ngay từ đầu, gợi ý chọn/nêu phòng + loại công việc/thiết bị + vấn đề. Còn needs_context/needs_description thì giữ mục mở, không phụ thuộc report_requested. Đủ thì thu gọn. Lời chào khi đã có phòng không hỏi lại phòng; phiên mới giữ phòng QR/form đang chọn, bỏ danh mục công việc cũ. Script asset cr12, không thay CSS sản phẩm hoặc endpoint.

incidentFacts loại lời chào/yêu cầu hỗ trợ chung và phần chỉ nêu ngữ cảnh khỏi xét mô tả/tóm tắt. Vẫn lưu nguyên văn messages/transcript; ConversationBrief lấy phần trích LỜI BÁO GỐC từ tin người dùng gốc thay vì tập facts đã lọc (vẫn áp dụng giới hạn trích 5000 ký tự của phiếu). Không sửa phiếu đã có.

## TDD và kết quả

- `php artisan test --compact --filter=SupportIntakeTest`: RED **13 failed / 1 passed / 38 assertions** do gọi provider khi thiếu context, coi lời chào là dữ kiện và tạo phiếu từ hi. GREEN **14 passed / 87 assertions**.
- Intake tests: thiếu cả phòng/công việc; đã biết loại thiết bị chỉ hỏi phòng; phòng chọn/QR được giữ; lời chào/tên phòng/danh mục không đủ mô tả; đủ thông tin gọi model; nhiều phòng không đoán; report từ hi không tạo phiếu; key replay không nhân đôi, clarification ghi operation riêng.
- Hai JS intake tests RED do picker bị đóng; sau sửa `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs`: **62 passed / 0 failed**. Bao phủ mở thông tin từ đầu/tạo mới, giữ QR, missing context dù chưa report, tự thu gọn khi đủ và hồi quy xóa/phân trang/logout/animation.
- Full run trung gian có 4 lỗi: test đổi danh mục khởi đầu bằng lời hỗ trợ chung nên không còn lượt model đầu; hai ca mong AI tạo brief trước biết phòng không còn đúng yêu cầu mới; raw source bị lọc lời chào. Đổi input test đổi danh mục thành dấu hiệu cụ thể để vẫn kiểm chứng cùng hành vi; đổi hai ca brief thiếu context thành oracle dự phòng/0 model call theo yêu cầu mới, giữ kiểm chứng nguyên văn problem. Riêng regression mất lời chào ở raw source được sửa ở implementation, giữ expectation cũ và số call=5 của test quota. Không sửa test để che lỗi raw data.
- `php artisan test --compact --filter='SupportIntakeTest|SupportConversationTest|SupportHandoffBriefTest'`: **67 passed / 567 assertions**.
- Kết quả cuối `php artisan test --compact`: **235 passed / 2715 assertions**, 14.02s. SQLite :memory:/fake API, không tác động database local.
- `php vendor/bin/pint --test` cho ConversationContext, SupportConversationService, ConversationBrief và 3 file test liên quan: **passed**. `git diff --check` đạt; cảnh báo CRLF/LF ở working tree có sẵn.

## Browser và review

`node tests/browser/support-intake-smoke.mjs`: **4/4 PASS**, thiếu/đủ thông tin × bố cục 1440/390px. Partial/CSS/JS thật, DOM/timer thật, API giả. Kiểm tra mở picker, không tự gửi khi chọn chế độ, giữ picker sau lời chào, không bịa phòng/không hiển thị hướng dẫn sớm, gửi đúng room/category/content, endpoint phiên đúng, hiển thị hướng dẫn và thu gọn khi đủ; không runtime error hoặc nội dung tràn khung. [Kết quả](../evidence/cr12-smoke/result.json), [ảnh bố cục nhỏ](../evidence/cr12-smoke/390-missing.png).

Đã xem ảnh và sửa fixture cho width layout 390px vì Chrome headless có thể ép viewport tối thiểu lớn hơn kích thước ảnh; kiểm tra thêm cạnh phải card và scrollWidth main thay vì chỉ so với innerWidth. Đây là kiểm chứng bố cục 390px bằng fixture, không tuyên bố emulation thiết bị thật hoặc ảnh nguyên trang portal đăng nhập.

Review: guard nằm ở backend trước provider, không nhận cờ client cấp quyền; thiếu context không bị tính quota gọi model; thông tin đã biết dùng lại; giả lập provider không tạo quyền gửi. Quyền chủ phiên/replay/xóa/transaction cũ giữ nguyên. Không migration, provider mới, gọi API thật, gửi notification thật hoặc seed dữ liệu local. Nhận diện nội dung thuần lời chào dựa trên mẫu xác định, không phải bộ hiểu ngôn ngữ toàn diện; model vẫn chịu trách nhiệm hỏi triệu chứng chi tiết hơn khi cần. Legacy HelpdeskChatController chưa chuyển sang luồng intake mới; phạm vi là hội thoại lưu trên portal hiện tại. Chưa UAT đăng nhập/session/CSRF browser với backend thật, screen reader, model Ollama thật hoặc MySQL concurrency trong lượt này.
