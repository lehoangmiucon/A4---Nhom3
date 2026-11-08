<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function filterTeam(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(['admin', 'staff'])],
            'status' => ['nullable', Rule::in(['active', 'unverified', 'locked'])],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $query = User::query()->whereIn('role', ['admin', 'staff']);
        if (!empty($filters['role'])) $query->where('role', $filters['role']);
        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'locked' => $query->where('is_deleted', true),
                'unverified' => $query->where('is_deleted', false)->whereNull('email_verified_at'),
                'active' => $query->where('is_deleted', false)->whereNotNull('email_verified_at'),
            };
        }
        if (!empty($filters['category'])) {
            $query->whereHas('categories', fn ($categories) => $categories->whereKey($filters['category']));
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(fn ($users) => $users
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->when($digits !== '', fn ($users) => $users->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?", ["%{$digits}%"])));
        }
        $ids = $query->orderBy('name')->pluck('id');
        return response()->json(['data' => ['ids' => $ids, 'count' => $ids->count()]]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'role' => ['nullable', Rule::in(['student', 'staff', 'admin'])], 'member_type' => ['nullable', Rule::in(['student', 'lecturer'])], 'status' => ['nullable', Rule::in(['active', 'locked', 'unverified'])]]);
        $users = User::query()->select('id', 'name', 'email', 'phone', 'role', 'member_type', 'member_code', 'is_deleted', 'email_verified_at', 'created_at')
            ->when($request->filled('role'), fn($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('member_type'), fn($query) => $query->where('role', 'student')->where(fn($q) => $request->input('member_type') === 'student' ? $q->where('member_type', 'student')->orWhereNull('member_type') : $q->where('member_type', 'lecturer')))
            ->when($request->input('status') === 'locked', fn($q) => $q->where('is_deleted', true))
            ->when($request->input('status') === 'active', fn($q) => $q->where('is_deleted', false)->whereNotNull('email_verified_at'))
            ->when($request->input('status') === 'unverified', fn($q) => $q->where('is_deleted', false)->whereNull('email_verified_at'))
            ->when($request->filled('search'), fn($query) => $query->where(fn($q) => $q
                ->where('name', 'like', '%' . $request->string('search') . '%')
                ->orWhere('email', 'like', '%' . $request->string('search') . '%')
                ->orWhere('member_code', 'like', '%' . $request->string('search') . '%')
                ->orWhere('phone', 'like', '%' . $request->string('search') . '%')))
            ->orderBy('name')->paginate(25);
        return response()->json($users);
    }

    public function staff(): JsonResponse
    {
        return response()->json(['data' => User::with('categories:id,name,color')->where('role', 'staff')->where('is_deleted', false)->orderBy('name')->get(['id', 'name', 'email', 'phone'])]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('categories:id,name,color');
        return response()->json(['data' => $user->only('id', 'name', 'email', 'phone', 'role', 'member_type', 'member_code', 'email_verified_at', 'is_deleted', 'created_at', 'categories')]);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $user->categories()->sync($data['category_ids']);
        ActivityLog::record($request, 'user.created', $user, 'Created a staff account.', ['role' => $user->role]);
        return response()->json(['message' => 'Đã tạo tài khoản nhân viên.', 'data' => $user->only('id', 'name', 'email', 'role')], 201);
    }

    public function createTeamAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'category_ids' => ['required_if:role,staff', 'array'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
        ]);
        if ($data['role'] === 'staff' && empty($data['category_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'category_ids' => 'Chọn ít nhất một danh mục sự cố cho nhân viên.',
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'email_verified_at' => now(),
        ]);
        if ($user->role === 'staff') {
            $user->categories()->sync($data['category_ids']);
        }
        ActivityLog::record($request, 'user.created', $user, 'Created a team account.', ['role' => $user->role]);

        return response()->json([
            'message' => $data['role'] === 'admin' ? 'Đã tạo tài khoản quản lý.' : 'Đã tạo tài khoản nhân viên.',
            'data' => $user->only('id', 'name', 'email', 'role'),
        ], 201);
    }

    public function updateStaff(Request $request, User $user): JsonResponse
    {
        abort_unless($user->role === 'staff', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->categories()->sync($data['category_ids']);

        if (! empty($data['password'])) $user->tokens()->delete();
        ActivityLog::record($request, 'staff.updated', $user, 'Updated staff account and issue categories.', [
            'category_ids' => array_map('intval', $data['category_ids']),
            'password_changed' => ! empty($data['password']),
        ]);

        return response()->json([
            'message' => 'Đã cập nhật thông tin, mật khẩu và danh mục xử lý của nhân viên.',
            'data' => $user->fresh()->load('categories:id,name,color')->only('id', 'name', 'email', 'role', 'is_deleted', 'categories'),
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        abort_if($user->is($request->user()), 422, 'Không thể tự đổi vai trò tài khoản đang đăng nhập.');
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'staff', 'student'])]]);
        $previousRole = $user->role;
        $user->update(['role' => $data['role']]);
        ActivityLog::record($request, 'user.role_updated', $user, 'Changed a user role.', [
            'from_role' => $previousRole,
            'to_role' => $user->role,
        ]);
        return response()->json(['message' => 'Đã cập nhật vai trò.', 'data' => $user->only('id', 'name', 'email', 'role')]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        abort_if($user->role === 'super_admin', 403, 'Tai khoan quan ly toi cao khong the bi khoa.');
        abort_if($user->is($request->user()), 422, 'Khong the tu khoa tai khoan dang dang nhap.');

        $data = $request->validate(['is_deleted' => ['required', 'boolean']]);
        $wasLocked = (bool) $user->is_deleted;
        $user->update(['is_deleted' => (bool) $data['is_deleted']]);

        if (!$wasLocked && $user->is_deleted) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }

        ActivityLog::record(
            $request,
            $user->is_deleted ? 'user.locked' : 'user.unlocked',
            $user,
            $user->is_deleted ? 'Locked a user account.' : 'Unlocked a user account.',
            ['is_deleted' => (int) $user->is_deleted],
        );

        return response()->json([
            'message' => $user->is_deleted ? 'Da khoa tai khoan.' : 'Da mo khoa tai khoan.',
            'data' => $user->only('id', 'name', 'email', 'role', 'is_deleted'),
        ]);
    }
}
