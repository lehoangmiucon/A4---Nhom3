# Tổng kết mobile Helpdesk UTH và backend dùng chung

Ngày tổng kết: 08/11/2026. Nhánh: `trong-le`.

## Thành quả

### Nền tảng

- Tạo project Flutter Android trong cùng repository với Laravel.
- Thiết lập SDK ổ D, chạy được trên emulator và Samsung qua Wireless debugging.
- Kết nối API thật, lưu token an toàn và phân giao diện theo role student/staff/admin.

### Xác thực và tài khoản

- Đăng nhập bằng email hoặc MSV; SV đăng ký có MSV, GV không cần mã.
- OTP xác minh email/gửi lại mã; xác minh xong dùng app, không thêm duyệt tài khoản thủ công.
- Khôi phục mật khẩu bằng mã ngẫu nhiên 6 chữ cái, hạn 10 phút, dùng một lần, lưu hash và giới hạn lần thử; email HTML/plain text được trình bày lại.
- Hồ sơ SV/GV: tên, email, MSV cho SV, số điện thoại tùy chọn. Sửa tên, điện thoại và MSV (kiểm tra trùng); mã mới dùng được để đăng nhập.
- Vai trò hiển thị riêng SV/GV/KTV/admin; không có khoa/lớp/đơn vị hoặc tải avatar.
- SV/GV đổi mật khẩu bằng mã gửi đến email trường của chính tài khoản. KTV/admin dùng mật khẩu hiện tại, không cần mã email. Đổi xong thu hồi token và yêu cầu đăng nhập lại.

### SV/GV

- Trang chủ và phiếu gần đây; danh sách có tìm kiếm, lọc, phân trang và kéo làm mới.
- Quét QR/nhập mã phòng → backend xác nhận tòa/phòng; bấm Báo sự cố ở trang chủ đi thẳng bước 1.
- Tạo phiếu hai bước: vị trí/danh mục/tiêu đề → mô tả/mức ảnh hưởng/ảnh; tối đa 5 ảnh, 5 MB mỗi ảnh.
- Thành công hiển thị mã phiếu thật, vị trí, trạng thái và các nút xem chi tiết/về trang chủ.
- Chi tiết phiếu: nội dung, người gửi, địa điểm, giờ trước ngày, nhân viên phụ trách hoặc Đang chờ phân công. Bỏ tiêu đề Dòng thời gian và log gửi báo cáo bị lặp trên UI.
- Đánh giá hài lòng/chưa hài lòng và góp ý sau khi phiếu hoàn tất; chỉ người gửi, một lần mỗi phiếu.
- Thông báo nhóm theo ngày, mở phiếu liên quan, đánh dấu một/tất cả đã đọc.
- Khi admin yêu cầu bổ sung, có thể bổ sung tiêu đề/mô tả trong chi tiết phiếu rồi gửi lại để duyệt.

### Kỹ thuật viên

- Ba tab Công việc / Lịch / Tài khoản, không có mã nhân sự.
- Công việc có bộ lọc, phân trang và lịch Hôm nay có thể bấm mở phiếu.
- Xác nhận lịch, chọn rõ trạng thái, ghi chú và thêm ảnh tiến độ/kết quả.
- Mobile gửi trạng thái tường minh: ảnh tiến độ không tự biến phiếu thành hoàn thành. Gửi kết quả hoàn thành cần ảnh và chờ quản lý xác nhận.
- Nhãn resolved là Chờ quản lý xác nhận; closed là Hoàn tất.

### Quản trị viên

- Năm màn chính: Tổng quan, Duyệt phiếu, Phân công, Người dùng, Tài khoản quản trị.
- Thống kê thật; tìm kiếm/lọc phiếu; duyệt thông tin hoặc gửi yêu cầu bổ sung.
- Phân công KTV theo danh mục, ưu tiên, ngày giờ và ghi chú; kiểm tra tài khoản hoạt động/lịch trùng.
- Xem ảnh báo cáo/kết quả, xác nhận hoàn thành hoặc yêu cầu xử lý lại.
- Người dùng: tìm kiếm MSV/tên/email, lọc vai trò/trạng thái, xem chi tiết, khóa/mở khóa, tạo KTV và chỉnh danh mục/mật khẩu KTV. Không có nút tự nâng quyền hay tự khóa tài khoản đang đăng nhập.
- Áp dụng các nguyên tắc phù hợp của taste-skill trong Flutter; render/golden test ở chiều rộng điện thoại. Không tuyên bố chạy HTML/browser gate cho app native.

## Backend và database

- Bổ sung API hồ sơ, mã đổi mật khẩu gắn với tài khoản, đánh giá, đọc tất cả thông báo, tổng quan admin, duyệt thông tin và chi tiết người dùng.
- Bổ sung tìm kiếm/lọc, metadata lịch KTV, phân công ưu tiên/lịch trùng và kiểm tra quyền.
- Các migration mới có trường thông tin thành viên, đánh giá và duyệt thông tin. Máy hiện tại đã áp dụng; máy thành viên khác cần chạy `php artisan migrate` sau khi cập nhật code.
- Không đưa database thật, ảnh upload, `.env`, token, cache hay APK lên Git.

## Kiểm chứng

- Gần nhất: 28 kiểm thử Laravel, 20 kiểm thử Flutter, `flutter analyze` và build Android debug đều đạt.
- Feature tests dùng SQLite trong bộ nhớ; giao diện golden dùng fixture, không thay thế kiểm tra dữ liệu/tài khoản thật.
- Người dùng đã xác nhận đăng nhập và dùng app trên emulator/Samsung; các luồng mới nhất của admin vẫn cần thử trọn vẹn với tài khoản thật.

## Việc còn lại trước khi chốt phát hành

- Kiểm tra trọn luồng SV/GV → admin duyệt/phân công → KTV xác nhận/xử lý → admin xác nhận → người gửi đánh giá.
- Kiểm tra SMTP, QR, ảnh, lịch/ngày giờ, thông báo và phân quyền trên thiết bị thật.
- Tự khôi phục phiên khi mở app, push nền và ảnh trong chi tiết SV/GV chưa hoàn thiện.
- Chốt application ID, icon, cấu hình HTTPS và ký bản phát hành.

## Bàn giao Git

Người dùng đã yêu cầu commit và push toàn bộ công việc hiện tại, gồm mobile và backend, lên `trong-le`. Các nhật ký trước có dòng “chưa commit” là bản ghi lịch sử; trạng thái mới nhất xem bằng `git status` và `git log`.
