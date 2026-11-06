@extends('admin.layouts.app')

@section('title', 'Đội ngũ · Helpdesk trường học')

@section('content')
<section class="page-title-row">
    <div>
        <p class="eyebrow">NHÂN SỰ HỖ TRỢ</p>
        <h1>Đội ngũ &amp; tài khoản</h1>
        <p>Quản lý tài khoản quản lý và nhân viên kỹ thuật. Sinh viên tự đăng ký.</p>
    </div>
    <div class="title-actions"><a class="button button-secondary" href="schedule">Lịch phân công</a><button class="button button-primary" type="button" data-open-team-create><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Tạo tài khoản</button></div>
</section>
<p class="api-feedback" id="team-feedback" role="status" aria-live="polite" hidden></p>

<section class="summary-strip">
    <div class="summary-tile"><span>Tổng tài khoản quản lý và nhân viên</span><strong>{{ $teamUsers->count() }}</strong></div>
    <div class="summary-tile"><span>Quản lý</span><strong>{{ $managementUsers->count() }}</strong></div>
    <div class="summary-tile"><span>Nhân viên kỹ thuật</span><strong>{{ $staffUsers->count() }}</strong></div>
    <div class="summary-tile"><span>Đã xác thực email</span><strong>{{ $teamUsers->filter(fn ($member) => $member->email_verified_at)->count() }}</strong></div>
</section>

<section class="team-filter-bar" aria-label="Tìm kiếm và lọc tài khoản đội ngũ">
    <label class="team-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" id="team-search" placeholder="Tìm theo tên, email hoặc số điện thoại" autocomplete="off"></label>
    <label><span>Vai trò</span><select id="team-role-filter">
            <option value="">Tất cả vai trò</option>
            <option value="admin">Quản lý</option>
            <option value="staff">Nhân viên kỹ thuật</option>
        </select></label>
    <label><span>Trạng thái</span><select id="team-status-filter">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="unverified">Chưa xác thực</option>
            <option value="locked">Đã khóa</option>
        </select></label>
    <label><span>Chuyên môn</span><select id="team-category-filter">
            <option value="">Tất cả chuyên môn</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
        </select></label>
    <button class="button button-secondary" type="button" id="team-filter-reset">Xóa lọc</button>
</section>
<style>
    .team-filter-bar {
        display: grid;
        grid-template-columns: minmax(250px, 1.8fr) repeat(3, minmax(145px, 1fr)) auto;
        align-items: end;
        gap: 12px;
        margin: 16px 0;
        padding: 16px;
        border: 1px solid #dce7f2;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(31, 65, 99, .04)
    }

    .team-filter-bar>label {
        display: grid;
        gap: 6px;
        color: #698099;
        font-size: 12px;
        font-weight: 600
    }

    .team-filter-bar input,
    .team-filter-bar select {
        width: 100%;
        height: 42px;
        box-sizing: border-box;
        border: 1px solid #d8e4ef;
        border-radius: 9px;
        background: #fff;
        color: #244565;
        font: inherit;
        padding: 0 12px
    }

    .team-filter-bar .team-search {
        position: relative;
        display: block
    }

    .team-search i {
        position: absolute;
        left: 13px;
        top: 14px;
        color: #7b90a5
    }

    .team-filter-bar .team-search input {
        padding-left: 38px
    }

    .team-filter-bar button {
        height: 42px;
        white-space: nowrap
    }

    .team-filter-empty {
        padding: 20px !important;
        color: #7b8da0;
        text-align: center
    }

    .team-table-wrap[hidden],
    [data-team-row][hidden],
    [data-team-filter-empty][hidden] {
        display: none !important
    }

    @media(max-width:1050px) {
        .team-filter-bar {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .team-filter-bar .team-search {
            grid-column: 1/-1
        }
    }

    @media(max-width:560px) {
        .team-filter-bar {
            grid-template-columns: 1fr
        }

        .team-filter-bar .team-search {
            grid-column: auto
        }
    }
</style>

<nav class="staff-tabs" aria-label="Nhóm tài khoản"><a href="#management">Quản lý</a><a href="#technicians">Nhân viên</a></nav>

<section class="panel table-panel team-table-wrap team-management" id="management">
    <div class="panel-heading" style="padding:18px 0 4px">
        <div>
            <p class="eyebrow">NHÓM QUẢN LÝ</p>
            <h2>Quản lý</h2>
        </div><span class="device-tag" data-team-count="admin">{{ $managementUsers->count() }} tài khoản</span>
    </div>
    <div class="table-wrap">
        <table class="team-table">
            <thead>
                <tr>
                    <th>TÀI KHOẢN</th>
                    <th>VAI TRÒ</th>
                    <th>PHIẾU ĐƯỢC GIAO</th>
                    <th>TRẠNG THÁI</th>
                    <th>THAO TÁC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($managementUsers as $teamUser)
                <tr data-team-row data-team-role="admin" data-team-status="{{ $teamUser->is_deleted ? 'locked' : ($teamUser->email_verified_at ? 'active' : 'unverified') }}" data-team-categories="" data-team-phone="{{ $teamUser->phone }}" data-team-search="{{ $teamUser->name }} {{ $teamUser->email }} {{ $teamUser->phone }}">
                    <td><b>{{ $teamUser->name }}</b><br><small>{{ $teamUser->email }}</small><br><small>{{ $teamUser->phone ?: 'Chưa có số điện thoại' }}</small></td>
                    <td>Quản lý</td>
                    <td>{{ $teamUser->assigned_tickets_count }}</td>
                    <td><span class="status-badge {{ $teamUser->is_deleted ? 'status-locked' : 'status-done' }}">{{ $teamUser->is_deleted ? 'Đã khóa' : ($teamUser->email_verified_at ? 'Đang hoạt động' : 'Chưa xác thực') }}</span></td>
                    <td>
                        <div class="staff-row-actions">
                            <a class="button button-secondary team-detail-button" href="{{ url('/admin/pages/team-detail?user='.$teamUser->id) }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Chi tiết</a>
                            @if($teamUser->is($currentUser))
                            <button class="button button-secondary management-lock-button" type="button" disabled title="Không thể khóa tài khoản đang đăng nhập"><i class="fa-solid fa-lock" aria-hidden="true"></i> Tài khoản của bạn</button>
                            @else
                            <button class="button {{ $teamUser->is_deleted ? 'button-secondary' : 'button-danger' }} management-lock-button" type="button" data-toggle-account data-user-id="{{ $teamUser->id }}" data-user-name="{{ $teamUser->name }}" data-locked="{{ (int) $teamUser->is_deleted }}"><i class="fa-solid {{ $teamUser->is_deleted ? 'fa-lock-open' : 'fa-lock' }}" aria-hidden="true"></i> {{ $teamUser->is_deleted ? 'Mở khóa' : 'Khóa tài khoản' }}</button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty<tr>
                    <td colspan="5">Chưa có tài khoản quản lý.</td>
                </tr>@endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel table-panel team-table-wrap team-technicians" id="technicians" style="margin-top:16px">
    <div class="panel-heading" style="padding:18px 0 4px">
        <div>
            <p class="eyebrow">NHÓM KỸ THUẬT</p>
            <h2>Nhân viên kỹ thuật</h2>
        </div><span class="device-tag" data-team-count="staff">{{ $staffUsers->count() }} tài khoản</span>
    </div>
    <div class="table-wrap">
        <table class="team-table staff-team-table">
            <thead>
                <tr>
                    <th>TÀI KHOẢN</th>
                    <th>VAI TRÒ</th>
                    <th>DANH MỤC CÓ THỂ XỬ LÝ</th>
                    <th>PHIẾU ĐƯỢC GIAO</th>
                    <th>TRẠNG THÁI</th>
                    <th>THAO TÁC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($staffUsers as $teamUser)
                <tr data-team-row data-team-role="staff" data-team-status="{{ $teamUser->is_deleted ? 'locked' : ($teamUser->email_verified_at ? 'active' : 'unverified') }}" data-team-categories="{{ $teamUser->categories->pluck('id')->implode(',') }}" data-team-phone="{{ $teamUser->phone }}" data-team-search="{{ $teamUser->name }} {{ $teamUser->email }} {{ $teamUser->phone }}">
                    <td><b>{{ $teamUser->name }}</b><br><small>{{ $teamUser->email }}</small><br><small>{{ $teamUser->phone ?: 'Chưa có số điện thoại' }}</small></td>
                    <td>Nhân viên</td>
                    <td>
                        <div class="staff-category-tags">@forelse($teamUser->categories as $category)<span style="--staff-category-color:{{ $category->color ?? '#64748B' }}">{{ $category->name }}</span>@empty<span class="staff-category-empty">Chưa gán danh mục</span>@endforelse</div>
                    </td>
                    <td>{{ $teamUser->assigned_tickets_count }}</td>
                    <td><span class="status-badge {{ $teamUser->is_deleted ? 'status-locked' : 'status-done' }}">{{ $teamUser->is_deleted ? 'Đã khóa' : ($teamUser->email_verified_at ? 'Đang hoạt động' : 'Chưa xác thực') }}</span></td>
                    <td>
                        <div class="staff-row-actions">
                            <a class="button button-secondary team-detail-button" href="{{ url('/admin/pages/team-detail?user='.$teamUser->id) }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Chi tiết</a>
                            <button class="button button-secondary staff-edit-button" type="button" data-edit-staff data-user-id="{{ $teamUser->id }}" data-user-name="{{ $teamUser->name }}" data-user-email="{{ $teamUser->email }}" data-user-phone="{{ $teamUser->phone }}" data-user-categories='@json($teamUser->categories->pluck("id"))'><i class="fa-solid fa-pen" aria-hidden="true"></i> Sửa</button>
                            <button class="button {{ $teamUser->is_deleted ? 'button-secondary' : 'button-danger' }} staff-lock-button" type="button" data-toggle-account data-user-id="{{ $teamUser->id }}" data-user-name="{{ $teamUser->name }}" data-locked="{{ (int) $teamUser->is_deleted }}"><i class="fa-solid {{ $teamUser->is_deleted ? 'fa-lock-open' : 'fa-lock' }}" aria-hidden="true"></i> {{ $teamUser->is_deleted ? 'Mở khóa' : 'Khóa' }}</button>
                        </div>
                    </td>
                </tr>
                @empty<tr>
                    <td colspan="6">Chưa có nhân viên kỹ thuật.</td>
                </tr>@endforelse
            </tbody>
        </table>
    </div>
</section>

<dialog class="calendar-modal team-account-modal" id="team-account-modal" aria-labelledby="team-modal-title">
    <header class="modal-head"><span class="modal-calendar-icon"><i class="fa-solid fa-user-gear" aria-hidden="true"></i></span><button class="modal-close" type="button" data-close-team-modal aria-label="Đóng">×</button></header>
    <h2 id="team-modal-title">Tạo tài khoản</h2>
    <p id="team-modal-description">Admin tạo tài khoản quản lý và nhân viên kỹ thuật.</p>
    <form id="team-account-form" class="role-form">
        <label class="form-item" data-create-only><span>Vai trò</span><select name="role" id="team-account-role" required>
                <option value="staff">Nhân viên</option>
                <option value="admin">Quản lý</option>
            </select></label>
        <label class="form-item"><span>Họ và tên</span><input name="name" maxlength="120" required></label>
        <label class="form-item"><span id="team-email-label">Email</span><input type="email" name="email" maxlength="255" required></label>
        <label class="form-item"><span>Số điện thoại</span><input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="Ví dụ: 0901 234 567"></label>
        <label class="form-item"><span id="team-password-label">Mật khẩu khởi tạo</span><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
        <fieldset class="staff-category-picker" data-staff-category-picker>
            <legend>Danh mục sự cố có thể xử lý <b>*</b></legend>
            <p>Chọn một hoặc nhiều nhóm phù hợp với chuyên môn nhân viên.</p>
            <div class="staff-category-options">
                @forelse($categories as $category)
                <label><input type="checkbox" name="category_ids" value="{{ $category->id }}"><span style="--staff-category-color:{{ $category->color ?? '#64748B' }}">{{ $category->name }}</span></label>
                @empty<p>Chưa có danh mục lỗi. Hãy tạo danh mục trước.</p>@endforelse
            </div>
        </fieldset>
        <p class="api-feedback" data-team-form-feedback role="status" aria-live="polite" hidden></p>
        <footer class="calendar-modal-footer"><button class="button button-secondary" type="button" data-close-team-modal>Hủy</button><button class="button button-primary" type="submit">Lưu tài khoản</button></footer>
    </form>
</dialog>
@endsection

@section('scripts')
<script src="../assets/js/team.js?v=20261106-fast-actions1"></script>
@endsection
