<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/') ),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Thông báo kỹ thuật · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/staff.css?v=20261106-local-fonts1">
    <link rel="stylesheet" href="../admin/assets/css/notifications.css?v=20261104-grouped1">
    <link rel="stylesheet" href="assets/css/staff-notifications.css?v=20261104-redesign1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261104-3">
</head>
<body class="staff-role-page role-page">
<div class="role-shell">
    @include('staff.partials.topbar')

    @php($unreadTotal = $notificationGroups->sum('unread_count'))

    <main class="role-main staff-notifications">
        <section class="sn-hero">
            <div class="sn-hero-copy">
                <span class="sn-eyebrow"><span></span> TRUNG TÂM CẬP NHẬT</span>
                <h1>Thông báo</h1>
                <p>Theo dõi phiếu được giao, lịch xử lý và cập nhật mới từ quản lý.</p>
            </div>
            <div class="sn-summary" aria-label="Tổng quan thông báo">
                <div class="sn-summary-item">
                    <span class="sn-summary-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    </span>
                    <span><strong>{{ $notificationGroups->count() }}</strong><small>Nhóm cập nhật</small></span>
                </div>
                <div class="sn-summary-item sn-summary-unread">
                    <span class="sn-summary-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9M12 7v5l3 2M19 3v6M16 6h6"/></svg>
                    </span>
                    <span><strong>{{ $unreadTotal }}</strong><small>Chưa đọc</small></span>
                </div>
            </div>
            <span class="sn-hero-orbit" aria-hidden="true"></span>
        </section>

        <section class="sn-panel" aria-labelledby="sn-list-title">
            <header class="sn-panel-heading">
                <div>
                    <span class="sn-section-kicker">HỘP THƯ CÔNG VIỆC</span>
                    <h2 id="sn-list-title">Cập nhật gần đây</h2>
                    <p>Thông báo cùng một phiếu được gộp lại để bạn dễ theo dõi.</p>
                </div>
                <span class="sn-total-pill">{{ $notificationGroups->count() }} mục</span>
            </header>

            <p class="api-feedback" id="staff-notification-error" role="status" aria-live="polite" hidden></p>

            <div class="sn-list">
                @forelse($notificationGroups as $group)
                    @php($notification = $group['latest'])
                    @php($notice = $notification->data)
                    @php($canOpenTicket = $group['ticket_id'] && $accessibleTicketIds->contains($group['ticket_id']))
                    <a class="notification-group sn-notification {{ $group['unread_count'] ? 'is-unread' : '' }}"
                       href="{{ $canOpenTicket ? url('/staff/ticket-detail').'?ticket='.$group['ticket_id'] : '#' }}"
                       data-notification-read-url="{{ $group['read_url'] }}"
                       data-notification-unread="{{ $group['unread_count'] }}">
                        <span class="sn-notification-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                        </span>
                        <span class="sn-notification-copy">
                            <span class="sn-notification-title">{{ $notice['title'] ?? 'Cập nhật Helpdesk' }} @if($group['unread_count'])<span class="sn-unread-label">Mới</span>@endif</span>
                            <span class="sn-notification-message">{{ $notice['message'] ?? 'Có cập nhật mới cho công việc của bạn.' }}</span>
                            @if($group['ticket_id'] && !$canOpenTicket)
                                <small>Phiếu không còn trong danh sách công việc của bạn.</small>
                            @endif
                            <span class="sn-notification-time">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ $notification->created_at->format('d/m/Y · H:i') }}
                            </span>
                        </span>
                        <span class="sn-notification-meta">
                            <span class="sn-update-count">{{ $group['count'] }} cập nhật</span>
                            @if($group['unread_count'])<span class="sn-unread-count">{{ $group['unread_count'] }} chưa đọc</span>@endif
                            @if($canOpenTicket)
                                <span class="sn-open-arrow" aria-hidden="true">→</span>
                            @endif
                        </span>
                    </a>
                @empty
                    <div class="sn-empty">
                        <span class="sn-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                        </span>
                        <h3>Chưa có thông báo mới</h3>
                        <p>Khi có phiếu được giao hoặc lịch thay đổi, thông tin sẽ xuất hiện tại đây.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <footer class="role-footer">Helpdesk trường học <span>·</span> Thông báo cá nhân</footer>
    </main>
</div>
<script src="assets/js/staff.js?v=20261106-cleanup1"></script>
<script src="../admin/assets/js/notification-groups.js?v=20261104-grouped1"></script>
</body>
</html>
