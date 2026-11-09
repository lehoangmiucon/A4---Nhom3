@php
    $pagePath = $pagePath ?? 'index';
    $topbarTitles = [
        'index' => 'Tổng quan',
        'pages/incidents' => 'Phiếu sự cố',
        'pages/ticket-detail' => 'Chi tiết phiếu',
        'pages/ticket-review' => 'Duyệt kết quả sửa chữa',
        'pages/schedule' => 'Lịch phân công',
        'pages/locations' => 'Địa điểm & thiết bị',
        'pages/building-detail' => 'Chi tiết khu nhà',
        'pages/room-detail' => 'Chi tiết phòng',
        'pages/users' => 'Danh sách người dùng',
        'pages/user-detail' => 'Chi tiết sinh viên',
        'pages/team-detail' => 'Chi tiết đội ngũ',
        'pages/team' => 'Đội ngũ',
        'pages/categories' => 'Danh mục lỗi',
        'pages/reports' => 'Báo cáo',
        'pages/notifications' => 'Thông báo',
        'pages/account' => 'Tài khoản',
    ];
@endphp
<header class="topbar">
    <label class="mobile-menu" for="nav-toggle" aria-label="Mở menu"><span></span><span></span><span></span></label>
    <div class="breadcrumb"><strong>{{ $topbarTitles[$pagePath] ?? 'Quản trị' }}</strong></div>
    <div class="top-actions">
        <span class="today-label"><i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ now()->format('d/m/Y') }}</span>
        <div class="notification-menu" data-notification-menu>
        <button type="button" class="icon-button notification-trigger" aria-label="Thông báo" aria-expanded="false" aria-controls="notification-popover" data-notification-trigger>
            <i class="fa-solid fa-bell icon-fa" aria-hidden="true"></i>
            @if(($unreadNotificationCount ?? 0) > 0)
                <span class="notification-count">{{ min($unreadNotificationCount, 99) }}</span>
            @endif
        </button>
        <section class="notification-popover" id="notification-popover" aria-label="Thông báo gần đây" data-notification-popover hidden>
            <header class="notification-popover-header"><div><strong>Thông báo</strong><small>{{ $unreadNotificationCount ?? 0 }} chưa đọc</small></div><a href="{{ url('/admin/pages/notifications') }}">Xem tất cả</a></header>
            <div class="notification-popover-list">
                @forelse(($notificationPreviewGroups ?? collect()) as $group)
                    @php($notification = $group['latest'])
                    @php($notice = $notification->data)
                    @php($ticketId = $group['ticket_id'])
                    <a class="notification-popover-item {{ $group['unread_count'] ? 'is-unread' : '' }}" href="{{ $ticketId ? url('/admin/pages/ticket-detail?ticket='.$ticketId) : url('/admin/pages/notifications') }}" data-notification-read-url="{{ $group['read_url'] }}" data-notification-unread="{{ $group['unread_count'] }}">
                        <span class="notification-popover-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                        <span class="notification-popover-copy"><strong>{{ $notice['title'] ?? 'Cập nhật Helpdesk' }}</strong><small>{{ $notice['message'] ?? '' }}</small><time>{{ $notification->created_at->format('d/m/Y H:i') }} · {{ $group['count_label'] }}</time></span>
                    </a>
                @empty
                    <p class="notification-popover-empty">Chưa có thông báo.</p>
                @endforelse
            </div>
            <a class="notification-popover-footer" href="{{ url('/admin/pages/notifications') }}">Mở trang thông báo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </section>
        </div>
        <a class="top-user top-user-profile" href="{{ url('/admin/pages/account') }}" aria-label="Xem thông tin cá nhân và cài đặt tài khoản">
            <span class="user-avatar">{{ mb_strtoupper(mb_substr($currentUser->name ?? 'AD', 0, 2)) }}</span>
            <span class="user-ident"><strong>{{ $currentUser->name ?? 'Quản trị viên' }}</strong><small>Quản trị viên</small></span>
        </a>
        <form class="topbar-logout-form" action="{{ url('/logout') }}" method="post" data-logout-form>
            @csrf
            <button class="topbar-logout" type="submit" aria-label="Đăng xuất" title="Đăng xuất">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Đăng xuất</span>
            </button>
        </form>
    </div>
</header>
<script src="{{ url('/shared/js/logout.js?v=20261106-csrf1') }}" defer></script>
<script src="{{ url('/shared/js/page-feedback.js?v=20261106-fast1') }}" defer></script>
