# Case <CASE-ID>

- Trạng thái: draft / reviewed / approved.
- Family/scenario:
- FR/NFR/UC/BR/TC:
- Reviewer/ngày: chưa xác nhận.
- Mục đích: unit/feature/AI eval/UAT.

## Preconditions và dữ liệu giả

Actor/role, phòng/thiết bị/model, state/version, published guides, facts đã biết, attempt history, quota/provider.

## Hội thoại hoặc thao tác

Ghi từng lượt, actor thực hiện, lệnh/response cần quan sát. Không chứa thông tin người dùng thật hoặc secret.

## Oracle

- Hành động được phép:
- Hành động bị cấm:
- Câu hỏi/bước đủ điều kiện:
- State/result/report/progress kỳ vọng:
- Side effects phải có/không được có:

## Lỗi/biến thể có ý nghĩa

Mạng lỗi, concurrency, ID người khác, provider muộn hoặc metadata thiếu. Không đếm các bản diễn đạt lại cùng family là test độc lập.

## Bằng chứng thực

Command/môi trường/version, expected/actual, pass/fail, issue và artifact. Chưa chạy thì ghi chưa chạy.
