<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Phiếu của tôi · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/customer.css?v=20261111-ticketsearch1">
    <link rel="stylesheet" href="assets/css/account.css?v=20261103-responsive1">
    <link rel="stylesheet" href="../admin/assets/css/ticket-status.css?v=20261104-status1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261110-pagination1">
    <link rel="stylesheet" href="{{ url('/shared/css/ticket-category.css?v=20261111-color1') }}">
</head>
<body class="customer-role-page role-page">
<div class="role-shell">
    @include('customer.partials.topbar')
    <main class="role-main student-tickets">
        <section class="role-welcome">
            <div>
                <span class="role-kicker">THEO DÕI YÊU CẦU</span>
                <h1>Phiếu sự cố của tôi</h1>
                <p>Xem trạng thái và lần cập nhật mới nhất cho các yêu cầu đã gửi.</p>
            </div>
            <a class="role-button" href="{{ url('/customer/portal') }}">＋ Gửi phiếu mới</a>
        </section>
        <section class="role-card">
            <p class="api-feedback" id="ticket-list-error" role="status" aria-live="polite" hidden></p>
            <form class="ticket-category-filter" method="get" action="{{ url('/customer/tickets') }}" role="search" aria-label="Tìm và lọc phiếu sự cố">
                <label class="ticket-search-field" for="ticket-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
                    <input id="ticket-search" name="q" type="search" value="{{ $ticketSearch }}" placeholder="Tìm mã phiếu, nội dung hoặc phòng…" aria-label="Tìm kiếm phiếu" autocomplete="off">
                </label>
                <div>
                    <select id="ticket-category-filter" name="category_id" aria-label="Lọc theo danh mục lỗi">
                        <option value="">Tất cả danh mục</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" data-color="{{ $category->color }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit">Lọc</button>
                    @if($selectedCategoryId !== '' || $ticketSearch !== '')
                        <a href="{{ url('/customer/tickets') }}">Xóa lọc</a>
                    @endif
                </div>
            </form>
            <div class="role-table-wrap">
                <table class="role-table">
                    <thead>
                        <tr><th>MÃ PHIẾU</th><th>NỘI DUNG</th><th>Danh mục lỗi</th><th>ĐỊA ĐIỂM</th><th>TRẠNG THÁI</th><th>CẬP NHẬT GẦN NHẤT</th></tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            @php($displayStatus = in_array($ticket->status, ['assigned', 'reopened'], true) ? 'in_progress' : $ticket->status)
                            <tr>
                                <td><a href="ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->ticket_number }}</a></td>
                                <td>{{ $ticket->title }}</td>
                                <td>@include('shared.ticket-category-badge', ['category' => $ticket->category, 'label' => $ticket->category?->name])</td>
                                <td>{{ $ticket->room?->building?->name }} · {{ $ticket->room?->name }}</td>
                                <td><span class="role-badge ticket-status ticket-status-{{ str_replace('_', '-', $displayStatus) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
                                <td>{{ $ticket->updated_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">{{ $selectedCategoryId !== '' || $ticketSearch !== '' ? 'Không tìm thấy phiếu phù hợp. Hãy thử từ khóa hoặc danh mục khác.' : 'Bạn chưa gửi phiếu sự cố nào.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('shared.ticket-pagination', ['paginator' => $tickets])
            <p class="card-lead">Danh sách này chỉ hiển thị các phiếu do bạn gửi.</p>
        </section>
        <footer class="role-footer">Helpdesk trường học · Cổng hỗ trợ sinh viên</footer>
    </main>
</div>
<script src="assets/js/customer.js?v=20261106-fast-actions1"></script>
</body>
</html>
