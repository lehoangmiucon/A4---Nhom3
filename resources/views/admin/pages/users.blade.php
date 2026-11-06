@extends('admin.layouts.app')

@section('title')
Người báo sự cố · Helpdesk trường học
@endsection

@section('content')
<section class="page-title-row">
    <div>
        <p class="eyebrow">TÀI KHOẢN SINH VIÊN</p>
        <h1>Người báo sự cố</h1>
        <p>Danh sách sinh viên đã đăng ký và gửi phiếu hỗ trợ.</p>
    </div>
    <span class="helper-text">Sinh viên tự đăng ký; admin tạo tài khoản nhân viên và quản lý.</span>
</section>
<section class="summary-strip">
    <div class="summary-tile"><span>Tổng sinh viên</span><strong>{{ $users->count() }}</strong></div>
    <div class="summary-tile"><span>Đang hoạt động</span><strong>{{ $users->where('is_deleted', false)->count() }}</strong></div>
    <div class="summary-tile"><span>Đã khóa</span><strong>{{ $users->where('is_deleted', true)->count() }}</strong></div>
    <div class="summary-tile"><span>Phiếu sinh viên gửi tháng này</span><strong>{{ $monthlyTicketCount }}</strong></div>
</section>
<section class="filter-bar">
    <input id="users-search" class="search-field" type="search" placeholder="Tìm theo tên hoặc email...">
    <select id="users-status-filter" class="select-field">
        <option value="">Tất cả trạng thái</option>
        <option value="active">Đang hoạt động</option>
        <option value="locked">Đã khóa</option>
    </select>
</section>
<p id="users-feedback" class="api-feedback" role="status" aria-live="polite" hidden></p>
<section class="panel table-panel users-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>NGƯỜI DÙNG</th>
                    <th>EMAIL</th>
                    <th>PHIẾU ĐÃ GỬI</th>
                    <th>TRẠNG THÁI</th>
                    <th>THAO TÁC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $listedUser)
                <tr data-user-row data-user-status="{{ $listedUser->is_deleted ? 'locked' : 'active' }}" data-user-search="{{ mb_strtolower($listedUser->name.' '.$listedUser->email) }}">
                    <td><span class="person-cell"><span class="mini-avatar av-blue">{{ mb_strtoupper(mb_substr($listedUser->name, 0, 1)) }}</span><strong>{{ $listedUser->name }}</strong></span></td>
                    <td>{{ $listedUser->email }}</td>
                    <td>{{ $listedUser->reported_tickets_count }}</td>
                    <td><span class="status-badge {{ $listedUser->is_deleted ? 'status-locked' : 'status-done' }}">{{ $listedUser->is_deleted ? 'Đã khóa' : 'Đang hoạt động' }}</span></td>
                    <td>
                        <a class="button button-secondary student-detail-button" href="{{ url('/admin/pages/user-detail?user='.$listedUser->id) }}">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i> Xem chi tiết
                        </a>
                        @if($listedUser->role === 'super_admin')
                            <span class="helper-text">Được bảo vệ</span>
                        @elseif($currentUser->is($listedUser))
                            <span class="helper-text">Tài khoản hiện tại</span>
                        @else
                            <button class="button {{ $listedUser->is_deleted ? 'button-secondary' : 'button-danger' }} account-toggle-button" type="button" data-account-toggle data-user-id="{{ $listedUser->id }}" data-user-name="{{ $listedUser->name }}" data-locked="{{ (int) $listedUser->is_deleted }}">
                                <i class="fa-solid {{ $listedUser->is_deleted ? 'fa-lock-open' : 'fa-lock' }}" aria-hidden="true"></i>
                                {{ $listedUser->is_deleted ? 'Mở khóa' : 'Khóa tài khoản' }}
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5">Chưa có sinh viên.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination"><span>Hiển thị {{ $users->count() }} sinh viên</span></div>
</section>

@endsection

@section('scripts')
<script src="{{ url('/admin/assets/js/users.js?v=20261106-fast-actions1') }}"></script>
@endsection
