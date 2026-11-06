# Helpdesk phòng học

Backend Laravel phục vụ website HTML/CSS hiện có và API dùng chung cho ứng dụng Flutter sau này.

Các tối ưu tải trang, số đo trước/sau và cách chạy kiểm tra hồi quy được ghi tại [docs/performance.md](docs/performance.md).

## Chạy với XAMPP

Yêu cầu PHP 8.2 trở lên, Composer và MySQL. Sau khi clone repository, mở PowerShell tại thư mục dự án:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

1. Mở XAMPP Control Panel và bật Apache, MySQL.
2. Mở phpMyAdmin và tạo database `helpdesk` nếu chưa có. Migration trong `database/migrations` mô tả cấu trúc bảng của hệ thống.
3. Kiểm tra `.env`: `DB_CONNECTION=mysql`, host `127.0.0.1`, port `3309` theo cấu hình máy hiện tại; đổi port và thông tin đăng nhập cho phù hợp máy cài đặt.
4. Chạy `php artisan migrate` để tạo bảng. Không cần tạo từng bảng bằng tay trong phpMyAdmin.
5. Chạy `php artisan storage:link` để truy cập ảnh minh chứng.
6. Trỏ document root của Apache vào thư mục `public`, hoặc dùng `php artisan serve`.
7. Đăng ký tài khoản, xác thực OTP rồi cấp quyền admin cho tài khoản đầu tiên bằng `php artisan helpdesk:make-admin email@domain.test`.

Repository chứa mã nguồn và migration; `.env`, `vendor`, cache, database cục bộ và ảnh do người dùng tải lên không được đưa lên Git. GitHub Pages chỉ phục vụ trang tĩnh, không chạy được backend Laravel/PHP của dự án.

Khi `MAIL_MAILER=log`, mã OTP được ghi vào log Laravel để phát triển trên máy. Muốn gửi email thật, cấu hình SMTP trong `.env`.

## Luồng API hiện có

- Đăng ký sinh viên, xác thực/gửi lại OTP, đăng nhập và đăng xuất bằng session cookie.
- Tra cứu phòng QR, tạo phiếu có ảnh; sinh viên chỉ xem phiếu của mình.
- Quản lý xem phiếu, phân công nhân viên/lịch, đóng hoặc mở lại phiếu.
- Nhân viên xem phiếu được giao, lịch cá nhân và cập nhật tiến độ/ảnh kết quả.
- Lịch sử trạng thái được lưu trong `ticket_logs`; thông báo được lưu theo Laravel Notifications.

Các trang HTML được phục vụ qua route Laravel và yêu cầu đúng vai trò. Tài nguyên giao diện nằm trong `public/admin`, `public/auth`, `public/customer`, `public/staff` và `public/home`; logo và JavaScript dùng chung nằm trong `public/shared`. Form báo sự cố sử dụng `resources/views/customer/portal.blade.php`.

## Thông báo thời gian thực

MySQL Notifications là lịch sử bền vững. Bước tiếp theo là Laravel Reverb + Laravel Echo để đẩy cập nhật tới website đang mở; Flutter dùng FCM khi bắt đầu làm app. WebSocket là kênh đẩy, không thay thế bảng notifications. Các tác vụ gửi email/push nên chạy qua queue worker.
