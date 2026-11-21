# App Helpdesk

Ứng dụng Flutter Android/iOS cho các vai trò customer (student/teacher), staff và admin. App gọi trực tiếp API của Laravel Helpdesk; token Sanctum được lưu bằng secure storage.

## Chạy trên Android Emulator

1. Chạy Apache và MySQL của XAMPP; API hiện cấu hình trong Laravel tại /api.
2. Từ thư mục này chạy lệnh flutter run.
3. URL mặc định là http://10.0.2.2:8888/Laravelpro/helpdesk/public/api (Android Emulator truy cập máy host qua 10.0.2.2).

Nếu dùng thiết bị thật, mở Cấu hình máy chủ ở màn hình đăng nhập và nhập URL máy chủ. Thiết bị phải truy cập được máy chạy XAMPP. Dùng HTTPS cho thiết bị thật; ngoại lệ HTTP trong bản debug chỉ giới hạn localhost và Android Emulator.

## Chức năng

- Đăng nhập, đăng ký sinh viên + xác thực OTP, quên/đổi mật khẩu.
- Dashboard, hồ sơ, thông báo.
- Customer: gửi phiếu kèm ảnh, quét QR phòng, xem/lọc/sửa phiếu đang chờ.
- Staff: phiếu được giao, phiếu do bản thân báo, lịch, xác nhận lịch, gửi tiến độ/ảnh sửa chữa.
- Admin: dashboard/báo cáo, phiếu, phân công, đóng/mở lại, lịch, danh mục, tài khoản/đội ngũ, tòa nhà/phòng.

## Build APK debug

Lệnh: flutter build apk --debug

APK đầu ra: build/app/outputs/flutter-apk/app-debug.apk.

## Giới hạn tích hợp hiện tại

- Push notification của Android/iOS chưa nối Firebase/APNs; app đọc thông báo lưu trong API.
- MySQL cấu hình hiện tại của dự án Laravel đang từ chối kết nối tại 127.0.0.1:3309; cần khởi động/điều chỉnh MySQL để đăng nhập và gọi dữ liệu thành công.
- iOS build cần macOS/Xcode. Khi chạy trên iPhone, cấu hình máy chủ HTTPS có thể truy cập từ điện thoại.
