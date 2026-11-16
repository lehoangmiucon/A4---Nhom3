@extends('admin.layouts.app')

@section('title')
Danh sách người dùng · Helpdesk trường học
@endsection

@section('content')
<section class="page-title-row">
    <div>
    <p class="eyebrow">TÀI KHOẢN SINH VIÊN VÀ GIÁO VIÊN</p>
        <h1>Danh sách người dùng</h1>
        <p>Quản lý tài khoản người báo cáo. Giáo viên dùng cổng sinh viên và phiếu được đánh dấu ưu tiên cao.</p>
    </div>
    <span class="helper-text">Tại trang chi tiết, admin có thể chuyển tài khoản sinh viên thành giáo viên.</span>
</section>
<section class="summary-strip">
    <div class="summary-tile"><span>Tổng người dùng</span><strong>{{ $users->count() }}</strong></div>
    <div class="summary-tile"><span>Giáo viên</span><strong>{{ $users->where('role', 'teacher')->count() }}</strong></div>
    <div class="summary-tile"><span>Đã khóa</span><strong>{{ $users->where('is_deleted', true)->count() }}</strong></div>
    <div class="summary-tile"><span>Phiếu đã gửi tháng này</span><strong>{{ $monthlyTicketCount }}</strong></div>
</section>
<section class="filter-bar">
    <input id="users-search" class="search-field" type="search" placeholder="Tìm theo tên hoặc email...">
    <select id="users-role-filter" class="select-field">
        <option value="">Tất cả vai trò</option>
        <option value="student">Sinh viên</option>
        <option value="teacher">Giáo viên</option>
    </select>
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
                    <th>VAI TRÒ</th>
                    <th>PHIẾU ĐÃ GỬI</th>
                    <th>TRẠNG THÁI</th>
                    <th>THAO TÁC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $listedUser)
                <tr data-user-row data-user-status="{{ $listedUser->is_deleted ? 'locked' : 'active' }}" data-user-role="{{ $listedUser->role }}" data-user-search="{{ mb_strtolower($listedUser->name.' '.$listedUser->email.' '.$listedUser->phone.' '.$listedUser->role) }}">
                    <td><span class="person-cell"><span class="mini-avatar av-blue">{{ mb_strtoupper(mb_substr($listedUser->name, 0, 1)) }}</span><strong>{{ $listedUser->name }}</strong></span></td>
                    <td>{{ $listedUser->email }}</td>
                    <td><span class="status-badge {{ $listedUser->role === 'teacher' ? 'status-wait' : 'status-done' }}">{{ $listedUser->role === 'teacher' ? 'Giáo viên' : 'Sinh viên' }}</span></td>
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
                <tr><td colspan="6">Chưa có tài khoản người dùng.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination"><span>Hiển thị {{ $users->count() }} người dùng</span></div>
</section>

@endsection

@section('scripts')
<script src="{{ url('/admin/assets/js/users.js?v=20261109-user-roles2') }}"></script>
@endsection
