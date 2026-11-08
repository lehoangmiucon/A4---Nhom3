# Helpdesk UTH — ứng dụng Android

Ứng dụng Flutter Android dùng chung backend Laravel và database với website. Có giao diện riêng cho sinh viên/giảng viên, kỹ thuật viên và quản trị viên. Mã nguồn app nằm trong thư mục này; Laravel ở thư mục gốc repository.

## Chức năng hiện có

- Đăng nhập bằng email/mã sinh viên; đăng ký SV/GV, OTP email và khôi phục mật khẩu.
- SV/GV: trang chủ, quét QR/nhập phòng, báo sự cố kèm ảnh, theo dõi phiếu, đánh giá, thông báo, hồ sơ và bảo mật qua mã email.
- KTV: công việc được giao, lịch, xác nhận lịch, cập nhật trạng thái/ghi chú/ảnh kết quả, hồ sơ và đổi mật khẩu hiện tại.
- Admin: tổng quan, duyệt/yêu cầu bổ sung, phân công/lịch/ưu tiên, xác nhận kết quả hoặc xử lý lại, quản lý người dùng và tài khoản.

## Chuẩn bị

1. Cài Flutter, Android Studio và Android SDK (bao gồm Command-line Tools).
2. Chạy `flutter doctor`. Khắc phục mục Android toolchain nếu chưa đạt; Visual Studio không bắt buộc khi chỉ build Android.
3. Cấu hình Laravel/MySQL/SMTP theo README ở thư mục gốc. Chạy migration mới để có trường mã thành viên, đánh giá và duyệt thông tin phiếu.
4. Tại thư mục `mobile`, chạy `flutter pub get`.

## Chạy bằng Android emulator

Bật MySQL, rồi mở terminal thứ nhất tại thư mục Laravel:

```powershell
& "D:\xampp\php\php.exe" artisan serve --host=0.0.0.0 --port=8000
```

Mở máy ảo từ Android Studio. Trong terminal thứ hai:

```powershell
cd mobile
flutter devices
flutter run -d emulator-5554
```

Thay ID nếu máy ảo có ID khác. Mặc định app gọi `http://10.0.2.2:8000/api`.

## Chạy trên điện thoại thật

Ghép đôi Wireless debugging hoặc kết nối USB, rồi chạy `flutter devices`. Điện thoại phải truy cập được Laravel qua địa chỉ mạng của laptop:

```powershell
flutter run -d "ID_DIEN_THOAI" --dart-define=API_BASE_URL=http://IP_LAPTOP:8000/api
```

Thay hai giá trị minh họa bằng ID/IP hiện tại. Khi quay lại emulator, bỏ `--dart-define` để dùng địa chỉ mặc định. Chỉ cho phép HTTP ở bản debug; triển khai thực tế cần HTTPS.

## Phím trong terminal Flutter

- `r`: hot reload, thường giữ trạng thái.
- `R`: hot restart, khởi động app lại từ đầu.
- `q`: dừng chạy. Cần q rồi chạy lại sau khi thêm plugin Android hoặc đổi cấu hình native/tham số chạy.

Khi nghỉ: q trong Flutter → đóng máy ảo/tắt gỡ lỗi không dây → Ctrl+C trong Laravel → Stop MySQL/Apache khi không còn sử dụng.

## Kiểm tra và build

```powershell
flutter analyze
flutter test
flutter build apk --debug
```

APK debug được tạo ở `build/app/outputs/flutter-apk/app-debug.apk` và không đưa lên Git. Các ảnh golden dùng fixture kiểm thử, không phải tài khoản thật. Font phục vụ kiểm thử có giấy phép trong `test/assets`.

## Lưu ý phát triển

- Không commit token, mật khẩu, `.env`, `android/local.properties`, keystore, cache hay thư mục build.
- Tên định danh Android hiện là `vn.edu.ut.helpdesk_mobile`; cần thống nhất trước khi phát hành.
- Kotlin incremental đang tắt để tránh lỗi đường dẫn plugin khác ổ C/D trên Windows; có thể làm build Kotlin chậm hơn.
- Chưa có push nền và tự khôi phục phiên đăng nhập khi mở lại app. Chưa hoàn thiện hiển thị ảnh trong chi tiết phiếu phía SV/GV.
- Cần kiểm tra trọn luồng bằng tài khoản thật của cả ba vai trò, nhất là SMTP, QR/camera, ảnh và ngày giờ.
- Không tự retry khi gửi phiếu/cập nhật bị timeout: tải lại để kiểm tra trước khi gửi lần nữa.

Tài liệu chi tiết: [Tổng kết](TONG_KET_MOBILE_VA_BACKEND.md), [Hướng dẫn và tiến độ](HUONG_DAN_VA_TIEN_DO.md), [Kết nối Samsung](NHAT_KY_PHIEU_VA_KET_NOI_SAMSUNG.md), [Thiết kế admin](ADMIN_DESIGN.md). Những dòng “chưa commit” trong nhật ký mô tả thời điểm ghi; kiểm tra `git status` để biết trạng thái hiện tại.
