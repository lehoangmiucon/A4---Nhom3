# Các bảng hiện có trong database `helpdesk`

Danh sách được đối chiếu trực tiếp với MySQL/XAMPP. Database hiện có **21 bảng**; `activity_logs` là bảng mới đã có migration nhưng chưa chạy.

| Bảng | File migration tạo bảng | Mục đích |
|---|---|---|
| `users` | `0001_01_01_000000_create_users_table.php` | Tài khoản và vai trò `admin`, `staff`, `student`. |
| `password_reset_tokens` | `0001_01_01_000000_create_users_table.php` | Token đặt lại mật khẩu. |
| `sessions` | `0001_01_01_000000_create_users_table.php` | Phiên đăng nhập website. |
| `cache` | `0001_01_01_000001_create_cache_table.php` | Dữ liệu cache Laravel. |
| `cache_locks` | `0001_01_01_000001_create_cache_table.php` | Khóa đồng bộ cache Laravel. |
| `jobs` | `0001_01_01_000002_create_jobs_table.php` | Hàng đợi tác vụ nền Laravel, không phải phiếu Helpdesk. |
| `job_batches` | `0001_01_01_000002_create_jobs_table.php` | Nhóm tác vụ trong hàng đợi Laravel. |
| `failed_jobs` | `0001_01_01_000002_create_jobs_table.php` | Tác vụ nền Laravel thất bại. |
| `email_otps` | `2026_11_02_135001_create_email_otps_table.php` | Mã OTP xác thực email. |
| `buildings` | `2026_11_02_135002_create_buildings_table.php` | Tòa nhà/khu nhà. |
| `rooms` | `2026_11_02_135003_create_rooms_table.php` | Phòng học và mã QR. Cột `floor` được thêm trong `2026_11_02_140000_add_floor_to_rooms_table.php`. |
| `categories` | `2026_11_02_135004_create_categories_table.php` | Danh mục sự cố. Cột `color` được thêm trong `2026_11_02_150000_add_color_to_categories_table.php`. |
| `equipment` | `2026_11_02_135005_create_equipment_table.php` | Thiết bị trong phòng học; cột mã thiết bị được gỡ bằng `2026_11_03_150000_remove_code_from_equipment_table.php`. |
| `tickets` | `2026_11_02_135006_create_tickets_table.php` | Phiếu báo cáo sự cố. |
| `ticket_logs` | `2026_11_02_135007_create_ticket_logs_table.php` | Lịch sử trạng thái và xử lý phiếu. |
| `ticket_attachments` | `2026_11_02_135008_create_ticket_attachments_table.php` | Ảnh/tệp đính kèm phiếu. |
| Phân loại ảnh đính kèm | `2026_11_04_110000_add_attachment_type_to_ticket_attachments_table.php` | Tách ảnh sinh viên gửi (`report`) khỏi ảnh nhân viên sửa chữa (`repair`); dữ liệu cũ được phân loại theo người tải lên. |
| `ticket_schedules` | `2026_11_02_135009_create_ticket_schedules_table.php` | Lịch phân công kỹ thuật viên. |
| `notifications` | `2026_11_02_135010_create_notifications_table.php` | Thông báo Laravel lưu trong database. |
| `personal_access_tokens` | `2026_11_02_160000_create_personal_access_tokens_table.php` | Token Sanctum cho API/mobile. |
| `activity_logs` | `2026_11_03_100000_create_activity_logs_table.php` | Nhật ký thao tác của người dùng, đối tượng bị tác động và thông tin request. |
| `staff_categories` | `2026_11_03_120000_create_staff_categories_table.php` | Links technicians to the issue categories they can handle. |
| `migrations` | Laravel tự tạo khi chạy migration | Ghi nhận migration nào đã chạy; không có file migration tạo bảng này. |

Migration `2026_11_02_130000_create_helpdesk_core_tables.php` được giữ lại để bảo toàn lịch sử database. Mười bảng nghiệp vụ hiện có migration riêng. `activity_logs` là migration mới, đang chờ chạy. Các file `add_floor...` và `add_color...` chỉ thay đổi cột, không tạo bảng mới. Những migration tạo bảng có kiểm tra `Schema::hasTable()` để không tạo trùng các bảng đã tồn tại trong database hiện tại.


## Applied migration

`2026_11_03_120000_create_staff_categories_table.php` has been applied to the configured `helpdesk` database.

`2026_11_03_130000_add_furniture_issue_category.php` adds the `Bàn ghế` issue category if it does not already exist. It does not create a table.

This category migration has been applied to the configured `helpdesk` database.
