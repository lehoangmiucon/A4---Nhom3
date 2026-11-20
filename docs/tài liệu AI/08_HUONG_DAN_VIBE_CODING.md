# 08 — Hướng dẫn vibe coding không lạc phạm vi

## 1. Mục đích

Dùng bộ tài liệu để giao một việc cụ thể cho AI coding agent, không giao “làm hết AI thật thông minh”. Hợp đồng và bằng chứng quyết định mức hoàn thành; câu trả lời tự tin hoặc demo một ca không đủ.

Root AGENTS.md dẫn đến bộ tài liệu này khi làm module AI. Những quy tắc ở đây áp dụng trong phạm vi nhiệm vụ được giao, không cho phép tự sửa chức năng khác.

## 2. Thứ tự đọc

1. README của bộ tài liệu và trạng thái CURRENT_CODE/TARGET.
2. tasks/todo.md, chọn task đầu chưa hoàn thành mà phụ thuộc đã đáp ứng.
3. Các FR/NFR, UC/BR, ma trận quyền, màn hình/sơ đồ và test case task tham chiếu.
4. tasks/plan.md và evidence gần nhất.
5. Git status, CodeGraph rồi mã cần sửa.

Không bắt model nạp mọi tài liệu mỗi lượt. Nạp index, task và phần liên quan; mỗi phiên làm việc cần giữ task ID và requirement IDs trong phần tóm tắt.

## 3. Mẫu giao việc

```text
Triển khai task <TASK-ID> trong tasks/todo.md.
Nguồn yêu cầu: docs/tài liệu AI/README.md và các file được task dẫn chiếu.
FR/NFR: <danh sách ID>. UC/BR/diagram: <ID liên quan>. Test cases: <danh sách TC>.
Chỉ làm acceptance criteria của task, giữ nguyên thay đổi ngoài phạm vi.
Kiểm tra code hiện tại bằng CodeGraph trước khi tìm/đọc code.
Viết test thất bại đúng lý do trước khi thay logic; dùng fake ở biên API.
Không gọi API trả phí hoặc migrate dữ liệu đang dùng nếu task không bao gồm việc đó.
Không xây Flutter/model hosting/endpoint tương lai để thay cho phần việc hiện tại.
Cập nhật contract, task và bằng chứng sau kiểm thử; báo phần chưa hoàn thành.
```

Bắt đầu theo README AI (đồng bộ 1.2), tasks/plan.md, tasks/todo.md và evidence gần nhất verification-cr13.md; verification-cr01.md là checkpoint lịch sử: CR-01 đã có hai chế độ báo sự cố, hội thoại lưu server và gửi phiếu trong chat. Kiểm tra cấu hình provider và các gate staging còn lại; không lặp lại phần đã có hoặc coi toàn M2/M3/M4 hoàn thành. Kiểm tra thay đổi thực tế nếu bối cảnh mới.

## 4. Các rào chống lạc hướng

| Dấu hiệu lệch | Cách xử lý |
| --- | --- |
| Đổi sang model riêng/GPU khi làm UI | Dừng phần thay đổi stack, đối chiếu quyết định D-01 |
| Gọi endpoint TARGET chưa có | Làm task API phụ thuộc hoặc UI disabled rõ; không tạo thành công giả |
| Backend để AI tự tạo thao tác | Quay về guide IDs + eligibility, không vá bằng prompt dài hơn |
| Thêm role/quyền rộng để test chạy | Viết policy đúng và test negative; không cấp admin cho giảng viên |
| Test fail rồi sửa expectation | Xác định baseline/regression; chỉ đổi expectation khi yêu cầu thực sự đổi |
| Chỉ chạy một case happy path | Chạy failure cases và kiểm tra của task |
| “Đã xong” nhưng chưa có schema/policy/test | Chưa DONE_CODE; ghi phần thiếu |
| Migrate/fresh để sửa database | Không thực hiện nếu chưa có quy trình dữ liệu được giao |
| Vừa làm xong API đã đánh dấu app xong | Tách DONE_CODE, staging và RELEASED |
| Sửa tài liệu cũ để phủ nhận thiếu tính năng | Cập nhật baseline hiện hành và evidence trung thực |

## 5. Quy tắc quyết định

Agent được tự xử lý các lựa chọn triển khai nhỏ trong phạm vi đã chốt. Không xin lại quyền cho việc người dùng đã giao. Chỉ cần làm rõ khi có mâu thuẫn thực sự ảnh hưởng task, thay đổi mục tiêu/hợp đồng hoặc thiếu cấu hình cần thiết.

Không suy đoán ngân sách, credential, người trực, guide đã duyệt hoặc kết quả test. Trong lúc thiếu thông tin môi trường, tiếp tục task độc lập bằng fake và ghi điều kiện còn chờ; không coi elapsed time là chấp thuận.

Không tự gửi email/push thật cho người khác trong task unit test hoặc viết tài liệu. Việc test/phát hành kênh thật phải nằm trong nhiệm vụ được giao.

## 6. Kỷ luật triển khai

- Mỗi task khoảng 3–5 file, tách nếu ảnh hưởng nhiều module độc lập.
- Không refactor controller phiếu rồi đồng thời đổi nghiệp vụ trùng lặp nếu task chỉ yêu cầu tách service.
- Không tạo interface/config/service rỗng cho các phần chưa sử dụng.
- Không trả nguyên Eloquent model hoặc raw provider output để frontend tự đoán.
- Không tin lịch sử client; mọi facts/attempt dùng để hành động lấy từ server.
- Thời gian chờ model không nằm trong DB transaction.
- Các test môi trường SQLite không được dùng để tuyên bố MySQL concurrency đã đạt.
- Lệnh kiểm tra không chạy được phải báo đúng; không chuyển “chưa chạy” thành “đạt”.
- Tài liệu/code không nhất quán: ghi delta, sửa file sở hữu nội dung và test; không tạo một bản spec cạnh tranh.
- Tôn trọng các thay đổi chưa commit của người dùng và tác vụ khác; không reset/clean toàn repo.

## 7. Kết quả cuối task cần báo

Nêu ngắn: task nào, hành vi đã đổi, kiểm thử và kết quả thực, lỗi nền, migration/API/production đã làm hay chưa, rủi ro còn lại và task kế tiếp.

Một báo cáo tốt không cần chép mọi file. Luôn dẫn evidence và không bắt người dùng đọc commentary cũ để biết trạng thái.

## 8. Templates

- [Task](templates/TASK.md)
- [Change request](templates/CHANGE_REQUEST.md)
- [Verification](templates/VERIFICATION.md)
- [Ca kiểm thử](templates/TEST_SCENARIO.md)
- [Biên bản UAT](templates/UAT.md)

Không lưu các template đã điền ở nhiều nơi thành nhiều bảng task. Task chính ở tasks/todo.md, evidence ở tasks/verification-<task-id>.md khi thực sự thực hiện.
