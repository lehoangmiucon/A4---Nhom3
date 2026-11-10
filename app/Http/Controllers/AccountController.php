<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        $user = $request->user();
        $oldName = $user->name;
        $changedFields = [];
        if (array_key_exists('name', $data)) {
            $user->name = trim($data['name']);
            if ($oldName !== $user->name) {
                $changedFields[] = 'name';
            }
        }
        $oldAvatarPath = $user->avatar_path;
        if ($request->hasFile('avatar')) {
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
            $changedFields[] = 'avatar';
        }
        $user->save();
        if ($oldAvatarPath && $oldAvatarPath !== $user->avatar_path) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        if ($changedFields !== []) {
            ActivityLog::record($request, 'account.profile_updated', $user, 'Người dùng cập nhật hồ sơ cá nhân.', [
                'changed_fields' => $changedFields,
                'old_name' => in_array('name', $changedFields, true) ? $oldName : null,
                'new_name' => in_array('name', $changedFields, true) ? $user->name : null,
            ]);
        }

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
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        ActivityLog::record($request, 'account.password_updated', $user, 'Người dùng đổi mật khẩu.');

        return back()->with('status', 'Đã đổi mật khẩu. Các phiên đăng nhập ứng dụng khác đã bị đăng xuất.');
    }
}
