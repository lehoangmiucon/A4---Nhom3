<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tổng quan sinh viên · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/customer.css?v=20261106-local-fonts1">
    <link rel="stylesheet" href="assets/css/account.css?v=20261103-responsive1">
    <link rel="stylesheet" href="../admin/assets/css/ticket-status.css?v=20261104-status1">
    <link rel="stylesheet" href="assets/css/dashboard-refresh.css?v=20261105-single-report2">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261105-staff-header2">
</head>
<body class="customer-role-page role-page customer-dashboard-page">
<div class="role-shell">
    @include('customer.partials.topbar')

    <main class="role-main student-dashboard">
        <section class="student-hero">
            <div class="student-hero-copy">
                <span class="role-kicker">CỔNG HỖ TRỢ SINH VIÊN</span>
                <h1>Xin chào, {{ $currentUser->name }}</h1>
                <p>Báo cáo sự cố tại phòng học và theo dõi tiến độ xử lý ngay tại đây.</p>
            </div>
            <svg class="student-campus-art" viewBox="0 0 300 150" role="img" aria-label="Minh họa khuôn viên trường">
                <path d="M24 132h250" stroke="#9fcdf2" stroke-width="5" stroke-linecap="round"/>
                <path d="M63 132V62l54-28 54 28v70" fill="#dcefff" stroke="#8fc5f2" stroke-width="3"/>
                <path d="M157 132V24h83v108" fill="#c8e5ff" stroke="#7fb8eb" stroke-width="3"/>
                <path d="M48 62h138M147 24h103" stroke="#8fc5f2" stroke-width="5"/>
                <path d="M81 78h18v18H81zm46 0h18v18h-18zm38 0h18v18h-18zm18-38h18v18h-18zm31 0h18v18h-18zm-49 46h18v18h-18zm31 0h18v18h-18z" fill="#79b9f1"/>
                <path d="M101 132V105a16 16 0 0 1 32 0v27" fill="#fff" stroke="#8fc5f2" stroke-width="3"/>
                <path d="M34 132V96m-13 6c0-12 7-19 13-19s13 7 13 19m-22 1c-7 0-10-5-10-10 0-6 4-10 10-10m22 20c7 0 10-5 10-10 0-6-4-10-10-10" fill="#79cb88" stroke="#55b66d" stroke-width="3" stroke-linecap="round"/>
                <path d="M268 132V94m-12 7c0-11 6-18 12-18s12 7 12 18m-20 0c-6 0-9-4-9-9 0-5 4-9 9-9m17 18c6 0 9-4 9-9 0-5-4-9-9-9" fill="#79cb88" stroke="#55b66d" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <a class="role-button student-hero-cta" href="{{ url('/customer/portal') }}"><span aria-hidden="true">＋</span> Báo sự cố mới</a>
        </section>

        <section class="role-stats student-stats" aria-label="Tổng quan phiếu sự cố">
            <article class="role-stat stat-blue"><span>Tổng phiếu đã gửi</span><strong>{{ $ticketCounts->sum() }}</strong><small>Tất cả báo cáo của bạn</small></article>
            <article class="role-stat stat-violet"><span>Đang tiếp nhận</span><strong>{{ ($ticketCounts['new'] ?? 0) + ($ticketCounts['assigned'] ?? 0) }}</strong><small>Đang chờ hoặc đã phân công</small></article>
            <article class="role-stat stat-teal"><span>Đang xử lý</span><strong>{{ ($ticketCounts['in_progress'] ?? 0) + ($ticketCounts['reopened'] ?? 0) }}</strong><small>Nhân viên đang khắc phục</small></article>
            <article class="role-stat stat-green"><span>Đã hoàn thành</span><strong>{{ ($ticketCounts['resolved'] ?? 0) + ($ticketCounts['closed'] ?? 0) }}</strong><small>Đã xử lý xong</small></article>
        </section>

                <section class="student-report-options" id="student-report-options" aria-labelledby="student-report-heading">
            <span class="student-report-heading-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zm4 0h2v6h-2zm-4 4h2v2h-2zM7 7h.01M17 7h.01M7 17h.01"/></svg>
            </span>
            <div class="student-report-copy">
                <span class="role-kicker">GỬI BÁO CÁO</span>
                <h2 id="student-report-heading">Tạo báo cáo sự cố</h2>
                <p>Quét mã QR tại phòng để điền vị trí tự động. Nếu chưa quét mã, mở biểu mẫu và chọn tòa nhà, phòng trong danh sách.</p>
                <small>Hai cách đều dùng chung một biểu mẫu.</small>
            </div>
            <a class="role-button student-report-cta" href="{{ url('/customer/portal') }}">Tạo báo cáo <span aria-hidden="true">→</span></a>
        </section>

        <section class="role-card student-recent-card">
            <div class="student-section-heading">
                <div><span class="role-kicker">THEO DÕI TIẾN ĐỘ</span><h2>Phiếu sự cố gần đây</h2></div>
                <a href="tickets">Tất cả phiếu <span aria-hidden="true">→</span></a>
            </div>
            <div class="role-table-wrap">
                <table class="role-table student-ticket-table">
                    <thead><tr><th>Mã phiếu</th><th>Nội dung sự cố</th><th>Phòng</th><th>Trạng thái</th><th>Cập nhật gần nhất</th><th></th></tr></thead>
                    <tbody>
                    @forelse($recentTickets as $ticket)
                        @php($displayStatus = in_array($ticket->status, ['assigned', 'reopened'], true) ? 'in_progress' : $ticket->status)
                        <tr>
                            <td><a class="student-ticket-number" href="ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->ticket_number }}</a></td>
                            <td><strong>{{ $ticket->title }}</strong><small>{{ $ticket->category?->name ?? 'Chưa phân loại' }}</small></td>
                            <td>{{ $ticket->room?->code ?? '—' }}</td>
                            <td><span class="role-badge ticket-status ticket-status-{{ str_replace('_', '-', $displayStatus) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
                            <td>{{ $ticket->updated_at->format('d/m/Y H:i') }}</td>
                            <td><a class="student-view-link" href="ticket-detail?ticket={{ $ticket->id }}">Chi tiết <span aria-hidden="true">→</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="student-empty"><strong>Bạn chưa gửi phiếu sự cố nào</strong><span>Khi gặp vấn đề tại phòng học, hãy tạo báo cáo để đội kỹ thuật hỗ trợ.</span><a href="{{ url('/customer/portal') }}">Tạo phiếu đầu tiên</a></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($recentTickets->isNotEmpty())<a class="role-button secondary student-all-tickets" href="tickets">Xem tất cả phiếu của tôi</a>@endif
        </section>

        <footer class="role-footer">Helpdesk trường học · Cổng hỗ trợ sinh viên</footer>
    </main>
</div>
</body>
</html>
