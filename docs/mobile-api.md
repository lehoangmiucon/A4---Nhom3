# API dùng chung cho web và mobile

API gốc: {APP_URL}/api. Ví dụ XAMPP:
http://<IP-máy-chủ>:8888/Laravelpro/helpdesk/public/api.
Điện thoại phải truy cập được máy chủ; localhost trên điện thoại là chính điện thoại.
Đặt APP_URL thành địa chỉ thiết bị truy cập được để URL ảnh và QR đúng. Khi triển khai dùng HTTPS.

## Xác thực và định dạng

- Mobile gửi Accept: application/json và Authorization: Bearer <access_token>.
- Đăng nhập yêu cầu email, password, device_name. Tên thiết bị trùng nhau thay token cũ của tài khoản trên thiết bị đó.
- Web vẫn dùng session; Sanctum chấp nhận cả session web và token mobile.
- API trả lỗi JSON kể cả khi thiếu Accept: 401 chưa đăng nhập, 403 sai quyền/tài khoản khóa, 404 không tồn tại, 422 dữ liệu không hợp lệ, 429 quá giới hạn.
- Chi tiết thường có dạng {"data": {...}}. Danh sách phân trang có data, current_page, last_page, total, per_page, next_page_url.
- Danh sách nhận page, per_page (1–100); lịch mặc định 50.
- avatar_url của tài khoản và url của ảnh phiếu là URL đầy đủ; password và remember_token được ẩn.
- Ngày lọc dùng YYYY-MM-DD; timestamp dùng ISO 8601. period=day|week|month|year theo múi giờ cấu hình máy chủ: ngày hiện tại, tuần từ thứ Hai đến hết thứ Bảy, tháng từ ngày 1 đến hết ngày cuối tháng, năm từ 1/1 đến hết 31/12. Không lấy 7/30/365 ngày lùi từ hôm nay.

## Đăng ký, OTP, tài khoản

| Method | Endpoint | Dữ liệu |
| --- | --- | --- |
| POST | /auth/register | name, email, password, password_confirmation |
| POST | /auth/email/verify-otp | email, otp gồm 6 chữ số |
| POST | /auth/email/resend-otp | email |
| POST | /auth/login | email, password, device_name |
| POST | /auth/logout | Thu hồi token thiết bị hiện tại |
| POST | /auth/password/forgot | email |
| POST | /auth/password/reset | email, otp, password, password_confirmation |
| GET | /me hoặc /account/profile | Hồ sơ, chuyên môn và ảnh của tài khoản đang đăng nhập |
| PATCH / POST | /account/profile | name, avatar (multipart, ảnh tối đa 5 MB) |
| POST | /account/password | current_password, password, password_confirmation |

Chỉ tự đăng ký yêu cầu email @ut.edu.vn và luôn tạo vai trò student; client không tự cấp quyền teacher/admin.
Admin cấp quyền hoặc tạo tài khoản nhân viên/quản lý qua API quản lý.
Quên mật khẩu áp dụng mọi email tài khoản đã xác thực và còn hoạt động.
OTP hết hạn sau 3 phút; chỉ được gửi lại sau 180 giây. Phản hồi gửi mã có server_time, otp_expires_at và otp_resend_available_at để web/mobile hiển thị đếm ngược.
Sau xác thực email, gọi /auth/login nhận access_token, token_type và user.
Sau đặt lại mật khẩu, đăng nhập lại. Phản hồi quên mật khẩu không tiết lộ email có tồn tại hay không.

## Dashboard và biểu đồ

| Method | Endpoint | Quyền |
| --- | --- | --- |
| GET | /admin/dashboard?period=month | Admin |
| GET | /staff/dashboard | Staff hoặc admin |
| GET | /customer/dashboard | Các vai trò được mở khu vực customer trên web; chỉ phiếu chính mình báo |
| GET | /admin/reports/summary?period=month | Admin; thống kê đã có |

Dashboard trả counts đủ sáu trạng thái, status_labels, total, recent_tickets,
unread_notifications, period, period_title, period_options.
Admin thêm unassigned, awaiting_confirmation, in_progress, teacher_reports,
categories[].tickets_count, average_resolution_hours, buildings[].open_tickets_count và rooms_count.
App dùng counts vẽ biểu đồ vòng, categories vẽ biểu đồ danh mục và buildings vẽ thống kê khu vực.
Staff thêm today_schedules, today_schedule_count, pending_acknowledgements.
Admin mặc định tháng hiện tại; staff/customer mặc định tất cả thời gian. Cả ba nhận period tùy chọn.
Lịch tuần/tháng lấy qua endpoint lịch với from, to.

## Phiếu sự cố, ảnh và QR

| Method | Endpoint | Chức năng |
| --- | --- | --- |
| GET | /rooms, /rooms/{code}, /categories | Tra phòng bằng mã/QR và danh mục |
| GET | /buildings | Tòa nhà kèm phòng cho tài khoản đăng nhập |
| POST | /tickets | Tạo phiếu với attachments[] |
| GET | /tickets | Danh sách phân trang tự giới hạn theo quyền |
| GET | /tickets/{id} | Chi tiết, ảnh, lịch, lịch sử; admin có eligible_staff phù hợp danh mục |
| PATCH / POST | /tickets/{id} | Người báo sửa phiếu còn trạng thái new |
| GET | /tickets/{id}/logs | Lịch sử xử lý |

Tạo phiếu: room_code, category_id, title, description; tùy chọn room_id, building_code, phone, priority, attachments[].
Sửa phiếu cần thêm room_id, building_code; remove_attachments=1 hoặc ảnh mới thay bộ ảnh báo cáo cũ.
Tải ảnh dùng multipart; ảnh báo cáo tối đa 5 ảnh, 5 MB mỗi ảnh. Giáo viên tự nhận ưu tiên high.
Tạo phiếu mới trả 201; nếu trùng theo điều kiện chống gửi lặp của web thì trả 200 kèm data.ticket_number của phiếu đang có.
Quét URL /r/{code} lấy code gọi /rooms/{code}. App tự tạo QR từ report_url của phòng.

GET /tickets hỗ trợ search (hoặc q), status, category (tên), category_id, room_id,
building (mã tòa), reporter_role, priority, period, from, to, page, per_page.
Admin xem tất cả; staff xem phiếu được giao; student/teacher xem phiếu của mình.
Nhân viên và giáo viên có thể gửi biểu mẫu tại /customer/portal hoặc quét QR phòng. Nhân viên xem phiếu đã gửi tại /customer/tickets; danh sách công việc vẫn chỉ gồm phiếu được giao. Người báo staff/student/teacher được sửa phiếu của mình khi còn chờ tiếp nhận.
Customer nhận ảnh báo cáo; ảnh sửa chữa và ghi chú nội bộ của lịch không được trả cho customer.
Các alias /admin/tickets và /staff/tickets dùng chung bộ lọc này.

## Phân công, lịch và xử lý

| Method | Endpoint | Chức năng |
| --- | --- | --- |
| GET | /admin/schedules, /staff/schedules | Danh sách phân trang, ID đầy đủ |
| GET | /admin/schedules/{id}, /staff/schedules/{id} | Chi tiết; staff chỉ xem lịch mình |
| POST | /admin/tickets/{id}/assign | Bắt buộc staff_id, scheduled_at, note; gửi thêm schedule_id khi sửa lịch |
| POST | /admin/schedules/{id}/cancel | Hủy lịch theo điều kiện nghiệp vụ hiện có |
| POST | /staff/schedules/{id}/acknowledge | Xác nhận bằng id trong danh sách lịch |
| PATCH / POST | /staff/tickets/{id}/status | note, status, attachments[] |
| POST | /admin/tickets/{id}/close | Duyệt hoàn thành, tùy chọn note |
| POST | /admin/tickets/{id}/reopen | Bắt buộc note yêu cầu sửa lại |

Lịch hỗ trợ from, to, staff_id (chỉ admin lọc người khác), category_id, ticket_status,
status=scheduled|acknowledged|cancelled, include_cancelled=1, page, per_page.
Mặc định ẩn lịch hủy; không còn giới hạn cứng 30 ngày trước.
Nhân viên phải có chuyên môn phù hợp; xác nhận lịch rồi gửi ghi chú/ảnh sửa chữa; quản lý mới được đóng phiếu.
Ứng dụng dùng cùng quy tắc nghiệp vụ và quyền với website.

## Quản lý người dùng, đội ngũ, địa điểm

Toàn bộ endpoint trong bảng yêu cầu admin.

| Method | Endpoint | Chức năng |
| --- | --- | --- |
| GET | /admin/users | Danh sách có số phiếu; role, search, status, page, per_page |
| GET | /admin/users/summary | Tổng student/teacher, xác thực, khóa, số phiếu tháng |
| GET | /admin/users/{id} | Hồ sơ và số phiếu theo trạng thái |
| GET | /admin/users/{id}/tickets | Phiếu đã báo, phân trang |
| GET | /admin/team | Đội ngũ; role, status, search, category_id, page, per_page |
| GET | /admin/team/summary | Tổng quản lý, nhân viên, xác thực, khóa |
| GET | /admin/team/{id} | Chi tiết quản lý/nhân viên và số lịch |
| GET | /admin/team/{id}/tickets | Phiếu được giao, phân trang |
| POST | /admin/team | Tạo quản lý/nhân viên |
| GET / POST | /admin/staff | Nhân viên hoạt động / tạo nhân viên |
| PUT | /admin/staff/{id} | Sửa thông tin, mật khẩu, chuyên môn |
| PATCH | /admin/users/{id}/role | role=student|teacher|staff|admin |
| PATCH | /admin/users/{id}/status | is_deleted để khóa/mở |
| GET / POST | /admin/categories | Danh mục / tạo danh mục |
| PUT / DELETE | /admin/categories/{id} | Sửa / xóa danh mục |
| GET / POST | /admin/buildings | Tòa nhà kèm phòng và số phiếu / tạo tòa |
| GET | /admin/buildings/{id} | Chi tiết, phòng và thiết bị |
| PUT / PATCH / DELETE | /admin/buildings/{id} | Sửa / xóa tòa |
| POST | /admin/buildings/{id}/rooms | Tạo phòng |
| GET | /admin/rooms/{id} | Chi tiết, thiết bị, phiếu phân trang, report_url để tạo QR |
| PUT / PATCH / DELETE | /admin/rooms/{id} | Sửa / xóa phòng |
| GET | /admin/locations/summary | Số tòa nhà và phòng |

Lịch của một nhân viên: /admin/schedules?staff_id={id}.
Chi tiết người dùng trả data.user, reported_counts, assigned_counts, schedules_count.
status lọc tài khoản: active|unverified|locked.
API cũ /admin/tickets/filter, /admin/team/filter, /admin/schedules/filter vẫn trả ID cho web;
mobile dùng endpoint danh sách đầy đủ tương ứng.

## Thông báo

GET /notifications trả phân trang và unread_count. Mỗi thông báo có ticket_id, title, body, status,
created_at, read_at, payload đầy đủ để app nhóm theo phiếu.
Đánh dấu đọc: PATCH /notifications/{id}/read, /notifications/tickets/{ticketId}/read hoặc /notifications/read-all.
Đây là thông báo lưu trong hệ thống như web. Thông báo hệ điều hành khi app đóng cần Firebase/APNs
và cấu hình riêng của ứng dụng mobile; dự án chưa tích hợp dịch vụ push.

## Tên ảnh

Ảnh tải lên hoặc thay mới được đặt tên tự động theo mã:

- Ảnh báo cáo: `{mã_phiếu}_REPORT_{YYYYMMDD}_{HHMMSS}_{4_số}.png`.
- Ảnh sửa chữa: `{mã_phiếu}_REPAIR_{YYYYMMDD}_{HHMMSS}_{4_số}.png`.
- Ảnh đại diện: `USER-{id}_AVATAR_{YYYYMMDD}_{HHMMSS}_{4_số}.png`.

Ví dụ: `USER-1_AVATAR_20261114_143025_4821.png`.
Ngày giờ theo múi giờ Việt Nam, kèm 4 số ngẫu nhiên. Nếu tên đã tồn tại, hệ thống tạo tên khác.
Đuôi file theo định dạng ảnh thực tế.
`original_name` của ảnh phiếu là tên đã chuẩn hóa; dùng URL API trả về để hiển thị ảnh.

## Kiểm tra

Chạy C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit tests/Feature/MobileApiTest.php.
Bộ kiểm thử dùng SQLite :memory: và từ chối chạy nếu database không phải SQLite trong bộ nhớ.
Không chạy migration kiểm thử trên database helpdesk đang sử dụng.
