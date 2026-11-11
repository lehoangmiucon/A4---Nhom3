@extends('admin.layouts.app')

@section('title')
Tổng quan · Helpdesk trường học
@endsection

@section('content')
@php
    $dashboardDonutStops = [];
    $dashboardDonutCursor = 0;
    foreach (array_keys($statusLabels) as $status) {
        $count = (int) ($dashboardCounts[$status] ?? 0);
        if ($count === 0 || $dashboardTotal === 0) continue;
        $end = $dashboardDonutCursor + $count / $dashboardTotal * 100;
        $color = 'var(--ticket-status-'.str_replace('_', '-', $status).'-color)';
        $dashboardDonutStops[] = $color.' '.$dashboardDonutCursor.'% '.$end.'%';
        $dashboardDonutCursor = $end;
    }
    $dashboardDonutColor = $dashboardDonutStops ? 'conic-gradient('.implode(', ', $dashboardDonutStops).')' : '#e8eef4';
@endphp
<section class="welcome-row">
                        <div>
                            <p class="eyebrow">TỔNG QUAN HỆ THỐNG</p>
                            <h1>
                                Xin chào, {{ $currentUser->name }}
                                <span class="wave">✦</span>
                            </h1>
                            <p class="page-lead">
                                Theo dõi sự cố thiết bị và hoạt động hỗ trợ
                                trong toàn trường.
                            </p>
                        </div>
                    </section>
                    <section class="dashboard-period-row" aria-label="Phạm vi thống kê">
                        <div>
                            <span class="eyebrow">PHẠM VI THỐNG KÊ</span>
                            <p>Số liệu trên trang được lọc theo khoảng thời gian đã chọn.</p>
                        </div>
                        <form class="period-filter dashboard-period-filter" method="GET">
                            <label for="dashboard-period">Xem thống kê theo</label>
                            <select class="period-select" id="dashboard-period" name="period">
                                @foreach($dashboardPeriodOptions as $periodValue => $periodLabel)
                                    <option value="{{ $periodValue }}" @selected($dashboardPeriod === $periodValue)>{{ $periodLabel }}</option>
                                @endforeach
                            </select>
                        </form>
                    </section>
                    <section
                        class="metric-grid dashboard-metric-grid"
                        aria-label="Tổng hợp phiếu sự cố"
                    >
                        <a class="metric-card metric-card-link" href="pages/incidents?focus=today" aria-label="Xem các phiếu sự cố được gửi hôm nay">
                            <div class="metric-top">
                                <span>Phiếu {{ $dashboardPeriodTitle }}</span
                                ><span class="metric-icon icon-amber"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
                            </div>
                            <div class="metric-value">{{ $dashboardTodayCount }}</div>
                            <div class="metric-note">
                                Báo cáo sự cố trong khoảng thời gian đã chọn
                            </div>
                            <div class="metric-progress">
                                <i style="width: {{ $dashboardTotal ? round($dashboardTodayCount / $dashboardTotal * 100) : 0 }}%; background: #f2a93b"></i>
                            </div>
                        </a>
                        <a class="metric-card metric-card-link" href="pages/incidents?focus=unassigned" aria-label="Xem các phiếu đang chờ phân công">
                            <div class="metric-top">
                                <span>Chờ phân công</span
                                ><span class="metric-icon" style="background:#fff4dc;color:#9a6100"><i class="fa-solid fa-user-clock" aria-hidden="true"></i></span>
                            </div>
                            <div class="metric-value">{{ $dashboardUnassignedCount }}</div>
                            <div class="metric-note">
                                Cần quản lý tiếp nhận và giao việc
                            </div>
                            <div class="metric-progress">
                                <i style="width: {{ $dashboardTotal ? round($dashboardUnassignedCount / $dashboardTotal * 100) : 0 }}%; background: #9a6100"></i>
                            </div>
                        </a>
                        <a class="metric-card metric-card-link" href="pages/incidents?focus=resolved" aria-label="Xem các phiếu nhân viên đã hoàn thành và đang chờ quản lý xác nhận">
                            <div class="metric-top">
                                <span>Chờ xác nhận</span
                                ><span class="metric-icon" style="background:#e8f8f7;color:var(--ticket-status-resolved-color)"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span>
                            </div>
                            <div class="metric-value">{{ $dashboardResolvedCount }}</div>
                            <div class="metric-note">
                                Nhân viên đã hoàn thành, chờ quản lý duyệt
                            </div>
                            <div class="metric-progress">
                                <i style="width: {{ $dashboardTotal ? round($dashboardResolvedCount / $dashboardTotal * 100) : 0 }}%; background: var(--ticket-status-resolved-color)"></i>
                            </div>
                        </a>
                        <a class="metric-card metric-card-link" href="pages/incidents?focus=in_progress" aria-label="Xem các phiếu đang được nhân viên xử lý">
                            <div class="metric-top">
                                <span>Đang xử lý</span
                                ><span class="metric-icon" style="background:#f2edff;color:#7049c6"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                            </div>
                            <div class="metric-value">{{ $dashboardInProgressCount }}</div>
                            <div class="metric-note">
                                Nhân viên đang khắc phục sự cố
                            </div>
                            <div class="metric-progress">
                                <i style="width: {{ $dashboardTotal ? round($dashboardInProgressCount / $dashboardTotal * 100) : 0 }}%; background: #7049c6"></i>
                            </div>
                        </a>
                        <a class="metric-card metric-card-link teacher-metric-card" href="pages/incidents?reporter_role=teacher&amp;period={{ $dashboardPeriod }}" aria-label="Xem báo cáo giáo viên {{ $dashboardPeriodTitle }}">
                            <div class="metric-top">
                                <span>Báo cáo giáo viên</span>
                                <span class="metric-icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
                            </div>
                            <div class="metric-value">{{ $dashboardTeacherCount }}</div>
                            <div class="metric-note">Phiếu ưu tiên cao từ giáo viên {{ $dashboardPeriodTitle }}</div>
                            <div class="metric-progress">
                                <i style="width: {{ $dashboardTotal ? round($dashboardTeacherCount / $dashboardTotal * 100) : 0 }}%; background: #c46b1a"></i>
                            </div>
                        </a>
                    </section>
                    <section class="dashboard-grid">
                        <article class="panel status-panel">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">TIẾN ĐỘ XỬ LÝ</p>
                                    <h2>Tình hình phiếu sự cố</h2>
                                </div>
                                <a class="text-link" href="pages/incidents"
                                    >Xem danh sách <span>→</span></a
                                >
                            </div>
                            <div class="status-chart">
                                <div class="donut" style="background:{{ $dashboardDonutColor }}">
                                    <div>
                                        <strong>{{ $dashboardTotal }}</strong
                                        ><span>phiếu sự cố</span>
                                    </div>
                                </div>
                                <div class="legend-list">
                                    @foreach($statusLabels as $status => $label)
                                    <div>
                                        <i class="dot" style="background:var(--ticket-status-{{ str_replace('_', '-', $status) }}-color)"></i>
                                        <span>{{ $label }}</span>
                                        <strong>{{ $dashboardCounts[$status] ?? 0 }}</strong>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="status-foot">
                                <span class="trend-up">{{ $weeklyTicketCount }}</span
                                ><span
                                    >phiếu được tạo {{ $dashboardPeriodTitle }}</span
                                >
                            </div>
                        </article>
                        <article class="panel equipment-panel">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">THEO DANH M&#7908;C L&#7894;I</p>
                                    <h2>S&#7921; c&#7889; theo danh m&#7909;c</h2>
                                </div>
                            </div>
                            <div class="category-list">
                                @forelse($equipmentBreakdown as $item)
                                @php
                                    $equipmentName = strtolower(\Illuminate\Support\Str::ascii($item->name ?? ''));
                                    $equipmentIcon = match (true) {
                                        str_contains($equipmentName, 'ban ghe'), str_contains($equipmentName, 'noi that') => 'fa-chair',
                                        str_contains($equipmentName, 'lanh'), str_contains($equipmentName, 'dieu hoa') => 'fa-snowflake',
                                        str_contains($equipmentName, 'chieu') => 'fa-video',
                                        str_contains($equipmentName, 'wifi'), str_contains($equipmentName, 'mang') => 'fa-wifi',
                                        str_contains($equipmentName, 'dien'), str_contains($equipmentName, 'sang'), str_contains($equipmentName, 'den') => 'fa-lightbulb',
                                        str_contains($equipmentName, 'may tinh'), str_contains($equipmentName, 'computer') => 'fa-computer',
                                        default => 'fa-toolbox',
                                    };
                                    $categoryColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $item->color) ? $item->color : '#64748B';
                                @endphp
                                <div class="category-row"><span class="category-icon category-color-icon" style="--category-color: {{ $categoryColor }}" title="{{ $item->name }}"><i class="fa-solid {{ $equipmentIcon }}" aria-hidden="true"></i></span><span class="category-name">{{ $item->name }}</span><span class="category-track"><i style="width: {{ $equipmentBreakdown->max('tickets_count') ? round($item->tickets_count / $equipmentBreakdown->max('tickets_count') * 100) : 0 }}%;background: {{ $categoryColor }}"></i></span><strong>{{ $item->tickets_count }}</strong></div>
                                @empty
                                <div class="category-empty-state">
                                    <span class="category-empty-icon"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span>
                                    <div><strong>Chưa có sự cố trong khoảng thời gian này</strong><p>Khi có phiếu, thống kê theo danh mục sẽ hiển thị tại đây.</p></div>
                                </div>
                                @endforelse
                            </div>
                        </article>
                    </section>
                    <section class="panel recent-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">CẬP NHẬT MỚI NHẤT</p>
                                <h2>Phiếu sự cố gần đây</h2>
                            </div>
                            <a class="text-link" href="pages/incidents"
                                >Tất cả phiếu <span>→</span></a
                            >
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>MÃ PHIẾU</th>
                                        <th>NỘI DUNG</th>
                                        <th>ĐỊA ĐIỂM</th>
                                        <th>NGƯỜI BÁO</th>
                                        <th>TRẠNG THÁI</th>
                                        <th>THỜI GIAN</th>
                                        <th>THAO T&#193;C</th>
                                    </tr>
                                </thead>
                                <tbody>@forelse($recentTickets as $ticket)
<tr><td><a class="ticket-code" href="pages/ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->ticket_number }}</a></td><td><span class="issue-title">{{ $ticket->title }}</span><span class="issue-sub">@include('shared.ticket-category-badge', ['category' => $ticket->category, 'label' => $ticket->equipment_label])</span></td><td>{{ $ticket->room?->name ?? $ticket->room_label }}</td><td><span class="person-cell">{{ $ticket->reporter_name }}</span></td><td><span class="status-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td><td>{{ $ticket->created_at->format("H:i d/m") }}</td><td><a class="button button-secondary incident-detail-button" href="pages/ticket-detail?ticket={{ $ticket->id }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Xem chi ti&#7871;t</a></td></tr>
@empty
<tr><td colspan="7">Chưa có phiếu sự cố.</td></tr>
@endforelse</tbody>
                            </table>
                        </div>
                    </section>
                    <section class="bottom-grid">
                        <article class="panel location-summary">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">KHÔNG GIAN TRƯỜNG</p>
                                    <h2>Sự cố theo khu vực</h2>
                                </div>
                                <a class="text-link" href="pages/locations"
                                    >Xem địa điểm <span>→</span></a
                                >
                            </div>
                            <div class="location-cards">
                                @php
                                    $buildingAccents = ['#1686df', '#806ce0', '#159b83', '#d98b2b', '#d66a72', '#5273c7'];
                                    $openTicketCounts = $dashboardBuildings->mapWithKeys(fn ($building) => [
                                        $building->id => $building->rooms->sum(fn ($room) => $room->tickets->where('status', '!=', 'closed')->count()),
                                    ]);
                                    $maxOpenTickets = max(1, (int) $openTicketCounts->max());
                                @endphp
                                @forelse($dashboardBuildings as $building)
                                @php
                                    $openTickets = $openTicketCounts[$building->id] ?? 0;
                                    $buildingAccent = $buildingAccents[$loop->index % count($buildingAccents)];
                                @endphp
                                <a class="location-card" href="pages/building-detail?code={{ urlencode($building->code) }}" style="--location-accent: {{ $buildingAccent }}">
                                    <span class="building-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                                    <span class="location-card-copy">
                                        <span class="location-card-title"><span class="location-card-code">{{ $building->code }}</span><strong>{{ $building->name }}</strong></span>
                                        <span class="location-card-meter" role="progressbar" aria-label="Tỷ lệ phiếu chưa đóng tại {{ $building->name }}" aria-valuemin="0" aria-valuemax="{{ $maxOpenTickets }}" aria-valuenow="{{ $openTickets }}"><span style="width: {{ ($openTickets / $maxOpenTickets) * 100 }}%"></span></span>
                                        <small>{{ $openTickets === 1 ? '1 phiếu chưa đóng' : $openTickets.' phiếu chưa đóng' }}</small>
                                    </span>
                                    <span class="location-card-total"><strong>{{ $openTickets }}</strong><small>phiếu</small></span>
                                </a>
                                @empty
                                <p>Chưa có khu nhà.</p>
                                @endforelse
                            </div>
                        </article>
                    </section>
@endsection

@section('scripts')
<script src="assets/js/api-client.js?v=20261110-report-toast-center1"></script>
<script src="{{ url('/admin/assets/js/dashboard-period.js?v=20261109-week-ajax1') }}"></script>
@endsection
