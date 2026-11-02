# Giao diện kỹ thuật viên

Mở `technician-app.html` để dùng bản hoàn chỉnh trong một trang. Lịch, nhiệm vụ, form nghiệm thu và báo cáo dùng chung thanh điều hướng, không tải sang trang HTML khác. Tailwind được tải qua CDN nên cần kết nối Internet.

Từ `index.html`, chọn Không gian kỹ thuật viên để mở giao diện tích hợp. HTML nguồn của khung trang nằm tại `pages/templates/technician-shell.html`; CSS trong `assets/css/` và JavaScript trong `assets/js/`. File gộp cũ đã được xóa; quy trình build không tạo lại file này.

Các trang riêng trong thư mục `pages/` vẫn hoạt động để tham khảo.

## Bản gộp trong một trang

- Địa chỉ `#schedule`, `#reports`, `#task?id=...`, `#resolve?id=...`, `#report?id=...` mở từng mục trong cùng trang. Nút Back/Forward của trình duyệt được hỗ trợ.
- Chuyển mục rồi quay lại form sẽ giữ nội dung, vật tư, ảnh và nét ký đang nhập trong phiên mở trang. Liên kết “Tiếp tục bản nháp” giúp mở lại nhanh. Bản nháp chưa lưu sẽ mất khi đóng/tải lại trang; trình duyệt được yêu cầu cảnh báo trước khi rời trang nếu có chỉnh sửa.
- Giữ ngày đang chọn và bộ lọc lịch khi mở nhiệm vụ rồi quay lại. Sau khi hoàn tất một báo cáo, lịch chuyển về ngày có nhiệm vụ đó và hiện liên kết xem báo cáo.
- Các trang dùng chung JavaScript; thay đổi chức năng không bị tách thành hai bản độc lập.
- Chạy `node scripts/build-technician-app.cjs` sau khi sửa giao diện hoặc mã dùng chung để cập nhật trang tích hợp `technician-app.html`.
- Chạy `node scripts/check-technician-app.cjs` để kiểm tra các quy tắc điều hướng, giữ mã phiếu trong liên kết, cấu trúc template, tham chiếu tài nguyên và cú pháp các tệp JavaScript của dự án.

## Luồng thao tác

1. **Lịch của tôi:** chọn ngày, chuyển tuần, tìm nhiệm vụ và lọc trạng thái. Dữ liệu lịch mẫu bắt đầu ngày 02/11/2026; nút “Ngày mẫu” quay lại mốc này.
2. **Chi tiết nhiệm vụ:** xem mô tả, thiết bị, địa điểm, người báo, lịch phân công và lưu ý tại hiện trường. Nhiệm vụ chưa bắt đầu có nút “Bắt đầu xử lý”.
3. **Báo cáo xử lý:** nhiệm vụ đang xử lý có nút mở form. Thêm vật tư, ảnh sau xử lý và chữ ký người tiếp nhận. Kiểm tra bản xem trước rồi chọn “Xác nhận & hoàn tất bản thử”.
4. **Quay lại lịch:** nhiệm vụ đổi trạng thái trong bản thử và có liên kết xem báo cáo. Mục “Báo cáo nghiệm thu” cũng liệt kê các báo cáo để mở lại.

“Đã nghiệm thu” ở đây có nghĩa người tiếp nhận tại hiện trường đã xác nhận bàn giao. Chưa có quy trình quản lý duyệt báo cáo.

## Phạm vi

- Chỉ có HTML, CSS và JavaScript; chưa có đăng nhập, API, Laravel hoặc MySQL.
- Thao tác thử, ảnh và chữ ký được lưu bằng IndexedDB trên trình duyệt hiện tại, không gửi lên máy chủ. Không dùng dữ liệu thật để thử.
- Báo cáo mẫu `SC-2026-081` có vùng giữ chỗ cho ảnh/chữ ký và được ghi rõ là dữ liệu minh họa. Báo cáo do người dùng thử tạo sẽ hiển thị ảnh và chữ ký đã nhập.
- Form lấy mã nhiệm vụ từ `?id=SC-2026-084`; phiếu không tồn tại, chưa bắt đầu hoặc đã nghiệm thu đều được chặn phù hợp.
- Khi bộ nhớ trình duyệt không khả dụng, lịch vẫn hiển thị dữ liệu mẫu. Lỗi lưu giữ người dùng tại form và không thông báo thành công.

## Tệp

- `pages/technician.html`: lịch cá nhân và danh sách báo cáo.
- `pages/technician-task.html`: chi tiết nhiệm vụ và báo cáo chỉ đọc.
- `pages/ticket-resolve.html`: form nghiệm thu.
- `assets/js/technician-data.js`: dữ liệu mẫu và bộ nhớ bản thử.
- `assets/js/technician.js`: tương tác lịch, nhiệm vụ và báo cáo.
- `assets/js/ticket-resolve.js`: vật tư, ảnh, canvas, kiểm tra dữ liệu và xác nhận bản thử.
- `assets/css/technician.css`, `assets/css/ticket-resolve.css`: bố cục responsive.

## Kiểm tra đã thực hiện

Đã kiểm tra cú pháp JavaScript, ID trùng trong từng màn hình, tham chiếu tài nguyên, quy tắc chuyển mục và mã phiếu trong liên kết. Trang tích hợp sử dụng 3 tệp CSS và 5 tệp JavaScript bên ngoài; build và kiểm tra đều đã hoàn tất. Chưa kiểm tra trực quan/tương tác trong trình duyệt tự động vì công cụ trình duyệt chặn giao thức `file:`. Cần thử lại ở kích thước điện thoại và máy tính, đặc biệt thao tác ký cảm ứng và luồng lưu ảnh.
