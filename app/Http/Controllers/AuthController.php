<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $this->normalizeSchoolEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'regex:/^[^@\s]+@ut\.edu\.vn$/', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], ['email.regex' => 'Vui lòng dùng email trường có đuôi @ut.edu.vn.']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'role' => 'student']);
            $this->sendSchoolOtp($user->email);

            return $user;
        });

        return response()->json(['message' => 'Đăng ký thành công. Vui lòng nhập mã OTP gửi tới email.', 'email' => $user->email], 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $this->normalizeSchoolEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'regex:/^[^@\s]+@ut\.edu\.vn$/'],
            'otp' => ['required', 'digits:6'],
        ], ['email.regex' => 'Vui lòng dùng email trường có đuôi @ut.edu.vn.']);
        $otp = DB::table('email_otps')->where('email', Str::lower($data['email']))
            ->whereNull('verified_at')->latest('id')->first();

        if (!$otp || now()->greaterThan($otp->expires_at) || !Hash::check($data['otp'], $otp->code_hash)) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP không đúng hoặc đã hết hạn.']);
        }

        User::where('email', Str::lower($data['email']))->update(['email_verified_at' => now()]);
        DB::table('email_otps')->where('id', $otp->id)->update(['verified_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Email đã được xác thực. Bạn có thể đăng nhập.']);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $this->normalizeSchoolEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'regex:/^[^@\s]+@ut\.edu\.vn$/'],
        ], ['email.regex' => 'Vui lòng dùng email trường có đuôi @ut.edu.vn.']);
        $user = User::where('email', Str::lower($data['email']))->whereNull('email_verified_at')->first();
        if (!$user) return response()->json(['message' => 'Nếu tài khoản chưa xác thực, mã mới sẽ được gửi tới email.']);

        DB::transaction(function () use ($user) {
            // Serialize resends for the same account; only the newest code remains usable.
            User::whereKey($user->id)->lockForUpdate()->first();
            DB::table('email_otps')->where('email', $user->email)->whereNull('verified_at')->update(['verified_at' => now(), 'updated_at' => now()]);
            $this->sendSchoolOtp($user->email);
        });
        return response()->json(['message' => 'Đã gửi mã OTP mới nếu email thuộc tài khoản chưa xác thực.']);
    }

    private function normalizeSchoolEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }

    private function sendSchoolOtp(string $email): void
    {
        if (config('mail.default') === 'log'
            || (config('mail.default') === 'array' && !app()->runningUnitTests())) {
            throw ValidationException::withMessages([
                'email' => 'Hệ thống chưa cấu hình gửi email thật. Quản trị viên cần cấu hình SMTP trong .env để gửi OTP tới hộp thư.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        DB::table('email_otps')->insert([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $verificationUrl = url('/auth/verify-otp').'?'.http_build_query(['email' => $email]);
            Mail::send(['html' => 'emails.verification-otp', 'text' => 'emails.verification-otp-text'], [
                'code' => $code,
                'email' => $email,
                'verificationUrl' => $verificationUrl,
            ], function ($message) use ($email) {
                $message->to($email)->subject('Mã xác thực tài khoản Helpdesk');
            });
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'email' => 'Chưa gửi được OTP. Vui lòng thử lại hoặc liên hệ quản trị viên kiểm tra cấu hình email.',
            ]);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không chính xác.']);
        }
        $request->session()->regenerate();
        $user = $request->user();
        if ($user->is_deleted) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Tai khoan da bi khoa. Vui long lien he quan ly.']);
        }
        if (!$user->email_verified_at) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Vui lòng xác thực email bằng OTP trước khi đăng nhập.']);
        }

        $defaultRedirect = match ($user->role) {
            'admin' => url('/admin/index'),
            'staff' => url('/staff/index'),
            default => url('/customer/index'),
        };
        $redirect = $request->session()->pull('url.intended', $defaultRedirect);
        return response()->json(['message' => 'Đăng nhập thành công.', 'user' => $user->only('id', 'name', 'email', 'role'), 'redirect' => $redirect]);
    }

    public function tokenLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', Str::lower($data['email']))->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không chính xác.']);
        }
        if ($user->is_deleted) {
            throw ValidationException::withMessages(['email' => 'Tai khoan da bi khoa. Vui long lien he quan ly.']);
        }
        if (!$user->email_verified_at) {
            throw ValidationException::withMessages(['email' => 'Vui lòng xác thực email bằng OTP trước khi đăng nhập.']);
        }

        $user->tokens()->where('name', $data['device_name'])->delete();
        $token = $user->createToken($data['device_name'], ['*']);

        return response()->json([
            'message' => 'Đăng nhập API thành công.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    public function tokenLogout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) $token->delete();

        return response()->json(['message' => 'Đã đăng xuất thiết bị này.']);
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (! $request->expectsJson()) {
            return redirect(url('/auth/login'));
        }

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mật khẩu hiện tại không chính xác.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }
}
