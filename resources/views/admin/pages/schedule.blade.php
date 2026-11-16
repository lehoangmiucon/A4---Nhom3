@extends('admin.layouts.app')

@section('title')
Lịch phân công · Helpdesk trường học
@endsection

@push('styles')
<link rel="stylesheet" href="{{ url('/admin/assets/css/calendar-overflow.css?v=20261114-time-picker1') }}">
@endpush

@section('content')
<section class="page-title-row">
                    <div>
                        <p class="eyebrow">ĐIỀU PHỐI CÔNG VIỆC</p>
                        <h1>Lịch phân công kỹ thuật</h1>
                        <p>Sắp xếp thời gian xử lý và phân công phiếu cho nhân viên kỹ thuật.</p>
                    </div><button class="button button-primary" type="button" data-open-assignment>＋ Phân công phiếu</button>
                </section>
                <section class="summary-strip">
                    <div class="summary-tile"><span>Lịch hôm nay</span><strong>{{ $scheduleCounts['today'] }}</strong></div>
                    <div class="summary-tile"><span>Chưa phân công</span><strong style="color:#bd821e">{{ $scheduleCounts['unassigned'] }}</strong></div>
                    <div class="summary-tile"><span>Nhân viên kỹ thuật</span><strong>{{ $scheduleCounts['staff'] }}</strong></div>
                    <div class="summary-tile"><span>Đã hoàn thành hôm nay</span><strong style="color:#26946c">{{ $scheduleCounts['completed_today'] }}</strong></div>
                </section>
                <section class="panel calendar-panel">
                    <div class="calendar-toolbar schedule-toolbar">
                        <div class="calendar-navigation"><button class="calendar-arrow" type="button" data-calendar-prev aria-label="Th&#225;ng tr&#432;&#7899;c">‹</button><button class="button button-secondary today-button" type="button">H&#244;m nay</button><button class="calendar-arrow" type="button" data-calendar-next aria-label="Th&#225;ng sau">›</button></div>
                        <h2 class="section-heading calendar-month-title">Tháng {{ now()->month }} năm {{ now()->year }}</h2>
                        <div class="calendar-tools"><div class="staff-filter" data-staff-filter><i class="fa-solid fa-user" aria-hidden="true"></i><select aria-label="Lọc theo nhân viên">
                                    <option value="">Tất cả nhân viên</option>@foreach($staffMembers as $staffMember)<option value="{{ $staffMember->name }}">{{ $staffMember->name }}</option>@endforeach
                                </select></div>
                            <div class="staff-filter category-filter" data-category-filter><i class="fa-solid fa-tag" aria-hidden="true"></i><select aria-label="Lọc theo danh mục báo cáo">
                                    <option value="">Tất cả danh mục lỗi</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-color="{{ $category->color }}">{{ $category->name }}</option>@endforeach
                                </select></div>
                            <div class="staff-filter status-filter" data-status-filter><i class="fa-solid fa-list-check" aria-hidden="true"></i><select aria-label="Lọc theo trạng thái phiếu">
                                    <option value="">Tất cả trạng thái</option>@foreach($statusLabels as $statusValue => $statusLabel)<option value="{{ $statusValue }}">{{ $statusLabel }}</option>@endforeach
                                </select></div>
                            <div class="calendar-tabs"><input type="radio" name="calendar-view" id="view-month" checked><label for="view-month">Th&#225;ng</label><input type="radio" name="calendar-view" id="view-week"><label for="view-week">Tu&#7847;n</label><input type="radio" name="calendar-view" id="view-day"><label for="view-day">Ng&#224;y</label></div>
                        </div>
                    </div>
                    <div class="month-scroll-viewport"><div class="month-grid month-head"><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span></div>@php($monthStart = now()->startOfMonth())
                    @php($calendarStart = $monthStart->copy()->startOfWeek(1))
                    <div class="month-grid month-days" data-time-zone="{{ config('app.timezone') }}">
                        @for($dayOffset = 0; $dayOffset < 42; $dayOffset++)
                            @php($calendarDate=$calendarStart->copy()->addDays($dayOffset))
                            <div class="day-cell {{ $calendarDate->month !== $monthStart->month ? 'muted-day' : '' }} {{ $calendarDate->isToday() ? 'today-cell' : '' }}" data-date="{{ $calendarDate->toDateString() }}"><span class="day-number {{ $calendarDate->isToday() ? 'today-number' : '' }}">{{ $calendarDate->day }}</span>@if($calendarDate->gte(today()))<button class="day-schedule-add" type="button" data-day-add>＋ Xếp lịch</button>@endif
                                @foreach($schedules->filter(fn ($item) => $item->starts_at->isSameDay($calendarDate)) as $schedule)
                                <div class="cal-event category-event" style="--category-color: {{ $schedule->ticket->category?->color ?? '#64748B' }}" data-category-color="{{ $schedule->ticket->category?->color ?? '#64748B' }}" data-category-id="{{ $schedule->ticket->category_id ?? '' }}" data-ticket-status="{{ $schedule->ticket->status }}" data-ticket-id="{{ $schedule->ticket_id }}" data-schedule-id="{{ $schedule->id }}" data-schedule-status="{{ $schedule->status }}" data-staff-id="{{ $schedule->staff_id }}" data-staff-name="{{ $schedule->staff->name }}" data-category-name="{{ $schedule->ticket->category?->name ?? 'Chưa phân loại' }}"><time class="event-time">{{ $schedule->starts_at->format('H:i') }}</time><strong class="event-title">{{ $schedule->ticket->ticket_number }} · {{ $schedule->ticket->room?->code }}</strong><small class="event-meta">{{ $schedule->ticket->category?->name ?? 'Chưa phân loại' }} · {{ $schedule->staff->name }}</small></div>
                                @endforeach
                            </div>
                            @endfor
                    </div>
                    </div>
                    <script type="application/json" id="schedule-event-data">@json($scheduleEvents)</script>
                    <div class="calendar-legend" aria-label="Màu theo danh mục lỗi">@forelse($categories as $category)<span><i class="legend-color" style="background:{{ $category->color ?? '#64748B' }}"></i>{{ $category->name }}</span>@empty<span>Chưa có danh mục lỗi.</span>@endforelse</div>
                    <dialog class="calendar-modal" id="calendar-modal" aria-labelledby="calendar-modal-date">
                        <header class="modal-head"><span class="modal-calendar-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                            <form method="dialog"><button class="modal-close" aria-label="&#272;&#243;ng">×</button></form>
                        </header>
                        <h2 id="calendar-modal-date" data-modal-date>Ch&#7885;n m&#7897;t ng&#224;y</h2>
                        <div class="modal-event-list" data-modal-events></div>
                        <p class="modal-empty" data-modal-empty hidden>Ng&#224;y n&#224;y ch&#432;a c&#243; l&#7883;ch x&#7917; l&#253;. B&#7841;n c&#243; th&#7875; x&#7871;p l&#7883;ch m&#7899;i.</p>
                        <section class="modal-assignment" data-modal-assignment hidden>
                            <div class="modal-assignment-heading"><span><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span>
                                <div>
                                    <h3>Ph&#226;n c&#244;ng nh&#226;n vi&#234;n k&#7929; thu&#7853;t</h3>
                                    <p>Ch&#7885;n phi&#7871;u, ng&#432;&#7901;i ph&#7909; tr&#225;ch v&#224; th&#7901;i gian d&#7921; ki&#7871;n.</p>
                                </div>
                            </div>
                            <div class="modal-assignment-grid"><label class="form-item"><span>Phi&#7871;u s&#7921; c&#7889; <b>*</b></span><select id="modal-ticket" required>
                                        <option value="">Ch&#7885;n phi&#7871;u s&#7921; c&#7889;</option>
                                        @foreach($tickets as $ticketOption)
                                            @php($ticketRoomName = $ticketOption->room?->name ?: ($ticketOption->room_label ?: ($ticketOption->room?->code ?: 'Chưa xác định phòng')))
                                            <option value="{{ $ticketOption->id }}" data-category-id="{{ $ticketOption->category_id }}" data-ticket-number="{{ $ticketOption->ticket_number }}" data-ticket-title="{{ $ticketOption->title }}" data-room-name="{{ $ticketRoomName }}" data-category-name="{{ $ticketOption->category?->name }}" data-category-color="{{ $ticketOption->category?->color }}">{{ $ticketOption->ticket_number }} &#183; {{ $ticketOption->title }} &#8212; {{ $ticketOption->category?->name }} &#183; {{ $ticketRoomName }}</option>
                                        @endforeach
                                    </select></label><label class="form-item"><span>Nh&#226;n vi&#234;n k&#7929; thu&#7853;t <b>*</b></span><select id="modal-technician" required disabled>
                                        <option value="">Vui l&#242;ng ch&#7885;n phi&#7871;u tr&#432;&#7899;c</option>@foreach($staffMembers as $staffOption)<option value="{{ $staffOption->id }}" data-category-ids='@json($staffOption->categories->pluck("id"))'>{{ $staffOption->name }}</option>@endforeach
                                    </select></label><label class="form-item modal-note-field"><span>Gi&#7901; b&#7855;t &#273;&#7847;u <b>*</b></span><input id="modal-assignment-time" type="time" required></label><label class="form-item modal-note-field"><span>Ghi ch&#250; ph&#226;n c&#244;ng <b>*</b></span><input id="modal-assignment-note" maxlength="2000" required placeholder="N&#7897;i dung c&#7847;n chu&#7849;n b&#7883; tr&#432;&#7899;c khi ki&#7875;m tra"></label></div>
                            <p class="modal-assignment-feedback" data-modal-feedback role="alert" hidden></p>
                        </section>
                        <footer class="calendar-modal-footer" data-modal-details-actions>
                            <form method="dialog"><button class="button button-secondary">&#272;&#243;ng</button></form><button class="button button-primary" type="button" data-modal-add>＋ X&#7871;p l&#7883;ch ng&#224;y n&#224;y</button>
                        </footer>
                        <footer class="calendar-modal-footer" data-modal-assignment-actions hidden><button class="button button-danger" type="button" data-modal-cancel-schedule hidden>H&#7911;y l&#7883;ch</button><button class="button button-secondary" type="button" data-modal-cancel>Quay l&#7841;i</button><button class="button button-primary" type="button" data-modal-save disabled>L&#432;u ph&#226;n c&#244;ng</button></footer>
                    </dialog>
@endsection

@section('scripts')
<script src="{{ url('/admin/assets/js/calendar-modal.js?v=20261114-time-picker1') }}"></script>
@endsection
