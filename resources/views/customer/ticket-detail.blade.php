<!doctype html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.HELPDESK_CONFIG={apiEnabled:true,appUrl:@json(url('/') ),apiBase:"/api"};</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chi tiết phiếu {{ $ticket->ticket_number }} · Helpdesk</title>
    <link rel="stylesheet" href="assets/css/customer.css?v=20261106-local-fonts1"><link rel="stylesheet" href="assets/css/account.css?v=20261103-responsive1"><link rel="stylesheet" href="../admin/assets/css/ticket-status.css?v=20261104-status1">
    <link rel="stylesheet" href="../admin/assets/css/role-refresh.css?v=20261105-staff-header2">
</head>
<body class="customer-role-page role-page ticket-detail-page">
@php
    $displayStatus = in_array($ticket->status, ['assigned', 'reopened'], true) ? 'in_progress' : $ticket->status;
    $stepIndex = match ($displayStatus) {
        'new' => 0,
        'assigned' => 1,
        'in_progress' => 2,
        'resolved' => 3,
        'closed' => 4,
        default => 0,
    };
    $statusLabel = $statusLabels[$displayStatus] ?? 'Mới';
    $priorityLabel = [
        'low' => 'Thấp', 'normal' => 'Bình thường', 'high' => 'Cao', 'urgent' => 'Cần xử lý gấp',
    ][$ticket->priority] ?? 'Bình thường';
    $latestSchedule = $ticket->schedules->sortByDesc('starts_at')->first();
@endphp
<div class="role-shell">
    @include('customer.partials.topbar')
    <main class="role-main ticket-detail-main">
        <section class="ticket-detail-heading">
            <div class="ticket-heading-copy">
                <span class="role-kicker">CHI TIẾT PHIẾU</span>
                <div class="ticket-number-line"><span>Mã phiếu</span><strong>{{ $ticket->ticket_number }}</strong></div>
                <h1>{{ $ticket->title }}</h1>
                <p>{{ $ticket->room?->building?->name ?? $ticket->building_label ?? 'Chưa có khu nhà' }} · {{ $ticket->room?->name ?? $ticket->room_label ?? 'Chưa có phòng' }} · Gửi lúc {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="ticket-heading-actions">
                <a class="role-button secondary" href="tickets">← Danh sách phiếu</a>
            </div>
        </section>

        <p class="api-feedback" id="ticket-load-error" role="status" aria-live="polite" hidden></p>

        <section class="role-card ticket-progress-card" aria-label="Tiến độ xử lý phiếu">
            <div class="ticket-section-heading ticket-progress-heading">
                <div><span class="role-kicker">QUY TRÌNH XỬ LÝ</span><p>Gửi sự cố → tiếp nhận → xử lý → xác nhận → đóng</p></div>
                <span class="role-badge ticket-status ticket-status-{{ str_replace('_', '-', $displayStatus) }}">● {{ $statusLabel }}</span>
            </div>
            <ol class="ticket-progress-steps">
                @foreach(['new' => ['Gửi sự cố', 'Báo cáo được tạo', '▣'], 'assigned' => ['Tiếp nhận & phân công', 'Quản lý giao nhân viên', '♙'], 'in_progress' => ['Đang xử lý', 'Nhân viên kiểm tra, sửa chữa', '⚒'], 'resolved' => ['Chờ xác nhận', 'Quản lý kiểm tra kết quả', '☑'], 'closed' => ['Đã đóng', 'Hoàn tất phiếu sự cố', '●']] as $stage => [$label, $description, $icon])
                    @php($index = $loop->index)
                    <li class="ticket-status-{{ str_replace('_', '-', $stage) }} {{ $index < $stepIndex ? 'is-complete' : ($index === $stepIndex ? 'is-current' : '') }}">
                        <span class="ticket-step-dot" aria-hidden="true">{{ $icon }}</span>
                        <span class="ticket-step-label">{{ $label }}</span>
                        <small class="ticket-step-description">{{ $description }}</small>
                        @if($index === $stepIndex)<span class="ticket-step-current">Hiện tại</span>@endif
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="ticket-detail-layout">
            <div class="ticket-detail-main-column">
                <section class="role-card ticket-information-card">
                    <div class="ticket-section-heading">
                        <div><span class="role-kicker">NỘI DUNG BÁO CÁO</span><h2>Thông tin sự cố</h2></div>
                    </div>
                    <p class="ticket-description">{{ $ticket->description }}</p>
                    <dl class="ticket-facts">
                        <div><dt>Danh mục lỗi</dt><dd><i style="--category-color:{{ $ticket->category?->color ?? '#64748B' }}"></i>{{ $ticket->category?->name ?? $ticket->equipment_label ?? 'Chưa phân loại' }}</dd></div>
                        <div><dt>Địa điểm</dt><dd>{{ $ticket->room?->building?->name ?? $ticket->building_label ?? '—' }} · {{ $ticket->room?->name ?? $ticket->room_label ?? '—' }}</dd></div>
                        <div><dt>Mức độ ảnh hưởng</dt><dd><span class="ticket-priority priority-{{ $ticket->priority }}">{{ $priorityLabel }}</span></dd></div>
                        <div><dt>Người báo</dt><dd>{{ $ticket->reporter_name ?? $currentUser->name }}</dd></div>
                    </dl>
                    @if($ticket->reportAttachments->isNotEmpty())
                        <div class="ticket-attachments">
                            <h3>Ảnh bạn đã gửi <span>{{ $ticket->reportAttachments->count() }}</span></h3>
                            <div class="ticket-attachment-grid">
                                @foreach($ticket->reportAttachments as $attachment)
                                    <a class="js-image-preview" href="{{ url('/storage/'.$attachment->path) }}" title="{{ $attachment->original_name }}">
                                        <img src="{{ url('/storage/'.$attachment->path) }}" alt="Ảnh bạn đã gửi: {{ $attachment->original_name }}" loading="lazy">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                @if($ticket->status === 'new')
                    <details class="role-card ticket-edit-card">
                        <summary><strong>Chỉnh sửa phiếu</strong><span>Bạn có thể cập nhật nội dung và ảnh khi phiếu còn chờ tiếp nhận.</span></summary>
                        <form class="ticket-edit-form" action="#" method="post" enctype="multipart/form-data" data-api-endpoint="/api/tickets/{{ $ticket->id }}" data-api-method="POST" data-success-message="Đã lưu thay đổi phiếu." data-reload-on-success="true">
                            <input type="hidden" name="_method" value="PATCH">
                            <div class="ticket-edit-fields">
                                <label>Số điện thoại<input name="phone" type="tel" maxlength="40" value="{{ $ticket->reporter_phone }}"></label>
                                <label>Danh mục sự cố<select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($ticket->category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                                <label>Tòa nhà<select name="building_code" data-edit-building required>@foreach($buildings as $building)<option value="{{ $building->code }}" @selected($ticket->room?->building_id === $building->id)>{{ $building->name }}</option>@endforeach</select></label>
                                <label>Phòng học<select name="room_code" data-edit-room required>@foreach($buildings as $building)@foreach($building->rooms as $room)<option value="{{ $room->code }}" data-building="{{ $building->code }}" data-room-id="{{ $room->id }}" @selected($ticket->room_id === $room->id)>{{ $building->name }} · {{ $room->name }}</option>@endforeach @endforeach</select><input type="hidden" name="room_id" value="{{ $ticket->room_id }}" data-edit-room-id></label>
                                <label class="ticket-edit-wide">Tiêu đề<input name="title" maxlength="180" required value="{{ $ticket->title }}"></label>
                                <label class="ticket-edit-wide">Mô tả<textarea name="description" maxlength="5000" required rows="5">{{ $ticket->description }}</textarea></label>
                                <label class="ticket-edit-wide">Mức độ ảnh hưởng<select name="priority"><option value="low" @selected($ticket->priority === 'low')>Thấp</option><option value="normal" @selected($ticket->priority === 'normal')>Bình thường</option><option value="high" @selected($ticket->priority === 'high')>Cao</option><option value="urgent" @selected($ticket->priority === 'urgent')>Cần xử lý gấp</option></select></label>
                                <label class="ticket-edit-wide">Thay ảnh báo cáo<input name="attachments[]" type="file" accept="image/*" multiple><small>Nếu chọn ảnh mới, ảnh báo cáo cũ sẽ được thay thế. Để trống nếu muốn giữ ảnh hiện tại. Tối đa 5 ảnh, mỗi ảnh 5 MB.</small></label>
                                @if($ticket->reportAttachments->isNotEmpty())<label class="ticket-edit-wide ticket-edit-remove-photos"><span><input type="checkbox" name="remove_attachments" value="1"> Xóa ảnh báo cáo hiện tại (chỉ áp dụng nếu không chọn ảnh mới)</span></label>@endif
                            </div>
                            <p class="api-feedback" data-api-feedback role="status" aria-live="polite" hidden></p>
                            <button class="role-button" type="submit">Lưu thay đổi</button>
                        </form>
                    </details>
                @endif


            </div>

            <aside class="ticket-detail-side-column">
                <section class="role-card ticket-assignment-card">
                    <span class="role-kicker">NHÂN VIÊN PHỤ TRÁCH</span>
                    @if($ticket->assignee)
                        <div class="ticket-assignee"><span class="ticket-assignee-avatar">KT</span><div><strong>{{ $ticket->assignee->name }}</strong><small>Nhân viên kỹ thuật</small></div></div>
                        <div class="ticket-schedule-info"><span>Lịch dự kiến</span><strong>{{ $latestSchedule?->starts_at?->format('d/m/Y · H:i') ?? 'Chưa xếp lịch' }}</strong></div>
                    @else
                        <div class="ticket-waiting-assignment"><span>…</span><strong>Đang chờ phân công</strong><small>Quản lý sẽ tiếp nhận và sắp xếp nhân viên kỹ thuật.</small></div>
                    @endif
                </section>
                <section class="role-card ticket-help-card">
                    <span class="ticket-help-icon">?</span>
                    <h2>Bạn cần hỗ trợ?</h2>
                    <p>Nếu có thêm thông tin hoặc sự cố khác, bạn có thể gửi báo cáo mới cho đội kỹ thuật.</p>
                    <a class="role-button" href="notifications">Xem thông báo</a>
                    <a class="role-button secondary" href="{{ url('/customer/portal') }}">Gửi sự cố khác</a>
                </section>
            </aside>
        </div>
        <footer class="role-footer">Helpdesk trường học <span>·</span> Theo dõi tiến độ phiếu</footer>
    </main>
</div>
<script src="assets/js/customer.js?v=20261106-fast-actions1"></script>
<script src="../admin/assets/js/image-lightbox.js?v=20261104-lightbox1"></script>
<style>
.ticket-edit-card summary{cursor:pointer;display:grid;gap:.35rem}.ticket-edit-card summary strong{color:#183b68}.ticket-edit-card summary span,.ticket-edit-form small{color:#71849d;font-size:.88rem}.ticket-edit-form{margin-top:1.25rem}.ticket-edit-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.ticket-edit-fields label{display:grid;gap:.45rem;color:#294d75;font-weight:600}.ticket-edit-fields input,.ticket-edit-fields select,.ticket-edit-fields textarea{width:100%;box-sizing:border-box;border:1px solid #d5e2ef;border-radius:10px;padding:.75rem;font:inherit;color:#183b68;background:#fff}.ticket-edit-fields small{font-weight:400}.ticket-edit-wide{grid-column:1/-1}@media(max-width:640px){.ticket-edit-fields{grid-template-columns:1fr}.ticket-edit-wide{grid-column:auto}}
</style>
<script>
function filterEditRooms(building){
    const form=building.closest('form');const rooms=form.querySelector('[data-edit-room]');const roomId=form.querySelector('[data-edit-room-id]');
    const options=[...rooms.options];let selected=null;
    options.forEach(option=>{const visible=option.dataset.building===building.value;option.hidden=!visible;if(visible&&!selected)selected=option;});
    if(selected){rooms.value=selected.value;roomId.value=selected.dataset.roomId;}
}
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-edit-building]').forEach(building=>{const rooms=building.closest('form').querySelector('[data-edit-room]');rooms.querySelectorAll('option').forEach(option=>option.hidden=option.dataset.building!==building.value);});});
document.addEventListener('change',function(event){if(event.target.matches('[data-edit-building]'))filterEditRooms(event.target);});
document.addEventListener('change',function(event){if(event.target.matches('[data-edit-room]'))event.target.closest('form').querySelector('[data-edit-room-id]').value=event.target.selectedOptions[0]?.dataset.roomId||'';});
</script>
</body>
</html>
