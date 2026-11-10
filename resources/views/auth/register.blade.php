<!doctype html>
<html lang="vi">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.HELPDESK_CONFIG = {
            apiEnabled: true,
            appUrl: @json(url('/')),
            apiBase: "/api"
        };
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Đăng ký sinh viên · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20261109-six-digit-otp5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
</head>

<body class="role-page">
    <main class="auth-page">
        <aside class="auth-aside"><a class="role-brand" href="{{ url('/') }}/"><img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk"></a>
            <div><span class="role-kicker">TÀI KHOẢN SINH VIÊN</span>
                <h1>Báo sự cố nhanh. Theo dõi rõ ràng.</h1>
                <p>Tạo tài khoản sinh viên để theo dõi các phiếu đã gửi và nhận cập nhật khi tiến độ thay đổi.</p>
            </div>
            <div class="auth-aside-foot">Tài khoản đăng ký công khai mặc định là sinh viên.</div>
        </aside>
        <section class="auth-content">
            <div class="auth-card">
                <h2>Tạo tài khoản</h2>
                <p>Sử dụng email trường có đuôi <strong>@ut.edu.vn</strong> để đăng ký và nhận mã OTP.</p>
                <form class="role-form" action="#" method="post" data-api-endpoint="/register" data-success-redirect="verify-otp" data-busy-label="Đang tạo tài khoản và gửi mã OTP…">
                    <div class="role-field"><label for="name">Họ và tên</label><input id="name" name="name" autocomplete="name" placeholder="Nguyễn Minh Anh" required></div>
                    <div class="role-field"><label for="email">Email trường</label><input id="email" name="email" type="email" autocomplete="email" placeholder="mssv@ut.edu.vn" pattern="[^@\s]+@[uU][tT]\.[eE][dD][uU]\.[vV][nN]" title="Chỉ chấp nhận email trường có đuôi @ut.edu.vn" aria-describedby="school-email-help" data-school-email required><small class="role-form-note" id="school-email-help">Nhập đúng email do trường cấp. Mã xác thực sẽ được gửi vào hộp thư này.</small></div>
                    <div class="role-field"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="Tối thiểu 8 ký tự" required></div>
                    <div class="role-field"><label for="password-confirmation">Xác nhận mật khẩu</label><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" placeholder="Nhập lại mật khẩu" required></div>
                    <div class="auth-role-note">Tài khoản được tạo với vai trò <strong>Sinh viên</strong>. Mã OTP gồm 6 số có hiệu lực trong {{ config('auth.email_otp_expire_minutes', 5) }} phút. Nếu không xác thực trong 24 giờ, tài khoản sẽ bị xóa và bạn cần đăng ký lại.</div><button class="role-button" type="submit">Đăng ký và gửi mã OTP</button>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <div class="auth-switch">Đã nhận mã xác thực? <a href="verify-otp">Nhập mã OTP 6 chữ số</a></div>
                <div class="auth-switch">Đã có tài khoản? <a href="login">Đăng nhập</a></div>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js?v=20261109-six-digit-otp5"></script>
</body>

</html>
