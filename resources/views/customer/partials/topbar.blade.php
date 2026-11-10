<header class="role-topbar customer-topbar">
    <a class="role-brand" href="{{ url('/customer/index') }}" aria-label="Helpdesk · Tổng quan">
        <img class="role-brand-image" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk">
        <span class="customer-brand-caption">CỔNG HỖ TRỢ TRƯỜNG HỌC</span>
    </a>

    <nav class="role-nav" aria-label="Điều hướng sinh viên">
        <a class="{{ ($pagePath ?? '') === 'index' ? 'current' : '' }}" href="{{ url('/customer/index') }}">Tổng quan</a>
        <a class="{{ in_array($pagePath ?? '', ['tickets', 'ticket-detail'], true) ? 'current' : '' }}" href="{{ url('/customer/tickets') }}">Phiếu của tôi</a>
        <a class="{{ ($pagePath ?? '') === 'notifications' ? 'current' : '' }}" href="{{ url('/customer/notifications') }}">Thông báo
            @if(($unreadNotificationCount ?? 0) > 0)
                <span class="customer-notification-badge" aria-label="{{ $unreadNotificationCount }} unread notifications">{{ min($unreadNotificationCount, 99) }}{{ $unreadNotificationCount > 99 ? '+' : '' }}</span>
            @endif
        </a>
        <a class="{{ request()->is('customer/portal') ? 'current' : '' }}" href="{{ url('/customer/portal') }}">Gửi sự cố</a>
    </nav>

    <div class="customer-user-menu">
        <section class="role-account" aria-label="Tài khoản đang đăng nhập">
            <a class="role-account-profile {{ ($pagePath ?? '') === 'account' ? 'is-current' : '' }}" href="{{ url('/customer/account') }}" aria-label="Xem thông tin tài khoản">
                @if($currentUser->avatar_path)
                    <img class="role-account-icon" style="object-fit:cover" src="{{ url('/storage/'.$currentUser->avatar_path) }}" alt="">
                @else
                    <span class="role-account-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                @endif
                <span class="role-account-details"><strong>{{ $currentUser->name }}</strong><small>{{ $currentUser->email }}</small></span>
            </a>
            <form action="{{ url('/logout') }}" method="post" data-logout-form>
                @csrf
                <button class="role-logout" type="submit">Đăng xuất</button>
            </form>
        </section>
    </div>
</header>
<script src="{{ url('/shared/js/logout.js?v=20261106-csrf1') }}" defer></script>
<script src="{{ url('/shared/js/page-feedback.js?v=20261110-report-toast-center1') }}" defer></script>
<script src="{{ url('/admin/assets/js/native-selects.js?v=20261110-custom-select1') }}" defer></script>
