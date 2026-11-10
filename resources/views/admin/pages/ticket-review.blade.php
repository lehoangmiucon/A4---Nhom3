@extends('admin.layouts.app')

@section('title', 'Duyệt kết quả sửa chữa · Helpdesk')

@section('content')
<section class="page-title-row">
    <div>
        <p class="eyebrow">KIỂM TRA KẾT QUẢ KỸ THUẬT</p>
        <h1>Duyệt hoàn thành phiếu</h1>
        <p><a class="ticket-code" href="ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->ticket_number }}</a> · {{ $ticket->title }}</p>
    </div>
    <a class="button button-secondary" href="ticket-detail?ticket={{ $ticket->id }}">← Chi tiết phiếu</a>
</section>

<p class="api-feedback" id="review-load-error" role="status" aria-live="polite" hidden></p>

<section class="summary-strip">
    <div class="summary-tile"><span>Trạng thái</span><strong><span class="status-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></strong></div>
    <div class="summary-tile"><span>Nhân viên phụ trách</span><strong>{{ $ticket->assignee?->name ?? 'Chưa phân công' }}</strong></div>
    <div class="summary-tile"><span>Phòng</span><strong>{{ $ticket->room?->name ?? $ticket->room_label }}</strong></div>
    <div class="summary-tile"><span>Gửi kết quả lúc</span><strong>{{ $ticket->resolved_at?->format('d/m/Y H:i') ?? 'Chưa gửi' }}</strong></div>
</section>

<section class="two-column ticket-review-columns">
    <article class="panel detail-panel">
        <p class="eyebrow">BÁO CÁO BAN ĐẦU</p>
        <h2 class="section-heading">Ảnh sinh viên gửi</h2>
        <p class="detail-description">Ảnh gốc được gửi cùng phiếu để đối chiếu tình trạng trước khi sửa.</p>
        <div class="ticket-attachments">
            @forelse($ticket->reportAttachments as $attachment)
            <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}"><img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh sinh viên gửi: {{ $attachment->original_name }}" loading="lazy"></a>
            @empty
            <p>Phiếu này không có ảnh ban đầu.</p>
            @endforelse
        </div>
    </article>

    <article class="panel detail-panel">
        <p class="eyebrow">KẾT QUẢ CỦA NHÂN VIÊN</p>
        <h2 class="section-heading">Ảnh minh chứng sau sửa chữa</h2>
        <p class="detail-description">{{ $ticket->resolution_note ?: 'Nhân viên chưa gửi ghi chú kết quả.' }}</p>
        <div class="ticket-attachments">
            @forelse($ticket->repairAttachments as $attachment)
            <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}"><img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh nhân viên sửa chữa: {{ $attachment->original_name }}" loading="lazy"></a>
            @empty
            <p>Chưa có ảnh minh chứng sửa chữa.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="panel detail-panel ticket-review-decision">
    <p class="eyebrow">QUẢN LÝ XÁC NHẬN</p>
    <h2 class="section-heading">Kết quả kiểm tra</h2>
    @if($ticket->status === 'resolved' && $ticket->repairAttachments->isNotEmpty())
    <p>Đối chiếu ảnh sinh viên gửi với ảnh sau sửa chữa. Nếu đã khắc phục, xác nhận đóng phiếu; nếu chưa đạt, ghi rõ nội dung cần làm lại.</p>
    <form class="role-form" action="#" method="post" data-api-endpoint="/api/admin/tickets/{id}/close" data-success-message="Đã xác nhận hoàn thành và đóng phiếu.">
        <label class="form-item"><span>Ghi chú xác nhận (không bắt buộc)</span><textarea name="note" maxlength="2000" placeholder="Ghi nhận kết quả kiểm tra"></textarea></label>
        <button class="button button-primary" type="submit">Xác nhận hoàn thành</button>
        <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
    </form>
    <hr>
    <form class="role-form" action="#" method="post" data-api-endpoint="/api/admin/tickets/{id}/reopen" data-success-message="Đã yêu cầu nhân viên tiếp tục xử lý.">
        <p class="assignment-help">Ghi rõ phần chưa đạt. Lý do này sẽ lưu vào lịch sử phiếu và gửi cho nhân viên được phân công.</p>
        <label class="form-item"><span>Yêu cầu làm lại <b>*</b></span><textarea name="note" required maxlength="2000" placeholder="Mô tả phần chưa đạt để nhân viên tiếp tục xử lý"></textarea></label>
        <button class="button button-danger" type="submit">Yêu cầu xử lý lại</button>
        <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
    </form>
    @elseif($ticket->status === 'closed')
    <p>Phiếu này đã được quản lý xác nhận hoàn thành và đóng.</p>
    @else
    <p>Chỉ có thể duyệt khi nhân viên đã gửi kết quả và ảnh minh chứng. Trạng thái hiện tại: <strong>{{ $statusLabels[$ticket->status] ?? $ticket->status }}</strong>.</p>
    @endif
</section>
@endsection

@section('scripts')
<script src="../assets/js/api-client.js?v=20261110-report-toast-center1"></script>
@endsection
