<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tài khoản của tôi · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/customer.css?v=20261103-responsive1">
    <link rel="stylesheet" href="assets/css/account.css?v=20261110-avatar-picker1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261110-custom-select1">
@include('shared.password-visibility-assets')
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
                    @if($currentUser->avatar_path)
                        <img class="account-avatar account-avatar-image" src="{{ url('/storage/'.$currentUser->avatar_path) }}" alt="Ảnh đại diện của {{ $currentUser->name }}">
                    @else
                        <span class="account-avatar">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                    @endif
                    <div>
                        <span class="role-kicker">HỒ SƠ CỦA BẠN</span>
                        <h2>{{ $currentUser->name }}</h2>
                        <span class="account-status"><i></i> Tài khoản đang hoạt động</span>
                    </div>
                </div>

                <form class="account-avatar-form" method="POST" action="{{ url('/account/profile') }}" enctype="multipart/form-data">
                    @csrf
                    <label for="customer-avatar-upload">Ảnh đại diện</label>
                    <input id="customer-avatar-upload" class="account-avatar-file" name="avatar" type="file" accept="image/*" required>
                    <small class="account-field-hint">Chọn ảnh JPG, PNG hoặc WebP, tối đa 5 MB. Email không thể chỉnh sửa.</small>
                    @error('avatar')<small class="account-field-error">{{ $message }}</small>@enderror
                    <button class="role-button account-submit" type="submit">Cập nhật ảnh đại diện</button>
                </form>
                <dl class="account-facts">
                    <div><dt>Họ và tên</dt><dd>{{ $currentUser->name }}</dd></div>
                    <div><dt>Email đăng nhập</dt><dd>{{ $currentUser->email }}</dd></div>
                    <div><dt>Vai trò</dt><dd>{{ ['admin' => 'Quản lý', 'staff' => 'Nhân viên kỹ thuật', 'student' => 'Sinh viên', 'teacher' => 'Giáo viên'][$currentUser->role] ?? 'Người dùng' }}</dd></div>
                    <div><dt>Email xác thực</dt><dd>{{ $currentUser->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</dd></div>
                    <div><dt>Ngày tạo tài khoản</dt><dd>{{ $currentUser->created_at?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
                @if(session('status'))<p class="account-readonly-note" role="status" data-auto-dismiss>{{ session('status') }}</p>@endif
                <p class="account-readonly-note">Bạn có thể tự cập nhật ảnh đại diện và đổi mật khẩu tại đây. Email không thể chỉnh sửa.</p>
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
<script src="../admin/assets/js/api-client.js?v=20261110-report-toast-center1"></script>
</body>
</html>
