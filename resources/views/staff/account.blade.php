<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tài khoản nhân viên · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/staff.css?v=20261106-cleanup1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261104-3">
    @include('shared.password-toggle')
</head>
<body class="staff-role-page role-page">
<div class="role-shell">
    @include('staff.partials.topbar')

    <main class="role-main staff-account">
        <section class="role-welcome">
            <div>
                <h1>Tài khoản của tôi</h1>
                <p>Xem thông tin cá nhân và cập nhật mật khẩu đăng nhập.</p>
            </div>
            <a class="role-button secondary" href="index">← Quay lại công việc</a>
        </section>

        @if (session('status'))
            <p class="staff-account-message is-success" role="status">{{ session('status') }}</p>
        @endif

        <div class="staff-account-grid">
            <section class="role-card staff-account-card">
                <div class="staff-account-identity">
                    <span class="staff-account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                    <div>
                        <h2>{{ $currentUser->name }}</h2>
                        <p>{{ $currentUser->role === 'admin' ? 'Quản lý' : 'Nhân viên kỹ thuật' }} · Helpdesk trường học</p>
                    </div>
                </div>

                <div class="staff-account-facts">
                    <div><span>Email đăng nhập</span><strong>{{ $currentUser->email }}</strong></div>
                    <div><span>Trạng thái email</span><strong>{{ $currentUser->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</strong></div>
                    <div><span>Ngày tạo tài khoản</span><strong>{{ $currentUser->created_at?->format('d/m/Y') ?? '—' }}</strong></div>
                </div>

                <form class="role-form staff-account-form" action="{{ url('/account/profile') }}" method="post">
                    @csrf
                    <h2>Thông tin cá nhân</h2>
                    <p class="role-form-note">Email được dùng để đăng nhập và không thể chỉnh sửa tại đây.</p>
                    <div class="role-field">
                        <label for="staff-name">Họ và tên</label>
                        <input id="staff-name" name="name" type="text" value="{{ old('name', $currentUser->name) }}" maxlength="120" required>
                        @error('name')<small class="staff-account-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="role-field">
                        <label for="staff-email">Email đăng nhập</label>
                        <input id="staff-email" type="email" value="{{ $currentUser->email }}" readonly aria-readonly="true">
                    </div>
                    <div class="role-field">
                        <label for="staff-phone">Số điện thoại (không bắt buộc)</label>
                        <input id="staff-phone" type="tel" name="phone" value="{{ old('phone', $currentUser->phone) }}" autocomplete="tel" pattern="\+?[0-9]{9,15}" maxlength="16">
                        @error('phone')<small class="staff-account-error">{{ $message }}</small>@enderror
                    </div>
                    <button class="role-button" type="submit">Lưu thông tin</button>
                </form>
            </section>

            <section class="role-card staff-account-card">
                <h2>Đổi mật khẩu</h2>
                <p class="role-form-note">Nhập mật khẩu hiện tại, sau đó chọn mật khẩu mới có ít nhất 8 ký tự.</p>
                <form class="role-form staff-account-form" action="{{ url('/account/password') }}" method="post">
                    @csrf
                    <div class="role-field">
                        <label for="current-password">Mật khẩu hiện tại</label>
                        <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password')<small class="staff-account-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="role-field">
                        <label for="new-password">Mật khẩu mới</label>
                        <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                        @error('password')<small class="staff-account-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="role-field">
                        <label for="confirm-password">Xác nhận mật khẩu mới</label>
                        <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button class="role-button" type="submit">Cập nhật mật khẩu</button>
                </form>
            </section>
        </div>

        <footer class="role-footer">Thông tin tài khoản nhân viên Helpdesk</footer>
    </main>
</div>
</body>
</html>
