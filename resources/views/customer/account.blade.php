<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tài khoản của tôi · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/customer.css?v=20261103-responsive1">
    <link rel="stylesheet" href="assets/css/account.css?v=20261109">
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

        @if(session('status'))<p class="web-account-notice" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="web-account-notice is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
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
                    <div><dt>Vai trò</dt><dd>{{ ['admin' => 'Quản lý', 'staff' => 'Nhân viên kỹ thuật', 'student' => $currentUser->member_type === 'lecturer' ? 'Giảng viên' : 'Sinh viên'][$currentUser->role] ?? 'Người dùng' }}</dd></div>
                    <div><dt>Email xác thực</dt><dd>{{ $currentUser->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</dd></div>
                    <div><dt>Ngày tạo tài khoản</dt><dd>{{ $currentUser->created_at?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
                <form class="account-password-form web-profile-form" action="{{ url('/account/profile') }}" method="post">
                    @csrf
                    <h2>Chỉnh sửa hồ sơ</h2>
                    <label for="profile-name">Họ và tên</label>
                    <input id="profile-name" name="name" value="{{ old('name', $currentUser->name) }}" maxlength="120" autocomplete="name" required>
                    @if($currentUser->member_type !== 'lecturer')
                    <label for="profile-code">Mã sinh viên</label>
                    <input id="profile-code" name="member_code" value="{{ old('member_code', $currentUser->member_code) }}" maxlength="80" required>
                    @endif
                    <label for="profile-phone">Số điện thoại <small>(không bắt buộc)</small></label>
                    <input id="profile-phone" type="tel" name="phone" value="{{ old('phone', $currentUser->phone) }}" autocomplete="tel" pattern="\+?[0-9]{9,15}" maxlength="16">
                    <button class="role-button account-submit" type="submit">Lưu hồ sơ</button>
                </form>
            </section>

            <section class="role-card account-password-card">
                <div class="account-section-heading">
                    <span class="account-section-icon" aria-hidden="true">⌑</span>
                    <div>
                        <span class="role-kicker">BẢO MẬT</span>
                        <h2>Đổi mật khẩu</h2>
                        <p>Xác thực mã gửi đến email trường của bạn trước khi đổi mật khẩu.</p>
                    </div>
                </div>

                <div class="account-code-recipient"><small>Email nhận mã</small><strong>{{ $currentUser->email }}</strong></div>
                <form class="account-password-form" data-password-code-form action="{{ url('/account/password-code') }}" method="post">
                    @csrf
                    <button class="role-button secondary account-submit" type="submit">Gửi mã xác thực</button>
                    <p data-account-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <form class="account-password-form" data-password-reset-form action="{{ url('/account/password-confirm') }}" method="post">
                    @csrf
                    <label for="password-code">Mã xác thực trong email</label>
                    <input id="password-code" name="token" maxlength="6" minlength="6" autocomplete="one-time-code" placeholder="Nhập mã xác thực" required>
                    <label for="new-password">Mật khẩu mới</label>
                    <input id="new-password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                    <small class="account-field-hint">Mật khẩu mới cần có ít nhất 8 ký tự.</small>
                    <label for="password-confirmation">Xác nhận mật khẩu mới</label>
                    <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    <p data-account-feedback role="status" aria-live="polite" hidden></p>
                    <button class="role-button account-submit" type="submit">Đổi mật khẩu</button>
                    <small class="account-field-hint">Đổi mật khẩu thành công sẽ yêu cầu đăng nhập lại.</small>
                </form>
            </section>
        </div>

        <footer class="role-footer">Helpdesk trường học · Quản lý tài khoản cá nhân</footer>
    </main>
</div>
<script src="{{ url('/shared/js/account-security.js?v=20261109') }}" defer></script>
</body>
</html>
