# Kiểm chứng <TASK-ID>

- Ngày/môi trường:
- Snapshot/commit hoặc mô tả thay đổi:
- FR/NFR và TC:
- Trạng thái: DONE_CODE / VERIFIED_STAGING / RELEASED / chưa đạt.

## Baseline

Command và kết quả trước thay đổi; lỗi nền riêng biệt.

## RED → GREEN

| Bước | Command | Kết quả thực |
| --- | --- | --- |
| RED | Chưa chạy | Chưa có bằng chứng |
| GREEN | Chưa chạy | Chưa có bằng chứng |
| Hồi quy | Chưa chạy | Chưa có bằng chứng |
| Lint/contract/UI | Chưa chạy | Chưa có bằng chứng |

Không dùng những dòng chưa chạy này làm báo cáo PASS.

## Review

Correctness, security, readability, architecture, performance; phát hiện và cách xử lý.

## Triển khai và giới hạn

Đã/chưa migrate database thật; đã/chưa gọi API trả phí; UI/MySQL/load/provider thật đã/chưa kiểm chứng. Nêu giới hạn cần cho người đọc hiểu đúng kết quả.

## Kết luận theo acceptance criteria

Đạt/không đạt, phần còn thiếu và task kế tiếp. Không ghi secret hay raw dữ liệu cá nhân.
