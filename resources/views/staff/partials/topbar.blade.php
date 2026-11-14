<header class="role-topbar staff-topbar">
    <a class="role-brand" href="index" aria-label="Helpdesk · Công việc">
        <img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk">
    </a>

    <nav class="role-nav" aria-label="Điều hướng nhân viên">
        <a class="{{ in_array($pagePath ?? '', ['index', 'ticket-detail'], true) ? 'current' : '' }}" href="index">Công việc</a>
        <a class="{{ ($pagePath ?? '') === 'schedule' ? 'current' : '' }}" href="schedule">Lịch của tôi</a>
        <a href="{{ url('/customer/tickets') }}">Phiếu đã gửi</a>
        <a href="{{ url('/customer/portal') }}">Gửi sự cố</a>
        <a class="{{ ($pagePath ?? '') === 'notifications' ? 'current' : '' }}" href="notifications">Thông báo
            @if(($unreadNotificationCount ?? 0) > 0)<span class="role-nav-notification-badge" aria-label="{{ $unreadNotificationCount }} unread notifications">{{ min($unreadNotificationCount, 99) }}{{ $unreadNotificationCount > 99 ? '+' : '' }}</span>
            @endif
        </a>
    </nav>

    <section class="role-account" aria-label="Tài khoản đang đăng nhập">
        <a class="role-account-profile {{ ($pagePath ?? '') === 'account' ? 'is-current' : '' }}" href="account" aria-label="Xem thông tin tài khoản">
            @if($currentUser->avatar_path)
                <img class="role-account-icon" style="object-fit:cover" src="{{ url('/storage/'.$currentUser->avatar_path) }}" alt="">
            @else
                <span class="role-account-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
            @endif
            <span class="role-account-details">
                <strong>{{ $currentUser->name }}</strong>
                <small>{{ $currentUser->email }}</small>
            </span>
        </a>
        <form action="{{ url('/logout') }}" method="post" data-logout-form>
            @csrf
            <button class="role-logout" type="submit">Đăng xuất</button>
        </form>
    </section>
</header>
<script src="{{ url('/shared/js/logout.js?v=20261106-csrf1') }}" defer></script>
<script src="{{ url('/shared/js/page-feedback.js?v=20261111-feedback-top1') }}" defer></script>
<script src="{{ url('/admin/assets/js/native-selects.js?v=20261111-categorymenu2') }}" defer></script>
