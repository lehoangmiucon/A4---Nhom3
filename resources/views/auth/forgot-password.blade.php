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
    <title>Quên mật khẩu · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20261109-password-reset1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
@include('shared.password-visibility-assets')
</head>

<body class="role-page">
    @php($resetStep = request()->query('step') === 'reset')
    <main class="auth-page">
        <aside class="auth-aside">
            <a class="role-brand" href="{{ url('/') }}/"><img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk"></a>
            <div><span class="role-kicker">BẢO VỆ TÀI KHOẢN</span>
                <h1>{{ $resetStep ? 'Tạo mật khẩu mới.' : 'Lấy lại quyền truy cập.' }}</h1>
                <p>{{ $resetStep ? 'Nhập mã OTP trong email và đặt mật khẩu mới cho tài khoản của bạn.' : 'Chúng tôi sẽ gửi mã OTP đến email đã xác thực để bạn đặt lại mật khẩu.' }}</p>
            </div>
            <div class="auth-aside-foot">Mã OTP có hiệu lực {{ config('auth.email_otp_expire_minutes', 5) }} phút.</div>
        </aside>
        <section class="auth-content">
            <div class="auth-card">
                <h2>{{ $resetStep ? 'Đặt lại mật khẩu' : 'Quên mật khẩu?' }}</h2>
                <p>{{ $resetStep ? 'Mã OTP chỉ dùng một lần. Mật khẩu mới cần ít nhất 8 ký tự.' : 'Nhập email tài khoản Helpdesk đã xác thực của bạn.' }}</p>
                @if($resetStep)
                <form class="role-form" action="#" method="post" data-api-endpoint="/password/reset" data-api-method="POST" data-reset-on-success="true" data-busy-label="Đang cập nhật…">
                    <div class="role-field"><label for="email">Email tài khoản</label><input id="email" name="email" type="email" autocomplete="email" value="{{ request()->query('email') }}" readonly required></div>
                    <div class="role-field otp-field" data-reset-field>
                        <label id="otp-label">Mã OTP 6 chữ số</label>
                        <input id="otp" name="otp" type="hidden" aria-describedby="otp-help">
                        <div class="otp-digit-group" data-otp-group role="group" aria-labelledby="otp-label" aria-describedby="otp-help">
                            @for($digit = 0; $digit < 6; $digit++)
                                <input class="otp-digit" type="text" inputmode="numeric" autocomplete="{{ $digit === 0 ? 'one-time-code' : 'off' }}" maxlength="6" aria-label="Chữ số thứ {{ $digit + 1 }}" aria-describedby="otp-help" data-otp-digit>
                                @endfor
                        </div>
                        <span class="role-form-note" id="otp-help">Mã có hiệu lực {{ config('auth.email_otp_expire_minutes', 5) }} phút.</span>
                    </div>
                    <div class="role-field" data-reset-field><label for="new-password">Mật khẩu mới</label><input id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
                    <div class="role-field" data-reset-field><label for="password-confirmation">Xác nhận mật khẩu mới</label><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
                    <button class="role-button" type="submit" data-reset-field>Đặt lại mật khẩu</button>
                    <button class="role-button secondary" type="button" data-api-action="/password/forgot" data-reset-field>Gửi lại mã OTP</button>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                </form>
                @else
                <form class="role-form" action="#" method="post" data-api-endpoint="/password/forgot" data-api-method="POST" data-success-redirect="forgot-password?step=reset" data-busy-label="Đang gửi OTP…" data-otp-resend-seconds="{{ config('auth.email_otp_resend_seconds', 60) }}">
                    <div class="role-field"><label for="email">Email tài khoản</label><input id="email" name="email" type="email" autocomplete="email" value="{{ request()->query('email') }}" placeholder="Nhập email của bạn" required></div>
                    <button class="role-button" type="submit">Gửi mã OTP</button>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                </form>
                @endif
                <div class="auth-switch">Nhớ mật khẩu rồi? <a href="login">Đăng nhập</a></div>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js?v=20261109-password-reset1"></script>
</body>

</html>
