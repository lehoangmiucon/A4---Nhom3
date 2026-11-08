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
    <title>Đăng nhập · Helpdesk</title>
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
            <div class="auth-card">
                <h2>Đăng nhập</h2>
                <p>Nhập thông tin tài khoản Helpdesk của bạn.</p>
                <form class="role-form" action="#" method="post" data-api-endpoint="/login" data-success-message="&#272;&#259;ng nh&#7853;p th&#224;nh c&#244;ng.">
                    <div class="role-field"><label for="email">Email trường học</label><input id="email" name="email" type="email" autocomplete="username" placeholder="ten@truong.edu.vn" required></div>
                    <div class="role-field"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required></div><button class="role-button" type="submit">Đăng nhập</button>
                    <div class="role-form-note">Sau khi xác thực, hệ thống mở khu vực phù hợp với vai trò đã được cấp cho tài khoản.</div>
                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <a class="auth-back" href="{{ url('/auth/forgot-password') }}">Quên mật khẩu?</a>
                <div class="auth-switch">Chưa có tài khoản sinh viên? <a href="register">Đăng ký</a></div>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js"></script>
</body>

</html>
