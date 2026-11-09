@php
    $pagePath = $pagePath ?? 'index';
    $isCurrent = fn (array $paths): bool => in_array($pagePath, $paths, true);
    $teamOpen = str_starts_with($pagePath, 'pages/team');
    $systemOpen = $pagePath === 'pages/account';
@endphp
<aside class="sidebar">
    <a class="brand" href="{{ url('/admin/index') }}" aria-label="Helpdesk - Trang tổng quan">
        <img class="brand-logo" src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk">
    </a>
    <div class="school-chip">
        <span class="school-avatar">TH</span>
        <span><strong>Trường học</strong><small>Quản lý thiết bị</small></span>
    </div>
    <nav class="side-nav" aria-label="Điều hướng chính">
        <span class="nav-caption">MENU CHÍNH</span>
        <a class="nav-link {{ $isCurrent(['index']) ? 'active' : '' }}" href="{{ url('/admin/index') }}">
            <i class="fa-solid fa-table-cells-large nav-icon-fa" aria-hidden="true"></i><span>Tổng quan</span>
        </a>
        <a class="nav-link {{ $isCurrent(['pages/incidents', 'pages/ticket-detail', 'pages/ticket-review']) ? 'active' : '' }}" href="{{ url('/admin/pages/incidents') }}">
            <i class="fa-solid fa-triangle-exclamation nav-icon-fa" aria-hidden="true"></i><span>Phiếu sự cố</span>
            <span class="nav-count">{{ $pendingTicketCount ?? 0 }}</span>
        </a>
        <a class="nav-link {{ $isCurrent(['pages/schedule']) ? 'active' : '' }}" href="{{ url('/admin/pages/schedule') }}">
            <i class="fa-solid fa-calendar-days nav-icon-fa" aria-hidden="true"></i><span>Lịch phân công</span>
        </a>
        <a class="nav-link {{ $isCurrent(['pages/locations', 'pages/building-detail', 'pages/room-detail']) ? 'active' : '' }}" href="{{ url('/admin/pages/locations') }}">
            <i class="fa-solid fa-building nav-icon-fa" aria-hidden="true"></i><span>Địa điểm &amp; thiết bị</span>
        </a>
        <a class="nav-link {{ $isCurrent(['pages/users']) ? 'active' : '' }}" href="{{ url('/admin/pages/users') }}">
            <i class="fa-solid fa-user nav-icon-fa" aria-hidden="true"></i><span>Danh sách người dùng</span>
        </a>
        <details class="nav-group" {{ $teamOpen ? 'open' : '' }}>
            <summary class="nav-link group-link">
                <i class="fa-solid fa-users nav-icon-fa" aria-hidden="true"></i><span>Đội ngũ</span>
                <i class="fa-solid fa-chevron-down nav-chevron" aria-hidden="true"></i>
            </summary>
            <div class="nav-submenu">
                <a href="{{ url('/admin/pages/team#management') }}">
                    <i class="fa-solid fa-user-gear submenu-icon-fa" aria-hidden="true"></i>Quản lý
                </a>
                <a href="{{ url('/admin/pages/team#technicians') }}">
                    <i class="fa-solid fa-screwdriver-wrench submenu-icon-fa" aria-hidden="true"></i>Nhân viên
                </a>
            </div>
        </details>
        <a class="nav-link {{ $isCurrent(['pages/categories']) ? 'active' : '' }}" href="{{ url('/admin/pages/categories') }}">
            <i class="fa-solid fa-tags nav-icon-fa" aria-hidden="true"></i><span>Danh mục lỗi</span>
        </a>
        <a class="nav-link {{ $isCurrent(['pages/reports']) ? 'active' : '' }}" href="{{ url('/admin/pages/reports') }}">
            <i class="fa-solid fa-chart-line nav-icon-fa" aria-hidden="true"></i><span>Báo cáo</span>
        </a>
        <a class="nav-link {{ $isCurrent(['pages/notifications']) ? 'active' : '' }}" href="{{ url('/admin/pages/notifications') }}">
            <i class="fa-solid fa-bell nav-icon-fa" aria-hidden="true"></i><span>Thông báo</span>
        </a>
        <details class="nav-group system-group" {{ $systemOpen ? 'open' : '' }}>
            <summary class="nav-link group-link">
                <i class="fa-solid fa-gear nav-icon-fa" aria-hidden="true"></i><span>Hệ thống</span>
                <i class="fa-solid fa-chevron-down nav-chevron" aria-hidden="true"></i>
            </summary>
            <div class="nav-submenu">
                <a class="{{ $systemOpen ? 'active' : '' }}" href="{{ url('/admin/pages/account') }}">Thông tin tài khoản</a>
            </div>
        </details>
    </nav>
    <div class="sidebar-bottom">
        <div class="sidebar-foot">HELPDESK · TRƯỜNG HỌC</div>
    </div>
</aside>
