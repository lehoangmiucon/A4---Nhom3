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
    <title>Đăng ký tài khoản · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20261109-compact">
    @include('shared.password-toggle')
</head>

<body class="role-page auth-register">
    <main class="auth-page">
        <aside class="auth-aside"><a class="role-brand" href="{{ url('/') }}/"><img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk"></a>
            <div><span class="role-kicker">TÀI KHOẢN TRƯỜNG HỌC</span>
                <h1>Báo sự cố nhanh. Theo dõi rõ ràng.</h1>
                <p>Tạo tài khoản để theo dõi các phiếu đã gửi và nhận cập nhật khi tiến độ thay đổi.</p>
            </div>
            <div class="auth-aside-foot">Dành cho sinh viên và giảng viên UTH.</div>
        </aside>
        <section class="auth-content">
            <div class="auth-card">
                <h2>Tạo tài khoản</h2>
                <p>Đăng ký bằng email trường @ut.edu.vn. Giảng viên được admin xác nhận sau khi đăng ký.</p>
                <form id="registration-panel" class="role-form" action="#" method="post" data-api-endpoint="/register" data-success-redirect="verify-otp">
                    <div class="role-field" data-student-code><label for="member-code">Mã sinh viên (không bắt buộc)</label><input id="member-code" name="member_code" maxlength="80" placeholder="Nhập mã sinh viên nếu có"></div>
                    <div class="role-field registration-name"><label for="name">Họ và tên</label><input id="name" name="name" autocomplete="name" placeholder="Nguyễn Minh Anh" required></div>
                    <div class="role-field registration-email"><label for="email">Email trường học</label><input id="email" name="email" type="email" autocomplete="email" placeholder="ten@ut.edu.vn" pattern="[^@\s]+@[uU][tT]\.[eE][dD][uU]\.[vV][nN]" title="Nhập email trường có đuôi @ut.edu.vn" maxlength="255" required></div>
                    <div class="role-field"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="Tối thiểu 8 ký tự" required></div>
                    <div class="role-field"><label for="password-confirmation">Xác nhận mật khẩu</label><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" placeholder="Nhập lại mật khẩu" required></div>
                    <div class="auth-role-note">Hệ thống sẽ gửi OTP 6 số đến email @ut.edu.vn vừa nhập. Mã có hiệu lực 10 phút.</div><button class="role-button" type="submit">Đăng ký và nhận OTP</button>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <div class="auth-switch">Đã nhận mã xác thực? <a href="verify-otp">Nhập mã OTP 6 chữ số</a></div>
                <div class="auth-switch">Đã có tài khoản? <a href="login">Đăng nhập</a></div>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js?v=20261109-teacher"></script>
</body>

</html>
