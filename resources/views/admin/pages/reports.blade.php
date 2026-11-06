@extends('admin.layouts.app')
@section('title')
Báo cáo · Helpdesk trường học
@endsection
@section('content')
<section class="page-title-row">
    <div>
        <p class="eyebrow">TỔNG HỢP HOẠT ĐỘNG HỖ TRỢ</p>
        <h1>Báo cáo sự cố</h1>
        <p>Th&#7889;ng k&#234; phi&#7871;u s&#7921; c&#7889; v&#224; ti&#7871;n &#273;&#7897; x&#7917; l&#253; trong tr&#432;&#7901;ng.</p>
    </div><span class="period-pill">To&#224;n th&#7901;i gian</span>
</section>
<section class="summary-strip report-summary-strip">
    <div class="summary-tile"><span>Tổng phiếu sự cố</span><strong>{{ $totalTickets }}</strong></div>
    <div class="summary-tile"><span>Đã đóng</span><strong style="color:var(--ticket-status-closed-color)">{{ $reportCounts['closed'] ?? 0 }}</strong></div>
    <div class="summary-tile"><span>Thời gian xử lý trung bình</span><strong>{{ $averageResolutionHours }} giờ</strong></div>
</section>
<section class="two-column">
    <article class="panel">
        <p class="eyebrow">DANH M&#7908;C L&#7894;I</p>
        <h2 class="section-heading">S&#7921; c&#7889; theo danh m&#7909;c</h2>
        <p class="section-lead">S&#7889; phi&#7871;u theo danh m&#7909;c l&#7895;i &#273;&#227; ghi nh&#7853;n.</p>
        <div class="report-bars">@forelse($equipmentBreakdown as $item)<div class="report-bar-row"><span>{{ $item->name }}</span>
                <div class="report-bar"><i style="width: {{ $equipmentBreakdown->max("tickets_count") ? round($item->tickets_count / $equipmentBreakdown->max("tickets_count") * 100) : 0 }}%;background:{{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $item->color) ? $item->color : "#64748B" }}"></i></div><strong>{{ $item->tickets_count }}</strong>
            </div>@empty<p>Chưa có dữ liệu phân loại sự cố.</p>@endforelse</div>
    </article>
    <article class="panel">
        <p class="eyebrow">TIẾN ĐỘ PHIẾU</p>
        <h2 class="section-heading">Tr&#7841;ng th&#225;i x&#7917; l&#253;</h2>
        <p class="section-lead">Tổng số phiếu theo trạng thái hiện tại.</p>
        <div class="report-bars">
            @foreach($statusLabels as $status => $label)
            <div class="report-bar-row"><span>{{ $label }}</span>
                <div class="report-bar"><i style="width:{{ $totalTickets ? round(($reportCounts[$status] ?? 0) / $totalTickets * 100) : 0 }}%;background:var(--ticket-status-{{ str_replace('_', '-', $status) }}-color)"></i></div><strong>{{ $reportCounts[$status] ?? 0 }}</strong>
            </div>
            @endforeach
        </div>
    </article>
</section>
@endsection
