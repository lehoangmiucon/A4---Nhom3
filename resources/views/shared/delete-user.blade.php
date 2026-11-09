@if(in_array($deleteUser->role, ['student', 'staff'], true) && !$deleteUser->is($currentUser))
<section class="panel" style="margin-top:20px;padding:22px">
    <h2>Xóa tài khoản</h2>
    <p>Tài khoản sẽ bị xóa khỏi danh sách và không thể đăng nhập. Lịch sử phiếu vẫn được giữ lại.</p>
    <button class="button button-danger" type="button" data-delete-user="{{ $deleteUser->id }}" data-delete-name="{{ $deleteUser->name }}" data-delete-return="{{ $deleteUser->role === 'staff' ? url('/admin/pages/team#technicians') : url('/admin/pages/users') }}">Xóa tài khoản</button>
    <p data-delete-feedback role="status" aria-live="polite" hidden></p>
</section>
<script src="{{ url('/admin/assets/js/delete-user.js?v=20261109') }}" defer></script>
@endif
