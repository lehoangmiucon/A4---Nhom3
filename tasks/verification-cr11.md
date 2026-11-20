# CR-11 — chỉ xóa phiên đã báo cáo và xử lý hoàn tất

Ngày 20/11/2026. DONE_CODE và migration local; chưa RELEASED/UAT staging. Task duy nhất ở todo.md. Yêu cầu người dùng: thêm xóa phiên, chỉ cho xóa phiên đã báo cáo và đã xử lý sự cố xong. Truy vết AI-FR-019/002/014, AI-NFR-001/006/008, BR-43; TC-033 và hồi quy TC-003/004/018.

## Quyết định và phạm vi

Đối chiếu TicketController::staffUpdate/close: ticket resolved là KTV gửi kết quả chờ quản lý; closed là đã được quản lý xác nhận. Chỉ chủ phiên student/teacher active/verified được xóa khi session handed_off có report.ticket.status=closed. Phiên tự resolved chưa báo cáo không đủ điều kiện. DTO ticket_status/is_completed/can_delete tính từ dữ liệu hiện tại, không thêm boolean DB dễ lệch khi ticket mở lại.

DELETE dùng transaction/lock phiên-report-ticket và kiểm tra lại trạng thái, không tin cờ client. Xóa mềm deleted_at, không thay status hoặc xóa dữ liệu ticket/report/transcript/messages/command/key. Route đọc/query mặc định bỏ phiên đã xóa; DELETE của cùng chủ lặp lại trả 200, người khác nhận 404. Hai đường replay command và key tạo phiên M1a kiểm tra phiên còn tồn tại trước trả receipt; không hồi sinh dữ liệu đã ẩn. Không thay response snapshot cũ của phiên chưa xóa.

Lịch sử có nhãn hoàn tất/chờ duyệt/chưa hoàn tất và nút Xóa phiên theo can_delete. Xác nhận nêu phiếu vẫn giữ; busy chống lặp và thao tác ghi xung đột, chặn xóa khi đang tải điều kiện mới. Lỗi giữ nội dung và nhả nút; backend 422 có lý do. Xóa phiên đang mở dọn khung/storage/receipt; xóa phiên khác giữ hội thoại đang đọc. Tải lại trang hiện tại và tự lùi nếu trang cuối hết dữ liệu. Logout bỏ phản hồi muộn. Asset CSS/JS customer version cr11.

## TDD và hồi quy

- Ban đầu sửa fixture thiếu last_activity_at để test hợp lệ; không coi lỗi fixture là bằng chứng RED. Sau sửa fixture, `php artisan test --compact --filter=SupportSessionDeletionTest`: **13 failed / 23 assertions**, do thiếu route DELETE và field DTO. Sau triển khai **13 passed / 71 assertions**; bổ sung bearer, replay key M1a và Swagger, focused đạt **16 passed / 81 assertions** (sau đó thêm một assertion Swagger vào full suite cuối).
- JS RED bốn test mới đều fail vì chưa có nút xóa. GREEN đạt; bổ sung xóa mục cuối trang/giữ phiên khác/logout. Review thêm guard refresh đang tải: RED 1 fail do delete vẫn enabled; sửa bằng loadingHistory.
- Kết quả cuối `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs`: **60 passed / 0 failed**.
- `php artisan test --compact`: **221 passed / 2626 assertions**, 12.81s, SQLite :memory: theo phpunit.xml. Full run trung gian phát hiện thiếu nhãn OpenAPI destroy và quy ước Swagger không liệt kê error catalog; đã sửa implementation để theo quy ước hiện có, không đổi expectation test cũ. Lỗi vẫn được mô tả trong description.
- Pint kiểm tra 7 file PHP nguồn/test mới sửa; phát hiện ordered_imports ở controller/test, đã chạy `php vendor/bin/pint app/Http/Controllers/SupportSessionController.php tests/Feature/Support/SupportSessionDeletionTest.php` và chỉ sửa thứ tự import. `git diff --check` đạt; cảnh báo CRLF/LF thuộc working tree có sẵn.

## Runtime và migration

- `node tests/browser/support-session-delete-smoke.mjs`: **PASS** trên Chrome headless, viewport 1440×1100. Dùng partial và CSS/JS thật, thay lựa chọn select bằng fixture, API/confirm giả; DOM và timer thật. Chờ 6 giây, kiểm tra trạng thái không bị xóa; nút chỉ có cho phiên hoàn tất, nhãn chờ quản lý, cancel không DELETE, bấm lặp chỉ một DELETE, giữ phiên pending, dọn phiên active và báo thành công, không runtime error/unhandled rejection. Đã xem ảnh sau xóa. [Kết quả](../evidence/cr11-smoke/result.json), [ảnh](../evidence/cr11-smoke/completed-session-deleted.png).
- Migration riêng `2026_11_20_150000_add_soft_deletes_to_support_sessions` ban đầu Pending; chạy `php artisan migrate --path=database/migrations/2026_11_20_150000_add_soft_deletes_to_support_sessions.php --force`: DONE 33.63ms; kiểm tra migrate:status **[17] Ran**. Chỉ thêm cột nullable, không xóa phiên thực, không migrate:fresh hoặc chạy các migration khác.

## Review và giới hạn

Review correctness/quyền/hiệu năng/lifecycle: cờ và DELETE đều ràng buộc report/ticket hiện tại; list eager-load report.ticket để không N+1; chỉ DELETE binding dùng withTrashed; GET/replay không dùng. Xóa mềm giữ FK/audit/receipt; response no-store giữ theo SupportApiResponse. UI dùng textContent và nút riêng ngoài nút mở phiên; có aria-label, focus về Tải lại sau xóa. Giữ thay đổi ngoài phạm vi.

Không gọi model/API trả phí hoặc gửi notification thật. Chrome smoke dùng fake API, chưa kiểm chứng đường login/session/CSRF của DELETE trên browser với DB thật; quyền/session và bearer kiểm tra bằng PHP feature tests. Chưa test MySQL concurrency, screen reader hoặc thiết bị thật; không dùng SQLite/Chrome fixture để tuyên bố staging/production đã nghiệm thu. Policy retention/purge vật lý vẫn là phần riêng; cờ deleted_at là ẩn phiên, không phải xóa hồ sơ sự cố.
