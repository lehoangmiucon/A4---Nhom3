# Gửi OTP tới email trường @ut.edu.vn

Đăng ký chỉ nhận địa chỉ thuộc đúng tên miền `ut.edu.vn`. Nút **Đăng ký và nhận OTP** tạo tài khoản sinh viên chưa xác thực và gửi mã 6 chữ số tới email vừa nhập. Mã có hiệu lực 10 phút; gửi lại sẽ thay thế mã cũ. Nếu SMTP báo lỗi, đăng ký được hoàn tác để người dùng có thể thử lại cùng email.

## Email gửi qua Google Workspace

1. Đăng nhập tài khoản trường dùng làm địa chỉ gửi, bật Xác minh 2 bước.
2. Mở https://myaccount.google.com/apppasswords và tạo mật khẩu ứng dụng cho Helpdesk. Nếu không có tùy chọn này, liên hệ quản trị viên Google Workspace của trường để được cung cấp phương thức gửi được cho phép. Không dùng mật khẩu đăng nhập thông thường thay thế.
3. Sửa các dòng mail trong `.env` trên máy, thay các giá trị minh họa dưới đây bằng tài khoản gửi thực tế:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=taikhoangui@ut.edu.vn
MAIL_PASSWORD="MAT_KHAU_UNG_DUNG"
MAIL_FROM_ADDRESS="taikhoangui@ut.edu.vn"
MAIL_FROM_NAME="Helpdesk UTH"
```

`MAIL_USERNAME` và `MAIL_FROM_ADDRESS` là cùng tài khoản gửi. Người nhận là email sinh viên nhập trên form, không phải một email cố định. Với cổng 587, transport SMTP tự nâng cấp STARTTLS. Nếu `.env` có `MAIL_URL`, bỏ giá trị cũ để tránh ghi đè cấu hình trên.

4. Chạy tại thư mục dự án:

```powershell
D:\xampp\php\php.exe artisan config:clear
```

5. Đăng ký bằng một địa chỉ `@ut.edu.vn` có hộp thư thật, chưa có trong hệ thống. Kiểm tra Hộp thư đến và Spam, nhập mã để xác thực, sau đó đăng nhập. Nếu tài khoản đã đăng ký nhưng chưa xác thực, vào trang nhập OTP và chọn **Gửi lại mã OTP**.

Không commit `.env` hay chia sẻ mật khẩu ứng dụng. `.env.example` vẫn mặc định `MAIL_MAILER=log` để máy mới không gửi thư ngoài ý muốn. Luồng OTP sẽ báo chưa cấu hình gửi email thật khi mailer là `log` (hoặc `array` ngoài môi trường kiểm tra tự động), thay vì báo đã gửi. Chỉ cấu hình SMTP hợp lệ mới kiểm tra được việc nhận thư thật.

## Kiểm tra tự động

```powershell
D:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit tests/Feature/SchoolOtpTest.php
```

Các kiểm tra dùng SQLite trong bộ nhớ và mail transport `array`; không gửi email thật và không sửa database MySQL cục bộ.

Tài liệu Google: [Mật khẩu ứng dụng và hạn chế tài khoản trường](https://support.google.com/accounts/answer/185833?hl=vi), [máy chủ SMTP và cổng 587](https://support.google.com/mail/answer/7104828?hl=vi).
