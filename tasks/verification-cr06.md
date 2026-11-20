# CR-06 — bản bàn giao khi AI hết lượt hoặc brief không dùng được

Ngày 20/11/2026. DONE_CODE, chưa RELEASED. Task duy nhất ở todo.md. Truy vết AI-FR-008, AI-NFR-003/008; UC-04/08/12, BR-40/41; TC-011/012/021/025 trong phạm vi formatter. Yêu cầu: người dùng cung cấp mô tả phiếu KTV từ phiên #7, bản tổng hợp chỉ có câu nhờ giúp và gợi ý chung, không đáp ứng mục tiêu tóm tắt/gợi ý.

## Bằng chứng nguyên nhân

Đọc riêng session #7 và các message/brief bằng truy vấn local chỉ đọc, không log thông tin tài khoản hay sửa phiếu. Phiên có 6 lượt chat: câu mở đầu, mô tả máy chiếu, “kiểm tra rồi”, “kiểm tra rồi đã cấm điện”, “nguồn điện oke hêt rồi”, “không tháy sáng đèn”. Lượt thứ 6 bị giới hạn AI nên không có brief mới. Brief cuối có context category_id=2 (Máy chiếu); khi gửi, category_id=4 (Điện & chiếu sáng). Facts mới và đổi category đã biết đều làm hash cũ không còn khớp đúng thiết kế. Fallback trước sửa lấy facts->first() là “tôi cần bạn giúp”, không trích các kiểm tra và chỉ dùng một gợi ý chung.

Phát hiện phụ có test riêng: lúc validate model, reported_checks được clean whitespace trước lưu; lúc bàn giao trích đoạn đã clean lại được so với raw user content chứa xuống dòng/khoảng trắng lặp, gây loại brief hợp lệ.

## Thay đổi

ConversationFallback tách formatter xác định khỏi ConversationBrief:

- Loại câu chào/nhờ giúp thuần khỏi tập chọn nội dung trình bày, giữ nguyên tập facts/hash và transcript. Tiêu đề lấy lời mô tả cụ thể đầu tiên, tối đa 180 ký tự. Tóm tắt dẫn nguồn mô tả đầu và hai tin bổ sung gần nhất, tối đa 600 ký tự; dùng dấu trích rõ, không biến lời người dùng thành kết luận kỹ thuật.
- Phần kiểm tra dự phòng lấy tối đa bốn lời báo khớp mẫu kiểm tra/đã thử/nguồn điện ok/oke; trích câu người dùng, không lấy câu khuyên của trợ lý. Câu mơ hồ như “kiểm tra rồi” không bị diễn giải thành đã đo điện hoặc thay dây.
- Với lời báo máy chiếu không lên/không bật/không sáng đèn, mẫu gợi ý cho KTV xác minh khởi động, đèn báo và đường cấp nguồn theo quy trình; không đoán bo nguồn hỏng, dụng cụ hoặc hạ tầng chưa biết. Vấn đề ngoài mẫu giữ gợi ý đối chiếu tại phòng.
- Danh mục được phát hiện qua nhãn catalog xuất hiện nguyên cụm sau chuẩn hóa dấu/khoảng trắng. Chỉ khi đúng một danh mục được đề cập và khác lựa chọn mới cảnh báo đối chiếu; không tự đổi category_id hoặc nghiệp vụ chống trùng.
- Brief AI hợp lệ vẫn được ưu tiên. Hash vẫn loại nguồn stale. Đối chiếu trích đoạn normalize whitespace hai phía, không thay từ. Output thêm việc chưa báo vẫn bị từ chối qua ca bảo vệ hiện có.

## RED/GREEN và kiểm chứng

- Baseline PHP gần nhất: 198 passed / 2427 assertions (CR-04).
- `php artisan test --compact tests/Feature/Support/SupportHandoffBriefTest.php` sau thêm test: **2 failed / 19 passed**, tóm tắt fallback thiếu mô tả máy chiếu và brief trích đoạn xuống dòng bị loại. Test không lấy lời khuyên AI làm kiểm tra đã làm ban đầu đạt, dùng làm guard hồi quy.
- Sau triển khai: 21 passed / 134 assertions. Tăng assertion yêu cầu “nguồn điện oke hêt rồi” phải có trong phần kiểm tra riêng, RED 1 failed do mẫu chỉ nhận “ok”; sửa nhận “oke”.
- `php artisan test --compact`: **201 passed / 2457 assertions**, 11,05 giây sau sửa mẫu. Sau đó chỉnh câu gợi ý cho trung tính, không ngụ ý nguồn điện đã được báo ổn trong mọi ca máy chiếu; focused cuối **21 passed / 135 assertions**, 1,92 giây.
- `php vendor/laravel/pint/builds/pint --test app/Services/Support/ConversationFallback.php app/Services/Support/ConversationBrief.php app/Services/Support/ConversationReportService.php tests/Feature/Support/SupportHandoffBriefTest.php`: passed.
- `git diff --check`: đạt; cảnh báo CRLF/LF ở các thay đổi đã có.

PHPUnit chạy SQLite :memory:, fake HTTP/notification database trong DB bộ nhớ. Không gọi OpenAI hoặc Ollama thật. Các lệnh PHPUnit/Pint đọc/ghi và MySQL local bị sandbox Windows chặn được chạy lại ngoài sandbox qua cơ chế xét duyệt; không đổi ACL hoặc dependency.

## Bản xem trước và review

Đã gọi formatter mới trên dữ liệu thật session #7 bằng truy vấn chỉ đọc và lưu [bản xem trước](evidence/cr06/session7-preview.txt). Có mô tả máy chiếu không lên, kết quả nguồn điện và không sáng đèn; cảnh báo Máy chiếu khác Điện & chiếu sáng; gợi ý kiểm tra đèn/đường cấp nguồn. Không cập nhật description/title/category của phiếu đã gửi, không tạo thông báo thật. Bản xem trước là artifact để review, không phải evidence phiếu cũ đã được thay.

Review xác nhận không thêm request model trong TX-B, không bỏ hash hoặc cấp quyền mới. Các phần description vẫn giới hạn 5000 ký tự và transcript giữ đầy đủ theo test dữ liệu dài. Không thay quota hoặc provider để che lỗi formatter. Trích đoạn trình bày có thể giữ lỗi gõ của người dùng nhằm không đổi nghĩa “cấm/cắm điện”; không khẳng định tất cả các bước người dùng nói “kiểm tra rồi” đã được thực hiện đúng kỹ thuật. Mẫu dự phòng chưa phải semantic summary/eval đa thiết bị; cảnh báo catalog không phát hiện mọi tên viết tắt/loại thiết bị. Chưa smoke browser, provider thật, MySQL concurrency, staging hoặc production trong lượt này.
