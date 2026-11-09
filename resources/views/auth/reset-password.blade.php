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
    <title>Đặt mật khẩu mới · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    @include('shared.password-toggle')
</head>

<body class="role-page">
    <main class="auth-page">
        <aside class="auth-aside"><a class="role-brand" href="{{ url('/') }}/"><img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk"></a>
            <div><span class="role-kicker">HỖ TRỢ KỸ THUẬT NHÀ TRƯỜNG</span>
                <h1>Mọi sự cố đều có thể được theo dõi.</h1>
                <p>Đăng nhập để gửi yêu cầu, cập nhật công việc hoặc quản lý hoạt động hỗ trợ trong trường.</p>
            </div>
            <div class="auth-aside-foot">HELPDESK · TRƯỜNG HỌC</div>
        </aside>
        <section class="auth-content">
            <div class="auth-card"><h2>Đặt mật khẩu mới</h2>
<p>Nếu email thuộc tài khoản trong hệ thống, mã đặt lại đã được gửi tới hộp thư. Dán mã trong email vào bên dưới.</p>
<form class="role-form" action="{{ url('/reset-password') }}" method="post" data-api-endpoint="/reset-password" data-success-redirect="login" data-success-delay="1800">
    @csrf
    <input name="email" type="hidden" value="{{ session('password_reset_email') }}">
    <div class="role-field"><label for="token">Mã đặt lại trong email</label><input id="token" class="reset-code-input" name="token" type="text" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" minlength="6" maxlength="6" pattern="[A-Za-z]{6}" placeholder="Nhập mã đặt lại tại đây" required></div>
    <div class="role-field"><label for="password">Mật khẩu mới</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="Tối thiểu 8 ký tự" required></div>
    <div class="role-field"><label for="password_confirmation">Xác nhận mật khẩu</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" placeholder="Nhập lại mật khẩu mới" required></div>
    <button class="role-button" type="submit">Đổi mật khẩu</button>
    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
</form>
<div class="auth-switch"><a href="{{ url('/auth/forgot-password') }}">Chưa nhận được mã? Gửi lại</a></div>
<a class="auth-back" href="{{ url('/auth/login') }}">← Quay lại đăng nhập</a>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js"></script>
</body>

</html>
