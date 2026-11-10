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
    <link rel="stylesheet" href="assets/css/auth.css?v=20261109-six-digit-otp5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
</head>

<body class="role-page">
    <main class="auth-page">
        <aside class="auth-aside"><a class="role-brand" href="{{ url('/') }}/"><img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk"></a>
            <div><span class="role-kicker">BẢO VỆ TÀI KHOẢN</span>
                <h1>Xác thực email của bạn.</h1>
                <p>Nhập mã dùng một lần được gửi đến email để hoàn tất đăng ký tài khoản sinh viên.</p>
            </div>
            <div class="auth-aside-foot">Mã xác thực gồm 6 chữ số.</div>
        </aside>
        <section class="auth-content">
            <div class="auth-card">
                <h2>Kiểm tra email</h2>
                <p>Nhập mã OTP 6 chữ số đã gửi đến <strong data-otp-email>email của bạn</strong>.</p>
                <form class="role-form" action="#" method="post" data-api-endpoint="/email/verify-otp" data-success-redirect="login" data-busy-label="Đang xác thực…" data-otp-resend-seconds="{{ config('auth.email_otp_resend_seconds', 60) }}">
                    <div class="role-field"><label for="email">Email đăng ký</label><input id="email" name="email" type="email" autocomplete="email" placeholder="ten@truong.edu.vn" required></div>
                    <div class="role-field otp-field">
                        <label id="otp-label">Mã xác thực OTP</label>
                        <input id="otp" name="otp" type="hidden" aria-describedby="otp-help">
                        <div class="otp-digit-group" data-otp-group role="group" aria-labelledby="otp-label" aria-describedby="otp-help">
                            @for($digit = 0; $digit < 6; $digit++)
                                <input class="otp-digit" type="text" inputmode="numeric" autocomplete="{{ $digit === 0 ? 'one-time-code' : 'off' }}" maxlength="6" aria-label="Chữ số thứ {{ $digit + 1 }}" aria-describedby="otp-help" data-otp-digit>
                            @endfor
                        </div>
                        <span class="role-form-note" id="otp-help">Mã có hiệu lực {{ config('auth.email_otp_expire_minutes', 5) }} phút. Bạn có thể gửi lại mã trong 24 giờ; sau thời gian đó, tài khoản chưa xác thực sẽ bị xóa và cần đăng ký lại.</span>
                    </div>
                    <button class="role-button" type="submit">Xác thực tài khoản</button>
                    <div class="role-form-note">Chưa thấy email? Kiểm tra mục Spam/Thư rác hoặc gửi lại mã bên dưới.</div><button class="role-button secondary" type="button" data-api-action="/email/resend-otp">Gửi lại mã OTP</button>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <div class="auth-switch">Đã xác thực? <a href="login">Đăng nhập</a></div><a class="auth-back" href="register">← Quay lại đăng ký</a>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js?v=20261109-six-digit-otp5"></script>
</body>

</html>
