@extends('admin.layouts.app')

@section('title')
Phiếu sự cố · Helpdesk trường học
@endsection

@section('content')
<section class="page-title-row">
                    <div>
                        <p class="eyebrow">THEO DÕI VÀ ĐIỀU PHỐI</p>
                        <h1>Phiếu sự cố</h1>
                        <p>Tiếp nhận và theo dõi các vấn đề về thiết bị trong trường.</p>
                    </div>
                </section>
                <section class="summary-strip">
                    <div class="summary-tile"><span>Tất cả phiếu</span><strong>{{ $incidentCounts['all'] }}</strong></div>
                    <div class="summary-tile"><span>Chờ xử lý</span><strong style="color:var(--ticket-status-new-color)">{{ $incidentCounts['pending'] }}</strong></div>
                    <div class="summary-tile"><span>Đang xử lý</span><strong style="color:var(--ticket-status-in-progress-color)">{{ $incidentCounts['in_progress'] }}</strong></div>
                    <div class="summary-tile"><span>Hoàn thành tháng này</span><strong style="color:var(--ticket-status-closed-color)">{{ $incidentCounts['completed_month'] }}</strong></div>
                </section>
                @if($incidentFocus)
                <div class="callout incident-focus-callout"><span class="incident-focus-icon"><i class="fa-solid fa-filter" aria-hidden="true"></i></span><div><strong>{{ $incidentFocusLabel }}</strong><small>Danh sách đã được lọc theo mục bạn chọn từ trang tổng quan.</small></div><a href="incidents">Xem tất cả phiếu <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
                @endif
                <section class="filter-bar incident-filter-bar"><input class="search-field" type="search" placeholder="Tìm phiếu, phòng hoặc người báo…" aria-label="Tìm phiếu sự cố"><select class="select-field" aria-label="Lọc trạng thái">
                        <option value="">Tất cả trạng thái</option>@foreach($statusLabels as $status => $label)<option value="{{ $status }}">{{ $label }}</option>@endforeach
                    </select><select class="select-field" id="incident-category-filter" aria-label="L&#7885;c danh m&#7909;c l&#7895;i">
                        <option value="">T&#7845;t c&#7843; danh m&#7909;c</option>@foreach($incidentEquipmentLabels as $label)<option value="{{ $label }}">{{ $label }}</option>@endforeach
                    </select><select class="select-field" aria-label="Lọc khu vực">
                        <option value="">Tất cả khu vực</option>@foreach($incidentBuildings as $buildingOption)<option value="{{ $buildingOption->code }}">{{ $buildingOption->name }}</option>@endforeach
                    </select><select class="select-field" aria-label="Lọc theo loại tài khoản người báo">
                        <option value="">Tất cả tài khoản</option>
                        <option value="student">Sinh viên</option>
                        <option value="teacher">Giáo viên</option>
                        <option value="staff">Nhân viên</option>
                        <option value="admin">Quản lý</option>
                    </select><select class="select-field" aria-label="Lọc theo thời gian tạo phiếu">
                        <option value="">Tất cả thời gian</option>
                        <option value="day">Hôm nay</option>
                        <option value="week">Tuần này</option>
                        <option value="month">Tháng này</option>
                        <option value="year">Năm nay</option>
                    </select></section>
                <section class="panel table-panel">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>MÃ PHIẾU</th>
                                    <th>NỘI DUNG SỰ CỐ</th>
                                    <th>ĐỊA ĐIỂM</th>
                                    <th>NGƯỜI BÁO</th>
                                    <th>ƯU TIÊN</th>
                                    <th>TRẠNG THÁI</th>
                                    <th>THỜI GIAN</th>
                                    <th>THAO TÁC</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tickets as $ticket)
                                @php($currentSchedule = $ticket->schedules->sortByDesc('starts_at')->first())
                                <tr data-incident-row data-ticket-status="{{ $ticket->status }}" data-ticket-category="{{ $ticket->equipment_label }}" data-ticket-building="{{ $ticket->room?->building?->code }}" data-ticket-building-name="{{ $ticket->room?->building?->name ?? $ticket->building_label }}" data-ticket-phone="{{ $ticket->reporter_phone }}" data-ticket-search="{{ $ticket->ticket_number }} {{ $ticket->title }} {{ $ticket->description }} {{ $ticket->equipment_label }} {{ $ticket->room?->name ?? $ticket->room_label }} {{ $ticket->room?->building?->name ?? $ticket->building_label }} {{ $ticket->reporter_name }} {{ $ticket->reporter_email }} {{ $ticket->reporter_phone }} {{ $ticket->reporter?->role }}">
                                    <td><a class="ticket-code" href="ticket-detail?ticket={{ $ticket->id }}">{{ $ticket->ticket_number }}</a></td>
                                    <td><span class="issue-title">{{ $ticket->title }}</span><span class="issue-sub">@include('shared.ticket-category-badge', ['category' => $ticket->category, 'label' => $ticket->equipment_label])</span></td>
                                    <td>{{ $ticket->building_label }} · {{ $ticket->room_label }}</td>
                                    <td>{{ $ticket->reporter_name }}@if($ticket->reporter?->role === 'teacher')<span class="issue-sub teacher-report-marker"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Giáo viên · ưu tiên cao</span>@endif</td>
                                    <td><span class="status-badge priority-badge priority-{{ $ticket->priority }}">{{ $ticket->priority_label }}</span></td>
                                    <td>
                                        <span class="status-badge ticket-status ticket-status-{{ str_replace('_', '-', $ticket->status) }}">{{ $ticket->status === 'new' ? 'Chờ tiếp nhận' : ($statusLabels[$ticket->status] ?? $ticket->status) }}</span>
                                        @if(in_array($ticket->status, ['assigned', 'in_progress', 'resolved'], true))
                                            <span class="issue-sub incident-assignment-meta">{{ $ticket->assignee?->name ?? 'Chưa phân công nhân viên' }}
                                                @if($currentSchedule)
                                                    · Đã xếp lịch {{ $currentSchedule->starts_at->format('d/m/Y H:i') }}
                                                @elseif($ticket->status === 'assigned')
                                                    · Chưa xếp lịch
                                                @endif
                                            </span>
                                        @elseif($ticket->status === 'reopened')
                                            <span class="issue-sub incident-assignment-meta">C&#7847;n ph&#226;n c&#244;ng l&#7841;i</span>
                                        @endif
                                    </td>
                                    <td>{{ $ticket->created_at->format('d/m/Y H:i') }}</td>
                                    <td><a class="button button-secondary incident-detail-button" href="ticket-detail?ticket={{ $ticket->id }}">Xem chi tiết <span aria-hidden="true">→</span></a></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8">Chưa có phiếu sự cố.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination"><span>Hiển thị {{ $tickets->count() }} phiếu sự cố</span></div>
                </section>
@endsection

@section('scripts')
<script src="../assets/js/incidents.js?v=20261110-pagination1"></script>
@endsection
