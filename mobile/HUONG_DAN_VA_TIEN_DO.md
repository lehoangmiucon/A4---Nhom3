# Helpdesk UTH — Tiến độ và hướng dẫn chạy mobile

Cập nhật ngày 08/11/2026. Nhánh làm việc: `trong-le`.

## Đã thực hiện

### Môi trường

- Flutter, Android Studio và Android SDK đã thiết lập; SDK nằm ở `D:\Android\Sdk`.
- Project Flutter Android nằm trong thư mục `mobile/`, dùng chung repository với Laravel.
- Đã chạy app trên máy ảo Android `emulator-5554` và đăng nhập thành công bằng tài khoản thật.
- Đã build APK debug thành công. Do project ở ổ D và Pub cache ở ổ C, đã tắt Kotlin incremental cache trong `mobile/android/gradle.properties` để tránh lỗi đường dẫn giữa hai ổ.

### Xác thực mobile

- Đăng nhập bằng email hoặc mã sinh viên. Kỹ thuật viên dùng email và mật khẩu do admin cấp.
- Đăng ký sinh viên: bắt buộc mã sinh viên.
- Đăng ký giảng viên: không có ô mã sinh viên, backend không yêu cầu mã.
- Xác minh email bằng OTP, gửi lại OTP và màn xác minh thành công.
- Xác minh email xong được dùng app, không có bước quản trị viên duyệt.
- Loại thành viên sinh viên/giảng viên được lưu riêng; cả hai dùng quyền `student` cho nghiệp vụ báo sự cố hiện tại.
- Quên mật khẩu, gửi mã qua email và đặt mật khẩu mới.
- Token đăng nhập được lưu bằng kho bảo mật Android. Hiện chưa tự khôi phục phiên khi mở lại app.

### Trang người dùng và phiếu

- Trang chủ có lời chào, thao tác nhanh và phiếu gần đây lấy từ API.
- Danh sách phiếu, phân trang, kéo làm mới và xử lý trạng thái rỗng/lỗi.
- Chi tiết phiếu: trạng thái, phòng, người xử lý, mô tả và lịch sử.
- Tab cá nhân hiện có thông tin cơ bản và đăng xuất.
- Bấm Báo sự cố từ trang chủ đi thẳng tới bước 1/2.
- Bước 1: chọn tòa, phòng, danh mục và nhập tiêu đề.
- Bước 2: mô tả, mức ảnh hưởng, chọn/chụp tối đa 5 ảnh, mỗi ảnh tối đa 5 MB.
- Gửi phiếu multipart tới Laravel; màn thành công hiển thị mã phiếu thật, vị trí, trạng thái, nút xem chi tiết và về trang chủ.
- Quay lại giữa bước 1 và 2 giữ nội dung trong bộ nhớ; chưa lưu bản nháp khi app đóng.
- Nếu gửi bị timeout, kiểm tra danh sách phiếu trước khi gửi lại để tránh trùng. App không tự retry yêu cầu tạo phiếu.
- Menu Tòa, Phòng và Danh mục dùng chung kiểu: nền trắng, bo góc, chữ 14, mỗi mục cao 48, menu cao tối đa 240 và cuộn khi nhiều mục.
- Đã bỏ dòng “Tên và email được lấy từ tài khoản đăng nhập” khỏi bước 1.

### QR

- Nhập mã thủ công → backend tra cứu → hiển thị tòa/phòng để xác nhận → báo sự cố.
- Đã tích hợp camera bằng `mobile_scanner`: quét mã → backend xác minh → tự điền tòa/phòng.
- Hỗ trợ mã thuần, URL `/r/{code}`, `/report/{code}` và query `room_code`/`room`. Chỉ trích mã để tra cứu, không mở URL quét được.
- Chưa thử camera với QR phòng thực tế. Máy ảo cần nguồn camera phù hợp; nên thử thêm trên Samsung.
- Backend hiện chưa có trường cơ sở, nên chỉ hiển thị tòa và phòng thật, không điền dữ liệu cơ sở giả.

### Web và backend dùng chung

- Web có liên kết Quên mật khẩu, trang yêu cầu mã và trang đặt mật khẩu mới.
- Sau khi yêu cầu mã, thông báo chung hiện 1,5 giây rồi chuyển trang. Email được giữ qua session, không yêu cầu nhập lại.
- Placeholder mã: “Nhập mã đặt lại tại đây”, chữ nhỏ.
- Email HTML và plain text được trình bày lại; không có nút copy hoặc trang phụ.
- Mã khôi phục gồm 6 chữ cái ngẫu nhiên A–Z, lưu dạng hash, hạn 10 phút, dùng một lần.
- Gửi lại mã có cooldown 60 giây; mã mới thay mã cũ. Tối đa 5 lần thử mỗi email trong 10 phút, dùng chung web/mobile; gửi lại không xóa bộ đếm thử sai.
- Đặt lại mật khẩu thành công thu hồi các token API cũ.
- Cần SMTP hoạt động để gửi email thật.

## Git và kiểm tra

- Phần web/backend trước đó đã push lên `origin/trong-le`, commit `33dd7f6`: “Hoàn thiện xác thực và khôi phục mật khẩu cho web”.
- Sau commit đó, backend sửa thêm `app/Http/Controllers/MobileAuthController.php` và `tests/Feature/MobileAuthTest.php` để giảng viên đăng ký không cần mã. Hai file này chưa commit/push tại thời điểm ghi tài liệu.
- Toàn bộ `mobile/` chưa commit/push. Chờ người dùng yêu cầu sau khi làm xong; không tự commit mobile.
- Lần kiểm thử gần nhất: 18 kiểm thử Laravel và 9 kiểm thử Flutter đạt. Các chỉnh sửa menu sau đó đã qua `flutter analyze`.
- Build Android có camera QR đã thành công. Chưa xác nhận gửi phiếu kèm ảnh/camera QR trực tiếp trên thiết bị thật.

## Mở mobile để làm việc

### 1. Bật database

Mở XAMPP và Start MySQL, đợi dòng MySQL hiện xanh. Máy hiện dùng cổng MySQL `3308`; `.env` Laravel phải khớp cấu hình đang chạy.

Không cần Apache khi dùng `artisan serve` bên dưới. Muốn mở web qua `http://localhost:8888/helpdesk-nhom3/public` thì bật thêm Apache.

### 2. Terminal thứ nhất: chạy Laravel

Mở PowerShell:

```powershell
cd D:\xampp\htdocs\helpdesk-nhom3
& "D:\xampp\php\php.exe" artisan serve --host=0.0.0.0 --port=8000
```

Thấy `Server running on [http://0.0.0.0:8000]` là được. Giữ terminal mở.

Nếu vừa đổi `.env`, dừng server bằng Ctrl+C, chạy lệnh sau rồi mở server lại:

```powershell
& "D:\xampp\php\php.exe" artisan config:clear
```

### 3. Bật máy ảo

Android Studio → Device Manager / Virtual Device Manager → bấm ▶ ở máy ảo → đợi màn hình Android xuất hiện.

### 4. Terminal thứ hai: chạy Flutter

```powershell
cd D:\xampp\htdocs\helpdesk-nhom3\mobile
flutter devices
flutter run -d emulator-5554
```

Nếu ID máy ảo khác, dùng ID từ `flutter devices`. Giữ terminal mở. App mặc định gọi Laravel qua `http://10.0.2.2:8000/api`; đây là địa chỉ máy host nhìn từ Android emulator.

Thông thường không cần chạy lại `flutter doctor` hay cài lại SDK mỗi lần mở app.

## Các phím khi Flutter đang chạy

Nhấn trong terminal Flutter, không phải terminal Laravel.

| Phím | Khi dùng |
| --- | --- |
| `r` thường | Hot reload: cập nhật giao diện/code nhanh, thường giữ trạng thái đang dùng. Đóng menu đang mở rồi mở lại để thấy thay đổi menu. |
| `R` hoa | Hot restart: chạy lại app từ đầu, mất trạng thái trong bộ nhớ và có thể phải đăng nhập lại. |
| `q` | Dừng app và kết thúc `flutter run`. |

Sau khi thêm plugin có phần Android (ví dụ image_picker, mobile_scanner), sửa cấu hình Android hoặc đổi tham số chạy: nhấn `q` rồi chạy lại `flutter run -d emulator-5554`. Hot reload/restart không đủ cho thay đổi native này.

Nếu terminal đã trở lại dấu nhắc `PS ...>`, không cần nhấn q, chỉ chạy lại lệnh Flutter.

## Tắt khi nghỉ

1. Trong terminal Flutter, nhấn `q`.
2. Đóng cửa sổ máy ảo; có thể đóng Android Studio.
3. Trong terminal Laravel, nhấn Ctrl+C.
4. XAMPP → Stop MySQL. Nếu Apache đang chạy và không dùng web nữa thì Stop Apache.
5. Đóng các terminal.

Nếu chỉ nghỉ ngắn, có thể giữ môi trường đang chạy. Nếu tắt laptop, lần sau làm lại các bước mở ở trên.

## Chạy trên Samsung sau này

1. Bật Tùy chọn nhà phát triển và Gỡ lỗi USB trên điện thoại.
2. Cắm cáp truyền dữ liệu, mở khóa và cho phép gỡ lỗi USB.
3. Chạy `flutter devices` để lấy ID điện thoại.
4. Điện thoại thật cần truy cập được máy chạy Laravel, ví dụ qua cùng mạng Wi-Fi. Không dùng `10.0.2.2` cho điện thoại thật.
5. Chạy với địa chỉ IP mạng của máy tính (thay các giá trị minh họa bằng giá trị thật):

```powershell
flutter run -d ID_DIEN_THOAI --dart-define=API_BASE_URL=http://IP_MAY_TINH:8000/api
```

Laravel, MySQL và kết nối mạng cần hoạt động; nếu chưa truy cập được thì kiểm tra địa chỉ, cổng và firewall. Không cần cấu hình Samsung để tiếp tục làm trên máy ảo.

## Tiếp tục ở lần làm việc sau

- Đọc tài liệu này và `mobile/README.md`, kiểm tra `git status` trước khi sửa.
- Tiếp tục theo góp ý giao diện/nghiệp vụ của người dùng; chưa được yêu cầu hoàn thiện tất cả các màn còn lại.
- Các phần còn cần thử/hoàn thiện: camera QR với mã phòng thật, gửi ảnh trên thiết bị thật, hiển thị ảnh chi tiết, khôi phục phiên đăng nhập và các màn cá nhân/thông báo theo thiết kế khi người dùng yêu cầu.
- Không commit/push mobile cho đến khi người dùng yêu cầu.

## Cập nhật phiếu, đánh giá và thông báo (08/11/2026)

- Phiếu của tôi: tìm kiếm mã/nội dung qua API, lọc tất cả/đang xử lý/hoàn tất, phân trang và kéo làm mới.
- Chi tiết phiếu: thông tin người gửi/phòng, dòng thời gian theo log thật, mô tả và kết quả xử lý.
- Phiếu resolved/closed của chính người gửi có thể đánh giá một lần (hài lòng/chưa hài lòng và góp ý tùy chọn). Đánh giá được lưu backend, không thay đổi trạng thái phiếu; gửi xong hiển thị lại trong chi tiết.
- Nút chuông ở thanh đầu mở Thông báo: hôm nay/trước đó, trạng thái đã đọc, phân trang, mở phiếu từ thông báo, đánh dấu tất cả đã đọc. Cập nhật khi mở trang/kéo làm mới, chưa có push nền.
- Backend bổ sung migration 2026_11_08_150000_add_feedback_to_tickets.php (đã chạy trên máy hiện tại), PATCH /api/tickets/{id}/review và PATCH /api/notifications/read-all. Tìm kiếm và lọc vẫn áp dụng quyền xem phiếu của backend.
- Cần chạy migration khi chuyển máy. Backend và mobile đợt này chưa commit/push. 21 kiểm thử Laravel và 11 kiểm thử Flutter đạt trước định dạng code. Chưa thử end-to-end với phiếu hoàn tất trên máy ảo.

## Cá nhân, hồ sơ và bảo mật (08/11/2026)

- Trang Cá nhân hiển thị tên, email, số điện thoại tùy chọn và mã sinh viên chỉ khi là sinh viên. Giảng viên không có ô mã. Không có khoa/lớp/đơn vị hay tải ảnh đại diện.
- Nhãn vai trò dùng riêng Sinh viên/Giảng viên theo member_type, Kỹ thuật viên/Quản trị viên theo role; đã bỏ nhãn ghép Sinh viên / Giảng viên trên các màn mobile, kể cả đăng ký.
- Chỉnh sửa và lưu họ tên/số điện thoại qua API; email, mã sinh viên và vai trò không sửa trong form này. Tên mới cập nhật vào dữ liệu tài khoản trên app.
- SV/GV có mục Bảo mật và mật khẩu: gửi mã đến email trường đã xác minh của chính tài khoản, nhập mã và mật khẩu mới; backend bỏ qua email đích do client tự gửi, chặn staff/admin ở API mới. Đổi xong thu hồi token và quay lại đăng nhập.
- Số điện thoại đăng ký cũng trở thành tùy chọn để thống nhất với hồ sơ.
- Backend sửa AccountController, AuthController (dữ liệu trả về khi token login), MobileAuthController (phone nullable), routes/api.php và kiểm thử. Không cần migration mới cho phần này.
- Đã kiểm thử 23 bài Laravel và 14 bài Flutter đạt. Chưa thử gửi email đổi mật khẩu trực tiếp trên thiết bị với SMTP thật. Khi chạy lại nên dùng R và đăng nhập lại để nhận đầy đủ member_type/member_code/phone.
- Chưa commit/push các thay đổi này.

## KTV — Công việc, lịch, xử lý và tài khoản (08/11/2026)

- Đăng nhập role staff chuyển sang StaffShell riêng, có 3 tab Công việc / Lịch / Tài khoản. Không hiện mã nhân sự.
- Công việc: danh sách phiếu được giao thật qua /api/staff/tickets, lọc Tất cả / Mới nhận / Đang xử lý, phân trang và kéo làm mới. Số lịch hôm nay lấy từ API, không giả lập.
- Lịch: dữ liệu từ /api/staff/schedules, nhóm hôm nay/sắp tới/trước đó; bấm lịch mở xử lý phiếu.
- Xử lý phiếu: thông tin người báo, địa điểm/mô tả, trạng thái, lịch. Xác nhận lịch sẽ bắt đầu xử lý theo backend hiện tại. Sau đó cập nhật ghi chú; chọn Gửi kết quả hoàn thành để chọn/chụp tối đa 5 ảnh, mỗi ảnh 5 MB. Backend yêu cầu ảnh khi resolved và chờ quản lý xác nhận. Không có dòng nhắc quyền thao tác trong UI, backend vẫn kiểm tra quyền thật.
- KTV có chỉnh hồ sơ và đổi mật khẩu bằng mật khẩu hiện tại + mật khẩu mới + xác nhận, không gửi mã email. Đổi thành công thu hồi các token API và quay lại đăng nhập. SV/GV vẫn dùng luồng mã email.
- Backend bổ sung id/status/acknowledged_at vào API lịch, staff_schedules trong chi tiết dành cho staff; API đổi mật khẩu thu hồi token sau khi đổi. Không cần migration mới.
- Đã qua 26 kiểm thử Laravel (gồm xử lý phiếu với ảnh và phân quyền) và 16 kiểm thử Flutter (gồm xác nhận lịch, cập nhật và đổi mật khẩu KTV). Chưa test end-to-end với tài khoản KTV thật trên thiết bị.
- Không thêm plugin native trong đợt KTV, dùng R và đăng nhập lại bằng tài khoản role staff do admin tạo để kiểm tra. Chưa commit/push.

### Điều chỉnh trạng thái KTV và Hôm nay

- Phiếu HD-20261107-96C6DB đã có log resolved trong database từ 07/11/2026, không phải app tự hoàn thành khi mở màn hình. Không sửa/reset dữ liệu phiếu cũ.
- KTV thấy resolved là Chờ quản lý xác nhận, closed là Hoàn tất.
- Form trạng thái/ghi chú/ảnh hiện cho phiếu còn xử lý, kể cả trước khi xác nhận lịch. Phải chọn rõ trạng thái và xác nhận lịch trước khi gửi. Ảnh có thể thêm ở cả đang xử lý và gửi kết quả hoàn thành; hoàn thành vẫn bắt buộc ảnh.
- Mobile gửi explicit_status=1 để backend tôn trọng lựa chọn; thêm ảnh tiến độ không tự chuyển resolved. Giữ tương thích cách gửi ảnh của form web cũ.
- Hôm nay trên tab Công việc đã hiển thị từng lịch có thể bấm mở phiếu.
- 26 kiểm thử Laravel và 17 kiểm thử Flutter đạt; kiểm thử có trường hợp ảnh in_progress không tự hoàn thành và lịch Hôm nay mở đúng ticket.

## Admin mobile — 5 trang (08/11/2026)

- Đăng nhập role admin mở AdminShell riêng, gồm Tổng quan, Duyệt phiếu, Phân công xử lý, Người dùng và Tài khoản quản trị (Phân công là màn từ chi tiết duyệt).
- Tổng quan: số liệu thật chờ duyệt/cần phân công/chờ xác nhận và phiếu cần hành động. Danh sách phiếu có tìm kiếm, lọc, phân trang.
- Duyệt thông tin hoặc yêu cầu bổ sung được lưu thật và gửi thông báo. Người báo có nút bổ sung tiêu đề/mô tả trong chi tiết, gửi lại chuyển về chờ duyệt.
- Phân công KTV phù hợp danh mục, ưu tiên, lịch ngày giờ và ghi chú; backend kiểm tra lịch trùng và KTV hoạt động. Kiểm tra ảnh/kết quả, xác nhận hoàn thành hoặc yêu cầu xử lý lại bằng API hiện có.
- Người dùng: tìm kiếm MSV/tên/email, lọc SV/GV/KTV/admin và trạng thái; xem chi tiết, khóa/mở khóa; tạo KTV và chỉnh danh mục/mật khẩu KTV. Không có giao diện tự nâng quyền hay tự khóa tài khoản đang đăng nhập.
- Tài khoản admin dùng hồ sơ và đổi mật khẩu hiện tại, không cần mã email. Không hiện mã nhân sự hay đơn vị giả.
- Áp dụng các nguyên tắc phù hợp của taste-skill cho Flutter native, ghi quyết định và giới hạn tại ADMIN_DESIGN.md. Kiểm tra bằng ảnh render Flutter ở 390px, không tuyên bố đạt HTML/browser gate của skill.
- Migration bổ sung 2026_11_08_210000_add_triage_to_tickets.php đã chạy trên máy hiện tại. Cần migrate khi chuyển máy. Không reset dữ liệu phiếu/tài khoản cũ.
- 28 kiểm thử Laravel, 20 kiểm thử Flutter, flutter analyze và build APK debug đều đạt. Ảnh preview test dùng fixture, chưa thay thế thử nghiệm với tài khoản admin thật trên thiết bị.
- Để thử: R hoa, đăng nhập bằng tài khoản admin thật. Đợt này không thêm plugin Android, không bắt buộc q/chạy lại. Toàn bộ mobile và backend mới vẫn chưa commit/push.
