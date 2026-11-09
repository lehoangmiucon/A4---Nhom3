# Đồng bộ tài khoản web và mobile — 09/11/2026

## Đã thực hiện

- Đăng nhập web bằng email hoặc mã sinh viên; giữ số 0 đầu mã và kiểm tra trạng thái khóa/xác thực email.
- Đăng ký theo hai tab Sinh viên / Giảng viên. Sinh viên nhập MSV; giảng viên không có trường MSV. Cả hai dùng email trường và xác thực OTP. Backend chỉ tạo tài khoản thành viên, không nhận quyền admin/KTV từ form.
- Admin tìm người báo sự cố theo tên, MSV hoặc email, kết hợp lọc trạng thái. Bảng bổ sung MSV hoặc nhãn Giảng viên và trạng thái không tìm thấy kết quả.
- Hồ sơ web SV/GV: chỉnh tên, số điện thoại tùy chọn; sinh viên chỉnh được MSV và được kiểm tra mã trùng. Email, vai trò giữ nguyên.
- Hồ sơ admin/KTV bổ sung số điện thoại tùy chọn; đổi mật khẩu bằng mật khẩu hiện tại, không cần mã email.
- SV/GV đổi mật khẩu bằng mã gửi đến chính email trường đã xác minh của người đăng nhập. Thành công sẽ đăng xuất phiên web hiện tại và thu hồi token ứng dụng. API đổi mật khẩu trực tiếp cũng chặn SV/GV để tránh bỏ qua mã email.
- Trang Đội ngũ: căn giữa số phiếu được giao; thêm nút Đổi mật khẩu tại dòng tài khoản admin hiện tại trong tab Quản lý, mở hộp thoại nhập mật khẩu hiện tại/mới/xác nhận. Không cho đổi mật khẩu người khác bằng chức năng này.
- Thu gọn form đăng ký: MSV/họ tên và hai ô mật khẩu chia thành hai cột trên máy tính; màn hình nhỏ về một cột, giữ đủ trường và trạng thái tab SV/GV.
- Menu Cá nhân / Tài khoản giúp mở trang hồ sơ từ khu vực khách hàng và kỹ thuật viên.
- Áp dụng nguyên tắc phù hợp của taste-skill (https://github.com/leonxlnx/taste-skill): giữ hệ thống màu/bố cục web hiện có, tab có trạng thái chọn và điều khiển bằng bàn phím, nhãn rõ ràng, phản hồi gửi mã/lưu hồ sơ. Đây là cập nhật biểu mẫu trong ứng dụng Laravel hiện có.

## Mở để kiểm tra

Bật Apache và MySQL trong XAMPP, rồi dùng các đường dẫn:

- Đăng nhập: http://localhost:8888/helpdesk-nhom3/public/auth/login
- Đăng ký: http://localhost:8888/helpdesk-nhom3/public/auth/register
- Hồ sơ SV/GV: http://localhost:8888/helpdesk-nhom3/public/customer/account
- Hồ sơ KTV: http://localhost:8888/helpdesk-nhom3/public/staff/account
- Hồ sơ admin: http://localhost:8888/helpdesk-nhom3/public/admin/pages/account
- Người báo sự cố: http://localhost:8888/helpdesk-nhom3/public/admin/pages/users

Đăng nhập đúng vai trò trước khi mở trang được bảo vệ. Nhấn Ctrl+F5 nếu trình duyệt giữ giao diện cũ. Gửi email thật dùng cấu hình SMTP hiện có; kiểm thử tự động dùng mail array, không gửi email ra ngoài.

## Kiểm chứng

- PHPUnit: 34 bài, 312 assertions đều qua trên SQLite trong bộ nhớ; không sửa dữ liệu MySQL thật.
- Kiểm tra cú pháp JavaScript của đăng ký, lọc người dùng và đổi mật khẩu.
- Đã mở trình duyệt xem đăng nhập và chuyển tab Giảng viên: ô MSV ẩn đúng. Các trang được bảo vệ và luồng cập nhật được kiểm tra bằng feature tests.
- Không sửa mã nguồn trong mobile/. Các thay đổi backend được dùng chung với mobile.
- Tổng hợp và bàn giao trên nhánh `trong-le` với commit tiếng Việt theo yêu cầu.
