@extends('admin.layouts.app')

@section('title')
Thông báo · Helpdesk trường học
@endsection

@section('content')
<section class="page-title-row">
                    <div>
                        <p class="eyebrow">CẬP NHẬT HOẠT ĐỘNG</p>
                        <h1>Thông báo</h1>
                        <p>Theo dõi phiếu mới, lịch phân công và cập nhật từ đội kỹ thuật.</p>
                    </div><a class="text-link" href="incidents">Đến danh sách phiếu →</a>
                </section>
                <section class="panel notification-panel">
                    <p class="api-feedback" id="notification-load-error" role="status" aria-live="polite" hidden></p>
                    <div class="role-list">
                        @forelse($notificationGroups as $group)
                        @php($notification = $group['latest'])
                        @php($notice = $notification->data)
                        @php($noticeTicket = $notificationTickets->get($notice['ticket_id'] ?? null))
                        @php($categoryName = $noticeTicket?->category?->name ?? $notice['category_name'] ?? '')
                        @php($categoryColor = $noticeTicket?->category?->color ?? $notice['category_color'] ?? '#1686df')
                        @php($categoryNameLower = mb_strtolower($categoryName))
                        @php($iconClass = str_contains($categoryNameLower, 'lạnh') ? 'fa-snowflake' : (str_contains($categoryNameLower, 'chiếu') ? 'fa-video' : (str_contains($categoryNameLower, 'wi-fi') || str_contains($categoryNameLower, 'mạng') ? 'fa-wifi' : (str_contains($categoryNameLower, 'điện') || str_contains($categoryNameLower, 'sáng') ? 'fa-bolt' : (str_contains($categoryNameLower, 'tính') ? 'fa-computer' : (str_contains($categoryNameLower, 'bàn') || str_contains($categoryNameLower, 'ghế') ? 'fa-chair' : ''))))))
                        @php($safeCategoryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $categoryColor) ? $categoryColor : '#1686df')
                        @php($statusIcon = match($notice['status'] ?? '') { 'new' => 'fa-ticket', 'assigned' => 'fa-user-check', 'in_progress' => 'fa-screwdriver-wrench', 'resolved' => 'fa-circle-check', 'closed' => 'fa-lock', 'reopened' => 'fa-rotate-left', default => str_contains(mb_strtolower($notice['message'] ?? ''), 'lịch') ? 'fa-calendar-days' : 'fa-bell' })
                        <a class="role-list-item notification-group {{ $group['unread_count'] ? 'is-unread' : '' }}" href="{{ $group['ticket_id'] ? 'ticket-detail?ticket='.$group['ticket_id'] : '#' }}" data-notification-read-url="{{ $group['read_url'] }}" data-notification-unread="{{ $group['unread_count'] }}">
                            <span class="role-list-icon notification-category-icon" style="--category-color: {{ $safeCategoryColor }}"><i class="fa-solid {{ $iconClass ?: $statusIcon }}" aria-hidden="true"></i></span>
                            <span class="notification-group-copy"><strong>{{ $notice['title'] ?? 'Cập nhật Helpdesk' }}</strong><small>{{ $notice['message'] ?? '' }} · {{ $notification->created_at->format('d/m/Y H:i') }}</small></span>
                            <span class="notification-group-count">{{ $group['count_label'] }}</span>
                        </a>
                        @empty
                        <p>Chưa có thông báo.</p>
                        @endforelse
                    </div>
                </section>
@endsection

@section('scripts')
<script src="../assets/js/api-client.js?v=20261106-fast-actions1"></script>
@endsection
