@extends('admin.layouts.app')

@section('title', 'Chi tiết sinh viên · Helpdesk')

@push('styles')
<style>
    .student-detail-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 20px
    }

    .student-detail-heading h1 {
        margin: 0 0 6px;
        color: #183958;
        font-size: 28px
    }

    .student-detail-heading p {
        margin: 0;
        color: #778aa0;
        font-size: 14px
    }

    .student-detail-layout {
        display: grid;
        grid-template-columns: minmax(280px, .72fr) minmax(0, 1.6fr);
        gap: 18px;
        align-items: start
    }

    .student-profile-card,
    .student-tickets-card {
        padding: 22px
    }

    .student-profile-top {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-bottom: 19px;
        border-bottom: 1px solid #eaf0f5
    }

    .student-profile-avatar {
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

    .student-profile-top h2 {
        margin: 0 0 5px;
        color: #23435f;
        font-size: 18px
    }

    .student-profile-top p {
        margin: 0;
        color: #7c8da1;
        font-size: 13px;
        overflow-wrap: anywhere
    }

    .student-account-facts {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
        margin: 10px 0 0
    }

    .student-account-facts>div {
        padding: 12px 0;
        border-bottom: 1px solid #eef2f6
    }

    .student-account-facts dt {
        margin-bottom: 5px;
        color: #8292a5;
        font-size: 12px
    }

    .student-account-facts dd {
        margin: 0;
        color: #304b67;
        font-size: 14px;
        font-weight: 600;
        overflow-wrap: anywhere
    }

    .student-detail-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 17px
    }

    .student-detail-stat {
        padding: 12px;
        border: 1px solid #e5edf4;
        border-radius: 10px;
        background: #f8fbfe
    }

    .student-detail-stat span,
    .student-detail-stat strong {
        display: block
    }

    .student-detail-stat span {
        color: #778aa0;
        font-size: 11px
    }

    .student-detail-stat strong {
        margin-top: 5px;
        color: #1f3d5b;
        font-size: 20px
    }

    .student-tickets-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px
    }

    .student-tickets-heading h2 {
        margin: 0;
        color: #23435f;
        font-size: 17px
    }

    .student-ticket-list {
        display: grid;
        gap: 10px
    }

    .student-ticket-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px 14px;
        padding: 14px;
        border: 1px solid #e7edf3;
        border-radius: 10px;
        background: #fff;
        transition: border-color .15s, background .15s
    }

    .student-ticket-item:hover {
        border-color: #b8d9f0;
        background: #fbfdff
    }

    .student-ticket-main {
        min-width: 0
    }

    .student-ticket-code {
        font-size: 12px;
        font-weight: 700
    }

    .student-ticket-main h3 {
        margin: 5px 0;
        color: #2a4663;
        font-size: 14px;
        overflow-wrap: anywhere
    }

    .student-ticket-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 12px;
        color: #7c8da1;
        font-size: 12px
    }

    .student-category {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--category-color, #64748b)
    }

    .student-category i {
        font-size: 7px
    }

    .student-ticket-status {
        align-self: start;
        white-space: nowrap
    }

    .student-ticket-date {
        grid-column: 1/-1;
        padding-top: 8px;
        border-top: 1px solid #eef2f6;
        color: #8292a5;
        font-size: 11px
    }

    .student-empty {
        padding: 32px 16px;
        border: 1px dashed #dce6ef;
        border-radius: 10px;
        background: #f9fbfd;
        color: #76899f;
        text-align: center;
        font-size: 13px
    }

    @media(max-width:850px) {
        .student-detail-layout {
            grid-template-columns: 1fr
        }

        .student-account-facts {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 18px
        }
    }

    @media(max-width:560px) {
        .student-detail-heading {
            flex-direction: column
        }

        .student-detail-heading h1 {
            font-size: 23px
        }

        .student-profile-card,
        .student-tickets-card {
            padding: 16px
        }

        .student-account-facts {
            grid-template-columns: 1fr
        }

        .student-ticket-item {
            grid-template-columns: 1fr
        }

        .student-ticket-status {
            grid-row: 1
        }

        .student-detail-stats {
            gap: 7px
        }

        .student-detail-stat {
            padding: 9px
        }

        .student-detail-stat strong {
            font-size: 18px
        }
    }
</style>
@endpush

@section('content')
<section class="student-detail-heading">
    <div>
        <p class="eyebrow">HỒ SƠ NGƯỜI BÁO SỰ CỐ</p>
        <h1>Chi tiết sinh viên</h1>
        <p>Thông tin tài khoản và lịch sử phiếu hỗ trợ đã gửi.</p>
    </div>
    <a class="button button-secondary" href="{{ url('/admin/pages/users') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Danh sách sinh viên</a>
</section>

<div class="student-detail-layout">
    <section class="panel student-profile-card" aria-labelledby="student-profile-title">
        <div class="student-profile-top">
            <span class="student-profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
            <div>
                <h2 id="student-profile-title">{{ $student->name }}</h2>
                <p>{{ $student->email }}</p>
            </div>
        </div>
        <dl class="student-account-facts">
            <div>
                <dt>Địa chỉ email</dt>
                <dd>{{ $student->email }}</dd>
            </div>
            <div>
                <dt>Xác thực email</dt>
                <dd>{{ $student->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</dd>
            </div>
            <div>
                <dt>Trạng thái tài khoản</dt>
                <dd><span class="status-badge {{ $student->is_deleted ? 'status-locked' : 'status-done' }}">{{ $student->is_deleted ? 'Đã khóa' : 'Đang hoạt động' }}</span></dd>
            </div>
            <div>
                <dt>Ngày đăng ký</dt>
                <dd>{{ $student->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </div>
        </dl>
        <div class="student-detail-stats" aria-label="Thống kê phiếu">
            <div class="student-detail-stat"><span>Tổng phiếu</span><strong>{{ $student->reported_tickets_count }}</strong></div>
            <div class="student-detail-stat"><span>Đang xử lý</span><strong>{{ $student->reportedTickets->whereIn('status', ['assigned', 'in_progress'])->count() }}</strong></div>
            <div class="student-detail-stat"><span>Đã hoàn tất</span><strong>{{ $student->reportedTickets->whereIn('status', ['resolved', 'closed'])->count() }}</strong></div>
        </div>
    </section>

    <section class="panel student-tickets-card" aria-labelledby="student-tickets-title">
        <div class="student-tickets-heading">
            <h2 id="student-tickets-title">Phiếu sự cố đã gửi</h2>
            <span class="period-pill">{{ $student->reported_tickets_count }} phiếu</span>
        </div>
        @if($student->reportedTickets->isNotEmpty())
        <div class="student-ticket-list">
            @foreach($student->reportedTickets as $ticket)
            <article class="student-ticket-item">
                <div class="student-ticket-main">
                    <a class="ticket-code student-ticket-code" href="{{ url('/admin/pages/ticket-detail?ticket='.$ticket->id) }}">{{ $ticket->ticket_number }}</a>
                    <h3>{{ $ticket->title }}</h3>
                    <div class="student-ticket-meta">
                        <span class="student-category" style="--category-color:{{ $ticket->category?->color ?? '#64748b' }}"><i class="fa-solid fa-circle" aria-hidden="true"></i>{{ $ticket->category?->name ?? 'Chưa phân loại' }}</span>
                        <span>{{ $ticket->room?->building?->name ?? $ticket->building_label }} · {{ $ticket->room?->name ?? $ticket->room_label }}</span>
                    </div>
                </div>
                <span class="status-badge student-ticket-status ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
                <time class="student-ticket-date" datetime="{{ $ticket->created_at?->toIso8601String() }}">Gửi lúc {{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}@if($ticket->assignee) · Nhân viên: {{ $ticket->assignee->name }}@endif</time>
            </article>
            @endforeach
        </div>
        @else
        <div class="student-empty">Sinh viên này chưa gửi phiếu sự cố nào.</div>
        @endif
    </section>
</div>
@endsection
