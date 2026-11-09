@extends('admin.layouts.app')

@section('title', 'Thông tin tài khoản · Helpdesk')

@section('content')
@php
    $roleLabels = ['admin' => 'Quản trị viên', 'staff' => 'Nhân viên kỹ thuật', 'student' => 'Sinh viên'];
@endphp

<section class="page-title-row account-page-heading">
    <div>
        <p class="eyebrow">TÀI KHOẢN CÁ NHÂN</p>
        <h1>Thông tin tài khoản</h1>
        <p>Quản lý hồ sơ và bảo mật thông tin đăng nhập của bạn.</p>
    </div>
    <a class="button button-secondary" href="{{ url('/admin/pages/team') }}">Đội ngũ &amp; tài khoản</a>
</section>

@if(session('status'))
    <div class="account-feedback account-feedback-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ session('status') }}</div>
@endif

<section class="account-hero panel">
    <div class="account-avatar">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 2)) }}</div>
    <div class="account-hero-copy">
        <span class="account-role-pill"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>{{ $roleLabels[$currentUser->role] ?? $currentUser->role }}</span>
        <h2>{{ $currentUser->name }}</h2>
        <p>{{ $currentUser->email }}</p>
    </div>
    <div class="account-status-pill {{ $currentUser->is_deleted ? 'is-locked' : '' }}">
        <i class="fa-solid {{ $currentUser->is_deleted ? 'fa-lock' : 'fa-circle-check' }}" aria-hidden="true"></i>
        {{ $currentUser->is_deleted ? 'Tài khoản bị khóa' : 'Tài khoản đang hoạt động' }}
    </div>
</section>

<div class="account-settings-grid">
    <section class="panel account-settings-card">
        <div class="account-card-heading">
            <span><i class="fa-regular fa-id-card" aria-hidden="true"></i></span>
            <div><h2>Hồ sơ cá nhân</h2><p>Cập nhật tên hiển thị trong hệ thống.</p></div>
        </div>
        <form method="POST" action="{{ url('/account/profile') }}" class="account-settings-form">
            @csrf
            <div class="form-item">
                <label for="account-name">Họ và tên</label>
                <input id="account-name" name="name" value="{{ old('name', $currentUser->name) }}" maxlength="120" required autocomplete="name">
                @error('name')<small class="account-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="form-item">
                <label for="account-email">Email đăng nhập</label>
                <input id="account-email" type="email" value="{{ $currentUser->email }}" readonly>
                <small class="account-field-hint">Email được dùng để đăng nhập và không thể đổi tại đây.</small>
            </div>
            <div class="form-item">
                <label for="account-phone">Số điện thoại (không bắt buộc)</label>
                <input id="account-phone" name="phone" type="tel" value="{{ old('phone', $currentUser->phone) }}" autocomplete="tel" pattern="\+?[0-9]{9,15}" maxlength="16">
                @error('phone')<small class="account-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="account-details-row">
                <div><small>Vai trò</small><strong>{{ $roleLabels[$currentUser->role] ?? $currentUser->role }}</strong></div>
                <div><small>Email xác thực</small><strong>{{ $currentUser->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</strong></div>
                <div><small>Ngày tham gia</small><strong>{{ $currentUser->created_at?->format('d/m/Y') ?? '—' }}</strong></div>
            </div>
            <div class="account-form-actions"><button class="button button-primary" type="submit"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i>Lưu hồ sơ</button></div>
        </form>
    </section>

    <section class="panel account-settings-card account-security-card">
        <div class="account-card-heading">
            <span><i class="fa-solid fa-key" aria-hidden="true"></i></span>
            <div><h2>Bảo mật tài khoản</h2><p>Đổi mật khẩu để bảo vệ tài khoản.</p></div>
        </div>
        <form method="POST" action="{{ url('/account/password') }}" class="account-settings-form">
            @csrf
            <div class="form-item">
                <label for="current-password">Mật khẩu hiện tại</label>
                <input id="current-password" type="password" name="current_password" required autocomplete="current-password">
                @error('current_password')<small class="account-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="form-item">
                <label for="new-password">Mật khẩu mới</label>
                <input id="new-password" type="password" name="password" minlength="8" required autocomplete="new-password">
                @error('password')<small class="account-field-error">{{ $message }}</small>@enderror
                <small class="account-field-hint">Sử dụng ít nhất 8 ký tự.</small>
            </div>
            <div class="form-item">
                <label for="password-confirmation">Xác nhận mật khẩu mới</label>
                <input id="password-confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
            </div>
            <div class="account-security-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Sau khi đổi mật khẩu, các phiên đăng nhập API trên thiết bị khác sẽ bị thu hồi.</span></div>
            <div class="account-form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-lock" aria-hidden="true"></i>Đổi mật khẩu</button></div>
        </form>
    </section>
</div>
@endsection
