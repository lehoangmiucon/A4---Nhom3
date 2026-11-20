# CR-13 — phân nhánh quan sát và chuyển KTV

20/11/2026. DONE_CODE; không tuyên bố RELEASED/UAT. AI-FR-004/007/008/014, AI-NFR-002/003/008; UC-02/04, BR-45, TC-035. Yêu cầu: từ mô tả người dùng hỏi quan sát tiếp theo, không lặp kiểm tra nguồn đã xác nhận; chuyển KTV khi yêu cầu tự nhiên; IT có hồ sơ gợi ý nhanh.

## Thay đổi

- ConversationContext nhận “hãy báo cáo lên KTV cho mình”, báo lên kỹ thuật viên, nhờ IT xử lý, gặp/gọi/chuyển KTV và câu hỗn hợp. Intent được xử lý trước model. Giữ phủ định/giả định/hỏi trạng thái, cách làm, phí; không tự gửi các câu này. Không bỏ yêu cầu gửi chỉ vì triệu chứng cùng câu chứa “không”. Câu lệnh thuần không thành facts; câu hỗn hợp còn nguyên trong transcript.
- SupportDiagnosticFlow tính trạng thái từ messages/receipt server: máy tính/máy chiếu không khởi động, mất hình, mạng, âm thanh. Hỏi từng quan sát, giữ câu đã hỏi/kết quả, “không biết” không là thành công; phỏng đoán/câu hỏi/trích lời AI không là xác nhận. Câu trả lời ngắn gắn câu hỏi vừa phát. Mã câu hỏi lưu trong receipt cùng message ID, không phụ thuộc hoàn toàn nội dung câu hỏi khi sửa cách viết.
- Đổi room/category đã biết lấy ranh giới từ receipt; thay loại thiết bị/nhánh bắt đầu lại phạm vi quan sát. Mất điện dừng hỏi thiết bị, chuyển đề nghị IT; khói/mùi khét/nguy cơ điện giật ưu tiên safety_stop trước model kể cả thiếu intake. Cảnh báo an toàn trong câu hủy report vẫn được giữ trong hồ sơ.
- Model nhận diagnostic_context giữ kết quả cả khi tin gốc ra khỏi cửa sổ lịch sử 6000 ký tự; cùng số lượt model như trước, không thêm call phân loại. Nhánh đã hỗ trợ dùng câu hỏi backend thay model lặp bước; model hỗ trợ brief trong cùng lượt. Ngoài nhánh giữ fallback/model hiện có. Không thay category theo model hoặc tự tạo phiếu khi hết câu hỏi.
- Response bổ sung diagnosis, không migration. IT nhận PHÂN NHÁNH SƠ BỘ, quan sát người dùng báo, CHƯA XÁC ĐỊNH và tối đa 3 hướng kiểm tra; không trình bày unknown như kiểm tra đã làm, không kết luận hỏng linh kiện. Giữ raw source/report, quyền và chống trùng/replay. Chưa thêm panel IT tương tác riêng.

## TDD và kiểm chứng

RED đầu `php artisan test --compact --filter=SupportDiagnosticFlowTest`: **14 failed / 5 passed**, lỗi 8 cách chuyển KTV không tạo phiếu, lặp nguồn và thiếu projection. Bổ sung RED cho structured context sau lịch sử dài, phủ định/không chắc, câu hỏi thông tin, bảo toàn safety evidence và mã câu hỏi qua thay wording; sửa implementation từng bước, không nới expectation để che lỗi.

Test cũ bad_brief mong nguyên câu reply model “chọn nguồn vào” được cập nhật thành câu quan sát đèn nguồn, do yêu cầu mới chủ đích backend chọn câu hỏi; vẫn kiểm chứng brief sai không phá chat/report và fallback còn hoạt động.

Kết quả cuối:

- Diagnostic focused: **41 passed / 177 assertions**.
- `php artisan test --compact`: **276 passed / 2892 assertions**, 18.46s. Các test dùng SQLite :memory: và HTTP/notification fake.
- `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs`: **62 passed**.
- Pint --test trên 8 file PHP mới/sửa: **passed**. Đã sửa formatting và lỗi import Kernel trong fixture sau Pint chuyển tên đầy đủ thành use đặt dưới lệnh bootstrap; imports hiện ở đầu script, browser chạy lại đạt.
- `git diff --check`: đạt, cảnh báo CRLF/LF của working tree có sẵn.

Sandbox Windows sau đổi môi trường báo không đọc được vendor/autoload.php mặc dù file tồn tại. Chạy cùng test ngoài sandbox được auto-review cho phép và đạt; không sửa dependency để né lỗi. Không tính lỗi sandbox là RED hành vi.

## Browser

`node tests/browser/support-diagnostic-smoke.mjs` đạt 1440 và 390px. PHP fixture migrate vào SQLite :memory:, dùng service thật/model fake tạo 3 snapshot: máy không mở → nguồn bình thường → yêu cầu báo lên KTV. Chrome chạy partial/CSS/JS thật và fetch giả trả đúng các snapshot service, kiểm tra câu hỏi đổi sang đèn báo, không lặp nguồn, report thực sự được service tạo và receipt hiển thị, khóa composer sau bàn giao, không runtime error/overflow. Đã xem ảnh 390px. [Kết quả](../evidence/cr13-smoke/result.json), [ảnh](../evidence/cr13-smoke/390.png).

Đây là kiểm chứng service + DOM qua fixture, chưa E2E HTTP/login/session/CSRF thực. Không tạo test data trong database local đã dọn, không gọi provider thật, không gửi notification thật, không migration schema ứng dụng.

## Review và giới hạn

Review correctness/security/performance: projection chỉ dựa server, không cấp quyền và không gọi mạng trong transaction. Ưu tiên request bàn giao/an toàn, receipt replay và late-result gate cũ giữ nguyên. Evidence là dữ liệu không phải lệnh; nhánh và hướng kiểm tra không là kết luận. Projection đọc lịch sử/receipt mỗi lượt, receipt gồm snapshot nên cần đo tải/giảm chi phí trước quy mô lớn; chưa kiểm chứng MySQL concurrency.

Nhận diện ngôn ngữ dựa mẫu xác định, chưa bao phủ mọi cách nói; các nhánh là tập quan sát ban đầu, không phải cây chẩn đoán toàn diện hoặc thư viện guide published M3. Hai thiết bị cùng loại/cùng phòng chưa có định danh riêng để tự phân biệt; đổi loại thiết bị/room/category có reset. Chưa chạy lại Ollama/OpenAI thật hoặc eval đa dạng. Không tuyên bố hoàn tất M3/M4/safety eval hoặc UAT từ test này.
