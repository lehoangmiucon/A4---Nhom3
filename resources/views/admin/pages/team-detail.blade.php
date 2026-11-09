@extends('admin.layouts.app')

@section('title', 'Chi tiết tài khoản · Helpdesk')

@push('styles')
<style>
    .team-detail-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 20px
    }

    .team-detail-heading h1 {
        margin: 0 0 6px;
        color: #183958;
        font-size: 28px
    }

    .team-detail-heading p {
        margin: 0;
        color: #778aa0;
        font-size: 14px
    }

    .team-detail-grid {
        display: grid;
        grid-template-columns: minmax(280px, .75fr) minmax(0, 1.55fr);
        gap: 18px;
        align-items: start
    }

    .team-detail-card {
        padding: 22px
    }

    .team-profile {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-bottom: 18px;
        border-bottom: 1px solid #eaf0f5
    }

    .team-profile-avatar {
        width: 54px;
        height: 54px;
        flex: none;
        display: grid;
        place-items: center;
        border-radius: 16px;
        background: #e8f4fd;
        color: #1686df;
        font-size: 20px;
        font-weight: 700
    }

    .team-profile h2 {
        margin: 0 0 5px;
        color: #23435f;
        font-size: 18px
    }

    .team-profile p {
        margin: 0;
        color: #7c8da1;
        font-size: 13px;
        overflow-wrap: anywhere
    }

    .team-facts {
        display: grid;
        gap: 0;
        margin: 10px 0 0
    }

    .team-facts>div {
        padding: 12px 0;
        border-bottom: 1px solid #eef2f6
    }

    .team-facts dt {
        margin-bottom: 5px;
        color: #8292a5;
        font-size: 12px
    }

    .team-facts dd {
        margin: 0;
        color: #304b67;
        font-size: 14px;
        font-weight: 600;
        overflow-wrap: anywhere
    }

    .team-category-list {
        display: flex;
        flex-wrap: wrap;
        gap: 7px
    }

    .team-category-list span {
        padding: 5px 9px;
        border-radius: 20px;
        background: color-mix(in srgb, var(--team-category-color) 12%, #fff);
        color: var(--team-category-color);
        font-size: 12px;
        font-weight: 600
    }

    .team-detail-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px
    }

    .team-detail-stat {
        padding: 12px;
        border: 1px solid #e5edf4;
        border-radius: 10px;
        background: #f8fbfe
    }

    .team-detail-stat span,
    .team-detail-stat strong {
        display: block
    }

    .team-detail-stat span {
        color: #778aa0;
        font-size: 11px
    }

    .team-detail-stat strong {
        margin-top: 5px;
        color: #1f3d5b;
        font-size: 20px
    }

    .team-history-section+.team-history-section {
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid #eaf0f5
    }

    .team-history-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px
    }

    .team-history-heading h2 {
        margin: 0;
        color: #23435f;
        font-size: 17px
    }

    .team-history-list {
        display: grid;
        gap: 9px
    }

    .team-history-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 13px;
        border: 1px solid #e7edf3;
        border-radius: 10px;
        background: #fff
    }

    .team-history-item:hover {
        border-color: #b8d9f0;
        background: #fbfdff
    }

    .team-history-item-main {
        min-width: 0
    }

    .team-history-item-main strong,
    .team-history-item-main small {
        display: block
    }

    .team-history-item-main strong {
        color: #2a4663;
        font-size: 14px
    }

    .team-history-item-main small {
        margin-top: 5px;
        color: #7c8da1;
        font-size: 12px;
        line-height: 1.5;
        overflow-wrap: anywhere
    }

    .team-history-item time {
        flex: none;
        color: #8292a5;
        font-size: 11px
    }

    .team-detail-empty {
        padding: 25px 15px;
        border: 1px dashed #dce6ef;
        border-radius: 10px;
        background: #f9fbfd;
        color: #76899f;
        text-align: center;
        font-size: 13px
    }

    @media(max-width:850px) {
        .team-detail-grid {
            grid-template-columns: 1fr
        }
    }

    @media(max-width:560px) {
        .team-detail-heading {
            flex-direction: column
        }

        .team-detail-heading h1 {
            font-size: 23px
        }

        .team-detail-card {
            padding: 16px
        }

        .team-history-item {
            flex-direction: column
        }

        .team-history-item time {
            order: -1
        }
    }
</style>
@endpush

@section('content')
@php($roleLabel = $teamMember->role === 'admin' ? 'Quản lý' : 'Nhân viên kỹ thuật')
<section class="team-detail-heading">
    <div>
        <p class="eyebrow">HỒ SƠ ĐỘI NGŨ</p>
        <h1>Chi tiết {{ mb_strtolower($roleLabel) }}</h1>
        <p>Thông tin tài khoản, chuyên môn và công việc được giao.</p>
    </div>
    <a class="button button-secondary" href="{{ url('/admin/pages/team#'.($teamMember->role === 'staff' ? 'technicians' : 'management')) }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Danh sách đội ngũ</a>
</section>

<div class="team-detail-grid">
    <section class="panel team-detail-card" aria-labelledby="team-member-name">
        <div class="team-profile">
            <span class="team-profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($teamMember->name, 0, 1)) }}</span>
            <div>
                <h2 id="team-member-name">{{ $teamMember->name }}</h2>
                <p>{{ $teamMember->email }}</p>
            </div>
        </div>
        <dl class="team-facts">
            <div>
                <dt>Vai trò</dt>
                <dd>{{ $roleLabel }}</dd>
            </div>
            <div>
                <dt>Email đăng nhập</dt>
                <dd>{{ $teamMember->email }}</dd>
            </div>
            <div>
                <dt>Số điện thoại</dt>
                <dd>{{ $teamMember->phone ?: 'Chưa cập nhật' }}</dd>
            </div>
            <div>
                <dt>Xác thực email</dt>
                <dd>{{ $teamMember->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</dd>
            </div>
            <div>
                <dt>Trạng thái tài khoản</dt>
                <dd><span class="status-badge {{ $teamMember->is_deleted ? 'status-locked' : 'status-done' }}">{{ $teamMember->is_deleted ? 'Đã khóa' : 'Đang hoạt động' }}</span></dd>
            </div>
            <div>
                <dt>Ngày tạo tài khoản</dt>
                <dd>{{ $teamMember->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </div>
            @if($teamMember->role === 'staff')
            <div>
                <dt>Danh mục có thể xử lý</dt>
                <dd><span class="team-category-list">@forelse($teamMember->categories as $category)<span style="--team-category-color:{{ $category->color ?? '#64748b' }}">{{ $category->name }}</span>@empty<span>Chưa gán danh mục</span>@endforelse</span></dd>
            </div>
            @endif
        </dl>
        <div class="team-detail-stats">
            <div class="team-detail-stat"><span>Phiếu được giao</span><strong>{{ $teamMember->assigned_tickets_count }}</strong></div>
            @if($teamMember->role === 'staff')
            <div class="team-detail-stat"><span>Lịch phân công</span><strong>{{ $teamScheduleCount }}</strong></div>
            @else
            <div class="team-detail-stat"><span>Email xác thực</span><strong>{{ $teamMember->email_verified_at ? 'Có' : 'Chưa' }}</strong></div>
            @endif
        </div>
    </section>

    <section class="panel team-detail-card">
        @if($teamMember->role === 'staff')
        <div class="team-history-section">
            <div class="team-history-heading">
                <h2>Lịch phân công gần đây</h2><span class="period-pill">{{ $teamSchedules->count() }} lịch</span>
            </div>
            @if($teamSchedules->isNotEmpty())
            <div class="team-history-list">
                @foreach($teamSchedules as $schedule)
                <a class="team-history-item" href="{{ url('/admin/pages/ticket-detail?ticket='.$schedule->ticket_id) }}">
                    <span class="team-history-item-main"><strong>{{ $schedule->ticket?->ticket_number }} · {{ $schedule->ticket?->title }}</strong><small>{{ $schedule->ticket?->category?->name ?? 'Chưa phân loại' }} · {{ $schedule->ticket?->room?->building?->name ?? $schedule->ticket?->building_label }} · {{ $schedule->ticket?->room?->name ?? $schedule->ticket?->room_label }}</small></span>
                    <time datetime="{{ $schedule->starts_at?->toIso8601String() }}">{{ $schedule->starts_at?->format('d/m/Y H:i') }}</time>
                </a>
                @endforeach
            </div>
            @else
            <div class="team-detail-empty">Nhân viên này chưa có lịch phân công.</div>
            @endif
        </div>
        @endif

        <div class="team-history-section">
            <div class="team-history-heading">
                <h2>Phiếu được giao gần đây</h2><span class="period-pill">{{ $teamMember->assigned_tickets_count }} phiếu</span>
            </div>
            @if($teamMember->assignedTickets->isNotEmpty())
            <div class="team-history-list">
                @foreach($teamMember->assignedTickets as $ticket)
                <a class="team-history-item" href="{{ url('/admin/pages/ticket-detail?ticket='.$ticket->id) }}">
                    <span class="team-history-item-main"><strong>{{ $ticket->ticket_number }} · {{ $ticket->title }}</strong><small>{{ $ticket->category?->name ?? 'Chưa phân loại' }} · {{ $ticket->room?->building?->name ?? $ticket->building_label }} · {{ $ticket->room?->name ?? $ticket->room_label }}</small></span>
                    <span class="status-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
                </a>
                @endforeach
            </div>
            @else
            <div class="team-detail-empty">Tài khoản này chưa được giao phiếu nào.</div>
            @endif
        </div>
    </section>
</div>
@include('shared.delete-user', ['deleteUser' => $teamMember])
@endsection
