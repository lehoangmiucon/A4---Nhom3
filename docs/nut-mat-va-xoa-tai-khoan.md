# Nút mắt mật khẩu và xóa tài khoản — 09/11/2026

## Các thay đổi đã hoàn thành

### Hiện/ẩn mật khẩu trên web

- Thêm nút mắt cho các ô mật khẩu tại đăng nhập, đăng ký, đặt lại mật khẩu, hồ sơ SV/GV, hồ sơ KTV, hồ sơ admin và hộp thoại tạo/sửa tài khoản đội ngũ.
- Mỗi ô bật/tắt độc lập, mặc định che mật khẩu. Nút không gửi biểu mẫu; hỗ trợ bàn phím và nhãn trợ năng Hiện/Ẩn. Khi reset form hoặc đóng hộp thoại, mật khẩu được che lại.
- Dùng chung Blade partial, CSS và JavaScript trong shared để các trang nhất quán.

### Quy tắc đổi mật khẩu giữ nguyên theo chốt cuối

- Admin và KTV tự đổi mật khẩu: bắt buộc mật khẩu hiện tại, mật khẩu mới và xác nhận mật khẩu mới.
- Admin vẫn có quyền đặt lại mật khẩu KTV trong chức năng chỉnh sửa nhân viên.
- SV/GV tiếp tục xác thực mã qua email trường khi đổi mật khẩu.
- Không bổ sung luồng email cá nhân mới cho KTV trong đợt này. Luồng Quên mật khẩu chung đã có trước đó được giữ nguyên.

### Xóa tài khoản trên web và mobile

- Web: nút Xóa tài khoản trong chi tiết người báo sự cố và chi tiết KTV.
- Mobile: nút Xóa tài khoản trong màn chi tiết người dùng/KTV của admin.
- Có bước xác nhận; bấm Hủy không gửi yêu cầu xóa.
- API DELETE /api/admin/users/{user} chỉ cho admin xóa SV/GV hoặc KTV; chặn tự xóa và chặn xóa tài khoản admin.
- Xóa mềm bằng deleted_at: tài khoản biến mất khỏi danh sách, không đăng nhập được; lịch sử phiếu, người báo, người xử lý, ảnh và nhật ký vẫn được giữ lại.
- Thu hồi token ứng dụng, phiên web trong bảng sessions, remember token và mã đặt lại mật khẩu của tài khoản bị xóa; ghi nhật ký user.deleted.
- KTV còn phiếu chưa hoàn tất hoặc lịch xử lý tương lai chưa hủy thì chưa được xóa. Cần hoàn tất/phân công lại phiếu và xử lý lịch trước.
- Chưa xây màn khôi phục tài khoản đã xóa. Email/MSV vẫn được giữ trong dữ liệu lịch sử và không được tái sử dụng tùy ý.

## Cập nhật và sử dụng

- Migration mới: database/migrations/2026_11_09_140000_add_deleted_at_to_users_table.php. Đã áp dụng trên máy hiện tại.
- Máy khác sau khi kéo code cần chạy php artisan migrate trước khi mở web/app; không chạy migrate:fresh.
- Web: Ctrl+F5, đăng nhập admin rồi vào Người báo sự cố hoặc Đội ngũ → Chi tiết → Xóa tài khoản.
- Mobile: hot restart bằng R trong terminal Flutter; vào Người dùng → chọn tài khoản → Xóa tài khoản.

## Kiểm chứng

- Backend: 36 bài kiểm thử, 332 assertions đạt trên SQLite trong bộ nhớ.
- Flutter analyze: không có vấn đề. Kiểm thử mobile bao gồm hủy/xác nhận xóa và gửi đúng HTTP DELETE.
- JavaScript nút mắt và xóa tài khoản qua kiểm tra cú pháp.
- Đã kiểm tra trực quan nút mắt đăng nhập/đăng ký và bật/tắt độc lập.
- Công cụ kiểm tra Chrome đã dừng vì không xác định chắc chắn URL hiện tại; chưa kiểm tra trực quan nút xóa trên Chrome. Không xóa thử tài khoản thật.
- Bàn giao trên nhánh trong-le với commit tiếng Việt.
