# Nhật ký công việc và hướng dẫn kết nối Samsung

Ngày 08/11/2026 — tổng kết đợt làm việc người dùng xác định từ khoảng 15:48 đến lúc ghi tài liệu.

Không có dấu thời gian chính xác cho từng tin nhắn trong bản ghi này; nội dung bên dưới được tổng hợp theo đợt trao đổi gần đây.

## 1. Công việc đã làm trong đợt gần đây

### Kết nối và chạy thiết bị

- Đã ghép đôi Samsung SM-A315G (Android 12) với Android Studio bằng Wireless debugging.
- Flutter đã nhận điện thoại và người dùng xác nhận app kết nối, sử dụng được trên Samsung.
- Đã hướng dẫn chuyển lại máy ảo và xử lý trường hợp cửa sổ lệch màn hình, máy ảo bị treo.

### Menu vai trò ở trang đăng ký

- Menu Sinh viên / Giảng viên dùng kiểu hiển thị giống menu Danh mục sự cố: nền trắng, bo góc 14, chữ 14, mỗi mục cao 48, menu cao tối đa 240.
- Sinh viên có ô Mã sinh viên; giảng viên không có ô mã, theo luồng đã hoàn thiện trước đó.

### Phiếu của tôi

- Làm lại bố cục theo thiết kế: tìm kiếm mã phiếu/nội dung, danh sách thẻ phiếu gọn, trạng thái và địa điểm.
- Có bộ lọc Tất cả / Đang xử lý / Hoàn tất, phân trang, kéo làm mới và trạng thái không có dữ liệu/lỗi.
- Tìm kiếm và lọc gọi API; backend vẫn giới hạn dữ liệu theo quyền của tài khoản.
- Nhóm Đang xử lý gồm new, assigned, in_progress, reopened; Hoàn tất gồm resolved, closed.

### Chi tiết phiếu

- Hiển thị sự cố, người gửi, tòa/phòng, thời điểm gửi, nhân viên phụ trách, cập nhật xử lý, mô tả, kết quả và đánh giá nếu có.
- Bỏ tiêu đề “Dòng thời gian”.
- Thời điểm dưới “Đã gửi yêu cầu” hiển thị giờ trước ngày, ví dụ `16:14 · 08/11/2026` theo giờ địa phương của thiết bị.
- Đặt “Nhân viên phụ trách” ngay dưới “Đã gửi yêu cầu”. Có nhân viên thì hiện tên; chưa có thì hiện “Đang chờ phân công”.
- Ẩn thẻ log khởi tạo “Người báo sự cố đã gửi báo cáo.” vì trùng với mục Đã gửi yêu cầu. Chỉ ẩn trên giao diện mobile, không xóa lịch sử trong database.
- Các cập nhật xử lý khác vẫn được hiển thị từ log thật.

### Đánh giá xử lý

- Thêm trang Đánh giá xử lý: Hài lòng / Chưa hài lòng và góp ý không bắt buộc, tối đa 2.000 ký tự.
- Nút đánh giá hiện trong chi tiết khi phiếu thuộc chính người gửi, trạng thái resolved/closed và chưa đánh giá.
- Backend kiểm tra lại quyền sở hữu, trạng thái hoàn tất và chặn gửi trùng bằng transaction/khóa bản ghi.
- Đánh giá được lưu thật; gửi xong tải lại chi tiết để hiển thị. Đánh giá không đổi trạng thái phiếu.

### Thông báo / cập nhật tiến độ

- Nút chuông trên thanh đầu của trang người dùng mở trang Thông báo.
- Nhóm Hôm nay / Trước đó, phân biệt chưa đọc/đã đọc, tải thêm và kéo làm mới.
- Bấm thông báo sẽ đánh dấu đã đọc rồi mở phiếu liên quan nếu có ticket_id.
- Có nút Đánh dấu tất cả đã đọc; backend chỉ cập nhật thông báo của tài khoản hiện tại.
- Đây là thông báo trong app khi mở/tải lại trang; chưa tích hợp push nền khi app đóng.

## 2. File và database liên quan

Mobile chủ yếu thay đổi:

- `mobile/lib/main.dart`: kiểu menu Vai trò.
- `mobile/lib/ticket_pages.dart`: danh sách, chi tiết và trang đánh giá; các chỉnh sửa hiển thị mới nhất.
- `mobile/lib/notifications_page.dart`: trang thông báo mới.
- `mobile/lib/customer_pages.dart`: nút chuông để mở thông báo.
- `mobile/lib/auth_api.dart`: hàm PATCH có xác thực cho đánh giá và đánh dấu đã đọc.
- `mobile/test/`: bổ sung/cập nhật kiểm thử.

Backend bổ sung cho đợt phiếu/đánh giá/thông báo:

- `app/Http/Controllers/TicketController.php`: tìm kiếm/lọc phiếu, cờ can_review và API lưu đánh giá.
- `app/Http/Controllers/NotificationController.php`: đánh dấu tất cả thông báo đã đọc của người dùng hiện tại.
- `app/Models/Ticket.php`: cast các trường đánh giá.
- `routes/api.php`: PATCH `/api/tickets/{ticket}/review` và `/api/notifications/read-all`.
- `database/migrations/2026_11_08_150000_add_feedback_to_tickets.php`: thêm feedback_satisfied, feedback_comment, feedback_at. Migration đã chạy trên database máy hiện tại.
- `tests/Feature/TicketFeedbackTest.php`: kiểm tra quyền, trạng thái, gửi trùng, tìm kiếm và thông báo.

Kiểm tra đã thực hiện:

- 21 kiểm thử Laravel đạt, gồm kiểm tra đánh giá và quyền truy cập.
- 11 kiểm thử Flutter đạt ở đợt hoàn thiện bốn trang.
- `flutter analyze` đạt sau đợt hoàn thiện bốn trang và sau chỉnh vị trí nhân viên/thời gian gửi.
- Chỉnh sửa cuối cùng ẩn log khởi tạo là thay đổi lọc hiển thị nhỏ; chưa chạy lại toàn bộ kiểm thử sau thay đổi đó.
- Chưa xác nhận end-to-end đánh giá phiếu hoàn tất trên thiết bị thật. Chưa tích hợp hiển thị ảnh đính kèm trong chi tiết.

## 3. Trạng thái Git

- Nhánh làm việc: `trong-le`.
- Commit đã push trước đó: `33dd7f6` — “Hoàn thiện xác thực và khôi phục mật khẩu cho web”.
- Backend của đợt đánh giá/thông báo và hai file sửa quy tắc đăng ký giảng viên sau commit đó đang chưa commit/push.
- Toàn bộ `mobile/` đang chưa commit/push. Chỉ commit khi người dùng yêu cầu.

## 4. Ghép đôi Samsung qua Wi-Fi

Không cần cáp USB khi dùng Wireless debugging. Điện thoại và laptop cần ở cùng mạng Wi-Fi có thể liên lạc với nhau.

1. Trên Samsung, bật Tùy chọn nhà phát triển nếu chưa có: Cài đặt → Thông tin điện thoại → Thông tin phần mềm → chạm Số hiệu bản tạo 7 lần, xác nhận mã khóa khi được hỏi.
2. Vào Cài đặt → Tùy chọn nhà phát triển → Gỡ lỗi không dây / Gỡ lỗi qua Wi-Fi, bật lên.
3. Chạm tên mục để mở chi tiết rồi chọn Ghép đôi bằng mã ghép đôi hoặc Ghép đôi bằng mã QR.
4. Trên Android Studio, mở chức năng Pair Devices Using Wi-Fi / Pair devices over Wi-Fi.
5. Chọn cùng phương thức với điện thoại: nhập mã từ điện thoại vào laptop, hoặc dùng trình quét trong mục ghép đôi trên điện thoại để quét QR trên laptop. Không dùng QR của app Helpdesk cho bước ghép đôi này.
6. Đợi hiện `samsung SM-A315G connected` rồi đóng hộp thoại.
7. Kiểm tra bằng PowerShell:

```powershell
cd D:\xampp\htdocs\helpdesk-nhom3\mobile
flutter devices
```

Thiết bị đã từng được nhận:

```text
SM A315G (wireless)
ID_SAMSUNG_TU_FLUTTER_DEVICES
```

ID có thể thay đổi khi kết nối lại; luôn dùng ID hiện tại trong `flutter devices`.

## 5. Chạy Laravel và app trên Samsung

### Bật backend

XAMPP → Start MySQL, đợi hiện xanh. Với cách chạy Laravel dưới đây không cần bật Apache.

Terminal thứ nhất:

```powershell
cd D:\xampp\htdocs\helpdesk-nhom3
& "D:\xampp\php\php.exe" artisan serve --host=0.0.0.0 --port=8000
```

Giữ terminal mở. `.env` database phải khớp MySQL đang chạy; máy hiện dùng cổng 3308.

### Kiểm tra IP laptop

Mở terminal khác:

```powershell
ipconfig
```

Lấy IPv4 của adapter Wi-Fi đang dùng. Lần kết nối đã thành công dùng IP laptop `192.168.4.14`. IP này có thể đổi khi đổi mạng hoặc khởi động lại router.

Trên Chrome của Samsung, thử mở:

```text
http://192.168.4.14:8000/api/categories
```

Thay IP nếu khác. Thấy JSON danh mục là điện thoại truy cập được Laravel. Nếu chưa mở được, kiểm tra Laravel, cùng mạng, IP/cổng và firewall trước khi thử đăng nhập app.

### Chạy Flutter trên điện thoại

Terminal thứ hai, dùng ID và IP thực tế. Lệnh đã dùng thành công:

```powershell
cd D:\xampp\htdocs\helpdesk-nhom3\mobile
flutter run -d "ID_SAMSUNG_TU_FLUTTER_DEVICES" --dart-define=API_BASE_URL=http://192.168.4.14:8000/api
```

Giữ điện thoại kết nối Wi-Fi; cho phép cài/chạy app nếu điện thoại hỏi. Lần build đầu có thể lâu hơn.

Địa chỉ `10.0.2.2` dùng cho Android emulator, không dùng để điện thoại thật kết nối Laravel. Việc ghép đôi ADB thành công không tự đảm bảo điện thoại truy cập được API, nên cần kiểm tra URL backend như trên.

## 6. Chuyển từ Samsung về máy ảo

1. Trong terminal Flutter đang chạy Samsung, nhấn `q`.
2. Android Studio → Device Manager → bấm ▶ để mở máy ảo, đợi màn hình chính ổn định.
3. Chạy:

```powershell
cd D:\xampp\htdocs\helpdesk-nhom3\mobile
flutter devices
flutter run -d emulator-5554
```

Dùng đúng ID máy ảo nếu khác. Bỏ `--dart-define=API_BASE_URL=...` để app dùng mặc định `http://10.0.2.2:8000/api` cho máy ảo.

MySQL và terminal Laravel giữ nguyên, không cần khởi động lại. Có thể tắt Gỡ lỗi không dây trên Samsung khi không dùng nữa.

## 7. Phím thường dùng và cách tắt

Nhấn trong terminal đang chạy Flutter:

| Phím | Tác dụng |
| --- | --- |
| `r` thường | Hot reload, thường giữ trạng thái đang dùng. Dùng khi sửa chữ, màu, bố cục hoặc logic hỗ trợ hot reload. |
| `R` hoa | Hot restart, chạy lại app từ đầu; có thể cần đăng nhập lại. |
| `q` | Dừng app/phiên flutter run. Dùng trước khi chuyển thiết bị hoặc chạy lại với địa chỉ API khác. |

Thêm plugin Android hoặc đổi cấu hình native thì nhấn q rồi chạy lại lệnh, không chỉ r/R.

Khi nghỉ:

1. Terminal Flutter → q.
2. Đóng máy ảo hoặc tắt Gỡ lỗi không dây trên Samsung nếu không sử dụng.
3. Terminal Laravel → Ctrl+C.
4. XAMPP → Stop MySQL; Stop Apache nếu đang bật và không cần web nữa.
5. Đóng terminal/Android Studio khi không cần.

Nếu máy ảo treo với lỗi qemu-system-x86_64.exe not responding: đóng tiến trình máy ảo qua Task Manager nếu cần, rồi Device Manager → menu ⋮ → Cold Boot Now. Chưa cần Wipe Data vì sẽ xóa dữ liệu máy ảo.

## 8. Tiếp tục lần sau

- Kiểm tra `git status` và đọc tài liệu này cùng `HUONG_DAN_VA_TIEN_DO.md`.
- Chạy thử phiếu hoàn tất: mở chi tiết → Đánh giá xử lý → gửi → xác nhận hiển thị lại và không gửi trùng.
- Thử thông báo thật: mở chuông, bấm phiếu, đánh dấu đã đọc, kéo làm mới.
- Tiếp tục chỉnh theo phản hồi của người dùng. Chưa có yêu cầu commit/push phần mobile.
