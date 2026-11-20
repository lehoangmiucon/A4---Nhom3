# DATA-CLEAN-01 — dọn dữ liệu thử nghiệm local

Ngày 20/11/2026, hoàn tất lúc 14:36 theo Asia/Bangkok. Người dùng yêu cầu xóa dữ liệu test; đã xác nhận bổ sung 6 phiếu nhập thủ công #6–11 cũng là test. Phạm vi chốt: toàn bộ 21 phiên AI và 21 phiếu test đang có, giữ tài khoản/phòng/thiết bị/danh mục. Không áp dụng điều kiện DELETE phiên hoàn tất cho thao tác quản trị dọn fixture này; không đổi nghiệp vụ hoặc giả chuyển trạng thái phiếu.

## Thực hiện

Đọc DB local MySQL/MariaDB: 21 phiên (IDs 1–21, user 16, ngày 20/11), 21 phiếu (IDs 1–21, gồm 5 DEMO, 6 nhập thủ công và 10 từ chat). Xem metadata FK trước xóa. Script one-off bootstrap Laravel, khóa bản ghi trong transaction, kiểm tra exact IDs/count/environment, preview trước execute. Không dùng truncate/reset/FK disable hoặc migrate:fresh.

Sao lưu đầy đủ các hàng sắp xóa (không xuất bảng users hoặc credential) vào thư mục private bị Git ignore, kiểm tra SHA-256 trước xóa:

`storage/app/private/test-data-backups/cleanup-20261120-073646-61d2a5.json`

SHA-256: `9187b801c3817a6827333af7898e4652af40881581d5febc0b2507955e77b476`.

Lệnh thực tế: `php storage/app/cleanup-test-data.php` preview, sau đó `php storage/app/cleanup-test-data.php --execute`; kiểm tra lại bằng `php storage/app/inspect-test-data.php`. Hai script one-off được bỏ sau hoàn tất, không thêm command xóa thường trực vào sản phẩm.

| Bảng | Hàng đã xóa | Còn lại |
| --- | ---: | ---: |
| support_sessions | 21 | 0 |
| support_messages | 134 | 0 |
| support_commands | 67 | 0 |
| support_reports | 10 | 0 |
| tickets | 21 | 0 |
| ticket_logs | 59 | 0 |
| ticket_schedules | 15 | 0 |
| ticket_attachments | 21 | 0 |
| notifications | 90 | 0 |
| activity_logs liên quan phiếu/phiên | 50 | 8 |
| support_idempotency_keys | 0 | 0 |

Giữ nguyên 14 users, 4 buildings, 4 rooms, 1 equipment, 6 categories, 16 staff_categories; counts trước/sau khớp. Giữ 8 activity logs khác. Giữ file ảnh gốc trên disk để bản sao dữ liệu có thể khôi phục; chỉ xóa liên kết attachment trong DB và khỏi giao diện. Không reset auto-increment hoặc đăng xuất tài khoản. Không gọi AI, gửi notification hoặc sửa chức năng app. Không chạy tests vì chỉ thao tác dữ liệu local; kiểm chứng là transaction/backup/hash/count thật, không thay bằng kết quả test SQLite.
