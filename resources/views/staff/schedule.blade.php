<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lịch làm việc · Helpdesk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/staff.css?v=20261106-cleanup1">
    <link rel="stylesheet" href="../admin/assets/css/ticket-status.css?v=20261104-status1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261110-custom-select1">
    <link rel="stylesheet" href="assets/css/schedule-week.css?v=20261106-week1">
</head>
<body class="staff-role-page role-page staff-week-page">
<div class="role-shell">
    @include('staff.partials.topbar')
    <main class="role-main staff-schedule">
        <section class="role-welcome staff-week-hero">
            <div>
                <span class="role-kicker">KẾ HOẠCH CÁ NHÂN</span>
                <h1>Lịch làm việc</h1>
                <p>Xem các phiếu được phân công theo từng ngày trong tuần.</p>
            </div>
            <a class="role-button secondary" href="index"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Công việc của tôi</a>
        </section>

        <section class="role-card staff-week-card">
            <div class="staff-week-heading">
                <div>
                    <span class="role-kicker">LỊCH PHÂN CÔNG</span>
                    <h2>Tuần {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</h2>
                    <p>{{ $schedules->count() }} lịch trong tuần này</p>
                </div>
                <a class="staff-week-today" href="{{ url('/staff/schedule') }}?week={{ today()->format('Y-m-d') }}"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i> Tuần này</a>
            </div>

            <nav class="staff-week-nav" aria-label="Chuyển tuần">
                <a href="{{ url('/staff/schedule') }}?week={{ $weekDate->copy()->subWeek()->format('Y-m-d') }}" aria-label="Tuần trước"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span>Tuần trước</span></a>
                <strong><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</strong>
                <a href="{{ url('/staff/schedule') }}?week={{ $weekDate->copy()->addWeek()->format('Y-m-d') }}" aria-label="Tuần sau"><span>Tuần sau</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            </nav>

            <p class="api-feedback" id="schedule-load-error" role="status" aria-live="polite" hidden></p>
            <div class="staff-week-grid">
                @php($weekDayNames = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ Nhật'])
                @foreach($weekDays as $index => $weekDay)
                    @php($date = $weekDay['date'])
                    <section class="staff-week-day {{ $weekDay['is_today'] ? 'is-today' : '' }}" aria-label="{{ $weekDayNames[$index] }} {{ $date->format('d/m/Y') }}">
                        <header class="staff-week-day-heading">
                            <div><strong>{{ $weekDayNames[$index] }}</strong><span>{{ $date->format('d/m') }}</span></div>
                            @if($weekDay['is_today'])<span class="staff-week-today-label">Hôm nay</span>@endif
                        </header>
                        <div class="staff-week-day-events">
                            @forelse($weekDay['schedules'] as $schedule)
                                <a class="staff-week-event" href="ticket-detail?ticket={{ $schedule->ticket_id }}">
                                    <time><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $schedule->starts_at->format('H:i') }}</time>
                                    <strong>{{ $schedule->ticket->title }}</strong>
                                    <small>{{ $schedule->ticket->ticket_number }}</small>
                                    <small><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $schedule->ticket->room?->building?->name }} · {{ $schedule->ticket->room?->name }}</small>
                                    @if($schedule->note)<small class="staff-week-note">{{ $schedule->note }}</small>@endif
                                    <span class="role-badge ticket-status ticket-status-{{ str_replace('_', '-', $schedule->ticket->status) }}">{{ $statusLabels[$schedule->ticket->status] ?? $schedule->ticket->status }}</span>
                                </a>
                            @empty
                                <p class="staff-week-empty"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Chưa có lịch</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
        <footer class="role-footer">Lịch cá nhân · Các thay đổi lịch sẽ được thông báo</footer>
    </main>
</div>
<script src="assets/js/staff.js?v=20261106-cleanup1"></script>
</body>
</html>