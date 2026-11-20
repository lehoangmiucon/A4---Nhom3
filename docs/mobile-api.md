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

## Phiên hỗ trợ AI — nền tảng M1a

Bốn endpoint M1a bên dưới vẫn được giữ cho tương thích. CR-01 bổ sung POST /support-conversations, POST /support-sessions/{supportSession}/messages, POST /support-sessions/{supportSession}/escalations, GET /support-sessions/{supportSession}/conversation và GET /support-sessions/{supportSession}/commands/{command}. Xem [contract CURRENT_CODE CR-01, mục 2.1](<tài liệu AI/03_DU_LIEU_VA_API.md>) cho payload, expected_version, receipt và retry. Portal dùng luồng CR-01; Flutter chưa triển khai. Bốn migration support đã có evidence trên local (batch 15/16/17), chưa xác nhận production; máy nhận phải kiểm tra migrate:status theo [hướng dẫn local/API](local-ai.md). Bốn endpoint M1a không cần khóa AI; gửi báo cáo CR-01 cũng không phụ thuộc model.

| Method | Endpoint | Dữ liệu / kết quả |
| --- | --- | --- |
| POST | /support-sessions | room_id, equipment_id tùy chọn, description tối đa 1.000 ký tự; bắt buộc header Idempotency-Key |
| GET | /support-sessions | status tùy chọn; page; per_page 1–50, mặc định 20; mới nhất trước |
| GET | /support-sessions/{supportSession} | Đọc lại phiên, mã tin đầu và nhãn phòng/thiết bị |
| GET | /support-sessions/{supportSession}/messages | Tin cũ đến mới; page, per_page 1–50; after_id để lấy các tin sau một ID |

Chỉ student/teacher đang hoạt động và đã xác thực email được sử dụng. Chỉ chủ phiên xem được; phiên người khác và ID không tồn tại cùng trả 404. Admin/staff chưa có quyền xem phiên ở M1a. Server xác định người báo; không nhận user_id, status, version hoặc messages do client gán khi tạo.

Tạo Idempotency-Key một lần cho mỗi ý định tạo phiên và giữ nguyên khi mạng yếu/gửi lại. Khóa dài tối đa 128 ký tự ASCII gồm chữ, số, dấu chấm, gạch dưới, hai chấm hoặc gạch ngang. Hai yêu cầu khác nhau phải dùng khóa khác nhau. Server lưu khóa dạng SHA-256; unique constraint và khóa giao dịch bảo vệ bản ghi. Cùng khóa/cùng nội dung trả nguyên dữ liệu tạo ban đầu và HTTP 201, không tạo phiên/tin thứ hai. Khác nội dung trả 422 với error.code IDEMPOTENCY_KEY_REUSED. GET chi tiết là trạng thái hiện tại; phản hồi replay là snapshot khi tạo, có thể khác nếu phòng/thiết bị đã bị xóa.

Phiên mới có status=collecting, version=1, initial_message_id, initial_description (nội dung tin đầu, nullable), room_id, equipment_id, các nhãn snapshot, handoff=null và permitted_actions=[] là các field M1a còn giữ tương thích; portal CR-01–13 có hành động riêng theo contract 03, không dùng mảng rỗng này để suy luận mọi hành động đều chưa có. initial_description được thêm ở M1b; replay key cũ giữ nguyên snapshot đã lưu và có thể thiếu trường này, dùng GET để lấy DTO hiện tại. Không suy luận rằng đã tạo phiếu từ việc tạo phiên.

Phản hồi endpoint mới dùng data và meta.request_id; danh sách có meta.pagination với current_page, per_page, total, last_page. Lỗi dùng error.code, error.message, error.details và meta.request_id. Mã lỗi hiện có: UNAUTHENTICATED, FORBIDDEN, NOT_FOUND, VALIDATION_ERROR, IDEMPOTENCY_KEY_REUSED, RATE_LIMITED, SERVICE_UNAVAILABLE. Nội dung trả có Cache-Control: no-store. POST tạo phiên giới hạn 30 yêu cầu/phút/tài khoản; khi 429 tuân theo Retry-After, không tạo key mới chỉ để lách giới hạn.

Mô tả được lưu thành một tin role=user ở server trong cùng giao dịch với phiên và kết quả idempotency. Phòng bắt buộc tồn tại khi tạo ý định mới; thiết bị phải thuộc phòng. Khi xóa phòng hoặc thiết bị, FK được null và nhãn snapshot vẫn còn; không mất hội thoại. Xóa vật lý tài khoản có dữ liệu hỗ trợ bị FK chặn; quy trình retention/ẩn danh sẽ được triển khai riêng.

### Bổ sung CURRENT_CODE CR-11–13

- `DELETE /support-sessions/{supportSession}`: xóa mềm phiên của chủ student/teacher active/verified, chỉ handed_off có report gắn ticket closed. Ticket resolved còn chờ quản lý nên không được xóa. Không body/Idempotency-Key; thành công 200 data={id,deleted:true}; cùng chủ retry 200, khác chủ 404, chưa đủ điều kiện 422 error.details.session. Giữ ticket/report/transcript/command; GET và replay phiên đã xóa nhận 404.
- DTO phiên bổ sung ticket_status nullable, is_completed/can_delete boolean do backend tính; không dùng can_delete ở client thay kiểm tra server.
- Chat cần room/category/mô tả trước hướng dẫn, thiếu thì hỏi trong messages; response bổ sung needs_description và diagnosis (branch, stage, observations, unknowns, next_question, technical_suggestions). Xem schema trường và enum ở contract 03 mục 2.1; mobile không tự đặt các cờ hoặc nhận snapshot replay là state mới.
- CR-13 nhận ý định chuyển KTV rõ ràng trước gọi model. Đường gửi report bypass quota AI nhưng vẫn cần dữ kiện hợp lệ/quyền/idempotency. Tối đa trước ghi 200 tin/chat mặc định, command chat 40/phiên và 12/phút; đây không phải giới hạn token/tiền.

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

Đổi mật khẩu được giới hạn **5 yêu cầu/phút cho mỗi tài khoản**. `POST /api/account/password` và `POST /account/password` trên web dùng chung bộ đếm, không tách theo token, thiết bị hay IP; tài khoản khác có bộ đếm riêng. Mọi yêu cầu qua middleware đều tính lượt, kể cả dữ liệu sai hoặc đổi mật khẩu thành công. Từ yêu cầu thứ 6 trong cửa sổ 60 giây trả **429**, có header `Retry-After` cho biết số giây cần chờ. App hiển thị thời gian chờ, không tự gửi lại liên tục; lỗi 429 không làm thay đổi mật khẩu hoặc thu hồi token.

Chỉ tự đăng ký yêu cầu email @ut.edu.vn và luôn tạo vai trò student; client không tự cấp quyền teacher/admin.
Admin cấp quyền hoặc tạo tài khoản nhân viên/quản lý qua API quản lý.
Quên mật khẩu áp dụng mọi email tài khoản đã xác thực và còn hoạt động.
OTP hết hạn sau 5 phút; gửi lại cách nhau ít nhất 60 giây.
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
| GET | /tickets/{ticket}/attachments/{attachment} | Dữ liệu ảnh sửa chữa; chỉ admin hoặc staff đang được giao phiếu |

Tạo phiếu: room_code, category_id, title, description; tùy chọn room_id, building_code, phone, priority, attachments[].
Sửa phiếu cần thêm room_id, building_code; remove_attachments=1 hoặc ảnh mới thay bộ ảnh báo cáo cũ.
Tải ảnh dùng multipart; ảnh báo cáo tối đa 5 ảnh, 5 MB mỗi ảnh. Giáo viên tự nhận ưu tiên high.
Tạo phiếu mới trả 201; nếu trùng theo điều kiện chống gửi lặp của web thì trả 200 kèm data.ticket_number của phiếu đang có.
Quét URL /r/{code} lấy code gọi /rooms/{code}. App tự tạo QR từ report_url của phòng.

GET /tickets hỗ trợ search (hoặc q), status, category (tên), category_id, room_id,
building (mã tòa), reporter_role, priority, period, from, to, page, per_page.
Admin xem tất cả; staff xem phiếu được giao; student/teacher xem phiếu của mình.
Customer nhận ảnh báo cáo; ảnh sửa chữa và ghi chú nội bộ của lịch không được trả cho customer.
Các alias /admin/tickets và /staff/tickets dùng chung bộ lọc này.

### Tải ảnh minh chứng sửa chữa (SEC-01)

Ảnh có `attachment_type=repair` nằm trên disk riêng tư. Thuộc tính `url` là URL đầy đủ của endpoint ở bảng trên; dùng URL API trả về, không ghép đường dẫn `/storage/` từ `path` hoặc tên file.

- Mobile phải gửi `Authorization: Bearer <access_token>` trong **request tải ảnh**, kể cả khi request lấy chi tiết phiếu trước đó đã có token. Cấu hình header cho thư viện hiển thị ảnh, hoặc tải bytes có header rồi hiển thị. Không truyền token qua query string.
- Web sử dụng session Sanctum đang đăng nhập cho ảnh và xem trước ảnh.
- API thành công trả trực tiếp dữ liệu ảnh theo MIME thực tế, không bọc JSON. Phản hồi có `Cache-Control: private, no-store` và `X-Content-Type-Options: nosniff`; client không lưu ảnh này vào cache dùng chung hoặc cache bền vững.
- Chưa đăng nhập/token không hợp lệ trả 401; sai quyền hoặc tài khoản bị khóa trả 403. Ảnh không tồn tại, sai phiếu, không phải loại `repair` hoặc thiếu file riêng tư trả 404 với người đã qua các kiểm tra tương ứng.
- Admin xem được ảnh; staff chỉ xem khi đang được giao phiếu. Student/teacher không được xem ảnh sửa chữa của chính phiếu mình báo. Khi đổi người phụ trách, staff cũ mất quyền; khi mở lại phiếu, URL ảnh cũ không còn hợp lệ.

Ảnh báo cáo và avatar vẫn dùng URL công khai hiện có. Máy chủ nâng cấp từ bản cũ phải chuyển dữ liệu theo [báo cáo bảo mật hiện có](<../tài liệu cá nhân/SECURITY_AUDIT.md>) (hướng dẫn SEC-01 riêng chưa có trong checkout); không có cơ chế đọc dự phòng ảnh sửa chữa từ public.

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

### Admin đổi mật khẩu nhân viên (SEC-02)

`PUT /admin/staff/{id}` vẫn nhận `name`, `category_ids`, tùy chọn `phone`, `password` và yêu cầu quyền admin. Khi gửi mật khẩu mới hợp lệ, khác mật khẩu hiện tại, hệ thống thu hồi **toàn bộ Bearer token** của nhân viên, xoay `remember_token` và vô hiệu hóa các phiên web. Lưu hồ sơ, mật khẩu, chuyên môn và xóa token trong cùng transaction; lỗi thu hồi sẽ rollback thay đổi.

Token cũ gọi API từ request tiếp theo nhận **401**; app cần xóa thông tin đăng nhập cục bộ và đưa người dùng về màn hình đăng nhập. Đăng nhập lại bằng mật khẩu mới để nhận token mới. Thông báo thành công là “Đã đổi mật khẩu và đăng xuất nhân viên khỏi các thiết bị.”

Không gửi `password`, hoặc gửi rỗng/null, chỉ cập nhật hồ sơ/chuyên môn và giữ nguyên đăng nhập. Mật khẩu không hợp lệ hoặc trùng mật khẩu hiện tại trả 422, giữ nguyên mật khẩu/token. Phiên và token của admin hoặc tài khoản khác không bị thu hồi. Đây là hành vi admin đặt lại mật khẩu; người dùng tự đổi mật khẩu vẫn áp dụng quy tắc giữ phiên/token hiện tại của endpoint tương ứng.

Khi triển khai bản sửa, phiên web cũ của staff chưa có dấu kiểm tra thông tin đăng nhập phải đăng nhập lại một lần. Xem [báo cáo bảo mật hiện có](<../tài liệu cá nhân/SECURITY_AUDIT.md>) (hướng dẫn SEC-02 riêng chưa có trong checkout) về triển khai và kiểm chứng.

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
