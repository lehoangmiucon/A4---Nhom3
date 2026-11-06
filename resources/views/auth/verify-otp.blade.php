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
    <title>Xác thực email · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body class="role-page">
    <main class="auth-page">
        <aside class="auth-aside"><a class="role-brand" href="{{ url('/') }}/"><img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk"></a>
            <div><span class="role-kicker">BẢO VỆ TÀI KHOẢN</span>
                <h1>Xác thực email trường học.</h1>
                <p>Nhập mã dùng một lần được gửi đến email để hoàn tất đăng ký tài khoản sinh viên.</p>
            </div>
            <div class="auth-aside-foot">Mã xác thực gồm 6 chữ số.</div>
        </aside>
        <section class="auth-content">
            <div class="auth-card">
                <h2>Kiểm tra email</h2>
                <p>Nhập mã OTP 6 chữ số đã gửi đến <strong data-otp-recipient>email @ut.edu.vn của bạn</strong>.</p>
                <form class="role-form" action="#" method="post" data-api-endpoint="/email/verify-otp" data-success-redirect="login" data-success-message="Đăng ký thành công" data-success-delay="1500">
                    <div class="role-field"><label for="email">Email đăng ký</label><input id="email" name="email" type="email" autocomplete="email" placeholder="ten@ut.edu.vn" pattern="[^@\s]+@[uU][tT]\.[eE][dD][uU]\.[vV][nN]" title="Nhập email trường có đuôi @ut.edu.vn" maxlength="255" required></div>
                    <div class="role-field"><label for="otp">Mã xác thực OTP</label><input id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" aria-describedby="otp-help" required><span class="role-form-note" id="otp-help">Mã gồm 6 chữ số và có thời hạn sử dụng.</span></div><button class="role-button" type="submit">Xác thực tài khoản</button>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                    <div class="role-form-note">N&#7871;u ch&#432;a nh&#7853;n &#273;&#432;&#7907;c m&#227;, h&#227;y ki&#7875;m tra h&#7897;p th&#432; ho&#7863;c g&#7917;i l&#7841;i.</div><button class="role-button secondary" type="button" data-api-action="/email/resend-otp">Gửi lại mã OTP</button>
                </form>
                <div class="auth-switch">Đã xác thực? <a href="login">Đăng nhập</a></div><a class="auth-back" href="register">← Quay lại đăng ký</a>
            </div>
        </section>
    </main>
    <script src="{{ url('/auth/assets/js/auth.js') }}?v={{ filemtime(public_path('auth/assets/js/auth.js')) }}"></script>
</body>

</html>
