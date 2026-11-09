@if(in_array($member->role, ['student', 'teacher'], true))
<button class="button button-secondary" type="button" data-confirm-teacher="{{ $member->id }}" data-teacher-name="{{ $member->name }}" data-is-teacher="{{ $member->role === 'teacher' ? '1' : '0' }}" @disabled(!$member->email_verified_at || $member->is_deleted) title="Admin xác nhận tư cách giảng viên sau khi kiểm tra thông tin">
    {{ $member->role === 'teacher' ? 'Bỏ xác nhận giảng viên' : 'Xác nhận giảng viên' }}
</button>
@endif
