<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/')),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chi tiết phiếu {{ $ticket->ticket_number }} · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/staff.css?v=20261109-red-validation1">
    <link rel="stylesheet" href="../admin/assets/css/ticket-status.css?v=20261104-status1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261104-3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
</head>
<body class="staff-role-page role-page">
@php
    $stepIndex = match ($ticket->status) {
        'new', 'reopened' => 0,
        'assigned' => 1,
        'in_progress' => 2,
        'resolved' => 3,
        'closed' => 4,
        default => 0,
    };
    $mySchedule = $ticket->schedules->first(fn ($schedule) => (int) $schedule->staff_id === (int) $currentUser->id);
    $statusLabel = $statusLabels[$ticket->status] ?? $ticket->status;
    $reopenReason = $ticket->status === 'reopened'
        ? $ticket->logs->first(fn ($log) => $log->to_status === 'reopened')?->note
        : null;
@endphp
<div class="role-shell">
    @include('staff.partials.topbar')
    <main class="role-main staff-ticket-detail">
        <section class="staff-ticket-hero">
            <div class="staff-ticket-hero-copy">
                <a class="staff-ticket-back" href="index">← Danh sách công việc</a>
                <span class="role-kicker">CHI TIẾT PHIẾU · {{ $ticket->ticket_number }}</span>
                <h1>{{ $ticket->title }}</h1>
                <p>{{ $ticket->room?->building?->name ?? $ticket->building_label ?? 'Chưa có khu nhà' }} · {{ $ticket->room?->name ?? $ticket->room_label ?? 'Chưa có phòng' }} · Người báo: {{ $ticket->reporter?->name ?? $ticket->reporter_name }} · {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </section>

        <p class="api-feedback" id="staff-ticket-error" role="status" aria-live="polite" hidden></p>

        <section class="role-card staff-ticket-process" aria-label="Tiến độ xử lý phiếu">
            <div class="staff-ticket-section-heading staff-ticket-process-heading">
                <div><span class="role-kicker">QUY TRÌNH XỬ LÝ</span><p>Gửi sự cố → tiếp nhận → xử lý → xác nhận → đóng</p></div>
                <span class="role-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">● {{ $statusLabel }}</span>
            </div>
            @if($ticket->status === 'reopened')
                <p class="staff-ticket-reopened">Phiếu đã được mở lại và đang chờ tiếp tục xử lý.</p>
                @if($reopenReason)<p class="staff-ticket-reopen-reason"><strong>Yêu cầu của quản lý:</strong> {{ $reopenReason }}</p>@endif
            @endif
            <ol class="staff-ticket-steps">
                @foreach(['new' => ['Gửi sự cố', 'Báo cáo được tạo', 'fa-inbox'], 'assigned' => ['Tiếp nhận & phân công', 'Quản lý giao nhân viên', 'fa-user-check'], 'in_progress' => ['Đang xử lý', 'Nhân viên kiểm tra, sửa chữa', 'fa-screwdriver-wrench'], 'resolved' => ['Chờ xác nhận', 'Quản lý kiểm tra kết quả', 'fa-clipboard-check'], 'closed' => ['Đã đóng', 'Hoàn tất phiếu sự cố', 'fa-circle-check']] as $stage => [$label, $description, $icon])
                    @php($index = $loop->index)
                    <li class="ticket-status-{{ str_replace('_', '-', $stage) }} {{ $index < $stepIndex ? 'is-complete' : ($index === $stepIndex ? 'is-current' : '') }}">
                        <span class="staff-ticket-step-dot" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                        <span class="staff-ticket-step-label">{{ $label }}</span>
                        <small class="staff-ticket-step-description">{{ $description }}</small>
                        @if($index === $stepIndex)<span class="staff-ticket-step-current">Hiện tại</span>@endif
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="staff-ticket-layout">
            <div class="staff-ticket-primary">
                <section class="role-card staff-ticket-card">
                    <div class="staff-ticket-section-heading">
                        <div><span class="role-kicker">NỘI DUNG BÁO CÁO</span><h2>Thông tin sự cố</h2></div>
                    </div>
                    <div class="staff-ticket-description"><span class="staff-ticket-detail-icon">!</span><div><strong>Mô tả chi tiết</strong><p>{{ $ticket->description }}</p></div></div>
                    <dl class="staff-ticket-facts">
                        <div><dt>Danh mục lỗi</dt><dd><i style="--category-color:{{ $ticket->category?->color ?? '#64748b' }}"></i>{{ $ticket->category?->name ?? $ticket->equipment_label ?? 'Chưa phân loại' }}</dd></div>
                        <div><dt>Địa điểm</dt><dd>{{ $ticket->room?->building?->name ?? $ticket->building_label ?? '—' }} · {{ $ticket->room?->name ?? $ticket->room_label ?? '—' }}</dd></div>
                        <div><dt>Người báo</dt><dd>{{ $ticket->reporter?->name ?? $ticket->reporter_name ?? '—' }}</dd></div>
                        <div><dt>Mức độ ảnh hưởng</dt><dd><span class="staff-ticket-priority priority-{{ $ticket->priority }}">{{ ['low' => 'Thấp', 'normal' => 'Bình thường', 'high' => 'Cao', 'urgent' => 'Khẩn cấp'][$ticket->priority] ?? 'Bình thường' }}</span></dd></div>
                    </dl>
                    @if($ticket->reportAttachments->isNotEmpty())
                        <div class="staff-ticket-attachments">
                            <h3>Ảnh sinh viên gửi ban đầu <span>{{ $ticket->reportAttachments->count() }}</span></h3>
                            <div class="staff-ticket-attachment-grid">
                                @foreach($ticket->reportAttachments as $attachment)
                                    <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}"><img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh do sinh viên gửi: {{ $attachment->original_name }}" loading="lazy"></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($ticket->repairAttachments->isNotEmpty())
                        <div class="staff-ticket-attachments staff-ticket-repair-evidence">
                            <h3>Ảnh nhân viên minh chứng sửa chữa <span>{{ $ticket->repairAttachments->count() }}</span></h3>
                            <div class="staff-ticket-attachment-grid">
                                @foreach($ticket->repairAttachments as $attachment)
                                    <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}"><img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh nhân viên sửa chữa: {{ $attachment->original_name }}" loading="lazy"></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                @if(in_array($ticket->status, ['assigned', 'reopened', 'in_progress'], true) && (!$mySchedule || $mySchedule->status !== 'acknowledged'))
                    <section class="role-card staff-ticket-card staff-ticket-readonly-note">
                        <strong>{{ $mySchedule ? 'Hãy xác nhận lịch trước khi bắt đầu' : 'Phiếu chưa có lịch xử lý' }}</strong>
                        <p>{{ $mySchedule ? 'Sau khi bấm “Xác nhận lịch — tôi đang đến”, bạn mới có thể bắt đầu xử lý và gửi kết quả.' : 'Quản lý cần xếp lịch cho phiếu này trước khi bạn bắt đầu xử lý.' }}</p>
                    </section>
                @elseif(in_array($ticket->status, ['assigned', 'reopened', 'in_progress'], true))
                    <section class="role-card staff-ticket-card staff-ticket-update-card">
                        <div class="staff-ticket-section-heading">
                            <div><span class="role-kicker">CẬP NHẬT CÔNG VIỆC</span><h2>{{ $ticket->status === 'in_progress' ? 'Ghi nhận kết quả xử lý' : 'Bắt đầu xử lý sự cố' }}</h2></div>
                        </div>
                        @if($ticket->status !== 'in_progress')
                            <p class="staff-ticket-next-step"><span aria-hidden="true">i</span><span>Phiếu đang được tiếp nhận. Hãy bắt đầu xử lý trước; sau khi chuyển sang “Đang xử lý”, bạn mới có thể gửi kết quả và ảnh minh chứng.</span></p>
                        @else
                            <p class="staff-ticket-next-step"><span aria-hidden="true">i</span><span>Thêm ảnh minh chứng sau sửa chữa rồi bấm “Lưu tiến độ” để gửi quản lý xác nhận hoàn thành.</span></p>
                        @endif
                        <form class="role-form" action="#" method="post" enctype="multipart/form-data" data-api-endpoint="/api/staff/tickets/{id}/status" data-custom-validation="staff-progress" data-success-message="Đã gửi cập nhật xử lý.">
                            <div class="role-field"><label for="progress">Ghi chú tiến độ <span aria-hidden="true">*</span></label><textarea id="progress" name="note" maxlength="3000" aria-describedby="progress-error" placeholder="Mô tả các bước đã thực hiện..."></textarea><p class="staff-inline-validation" id="progress-error" data-note-error role="alert" hidden></p></div>
                            <div class="role-field staff-repair-upload">
                                <label for="evidence"><i class="fa-regular fa-images" aria-hidden="true"></i> Ảnh minh chứng sau sửa chữa</label>
                                <input id="evidence" name="attachments[]" multiple type="file" accept="image/*" aria-describedby="repair-upload-help">
                                <small class="staff-ticket-help" id="repair-upload-help">Chọn tối đa 5 ảnh, mỗi ảnh không quá 5 MB. Bấm vào ảnh thu nhỏ để xem rõ.</small>
                                <div class="repair-image-preview" id="repair-image-preview" aria-live="polite" hidden></div>
                            </div>
                            <input id="status" type="hidden" name="status" value="in_progress">
                            <button class="role-button" type="submit" data-submit-progress>{{ $ticket->status === 'in_progress' ? 'Lưu tiến độ' : 'Bắt đầu xử lý' }}</button>
                            <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                            <span class="role-form-note">Cập nhật sẽ được lưu vào lịch sử phiếu.</span>
                        </form>
                    </section>
                @elseif($ticket->status === 'resolved')
                    <section class="role-card staff-ticket-card staff-ticket-readonly-note"><strong>Đã gửi kết quả xử lý</strong><p>Phiếu đang chờ quản lý kiểm tra và xác nhận.</p></section>
                @elseif($ticket->status === 'closed')
                    <section class="role-card staff-ticket-card staff-ticket-readonly-note"><strong>Phiếu đã đóng</strong><p>Quản lý đã xác nhận hoàn thành phiếu này.</p></section>
                @endif
            </div>

            <aside class="staff-ticket-sidebar">
                <section class="role-card staff-ticket-card staff-ticket-schedule-card">
                    <div class="staff-ticket-section-heading"><div><span class="role-kicker">LỊCH CÔNG VIỆC</span><h2>Lịch xử lý</h2></div></div>
                    @if($mySchedule)
                        <div class="staff-ticket-schedule-info"><span class="staff-ticket-detail-icon">▦</span><div><strong>{{ $mySchedule->starts_at->format('d/m/Y · H:i') }}</strong><small>{{ $mySchedule->status === 'acknowledged' ? 'Bạn đã xác nhận lịch và đang di chuyển.' : 'Bạn chưa xác nhận lịch này.' }}</small></div></div>
                        @if($mySchedule->status === 'scheduled')
                            <button class="role-button schedule-acknowledge-button" type="button" data-acknowledge-schedule="{{ $mySchedule->id }}" data-acknowledge-url="{{ url('/api/staff/schedules/'.$mySchedule->id.'/acknowledge') }}">✓ Xác nhận lịch — tôi đang đến</button>
                        @endif
                        <p class="api-feedback" data-schedule-ack-feedback role="status" aria-live="polite" hidden></p>
                    @else
                        <p class="staff-ticket-empty">Chưa có lịch xử lý đang hoạt động cho bạn.</p>
                    @endif
                </section>


            </aside>
        </div>
        <footer class="role-footer">Helpdesk trường học <span>·</span> Chi tiết công việc kỹ thuật</footer>
    </main>
</div>
<script src="assets/js/staff.js?v=20261106-cleanup1"></script>
<script src="assets/js/repair-attachments.js?v=20261104-preview2"></script>
<script src="../admin/assets/js/image-lightbox.js?v=20261104-lightbox1"></script>
</body>
</html>
