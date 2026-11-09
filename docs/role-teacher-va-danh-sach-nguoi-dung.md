# Danh sách người dùng và vai trò teacher — 09/11/2026

## Quy tắc hiện tại

- Mọi người đăng ký bằng cùng một form với email trường, họ tên, mật khẩu và MSV không bắt buộc; không tự chọn Giảng viên.
- Sau xác thực email, tài khoản được sử dụng chức năng báo sự cố bình thường, không có bước chờ duyệt đăng ký.
- Đăng ký công khai luôn tạo role=student. Gửi role=teacher/admin hoặc member_type=lecturer từ app cũ hay request thủ công không cấp vai trò giảng viên.
- Chỉ admin xác nhận tư cách giảng viên mới lưu role=teacher. Có thể bỏ xác nhận để trở về role=student.
- Teacher là nhóm quyền người báo sự cố, không có quyền KTV/admin. Được gửi phiếu, xem/sửa bổ sung phiếu của mình, đánh giá, nhận thông báo, chỉnh hồ sơ và đổi mật khẩu qua mã email trường.

## Giao diện admin

- Đổi Người báo sự cố thành Danh sách người dùng trên menu, tiêu đề và danh sách web.
- Bổ sung cột Loại người dùng, lọc Sinh viên/Giảng viên, giữ tìm kiếm tên/MSV/email và lọc trạng thái.
- Nút Xác nhận giảng viên tại từng dòng danh sách và trang chi tiết. Khi đã là teacher, nút chuyển thành Bỏ xác nhận giảng viên.
- Mobile có lọc role teacher, hiển thị loại người dùng và nút xác nhận/bỏ xác nhận trong chi tiết tài khoản.
- Cần tài khoản đã xác thực email trường, không khóa; thao tác có xác nhận và được ghi nhật ký. Đổi loại người dùng thu hồi phiên web/token để người dùng đăng nhập lại và nhận đúng vai trò.
- API: PATCH /api/admin/users/{user}/teacher với is_teacher=true/false, chỉ admin được gọi.

## Dữ liệu và tương thích

| Loại | role | member_type tương thích |
|---|---|---|
| Sinh viên / người đăng ký thông thường | student | student |
| Giảng viên do admin xác nhận | teacher | lecturer |
| Kỹ thuật viên | staff | Không dùng để phân quyền |
| Quản trị viên | admin | Không dùng để phân quyền |

- role là nguồn xác định loại người dùng và phân quyền. member_type=lecturer trên tài khoản student cũ chỉ là dữ liệu tự khai, không làm tài khoản thành teacher.
- Không tự nâng những tài khoản từng tự chọn Giảng viên thành teacher. Admin cần kiểm tra rồi xác nhận từng tài khoản.
- Giữ MSV đã có khi xác nhận/bỏ xác nhận để không mất dữ liệu. Giao diện giảng viên không yêu cầu MSV.
- Cột role đang là string nên không cần migration mới. Không thay đổi vai trò tài khoản thật khi kiểm thử.

## Kiểm chứng và mở lại

- Laravel: 39 bài kiểm thử, 370 assertions đạt trên SQLite trong bộ nhớ.
- Flutter: 22 bài kiểm thử đạt; flutter analyze không báo lỗi.
- Kiểm thử quyền teacher với tạo/bổ sung/đánh giá phiếu, quyền sở hữu, chặn truy cập admin/KTV, xác nhận chỉ bởi admin và thu hồi phiên đăng nhập.
- Đã xem form đăng ký chung trên trình duyệt; trang admin được kiểm tra render bằng feature tests. Chưa thao tác xác nhận tài khoản thật trên trình duyệt.
- Web: Ctrl+F5. Mobile: hot restart bằng R trong terminal Flutter. Sau khi đổi loại, tài khoản liên quan cần đăng nhập lại.
- Bàn giao trên nhánh `trong-le` với commit tiếng Việt. Sau khi pull, người dùng có thể tiếp tục chỉnh sửa theo nhu cầu của nhóm.
