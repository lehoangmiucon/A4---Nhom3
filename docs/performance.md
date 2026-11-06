# Tối ưu tải trang

## Cách hoạt động

Laravel render danh sách vào HTML. Trang Đội ngũ và Phiếu sự cố dùng lại dữ liệu đó khi không có điều kiện lọc; chỉ gọi API khi người dùng tìm kiếm hoặc chọn bộ lọc. Khi xóa lọc, JavaScript khôi phục các hàng đã có, không gọi API. Giá trị bộ lọc được trình duyệt khôi phục vẫn được áp dụng. Request lọc trước bị hủy khi bắt đầu request mới, và kết quả cũ không được phép ghi đè trạng thái mới.

Thống kê Tổng quan và Báo cáo tái sử dụng kết quả đếm theo trạng thái. Trang Địa điểm và thống kê tòa nhà trên Tổng quan dùng số đếm của database thay vì nạp toàn bộ phiếu thành model PHP. Thống kê Tổng quan vẫn giới hạn đúng kỳ ngày/tháng/năm đã chọn.

Trang Thông báo tái sử dụng danh sách chính cho phần xem trước. Phần xem trước vẫn lấy 100 thông báo gần nhất, nhóm theo cùng quy tắc và hiển thị 5 nhóm; không rút ngắn cửa sổ dữ liệu vì có thể làm sai số cập nhật/chưa đọc trong mỗi nhóm.

## Font và icon

- Be Vietnam Pro: `public/shared/fonts/`, giấy phép SIL OFL trong `OFL.txt`.
- Font Awesome Free 7.3.1: `public/shared/fontawesome/`, giấy phép trong `LICENSE.txt`.
- CSS dùng đường dẫn tương đối tới tài nguyên local, hoạt động với Docker và XAMPP, kể cả ứng dụng đặt trong thư mục con.
- Giao diện không còn tải font từ Google Fonts hoặc icon từ CDN. CSS/JavaScript đã đổi phiên bản URL để trình duyệt nhận bản mới. Thay thế asset local về sau cũng cần tăng phiên bản tương ứng.

## Kết quả đo trên dữ liệu local

Ngày 06/11/2026, dữ liệu có 11 phiếu và 27 thông báo của admin. Đo trực tiếp controller/render và đếm truy vấn; các con số dưới đây không bao gồm middleware, kết nối HTTP hoặc thời gian vẽ của trình duyệt.

| Trang | Query trước → sau | Model được nạp trước → sau |
|---|---|---|
| Tổng quan | 14 → 11 | 64 → 53 |
| Báo cáo | 8 → 6 | 36 → 36 |
| Phiếu sự cố | 14 → 13 | 66 → 66 |
| Địa điểm | 7 → 6 | 46 → 35 |
| Thông báo | 6 → 5 | 70 → 43 |

Trang Đội ngũ và Phiếu sự cố bỏ được mỗi trang một request API lúc mở với bộ lọc trống. Không dùng các số trên để suy ra tỷ lệ tăng tốc cố định cho máy khác. Chi phí mount source từ Windows và việc `localhost` thử IPv6 trước IPv4 vẫn thuộc cấu hình môi trường từng máy.

## Kiểm tra hồi quy

PHP test dùng SQLite trong bộ nhớ theo `phpunit.xml`, không dùng dữ liệu MySQL thật:

```powershell
php artisan test
node --test tests/js/admin-filters.test.mjs
```

Chạy các lệnh trong thư mục dự án, với PHP/Composer và Node đã cài trên máy. Test PHP kiểm tra thống kê, giới hạn kỳ, cửa sổ xem trước thông báo và lượng dữ liệu tải; test JavaScript kiểm tra lần mở trang, bộ lọc được khôi phục, đổi lọc nhanh và xóa lọc khi request cũ chưa xong. Node chỉ cần để chạy test JavaScript, không bắt buộc để phục vụ website.
