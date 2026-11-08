# Thiết kế admin mobile

Loại sản phẩm: ứng dụng quản trị nội bộ viết bằng Flutter native.

## Nguồn và cách áp dụng

- Theo 5 màn tham chiếu của người dùng: Tổng quan, Duyệt phiếu, Phân công, Người dùng, Tài khoản quản trị.
- Đọc skill `design-taste-frontend` tại https://github.com/Leonxlnx/taste-skill/blob/main/skills/taste-skill/SKILL.md và các tham chiếu system, copy, copy-voice, layout-v4, sections.
- Áp dụng các nguyên tắc phù hợp: một hệ thống giao diện, lề thẳng hàng, khoảng cách nhất quán, nhãn thao tác cụ thể, dữ liệu thật, không chèn số liệu hoặc trạng thái giả; phân cấp rõ nội dung và hành động.
- Giữ màu xanh UTH, thẻ nền trắng và hệ thống nút hiện có của app. Danh sách người dùng có biểu tượng theo vai trò, trạng thái riêng, tìm kiếm và bộ lọc gọn trên màn điện thoại.
- Bộ dựng trang HTML, shader/hero website và bộ gate trình duyệt trong skill không áp dụng cho Flutter native. Không cài hoặc chạy script từ repo ngoài. Không tuyên bố đã qua HTML gate.
- Kiểm tra thay thế bằng Flutter render ở chiều rộng 390, widget/API tests, phân tích code và build Android. Ảnh trong `test/goldens/admin_*_390.png` dùng dữ liệu fixture kiểm thử, không phải ảnh tài khoản/database thật. Font và biểu tượng kiểm thử có giấy phép kèm trong `test/assets/`.

## Các màn và luồng

1. Tổng quan: số chờ duyệt/cần phân công/chờ xác nhận lấy từ API; bấm thống kê mở danh sách tương ứng.
2. Duyệt: tìm kiếm/lọc phiếu, xem báo cáo và ảnh; duyệt hoặc yêu cầu bổ sung. Với kết quả sửa chữa: xem ảnh, xác nhận hoàn thành hoặc yêu cầu xử lý lại. Thao tác quan trọng có hộp xác nhận.
3. Phân công: KTV phù hợp danh mục, mức ưu tiên, ngày giờ và ghi chú. Backend kiểm tra tài khoản KTV hoạt động, danh mục và lịch trùng.
4. Người dùng: tìm kiếm mã SV/tên/email, lọc vai trò/trạng thái, phân trang; chi tiết trong sheet; khóa/mở khóa; tạo KTV và sửa danh mục xử lý. Không có chức năng tự khóa tài khoản đang đăng nhập và không có nút tự nâng quyền.
5. Tài khoản: hồ sơ, thông báo, đổi mật khẩu hiện tại và đăng xuất. Không hiện mã nhân sự/đơn vị giả.

Trang bổ sung mô tả cho SV/GV được nối vào chi tiết phiếu khi admin yêu cầu thông tin, gửi lại sẽ đưa thông tin về hàng chờ duyệt. Luồng xác minh tài khoản SV/GV vẫn chỉ dùng email, không thêm bước admin duyệt tài khoản.

## Backend

- Migration `2026_11_08_210000_add_triage_to_tickets.php`: triage_status và triaged_at. Đã chạy trên máy hiện tại, cần migrate khi chuyển máy.
- GET `/api/admin/summary`; POST `/api/admin/tickets/{id}/triage`; GET `/api/admin/users/{id}`.
- Thêm queue action/assignment, tìm kiếm/lọc người dùng, ưu tiên khi phân công và kiểm tra lịch trùng. API lịch/phân công cũ vẫn được dùng chung với web.
- Đổi mật khẩu KTV từ trang quản lý sẽ thu hồi token API cũ của KTV.
- Không thay đổi dữ liệu tài khoản thật hoặc thực hiện phân công thật khi kiểm thử. Các feature tests dùng SQLite trong bộ nhớ.

## Giới hạn cần kiểm tra thực tế

- Cần thử tài khoản admin thật trên emulator/điện thoại, nhất là ảnh từ storage, phân công ngày giờ, và chuyển vai trò qua đăng xuất/đăng nhập.
- Ảnh kiểm thử không chứng minh kết nối SMTP, camera hoặc dữ liệu MySQL thực tế.
- Giao diện quản lý tài khoản hiện hỗ trợ xem/lọc/khóa/mở khóa và tạo/chỉnh KTV; chưa có giao diện cấp thêm tài khoản quản trị hoặc đổi vai trò tùy ý.
- Chưa commit/push phần mobile và các thay đổi backend mới.
