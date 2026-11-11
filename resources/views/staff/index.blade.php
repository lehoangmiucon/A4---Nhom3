<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Công việc kỹ thuật · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/staff.css?v=20261106-cleanup1">
    <link rel="stylesheet" href="../admin/assets/css/ticket-status.css?v=20261104-status1">
    <link rel="stylesheet" href="assets/css/dashboard-refresh.css?v=20261111-categoryfilter1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261110-pagination1">
    <link rel="stylesheet" href="{{ url('/shared/css/ticket-category.css?v=20261111-color1') }}">
</head>
<body class="staff-role-page role-page staff-dashboard-page">
<div class="role-shell">
    @include('staff.partials.topbar')

    <main class="role-main staff-dashboard">
        <section class="staff-hero">
            <div>
                <span class="role-kicker">KHU VỰC KỸ THUẬT</span>
                <h1>{{ $currentUser->role === 'admin' ? 'Tổng quan công việc kỹ thuật' : 'Xin chào, ' . $currentUser->name }}</h1>
                <p>{{ $currentUser->role === 'admin' ? 'Theo dõi các phiếu và lịch phân công của đội kỹ thuật.' : 'Theo dõi các phiếu được giao cho bạn và cập nhật tiến độ xử lý.' }}</p>
            </div>
            <a class="role-button secondary" href="schedule"><span aria-hidden="true">▦</span> Xem lịch làm việc</a>
        </section>

        <section class="role-stats staff-stats" aria-label="Tổng quan công việc">
            <article class="role-stat stat-blue"><span>{{ $currentUser->role === 'admin' ? 'Phiếu đang được giao' : 'Phiếu được giao' }}</span><strong>{{ $tickets->count() }}</strong><small>{{ $currentUser->role === 'admin' ? 'Toàn đội kỹ thuật' : 'Các phiếu bạn phụ trách' }}</small></article>
            <article class="role-stat stat-violet"><span>Đang xử lý</span><strong>{{ $ticketCounts['in_progress'] ?? 0 }}</strong><small>Nhân viên đang khắc phục</small></article>
            <article class="role-stat stat-teal"><span>Lịch hôm nay</span><strong>{{ $todaySchedules->count() }}</strong><small>{{ now()->locale('vi')->translatedFormat('l, d/m') }}</small></article>
            <article class="role-stat stat-amber"><span>Chờ quản lý xác nhận</span><strong>{{ $ticketCounts['resolved'] ?? 0 }}</strong><small>Đã gửi kết quả xử lý</small></article>
        </section>

        <div class="staff-dashboard-grid">
            <section class="role-card staff-ticket-panel">
                <div class="staff-panel-heading">
                    <div><span class="role-kicker">{{ $currentUser->role === 'admin' ? 'PHÂN CÔNG HIỆN TẠI' : 'CÔNG VIỆC CỦA BẠN' }}</span><h2>Phiếu kỹ thuật</h2></div>
                    <span class="staff-count-pill">{{ $tickets->count() }} phiếu</span>
                </div>
                <div class="staff-ticket-tabs" role="tablist" aria-label="Lọc phiếu theo trạng thái">
                    <button class="is-active" type="button" data-staff-filter="all" role="tab" aria-selected="true">Tất cả <span>{{ $tickets->count() }}</span></button>
                    <button type="button" data-staff-filter="assigned" role="tab" aria-selected="false">Đã phân công <span>{{ $tickets->whereIn('status', ['assigned', 'new', 'reopened'])->count() }}</span></button>
                    <button type="button" data-staff-filter="in_progress" role="tab" aria-selected="false">Đang xử lý <span>{{ $tickets->where('status', 'in_progress')->count() }}</span></button>
                    <button type="button" data-staff-filter="completed" role="tab" aria-selected="false">Đã hoàn thành <span>{{ $tickets->whereIn('status', ['resolved', 'closed'])->count() }}</span></button>
                </div>
                <label class="staff-category-filter" for="staff-category-filter">
                    <span>Danh mục lỗi</span>
                    <select id="staff-category-filter">
                        <option value="">Tất cả danh mục</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" data-color="{{ $category->color }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="api-feedback" id="staff-list-error" role="status" aria-live="polite" hidden></p>
                <div class="staff-ticket-list" id="staff-ticket-list">
                    @forelse($tickets as $ticket)
                        @php
                            $categoryColor = $ticket->category?->color ?? '#94A3B8';
                            $nextSchedule = $ticket->schedules->first();
                            $ticketFilter = in_array($ticket->status, ['new', 'reopened', 'assigned'], true) ? 'assigned' : (in_array($ticket->status, ['resolved', 'closed'], true) ? 'completed' : $ticket->status);
                        @endphp
                        <article class="staff-ticket-item" data-ticket-status="{{ $ticketFilter }}" data-ticket-category="{{ $ticket->category_id ?? '' }}" style="--category-color: {{ $categoryColor }}">
                            <div class="staff-ticket-item-top">
                                <a class="staff-ticket-number" href="ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->ticket_number }}</a>
                                <span class="role-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
                            </div>
                            <h3><a href="ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->title }}</a></h3>
                            <div class="staff-ticket-meta">
                                @include('shared.ticket-category-badge', ['category' => $ticket->category, 'label' => $ticket->category?->name])
                                <span>{{ $ticket->room?->building?->name }} · {{ $ticket->room?->name }}</span>
                            </div>
                            <div class="staff-ticket-bottom">
                                <span>{{ $nextSchedule?->starts_at?->format('d/m/Y · H:i') ?? 'Chưa xếp lịch' }}</span>
                                <a href="ticket-detail?ticket={{ $ticket->id }}">Xem chi tiết <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @empty
                        <div class="role-empty staff-empty">Hiện chưa có phiếu nào được phân công.</div>
                    @endforelse
                    <div class="role-empty staff-filter-empty" id="staff-filter-empty" hidden>Không có phiếu ở trạng thái này.</div>
                </div>
            </section>

            <aside class="role-card staff-calendar-panel">
                <div class="staff-panel-heading">
                    <div><span class="role-kicker">LỊCH ĐIỀU PHỐI</span><h2>{{ $calendarPeriod === 'week' ? 'Lịch tuần' : 'Lịch tháng' }}</h2></div>
                    <a class="staff-today-link" href="{{ url('/staff/index') }}?{{ http_build_query(['period' => $calendarPeriod, 'day' => today()->format('Y-m-d'), 'month' => today()->format('Y-m')]) }}"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i> Hôm nay</a>
                </div>
                <div class="staff-calendar-period" role="group" aria-label="Chọn khoảng thời gian">
                    <a class="{{ $calendarPeriod === 'week' ? 'is-active' : '' }}" href="{{ url('/staff/index') }}?{{ http_build_query(['period' => 'week', 'day' => today()->format('Y-m-d')]) }}" aria-pressed="{{ $calendarPeriod === 'week' ? 'true' : 'false' }}"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i> Tuần này</a>
                    <a class="{{ $calendarPeriod === 'month' ? 'is-active' : '' }}" href="{{ url('/staff/index') }}?{{ http_build_query(['period' => 'month', 'month' => today()->format('Y-m'), 'day' => today()->format('Y-m-d')]) }}" aria-pressed="{{ $calendarPeriod === 'month' ? 'true' : 'false' }}"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Tháng này</a>
                </div>
                <div class="staff-calendar-toolbar">
                    @if($calendarPeriod === 'week')
                        <a href="{{ url('/staff/index') }}?{{ http_build_query(['period' => 'week', 'day' => $selectedDate->copy()->subWeek()->format('Y-m-d')]) }}" aria-label="Tuần trước"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                        <strong>{{ $calendarStart->format('d/m') }} – {{ $calendarEnd->format('d/m/Y') }}</strong>
                        <a href="{{ url('/staff/index') }}?{{ http_build_query(['period' => 'week', 'day' => $selectedDate->copy()->addWeek()->format('Y-m-d')]) }}" aria-label="Tuần sau"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                    @else
                        <a href="{{ url('/staff/index') }}?{{ http_build_query(['period' => 'month', 'month' => $calendarMonth->copy()->subMonth()->format('Y-m')]) }}" aria-label="Tháng trước"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                        <strong>{{ $calendarMonth->locale('vi')->translatedFormat('F Y') }}</strong>
                        <a href="{{ url('/staff/index') }}?{{ http_build_query(['period' => 'month', 'month' => $calendarMonth->copy()->addMonth()->format('Y-m')]) }}" aria-label="Tháng sau"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                    @endif
                </div>
                <div class="staff-calendar-grid {{ $calendarPeriod === 'week' ? 'is-week-view' : '' }}" role="grid" aria-label="Lịch phân công {{ $calendarPeriod === 'week' ? 'tuần' : 'tháng' }}">
                    @foreach(['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $weekday)
                        <span class="staff-calendar-weekday" role="columnheader">{{ $weekday }}</span>
                    @endforeach
                    @foreach($calendarDays as $calendarDay)
                        @php($day = $calendarDay['date'])
                        <a class="staff-calendar-day {{ $calendarDay['in_month'] ? '' : 'is-outside' }} {{ $calendarDay['is_today'] ? 'is-today' : '' }} {{ $calendarDay['is_selected'] ? 'is-selected' : '' }} {{ $calendarDay['schedules']->isNotEmpty() ? 'has-schedules' : '' }}"
                           href="{{ url('/staff/index') }}?{{ http_build_query(['period' => $calendarPeriod, 'month' => $calendarMonth->format('Y-m'), 'day' => $day->format('Y-m-d')]) }}"
                           aria-label="{{ $day->format('d/m/Y') }}, {{ $calendarDay['schedules']->count() }} lịch">
                            <span class="staff-calendar-date">{{ $day->day }}</span>
                            @if($calendarDay['schedules']->isNotEmpty())
                                <span class="staff-calendar-dots" aria-hidden="true">
                                    @foreach($calendarDay['schedules']->take(4) as $schedule)<i style="--category-color: {{ $schedule->ticket->category?->color ?? '#94A3B8' }}"></i>@endforeach
                                </span>
                                <small>{{ $calendarDay['schedules']->count() }}</small>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="staff-calendar-legend">
                    @foreach($calendarCategories as $category)<span><i style="--category-color: {{ $category->color ?? '#94A3B8' }}"></i>{{ $category->name }}</span>@endforeach
                    @if(!$hasCalendarSchedules)<span class="staff-no-events">{{ $calendarPeriod === 'week' ? 'Chưa có lịch trong tuần này' : 'Chưa có lịch trong tháng này' }}</span>@endif
                </div>
                <div class="staff-selected-day">
                    <div class="staff-selected-day-heading"><h3>{{ $selectedDate->locale('vi')->translatedFormat('l, d/m/Y') }}</h3><span>{{ $selectedDaySchedules->count() }} lịch</span></div>
                    @forelse($selectedDaySchedules as $schedule)
                        @php($scheduleColor = $schedule->ticket->category?->color ?? '#94A3B8')
                        <a class="staff-day-event" style="--category-color: {{ $scheduleColor }}" href="ticket-detail?ticket={{ $schedule->ticket_id }}">
                            <time>{{ $schedule->starts_at->format('H:i') }}</time>
                            <span><strong>{{ $schedule->ticket->ticket_number }} · {{ $schedule->ticket->title }}</strong><small>{{ $schedule->ticket->category?->name ?? 'Chưa phân loại' }} · {{ $schedule->ticket->room?->building?->name }} · {{ $schedule->ticket->room?->name }}</small></span>
                        </a>
                    @empty
                        <p class="staff-no-day-events">Không có lịch xử lý trong ngày này.</p>
                    @endforelse
                </div>
            </aside>
        </div>

        <footer class="role-footer">Lịch và phiếu được lấy từ các phân công hiện có trong Helpdesk.</footer>
    </main>
</div>
<script src="assets/js/staff.js?v=20261106-cleanup1"></script>
<script>
(function () {
    var cards = Array.from(document.querySelectorAll('.staff-ticket-item'));
    var list = document.getElementById('staff-ticket-list');
    if (!list || !cards.length) return;
    var pageSize = 15;
    var page = 1;
    var filter = 'all';
    var categoryFilter = document.getElementById('staff-category-filter');
    var nav = document.createElement('nav');
    nav.className = 'ticket-pagination staff-ticket-pagination';
    nav.setAttribute('aria-label', 'Phân trang phiếu kỹ thuật');
    nav.innerHTML = '<span></span><div class="ticket-pagination-links"></div>';
    list.after(nav);
    function render() {
        var selectedCategory = categoryFilter ? categoryFilter.value : '';
        var matching = cards.filter(function (card) {
            return (filter === 'all' || card.dataset.ticketStatus === filter)
                && (!selectedCategory || card.dataset.ticketCategory === selectedCategory);
        });
        var pages = Math.max(1, Math.ceil(matching.length / pageSize));
        page = Math.min(page, pages);
        var first = (page - 1) * pageSize;
        var shown = new Set(matching.slice(first, first + pageSize));
        cards.forEach(function (card) { card.hidden = !shown.has(card); });
        var empty = document.getElementById('staff-filter-empty');
        if (empty) empty.hidden = matching.length !== 0;
        nav.querySelector('span').textContent = matching.length ? 'Hiển thị ' + (first + 1) + '–' + Math.min(first + pageSize, matching.length) + ' / ' + matching.length + ' phiếu' : 'Hiển thị 0 phiếu';
        var links = nav.querySelector('.ticket-pagination-links');
        links.replaceChildren();
        if (pages < 2) return;
        function button(label, target, disabled, active) {
            var item = document.createElement('button');
            item.type = 'button'; item.textContent = label; item.disabled = disabled;
            if (active) { item.className = 'current'; item.setAttribute('aria-current', 'page'); }
            item.addEventListener('click', function () { page = target; render(); });
            links.append(item);
        }
        button('‹', page - 1, page === 1, false);
        for (var number = Math.max(1, page - 2); number <= Math.min(pages, page + 2); number++) button(String(number), number, false, number === page);
        button('›', page + 1, page === pages, false);
    }
    document.querySelectorAll('[data-staff-filter]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            filter = tab.dataset.staffFilter;
            page = 1;
            document.querySelectorAll('[data-staff-filter]').forEach(function (item) {
                var active = item === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            render();
        });
    });
    if (categoryFilter) categoryFilter.addEventListener('change', function () { page = 1; render(); });
    render();
})();
</script>
</body>
</html>
