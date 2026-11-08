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
    <link rel="stylesheet" href="assets/css/auth.css">
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
            <div class="auth-card"><h2>Quên mật khẩu</h2>
<p>Nhập email tài khoản để nhận mã đặt lại mật khẩu.</p>
<form class="role-form" action="{{ url('/forgot-password') }}" method="post" data-api-endpoint="/forgot-password" data-success-redirect="reset-password" data-success-delay="1500">
    @csrf
    <div class="role-field"><label for="email">Email tài khoản</label><input id="email" name="email" type="email" autocomplete="email" maxlength="255" placeholder="Nhập email đã đăng ký" required></div>
    <button class="role-button" type="submit">Gửi mã đặt lại</button>
    <p class="role-form-note">Nếu email thuộc tài khoản trong hệ thống, hướng dẫn sẽ được gửi tới hộp thư. Kiểm tra cả thư mục thư rác.</p>
    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
</form>
<div class="auth-switch"><a href="{{ url('/auth/reset-password') }}">Tôi đã có mã đặt lại</a></div>
<a class="auth-back" href="{{ url('/auth/login') }}">← Quay lại đăng nhập</a>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js"></script>
</body>

</html>
