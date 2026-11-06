<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tài khoản của tôi · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/customer.css?v=20261106-local-fonts1">
    <link rel="stylesheet" href="assets/css/account.css?v=20261103-responsive1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261105-staff-header2">
</head>
<body class="customer-role-page role-page account-page">
<div class="role-shell">
    @include('customer.partials.topbar')

    <main class="role-main account-main">
        <section class="role-welcome account-welcome">
            <div>
                <span class="role-kicker">TÀI KHOẢN CÁ NHÂN</span>
                <h1>Thông tin tài khoản</h1>
                <p>Xem thông tin cá nhân và cập nhật mật khẩu đăng nhập.</p>
            </div>
            <a class="role-button secondary" href="index">← Quay lại tổng quan</a>
        </section>

        <div class="account-layout">
            <section class="role-card account-profile-card">
                <div class="account-card-heading">
                    <span class="account-avatar">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                    <div>
                        <span class="role-kicker">HỒ SƠ CỦA BẠN</span>
                        <h2>{{ $currentUser->name }}</h2>
                        <span class="account-status"><i></i> Tài khoản đang hoạt động</span>
                    </div>
                </div>

                <dl class="account-facts">
                    <div><dt>Họ và tên</dt><dd>{{ $currentUser->name }}</dd></div>
                    <div><dt>Email đăng nhập</dt><dd>{{ $currentUser->email }}</dd></div>
                    <div><dt>Vai trò</dt><dd>{{ ['admin' => 'Quản lý', 'staff' => 'Nhân viên kỹ thuật', 'student' => 'Sinh viên'][$currentUser->role] ?? 'Người dùng' }}</dd></div>
                    <div><dt>Email xác thực</dt><dd>{{ $currentUser->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</dd></div>
                    <div><dt>Ngày tạo tài khoản</dt><dd>{{ $currentUser->created_at?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
                <p class="account-readonly-note">Email và thông tin hồ sơ do quản lý hệ thống cập nhật. Bạn có thể tự đổi mật khẩu tại đây.</p>
            </section>

            <section class="role-card account-password-card">
                <div class="account-section-heading">
                    <span class="account-section-icon" aria-hidden="true">⌑</span>
                    <div>
                        <span class="role-kicker">BẢO MẬT</span>
                        <h2>Đổi mật khẩu</h2>
                        <p>Nhập mật khẩu hiện tại để xác nhận thay đổi.</p>
                    </div>
                </div>

                <form class="account-password-form" data-api-endpoint="account/password" data-api-method="POST">
                    <label for="current-password">Mật khẩu hiện tại</label>
                    <input id="current-password" type="password" name="current_password" autocomplete="current-password" required>

                    <label for="new-password">Mật khẩu mới</label>
                    <input id="new-password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                    <small class="account-field-hint">Mật khẩu mới cần có ít nhất 8 ký tự.</small>

                    <label for="password-confirmation">Xác nhận mật khẩu mới</label>
                    <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>

                    <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                    <button class="role-button account-submit" type="submit">Lưu mật khẩu mới</button>
                </form>
            </section>
        </div>

        <footer class="role-footer">Helpdesk trường học · Quản lý tài khoản cá nhân</footer>
    </main>
</div>
<script src="../admin/assets/js/api-client.js?v=20261106-fast-actions1"></script>
</body>
</html>
