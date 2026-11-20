# CR-03 — chặn phiếu AI trùng với sự cố chưa xử lý

Ngày 20/11/2026. DONE_CODE với test tự động; chưa nghiệm thu production. Nguồn yêu cầu: người dùng báo chat AI vẫn chấp nhận gửi trùng cùng phòng khi sự cố chưa xử lý. Trạng thái task ở todo.md. Phạm vi AI-FR-009, AI-NFR-001/003/008, UC-04/05, TC-006/013/023; thay đổi CURRENT_CODE có chủ đích, chưa hoàn thành TARGET M2-05.

## Nguyên nhân và hành vi mới

CodeGraph xác nhận TicketCreationService mặc định chống trùng theo room_id/category_id với trạng thái new/reopened/assigned/in_progress, có khóa dòng phòng và phiếu trong transaction. Form thủ công dùng mặc định. ConversationReportService gọi với deduplicate:false, chủ động bỏ kiểm tra; test CR-01 cũ cũng yêu cầu tạo hai phiếu. Theo yêu cầu mới, bỏ ngoại lệ này và thay test với expectation nghiệp vụ mới, không thay expectation để che lỗi.

Nếu trùng: formatter tạo brief/description không gây ghi dữ liệu; service trả null trước report/audit/notification. Orchestrator lưu câu trả lời xác định: “Sự cố cùng phòng và danh mục thiết bị đã được ghi nhận nhưng chưa được xử lý xong. Không tạo thêm phiếu trùng. Hội thoại của bạn vẫn được lưu.” Session collecting, report_requested=false; receipt completed/HTTP 200 với report=null, không hiện “Đã gửi báo cáo”. Không trả mã/link/ticket_id của người khác và không cấp quyền xem phiếu cũ.

Cùng key replay nguyên kết quả, không lặp tin; key mới cũng kiểm tra lại. Nếu phiếu cũ resolved/closed, người dùng có thể gửi lại từ phiên vừa bị từ chối. Khác phòng/danh mục vẫn tạo phiếu mới. Hội thoại gốc giữ trong support_messages; không tạo support_report cho lần bị chặn.

## Kiểm chứng

- Baseline gần nhất: 180 passed / 2235 assertions.
- RED: `php artisan test --compact tests/Feature/Support/SupportConversationTest.php`: 10 failed / 14 passed; ca chặn trùng trả 201 thay vì 200. Test bao phủ bốn trạng thái active với cùng người/khác người và báo lại sau hoàn tất.
- GREEN sau sửa: cùng lệnh đạt 24 passed / 238 assertions.
- Thêm kiểm tra phiếu trước được báo thủ công, khác phòng, khác danh mục; chạy `php artisan test --compact`: **192 passed / 2396 assertions**, 10,38 giây.
- `vendor/bin/pint --test app/Services/Support/ConversationReportService.php app/Services/Support/SupportConversationService.php tests/Feature/Support/SupportConversationTest.php`: passed.
- Test SQLite :memory:, fake HTTP; notification database chỉ ghi trong DB kiểm thử. Không gọi model hoặc gửi email/push thật, không migrate/ghi vào database local đang dùng.

## Giới hạn và review

Đối chiếu hành vi form và nhánh frontend: report=null không hiển thị biên nhận thành công; câu từ chối hiện trong lịch sử hội thoại, vẫn cho người dùng chat tiếp. Không thay JavaScript/Blade. Kiểm tra source cho thấy duplicate return trước mọi report/log/notification mới; receipt và trạng thái phiên được commit cùng transaction, nhánh lỗi/retry cũ vẫn qua suite. Chưa smoke trình duyệt hoặc model thật trong lượt này.

Quy tắc dùng phòng/danh mục nên có thể chặn cả lỗi khác triệu chứng trong cùng danh mục; không tuyên bố AI đã hiểu hai mô tả tương đương. So khớp thiết bị/symptom chính xác, MySQL concurrency và UAT/staging còn TARGET. Không xóa/gộp các phiếu trùng đã có hoặc sửa lịch sử báo cáo.
