<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller {
    public function register(Request $request) {
        $code = trim((string) $request->input('member_code'));
        $request->merge(['email' => Str::lower(trim((string) $request->input('email'))), 'member_type' => 'student', 'member_code' => $code === '' ? null : $code]);
        $identity = $request->validate([
            'member_type' => ['required', 'in:student'],
            'member_code' => ['nullable', 'string', 'max:80', 'unique:users,member_code'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ]);
        // Keep the existing OTP transaction and school-email validation.
        return DB::transaction(function () use ($request, $identity) {
            $response = app(AuthController::class)->register($request);
            User::where('email', $request->input('email'))->update($identity);
            return $response;
        });
    }
    public function forgot(Request $request) {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        if (in_array(config('mail.default'), ['log', 'array']) && !app()->runningUnitTests()) {
            throw ValidationException::withMessages(['email' => 'Hệ thống chưa cấu hình gửi email thật. Vui lòng liên hệ quản trị viên.']);
        }
        app(\App\Services\PasswordResetCode::class)->send(Str::lower(trim($data['email'])));
        if ($request->hasSession()) {
            $request->session()->put('password_reset_email', Str::lower(trim($data['email'])));
        }
        return response()->json(['message' => 'Nếu email thuộc tài khoản trong hệ thống, hướng dẫn đặt lại mật khẩu sẽ được gửi tới hộp thư.']);
    }
    public function reset(Request $request) {
        $data = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $data['email'] = Str::lower(trim($data['email']));
        app(\App\Services\PasswordResetCode::class)->reset($data['email'], Str::upper(trim($data['token'])), $data['password']);
        if ($request->hasSession()) $request->session()->forget('password_reset_email');
        return response()->json(['message' => 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.']);
    }
}
