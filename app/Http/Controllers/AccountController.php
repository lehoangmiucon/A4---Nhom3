<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $oldName = $user->name;
        $user->name = trim($data['name']);
        $user->save();

        ActivityLog::record($request, 'account.profile_updated', $user, 'Người dùng cập nhật tên tài khoản.', [
            'old_name' => $oldName,
            'new_name' => $user->name,
        ]);

        return back()->with('status', 'Đã cập nhật thông tin cá nhân.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
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
