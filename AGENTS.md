# Hướng dẫn làm việc trong repository

<!-- CODEGRAPH_START -->
## CodeGraph

Khi repository có .codegraph/, dùng codegraph_explore hoặc `codegraph explore "<câu hỏi hoặc symbol>"` trước grep/find hoặc đọc mã để định vị và hiểu code. Có thể đọc trực tiếp phần CodeGraph không trả về hoặc tệp cấu hình/tài liệu không được index. Không tự khởi tạo index nếu chưa có .codegraph/.
<!-- CODEGRAPH_END -->

## Khi phát triển tính năng AI Helpdesk

1. Đọc [Tài liệu AI](<docs/tài liệu AI/README.md>) và [hướng dẫn vibe coding](<docs/tài liệu AI/08_HUONG_DAN_VIBE_CODING.md>).
2. Dùng tasks/todo.md làm danh sách task và trạng thái duy nhất; đọc task đang làm, FR/NFR, UC/BR, ma trận quyền, contract, sơ đồ và TC liên quan trước khi sửa.
3. Phân biệt CURRENT_CODE, TARGET và DEPLOYED. Không gọi API tương lai hoặc báo app/agent hoàn tất chỉ vì có tài liệu.
4. Giữ Laravel hiện có, API AI trả theo usage và backend kiểm soát hành động. Không tự chuyển sang fine-tuning, GPU hosting hoặc stack khác.
5. Khi đổi logic: test hành vi trước, xác nhận RED đúng lý do, triển khai tối thiểu và kiểm chứng GREEN. Test tự động dùng SQLite :memory: và fake API/notification.
6. Không chạy migrate:fresh, không xóa dữ liệu hoặc gọi API trả phí ngoài nhiệm vụ được giao; không đưa key vào chat, Git, frontend, mobile hoặc log.
7. Thay đổi contract/phạm vi có chủ đích phải cập nhật tài liệu sở hữu và task; không sửa expectation chỉ để che lỗi. Không hỏi lại các quyết định người dùng đã chốt.
8. Ghi evidence thật: command, kết quả, baseline, giới hạn, migration/provider/UI đã hoặc chưa kiểm chứng. Không đánh dấu RELEASED bằng kết quả unit test.
9. Không sửa/reset/clean các thay đổi ngoài phạm vi task; không tự gửi thông báo thật trong task viết tài liệu hoặc unit test.

Bộ tài liệu chỉ áp dụng trong phạm vi AI Helpdesk. Khi có yêu cầu mới của người dùng, cập nhật baseline và những phần bị ảnh hưởng; không dùng tài liệu cũ để phủ định yêu cầu mới.
