<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    private function profileData(Request $request): array
    {
        return $request->user()->only('id', 'name', 'email', 'role', 'member_type', 'member_code', 'phone', 'email_verified_at');
    }

    public function profile(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['data' => $this->profileData($request)]);
    }

    public function saveMobileProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $student = $request->user()->role === 'student';
        if ($request->has('member_code')) $request->merge(['member_code' => trim((string) $request->input('member_code')) ?: null]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'member_code' => $student ? ['sometimes', 'nullable', 'string', 'max:80', \Illuminate\Validation\Rule::unique('users', 'member_code')->ignore($request->user()->id)] : ['prohibited'],
        ], ['member_code.required' => 'Vui lòng nhập mã sinh viên.', 'member_code.unique' => 'Mã sinh viên đã được sử dụng bởi tài khoản khác.']);
        $changes = ['name' => trim($data['name']), 'phone' => $data['phone'] ?? null];
        if ($student && array_key_exists('member_code', $data)) $changes['member_code'] = $data['member_code'];
        $request->user()->forceFill($changes)->save();
        return response()->json(['message' => 'Đã lưu hồ sơ.', 'data' => $this->profileData($request)]);
    }

    private function requireSchoolMember(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['student', 'teacher'], true) && $request->user()->email_verified_at
            && str_ends_with(strtolower($request->user()->email), '@ut.edu.vn'), 403, 'Chức năng này chỉ dành cho sinh viên và giảng viên có email trường đã xác minh.');
    }

    public function sendPasswordCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->requireSchoolMember($request);
        $request->merge(['email' => $request->user()->email]);
        return app(MobileAuthController::class)->forgot($request);
    }

    public function resetWithCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->requireSchoolMember($request);
        $request->merge(['email' => $request->user()->email]);
        return app(MobileAuthController::class)->reset($request);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldName = $user->name;
        $this->saveMobileProfile($request);

        ActivityLog::record($request, 'account.profile_updated', $user, 'Người dùng cập nhật hồ sơ cá nhân.', [
            'old_name' => $oldName,
            'new_name' => $user->name,
        ]);

        return back()->with('status', 'Đã cập nhật thông tin cá nhân.');
    }

    public function resetWebWithCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->resetWithCode($request);
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.', 'redirect' => url('/auth/login')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'staff'], true), 403, 'Vui lòng xác thực mã qua email trường để đổi mật khẩu.');
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mật khẩu hiện tại không chính xác.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();
        $user->tokens()->delete();

        ActivityLog::record($request, 'account.password_updated', $user, 'Người dùng đổi mật khẩu.');

        return back()->with('status', 'Đã đổi mật khẩu. Các phiên đăng nhập ứng dụng khác đã bị đăng xuất.');
    }
}
