@extends('admin.layouts.app')

@section('title', 'Chi tiết phiếu · Helpdesk')
@section('content_class', 'ticket-detail-page')

@section('content')
@php
$workflowEvents = $ticket->logs->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();
if ($workflowEvents->isEmpty() || $workflowEvents->last()->to_status !== $ticket->status) {
$workflowEvents->push((object) [
'id' => 'current-'.$ticket->id,
'to_status' => $ticket->status,
'created_at' => $ticket->updated_at,
'user' => null,
'note' => null,
]);
}
$workflowStatusClasses = ['new' => 'wait', 'assigned' => 'received', 'in_progress' => 'progress', 'resolved' => 'review', 'closed' => 'done', 'reopened' => 'reopened'];
$workflowSteps = [
['status' => 'new', 'title' => 'Gửi sự cố', 'description' => 'Báo cáo được tạo', 'icon' => 'fa-inbox'],
['status' => 'assigned', 'title' => 'Tiếp nhận & phân công', 'description' => 'Quản lý giao nhân viên', 'icon' => 'fa-user-check'],
['status' => 'in_progress', 'title' => 'Đang xử lý', 'description' => 'Nhân viên kiểm tra, sửa chữa', 'icon' => 'fa-screwdriver-wrench'],
['status' => 'resolved', 'title' => 'Chờ xác nhận', 'description' => 'Quản lý kiểm tra kết quả', 'icon' => 'fa-clipboard-check'],
['status' => 'closed', 'title' => 'Đã đóng', 'description' => 'Hoàn tất phiếu sự cố', 'icon' => 'fa-circle-check'],
];
$workflowPosition = match ($ticket->status) {
'assigned', 'reopened' => 1,
'in_progress' => 2,
'resolved' => 3,
'closed' => 4,
default => 0,
};
$workflowCurrentLabel = $ticket->status === 'reopened'
? 'Đang xử lý lại'
: ($statusLabels[$ticket->status] ?? $ticket->status);
$workflowCurrentClass = $workflowStatusClasses[$ticket->status] ?? 'wait';
@endphp

@php
$currentSchedule = $ticket->schedules
    ->where('status', '!=', 'cancelled')
    ->whereNull('cancelled_at')
    ->sortByDesc('starts_at')
    ->first();
@endphp

<section class="page-title-row">
    <div>
        <p class="eyebrow">PHIẾU SỰ CỐ · {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
        <h1>{{ $ticket->title }}</h1>
        <p><a class="ticket-code" href="incidents">{{ $ticket->ticket_number }}</a> <span class="status-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></p>
    </div>
    <div class="title-actions">
        <a class="button button-secondary" href="incidents"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Danh sách phiếu</a>
        @if(in_array($ticket->status, ['new', 'reopened', 'assigned'], true))
        <a class="button button-primary" href="schedule"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Tiếp nhận &amp; phân công</a>
        @elseif($ticket->status === 'resolved')
        @if($ticket->repairAttachments->isEmpty())
        <a class="button button-primary" href="ticket-review?ticket={{ $ticket->id }}"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Kiểm tra kết quả</a>
        @endif
        @endif
    </div>
</section>

<p class="api-feedback" id="admin-ticket-error" role="status" aria-live="polite" hidden></p>

        @if($ticket->assignee || in_array($ticket->status, ['new', 'reopened', 'assigned'], true))
        <article class="panel detail-panel assignment-panel assignment-panel-top">
            <p class="eyebrow">PHÂN CÔNG</p>
            <h2 class="section-heading">{{ $ticket->assignee ? 'Thông tin nhân viên xử lý' : 'Phân công nhân viên' }}</h2>

            @if($ticket->assignee)
            <div class="assignment-current">
                <span class="assignment-avatar">{{ mb_strtoupper(mb_substr($ticket->assignee->name, 0, 2)) }}</span>
                <div class="assignment-person"><strong>{{ $ticket->assignee->name }}</strong><span>Nhân viên kỹ thuật được phân công</span><span>{{ $ticket->assignee->email }}</span></div>
                <span class="assignment-status ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $ticket->status === 'resolved' ? 'Chờ xác nhận' : ($ticket->status === 'closed' ? 'Đã hoàn thành' : ($ticket->status === 'in_progress' ? 'Đang xử lý' : ($currentSchedule ? 'Đã phân công' : 'Chờ xếp lịch'))) }}</span>
                <div class="assignment-schedule"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span><small>Lịch xử lý</small><strong>{{ $currentSchedule?->starts_at?->format('d/m/Y · H:i') ?? 'Chưa xếp lịch' }}</strong></span></div>
                @if($currentSchedule?->note)<p class="assignment-note">{{ $currentSchedule->note }}</p>@endif
            </div>
            @endif

            @if(in_array($ticket->status, ['new', 'reopened', 'assigned'], true))
            <form class="role-form assignment-form" action="#" method="post" data-api-endpoint="/api/admin/tickets/{id}/assign">
                <input type="hidden" name="schedule_id" value="{{ $currentSchedule?->id }}">
                <p class="assignment-form-title">{{ $ticket->assignee ? 'Cập nhật phân công hoặc lịch xử lý' : 'Chọn nhân viên và lịch xử lý' }}</p>
                <label class="form-item assignment-staff-field"><span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Nhân viên kỹ thuật</span><select name="staff_id" required>
                        <option value="">Chọn nhân viên</option>
                        @foreach($staffMembers as $staffMember)
                        <option value="{{ $staffMember->id }}" @selected($ticket->assigned_to_id === $staffMember->id)>{{ $staffMember->name }}</option>
                        @endforeach
                    </select></label>
                @if($staffMembers->isEmpty())
                <p class="assignment-no-staff">Chưa có nhân viên nào được gán danh mục <strong>{{ $ticket->category?->name ?? $ticket->equipment_label }}</strong>. <a href="team#technicians">Cập nhật chuyên môn nhân viên</a></p>
                @endif
                <label class="form-item assignment-date-field"><span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Thời gian xử lý</span><input id="assignment-scheduled-at" type="datetime-local" name="scheduled_at"><small class="assignment-help">Để trống nếu muốn giữ lịch hiện tại.</small></label>
                <label class="form-item assignment-note-field"><span><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Ghi chú phân công</span><textarea name="note" maxlength="2000" placeholder="Nội dung cần chuẩn bị trước khi xử lý">{{ $currentSchedule?->note }}</textarea></label>
                <div class="assignment-form-actions">
                    <small><i class="fa-regular fa-bell" aria-hidden="true"></i> Nhân viên sẽ nhận thông báo khi cập nhật phân công.</small>
                    <button class="button button-primary" type="submit" @disabled($staffMembers->isEmpty())><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> {{ $ticket->assignee ? 'Lưu thay đổi phân công' : 'Tiếp nhận và phân công' }}</button>
                </div>
                <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
            </form>
            @endif
        </article>
        @endif

<section class="workflow-panel" aria-label="Tiến trình xử lý phiếu">
    <div class="workflow-panel-heading">

        <span class="workflow-current workflow-current-{{ $workflowCurrentClass }} ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}"><i class="fa-solid fa-circle" aria-hidden="true"></i>{{ $workflowCurrentLabel }}</span>
    </div>
    <ol class="workflow-process-steps">
        @foreach($workflowSteps as $index => $step)
        @php
        $stepState = $index < $workflowPosition ? 'done' : ($index===$workflowPosition ? 'current' : 'pending' );
            $stepTitle=$ticket->status === 'reopened' && $index === 1 ? 'Mở lại xử lý' : $step['title'];
            $stepDescription = $ticket->status === 'reopened' && $index === 1 ? 'Cần tiếp tục xử lý sự cố' : $step['description'];
            @endphp
            <li class="workflow-process-step is-{{ $stepState }} ticket-status-{{ str_replace('_', '-', $stepState === 'current' ? $ticket->status : $step['status']) }}">
                <span class="workflow-process-marker"><i class="fa-solid {{ $step['icon'] }}" aria-hidden="true"></i></span>
                <span class="workflow-process-copy"><strong>{{ $stepTitle }}</strong><small>{{ $stepDescription }}</small></span>
                @if($stepState === 'current')<span class="workflow-process-tag">Hiện tại</span>@endif
            </li>
            @endforeach
    </ol>
</section>

@php
[$stateIcon, $stateTitle, $stateMessage, $stateStyle] = match ($ticket->status) {
'assigned' => ['fa-circle-check', 'Phiếu đã được tiếp nhận', $ticket->assignee ? 'Đã phân công cho '.$ticket->assignee->name.' xử lý sự cố.' : 'Phiếu đang chờ phân công nhân viên kỹ thuật.', 'received'],
'in_progress' => ['fa-screwdriver-wrench', 'Phiếu đang được xử lý', 'Nút Hoàn thành hoặc Mở lại sẽ xuất hiện sau khi nhân viên gửi kết quả sửa chữa để quản lý xác nhận.', 'progress'],
'resolved' => ['fa-clipboard-check', 'Nhân viên đã gửi kết quả', 'Phiếu đang chờ quản lý kiểm tra và xác nhận hoàn thành.', 'resolved'],
'closed' => ['fa-circle-check', 'Phiếu đã hoàn tất', 'Quản lý đã xác nhận kết quả xử lý sự cố.', 'closed'],
'reopened' => ['fa-rotate-left', 'Phiếu đã được mở lại', 'Cần tiếp tục phân công nhân viên để xử lý phần chưa đạt.', 'reopened'],
default => ['fa-clock', 'Phiếu đang chờ tiếp nhận', 'Quản lý cần tiếp nhận và phân công nhân viên kỹ thuật.', 'new'],
};
if ($ticket->status === 'assigned') {
$stateTitle = $ticket->assignee
? ($currentSchedule ? 'Đã tiếp nhận và xếp lịch' : 'Đã tiếp nhận · Chưa xếp lịch')
: 'Đã tiếp nhận · Chưa phân công';
$stateMessage = $ticket->assignee
? 'Nhân viên phụ trách: '.$ticket->assignee->name.($currentSchedule ? ' · Lịch xử lý: '.$currentSchedule->starts_at->format('d/m/Y H:i') : ' · Cần xếp lịch xử lý.')
: 'Phiếu đã được tiếp nhận nhưng chưa có nhân viên phụ trách.';
}
@endphp
<aside class="ticket-state-notice state-{{ $stateStyle }} ticket-status-{{ str_replace('_', '-', $ticket->status) }}" role="status">
    <span class="ticket-state-icon"><i class="fa-solid {{ $stateIcon }}" aria-hidden="true"></i></span>
    <span><strong>{{ $stateTitle }}</strong><small>{{ $stateMessage }}</small></span>
</aside>

<section class="two-column detail-columns">
    <div>
        <article class="panel detail-panel">
            <p class="eyebrow">THÔNG TIN SỰ CỐ</p>
            <h2 class="section-heading">Mô tả vấn đề</h2>
            <p class="detail-description">{{ $ticket->description }}</p>
            <div class="detail-facts ticket-issue-facts">
                <div><small>Danh mục lỗi</small><strong>{{ $ticket->category?->name ?? $ticket->equipment_label }}</strong></div>
                <div><small>Địa điểm</small><strong>{{ $ticket->room?->building?->name ?? $ticket->building_label }} · {{ $ticket->room?->name ?? $ticket->room_label }}</strong></div>
                <div><small>Mức độ ưu tiên</small><strong><span class="status-badge status-wait">{{ $ticket->priority }}</span></strong></div>
            </div>
        </article>

        <article class="panel detail-panel">
            <p class="eyebrow">NGƯỜI BÁO</p>
            <div class="person-profile">
                <span class="mini-avatar av-blue" style="width:40px;height:40px;font-size:12px">{{ mb_strtoupper(mb_substr($ticket->reporter?->name ?? $ticket->reporter_name, 0, 2)) }}</span>
                <span><strong>{{ $ticket->reporter?->name ?? $ticket->reporter_name }}</strong><small>Người gửi sự cố</small><small>{{ $ticket->reporter?->email ?? $ticket->reporter_email }}</small></span>
            </div>
            <div class="detail-facts single-fact">
                <div><small>Số điện thoại</small><strong>{{ $ticket->reporter_phone ?: '—' }}</strong></div>
                <div><small>Kênh gửi</small><strong>Cổng người dùng</strong></div>
            </div>
        </article>
    </div>

    <div>


        @if($ticket->status === 'resolved')
        <article class="panel detail-panel ticket-review-actions" id="ticket-rework-panel">
            <p class="eyebrow">QUẢN LÝ XÁC NHẬN</p>
            <h2 class="section-heading">Kết quả sửa chữa</h2>
            @if($ticket->repairAttachments->isNotEmpty())
            <p>Kiểm tra ảnh minh chứng của nhân viên. Xác nhận để đóng phiếu, hoặc ghi rõ nội dung cần làm lại bên dưới.</p>
            <form class="ticket-close-inline-form" action="#" method="post" data-api-endpoint="/api/admin/tickets/{id}/close" data-success-message="Đã xác nhận hoàn thành và đóng phiếu.">
                <button class="button button-primary" type="submit"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Xác nhận hoàn thành</button>
                <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
            </form>
            <form class="role-form ticket-rework-form" action="#" method="post" data-api-endpoint="/api/admin/tickets/{id}/reopen" data-success-message="Đã yêu cầu nhân viên tiếp tục xử lý.">
                <p class="assignment-help">Ghi rõ phần chưa đạt. Lý do này sẽ lưu vào lịch sử phiếu và gửi cho nhân viên được phân công.</p>
                <label class="form-item"><span>Yêu cầu nhân viên làm lại</span><textarea name="note" required maxlength="2000" placeholder="Mô tả phần chưa đạt để nhân viên tiếp tục xử lý"></textarea></label>
                <button class="button button-danger full-button" type="submit"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Yêu cầu xử lý lại</button>
                <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
            </form>
            @else
            <p class="assignment-no-staff">Chưa thể xác nhận: nhân viên chưa gửi ảnh minh chứng sửa chữa.</p>
            @endif
        </article>
        @endif

        <article class="panel detail-panel ticket-history-panel">
            <details class="ticket-history-disclosure">
                <summary><span class="ticket-history-summary-label"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Xem l&#7883;ch s&#7917; thao t&#225;c</span><span class="ticket-history-summary-count">{{ $workflowEvents->count() }} c&#7853;p nh&#7853;t</span><i class="fa-solid fa-chevron-down ticket-history-chevron" aria-hidden="true"></i></summary>
                <div class="history-list">
                @forelse($workflowEvents as $log)
                <div class="history-item ticket-status-{{ str_replace('_', '-', $log->to_status) }}">
                    <i></i>
                    <div><strong>{{ $statusLabels[$log->to_status] ?? $log->to_status }}</strong><span>{{ $log->user?->name ?? 'Hệ thống' }}@if($log->note) · {{ $log->note }}@endif</span><small>{{ $log->created_at->format('d/m/Y H:i') }}</small></div>
                </div>
                @empty
                <p>Chưa có lịch sử trạng thái.</p>
                @endforelse
            </div>
            </details>
        </article>
    </div>

    <div class="ticket-evidence-comparison" aria-label="Ảnh minh chứng sự cố">
        <article class="panel detail-panel evidence-card report-evidence-card">
            <div class="evidence-heading">
                <span class="evidence-heading-icon"><i class="fa-solid fa-camera" aria-hidden="true"></i></span>
                <div class="evidence-heading-copy">
                    <p class="eyebrow">KHI GỬI BÁO CÁO</p>
                    <h2 class="section-heading">Ảnh minh chứng của sinh viên</h2>
                    <small>Hình ảnh ban đầu về tình trạng sự cố · {{ $ticket->reportAttachments->count() }} ảnh</small>
                </div>
            </div>
            @if($ticket->reportAttachments->isNotEmpty())
            <div class="ticket-attachments evidence-card-attachments">
                @foreach($ticket->reportAttachments as $attachment)
                @if(\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->path))
                <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}"><img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh sinh viên gửi: {{ $attachment->original_name }}"><span><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i> Xem ảnh</span></a>
                @else
                <p class="evidence-empty" title="{{ $attachment->original_name }}">Không tìm thấy ảnh gốc. Vui lòng khôi phục hoặc tải lại ảnh minh chứng.</p>
                @endif
                @endforeach
            </div>
            @else
            <p class="evidence-empty">Sinh viên không đính kèm ảnh khi gửi báo cáo.</p>
            @endif
        </article>

        <article class="panel detail-panel evidence-card repair-evidence-panel">
            <div class="evidence-heading">
                <span class="evidence-heading-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                <div class="evidence-heading-copy">
                    <p class="eyebrow">SAU KHI XỬ LÝ</p>
                    <h2 class="section-heading">Ảnh minh chứng của nhân viên</h2>
                    <small>Kết quả sửa chữa gửi quản lý kiểm tra · {{ $ticket->repairAttachments->count() }} ảnh</small>
                </div>
            </div>
            @if($ticket->repairAttachments->isNotEmpty())
            <div class="ticket-attachments evidence-card-attachments repair-evidence-attachments">
                @foreach($ticket->repairAttachments as $attachment)
                @if(\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->path))
                <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}"><img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh nhân viên sửa chữa: {{ $attachment->original_name }}"><span><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i> Xem ảnh</span></a>
                @else
                <p class="evidence-empty" title="{{ $attachment->original_name }}">Không tìm thấy ảnh gốc. Vui lòng khôi phục hoặc tải lại ảnh minh chứng.</p>
                @endif
                @endforeach
            </div>
            @else
            <p class="evidence-empty">Nhân viên chưa gửi ảnh minh chứng sửa chữa.</p>
            @endif
        </article>
    </div>
</section>
@endsection

@section('scripts')
<script src="../assets/js/api-client.js?v=20261106-fast-actions1"></script>
<script>
    document.querySelector('#assignment-scheduled-at')?.addEventListener('click', function() {
        try {
            this.showPicker();
        } catch (_) {
            this.focus();
        }
    });
</script>
@endsection
