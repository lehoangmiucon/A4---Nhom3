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
5. Chạy `php artisan storage:link` để truy cập ảnh báo cáo và ảnh đại diện. Ảnh minh chứng sửa chữa lưu riêng tư và được tải qua API có kiểm tra quyền.
6. Trỏ document root của Apache vào thư mục `public`, hoặc dùng `php artisan serve`.
7. Đăng ký tài khoản, xác thực OTP rồi cấp quyền admin cho tài khoản đầu tiên bằng `php artisan helpdesk:make-admin email@domain.test`.
8. Mở thêm một cửa sổ PowerShell và chạy `php artisan schedule:work` để scheduler tự dọn tài khoản chưa xác thực sau 24 giờ khi đang phát triển local.

Repository chứa mã nguồn và migration; `.env`, `vendor`, cache, database cục bộ và ảnh do người dùng tải lên không được đưa lên Git. GitHub Pages chỉ phục vụ trang tĩnh, không chạy được backend Laravel/PHP của dự án.

Khi cập nhật hệ thống đã có ảnh sửa chữa, chạy `php artisan attachments:privatize-repairs --dry-run` rồi `php artisan attachments:privatize-repairs` trên từng máy lưu ảnh. Xem [báo cáo bảo mật hiện có](<tài liệu cá nhân/SECURITY_AUDIT.md>) (file hướng dẫn SEC-01 riêng chưa có trong checkout) về chặn URL cũ, chuyển ảnh và kiểm tra sau triển khai. Trạng thái các phát hiện nằm trong [báo cáo bảo mật](<tài liệu cá nhân/SECURITY_AUDIT.md>).

Khi `MAIL_MAILER=log`, mã OTP được ghi vào log Laravel để phát triển trên máy. Muốn gửi email thật, cấu hình SMTP trong `.env`.

SEC-02: admin đổi mật khẩu nhân viên sẽ thu hồi toàn bộ Bearer token và vô hiệu hóa phiên staff. Khi cập nhật bản sửa, phiên web staff cũ cần đăng nhập lại một lần. Xem [báo cáo bảo mật hiện có](<tài liệu cá nhân/SECURITY_AUDIT.md>) (file hướng dẫn SEC-02 riêng chưa có trong checkout).

## Trợ lý AI trên trang báo sự cố

Tài liệu sở hữu hiện trạng/thiết kế: [Tài liệu AI](<docs/tài liệu AI/README.md>), [task duy nhất](tasks/todo.md) và [kế hoạch nhận việc](tasks/plan.md). Cập nhật đến CR-13 ngày 20/11/2026; không dùng tài liệu cá nhân cũ thay contract hiện hành.

Portal có **Báo cáo thủ công / Nhờ AI hỗ trợ**, hội thoại lưu server, khôi phục/phân trang, hỏi phòng/công việc trước hướng dẫn và phân nhánh quan sát ban đầu. Yêu cầu “hãy báo cáo lên KTV cho mình” hoặc nút gửi báo cáo tạo phiếu ngay trong chat khi đủ dữ kiện; không bắt điền nháp vào form. Xóa mềm chỉ cho chủ phiên đã báo cáo với phiếu được quản lý xác nhận hoàn tất. Hồ sơ IT có tóm tắt, kết quả người dùng nêu và gợi ý chưa xác minh.

1. Cài dependency và cấu hình database trên máy của bạn; kiểm tra các migration support trước khi dùng. Chỉ chạy migration ở môi trường đã xác định, không migrate:fresh để cập nhật.
2. Chọn OpenAI API hoặc Ollama theo [hướng dẫn local/API](docs/local-ai.md). Key chỉ lưu ở server; không có cloud fallback tự động. Đổi provider giữ contract/luật backend, không có nghĩa mọi model đều tương thích hoặc đã được đánh giá.
3. Đăng nhập student/teacher active, verified, mở `/customer/portal` → **Nhờ AI hỗ trợ**. Chọn/nêu phòng có thật, loại thiết bị/công việc và mô tả dấu hiệu. Gửi báo cáo sẽ ghi phiếu/notification thật vào DB đang chọn.

Checkpoint CR-13: **276 test PHP / 2892 assertions, 62 test JavaScript** và Chrome với backend SQLite :memory:/model giả; [evidence](tasks/verification-cr13.md). Đã smoke Ollama thật ở checkpoint cũ, chưa eval model thật sau CR-13, chưa nghiệm thu staging/production. Guide published, outbox, app Flutter và kiểm soát chi phí vận hành đầy đủ còn TARGET; không gọi agent đã hoàn thiện chỉ từ số test đạt.

## Luồng API hiện có

Swagger UI để xem và gọi thử API: mở /api. Xem [hướng dẫn Swagger](docs/swagger.md).

Tài liệu endpoint dùng chung cho website và app mobile: [docs/mobile-api.md](docs/mobile-api.md).
API có đăng ký/OTP, hồ sơ và ảnh đại diện, dashboard theo vai trò, danh sách lịch/phiếu phân trang,
chi tiết người dùng/đội ngũ, địa điểm và thông báo. Mobile đăng nhập lấy Bearer token qua /api/auth/login.

- Đăng ký sinh viên, xác thực/gửi lại OTP, đăng nhập và đăng xuất bằng session cookie.
- Tra cứu phòng QR, tạo phiếu có ảnh; sinh viên chỉ xem phiếu của mình.
- Quản lý xem phiếu, phân công nhân viên/lịch, đóng hoặc mở lại phiếu.
- Nhân viên xem phiếu được giao, lịch cá nhân và cập nhật tiến độ/ảnh kết quả.
- Lịch sử trạng thái được lưu trong `ticket_logs`; thông báo được lưu theo Laravel Notifications.

Các trang HTML được phục vụ qua route Laravel và yêu cầu đúng vai trò. Tài nguyên giao diện nằm trong `public/admin`, `public/auth`, `public/customer`, `public/staff` và `public/home`; logo và JavaScript dùng chung nằm trong `public/shared`. Form báo sự cố sử dụng `resources/views/customer/portal.blade.php`.

## Thông báo thời gian thực

MySQL Notifications là lịch sử bền vững. Bước tiếp theo là Laravel Reverb + Laravel Echo để đẩy cập nhật tới website đang mở; Flutter dùng FCM khi bắt đầu làm app. WebSocket là kênh đẩy, không thay thế bảng notifications. Các tác vụ gửi email/push nên chạy qua queue worker.
