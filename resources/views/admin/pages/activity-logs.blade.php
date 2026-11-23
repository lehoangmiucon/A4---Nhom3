@extends('admin.layouts.app')
@section('title', 'Nhật ký thao tác · Helpdesk')
@section('content')
<section class="page-title-row"><div><p class="eyebrow">QUẢN TRỊ HỆ THỐNG</p><h1>Nhật ký thao tác</h1><p>Lịch sử phân công và thay đổi do người dùng thực hiện trên web hoặc ứng dụng.</p></div></section>
<section class="panel" style="overflow:auto">
    <table class="data-table" style="width:100%;border-collapse:collapse">
        <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Nội dung</th><th>Địa chỉ IP</th></tr></thead>
        <tbody>
        @forelse($activityLogs as $log)
            <tr>
                <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                <td>{{ $log->user?->name ?? 'Tài khoản đã xóa' }}<br><small>{{ $log->user?->email }}</small></td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->description }}
                    @if($log->properties)<details><summary>Chi tiết thay đổi</summary><pre style="white-space:pre-wrap">{{ json_encode($log->properties, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
                </td>
                <td>{{ $log->ip_address }}</td>
            </tr>
        @empty<tr><td colspan="5">Chưa có thao tác được ghi lại.</td></tr>@endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $activityLogs->links() }}</div>
</section>
@endsection
