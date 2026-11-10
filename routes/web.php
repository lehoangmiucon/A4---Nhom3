<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\UiController;
use App\Models\Room;
use App\Models\Building;
use App\Models\Category;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/email/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/email/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:3,1');
Route::post('/password/forgot', [AuthController::class, 'sendPasswordResetOtp'])->middleware('throttle:5,1');
Route::post('/password/reset', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/login', fn () => redirect(url('/auth/login')))->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});

Route::get('/', fn () => response()->view('index'));
Route::get('/index.html', fn () => redirect(url('/')));
Route::get('/auth/{page}', function (string $page) {
    abort_unless(in_array($page, ['login', 'register', 'verify-otp', 'forgot-password'], true), 404);
    return response()->view("auth.{$page}");
})->where('page', 'login|register|verify-otp|forgot-password');
Route::get('/auth', fn () => redirect(url('/auth/login')));
Route::get('/auth/{page}.html', fn (string $page) => redirect(url('/auth/'.$page), 301))
    ->where('page', 'login|register|verify-otp|forgot-password');
Route::get('/r/{code}', function (Request $request, string $code) {
    $room = Room::with('building')->where('qr_code', $code)->orWhere('code', $code)->firstOrFail();
    $portalPath = in_array($request->user()?->role, ['student', 'teacher'], true) || !$request->user() ? '/customer/portal' : '/admin/portal';
    $target = $portalPath.'?'.http_build_query([
        'room_code' => $room->qr_code ?: $room->code,
        'room_name' => $room->name,
        'building_code' => $room->building?->code,
        'building_name' => $room->building?->name,
    ]);
    if (!Auth::check()) {
        $request->session()->put('url.intended', url($target));
        return redirect(url('/auth/register'));
    }
    abort_unless(in_array($request->user()->role, ['admin', 'staff', 'student', 'teacher'], true), 403);
    abort_if($request->user()->is_deleted, 403, 'Tai khoan da bi khoa.');
    return redirect(url($target));
});
Route::get('/report/{code}', function (Request $request, string $code) {
    $room = Room::with('building')->where('qr_code', $code)->orWhere('code', $code)->firstOrFail();
    $portalPath = in_array($request->user()?->role, ['student', 'teacher'], true) || !$request->user() ? '/customer/portal' : '/admin/portal';
    $target = $portalPath.'?'.http_build_query([
        'room_code' => $room->qr_code ?: $room->code,
        'room_name' => $room->name,
        'building_code' => $room->building?->code,
        'building_name' => $room->building?->name,
    ]);
    if (!Auth::check()) {
        $request->session()->put('url.intended', url($target));
        return redirect(url('/auth/register'));
    }
    abort_unless(in_array($request->user()->role, ['admin', 'staff', 'student', 'teacher'], true), 403);
    abort_if($request->user()->is_deleted, 403, 'Tai khoan da bi khoa.');
    return redirect(url($target));
});
Route::get('/admin/{path?}', function (Request $request, ?string $path = null) {
    if (!$path) return redirect(url('/admin/index'), 301);
    $path = trim($path ?? '', '/');
    if (in_array($path, ['portal', 'portal/index.html', 'portal/index'], true)) {
        if (!$request->user()) return redirect(url('/auth/login'));
        abort_if($request->user()->is_deleted, 403, 'Tai khoan da bi khoa.');
        abort_unless(in_array($request->user()->role, ['admin', 'staff', 'student', 'teacher'], true), 403);
        if (in_array($request->user()->role, ['student', 'teacher'], true)) return redirect(url('/customer/portal').($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        if ($request->user()->role === 'staff') return redirect(url('/staff/index'));
        return redirect(url('/admin/pages/incidents'));
    }
    return app(UiController::class)->page($request, 'admin', $path);
})->where('path', '.*');
Route::get('/staff/{path?}', function (Request $request, ?string $path = null) {
    if (!$path) return redirect(url('/staff/index'), 301);
    return app(UiController::class)->page($request, 'staff', $path);
})->where('path', '.*');
Route::get('/customer/{path?}', function (Request $request, ?string $path = null) {
    if (!$path) return redirect(url('/customer/index'), 301);
    $path = trim($path, '/');
    if (in_array($path, ['portal/index.html', 'portal/index'], true)) {
        return redirect(url('/customer/portal').($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
    }
    if ($path === 'portal') {
        if (!$request->user()) {
            $request->session()->put('url.intended', url('/customer/portal').($request->getQueryString() ? '?'.$request->getQueryString() : ''));
            return redirect(url('/auth/login'));
        }
        abort_if($request->user()->is_deleted, 403, 'Tai khoan da bi khoa.');
        if (! in_array($request->user()->role, ['student', 'teacher'], true)) {
            return redirect(url($request->user()->role === 'staff' ? '/staff/index' : '/admin/index'));
        }
        return response()->view('customer.portal', [
            'buildings' => Building::with('rooms')->orderBy('code')->get(),
            'categories' => Category::orderBy('name')->get(),
            'currentUser' => $request->user(),
            'pagePath' => 'portal',
            'unreadNotificationCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }
    return app(UiController::class)->page($request, 'customer', $path);
})->where('path', '.*');
