<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Mail\EmailVerificationOtp;
use App\Services\PurgeExpiredUnverifiedAccounts;
use App\Support\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function register(Request $request, PurgeExpiredUnverifiedAccounts $purger): JsonResponse
    {
        $purger->handle();
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['bail', 'required', 'email', 'max:255', 'ends_with:@ut.edu.vn', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.ends_with' => 'Vui lòng sử dụng email do trường cấp có đuôi @ut.edu.vn.',
        ]);

        $data['email'] = Str::lower($data['email']);
        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create([...$data, 'role' => 'student']);
                $this->sendEmailOtp($user);
                return $user;
            });
        } catch (TransportExceptionInterface $exception) {
            throw ValidationException::withMessages(['email' => 'Chưa thể gửi email xác thực. Vui lòng thử đăng ký lại sau ít phút.']);
        }

        return response()->json(['message' => 'Đăng ký thành công. Vui lòng nhập mã OTP gửi tới email.', 'email' => $user->email] + $this->otpTiming($user->email, 'email_verification'), 201);
    }

    public function verifyOtp(Request $request, PurgeExpiredUnverifiedAccounts $purger): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'otp' => ['required', 'digits:6']]);
        $email = Str::lower($data['email']);
        $key = 'verify-email:' . hash('sha256', $email);
        $purger->handle();

        $error = DB::transaction(function () use ($data, $email, $key): ?string {
            // Serialize verification and resend requests for the same account.
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (!$user || $user->email_verified_at) {
                return 'Không tìm thấy tài khoản chưa xác thực. Nếu tài khoản đã quá hạn 24 giờ, vui lòng đăng ký lại.';
            }

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return "Bạn đã thử xác thực quá nhiều lần. Vui lòng thử lại sau {$seconds} giây hoặc bấm “Gửi lại mã OTP” để nhận mã mới.";
            }

            RateLimiter::hit($key, 600);
            $otp = DB::table('email_otps')->where('email', $email)->where('purpose', 'email_verification')
                ->whereNull('verified_at')->latest('id')->lockForUpdate()->first();

            if (!$otp) {
                $this->rejectExpiredCode($email, 'email_verification', $data['otp']);
                return 'Mã OTP không đúng hoặc đã được sử dụng.';
            }

            $lockedMessage = 'Mã OTP đã bị khóa do nhập sai 5 lần. Bấm “Gửi lại mã OTP” để nhận mã mới.';
            if ((int) $otp->attempts >= 5) {
                return $lockedMessage;
            }
            if (now()->greaterThanOrEqualTo($otp->expires_at)) {
                return 'Mã OTP đã hết hạn. Bấm “Gửi lại mã OTP” để nhận mã mới.';
            }
            if (!Hash::check($data['otp'], $otp->code_hash)) {
                $this->rejectExpiredCode($email, 'email_verification', $data['otp']);
                DB::table('email_otps')->where('id', $otp->id)->increment('attempts', 1, ['updated_at' => now()]);
                $remaining = 5 - ((int) $otp->attempts + 1);
                return $remaining > 0 ? "Mã OTP không đúng. Bạn còn {$remaining} lần thử." : $lockedMessage;
            }

            $user->forceFill(['email_verified_at' => now()])->save();
            DB::table('email_otps')->where('id', $otp->id)->update(['verified_at' => now(), 'updated_at' => now()]);
            RateLimiter::clear($key);
            return null;
        });

        // Throw after commit so a failed OTP does not roll back its attempt count.
        if ($error !== null) {
            throw ValidationException::withMessages(['otp' => $error]);
        }

        if ($request->hasSession()) {
            $request->session()->flash('registration_success', 'Email đã được xác thực. Bạn có thể đăng nhập ngay.');
            $request->session()->flash('registration_email', $email);
        }

        return response()->json(['message' => 'Đăng ký thành công! Email đã được xác thực. Bạn có thể đăng nhập ngay.']);
    }

    public function resendOtp(Request $request, PurgeExpiredUnverifiedAccounts $purger): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower($data['email']);
        $purger->handle();

        try {
            $sent = DB::transaction(function () use ($email): bool {
                $user = User::where('email', $email)->lockForUpdate()->first();
                if (!$user || $user->email_verified_at) {
                    return false;
                }

                $latestOtp = DB::table('email_otps')->where('email', $email)->where('purpose', 'email_verification')->latest('id')->first();
                if ($latestOtp) {
                    $availableAt = \Illuminate\Support\Carbon::parse($latestOtp->created_at)->addSeconds(config('auth.email_otp_resend_seconds', 180));
                    if (now()->lessThan($availableAt)) {
                        $seconds = (int) ceil(now()->diffInSeconds($availableAt));
                        throw ValidationException::withMessages(['email' => "Vui lòng chờ {$seconds} giây trước khi gửi lại mã OTP."]);
                    }
                }

                $this->sendEmailOtp($user);
                RateLimiter::clear('verify-email:' . hash('sha256', $email));
                return true;
            });
        } catch (TransportExceptionInterface $exception) {
            throw ValidationException::withMessages(['email' => 'Chưa thể gửi lại mã OTP. Vui lòng thử lại sau ít phút.']);
        }
        if (!$sent) return response()->json(['message' => 'Nếu tài khoản chưa xác thực và chưa quá 24 giờ, mã mới sẽ được gửi tới email. Nếu đã quá 24 giờ, vui lòng đăng ký lại.'] + $this->otpTiming('', 'email_verification'));
        return response()->json(['message' => 'Đã gửi mã OTP mới nếu email thuộc tài khoản chưa xác thực.'] + $this->otpTiming($email, 'email_verification'));
    }

    private function otpTiming(string $email, string $purpose): array
    {
        $otp = DB::table('email_otps')->where('email', $email)->where('purpose', $purpose)->latest('id')->first();
        $sentAt = $otp ? \Illuminate\Support\Carbon::parse($otp->created_at) : now();
        return [
            'server_time' => now()->toIso8601String(),
            'otp_expires_at' => ($otp ? \Illuminate\Support\Carbon::parse($otp->expires_at) : $sentAt->copy()->addMinutes(config('auth.email_otp_expire_minutes', 3)))->toIso8601String(),
            'otp_resend_available_at' => $sentAt->copy()->addSeconds(config('auth.email_otp_resend_seconds', 180))->toIso8601String(),
        ];
    }

    private function rejectExpiredCode(string $email, string $purpose, string $code): void
    {
        $expired = DB::table('email_otps')->where('email', $email)->where('purpose', $purpose)
            ->where('expires_at', '<=', now())->latest('id')->limit(10)->get(['code_hash']);
        foreach ($expired as $otp) {
            if (Hash::check($code, $otp->code_hash)) {
                throw ValidationException::withMessages(['otp' => 'Mã OTP đã hết hạn. Bấm “Gửi lại mã OTP” để nhận mã mới.']);
            }
        }
    }

    private function sendEmailOtp(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $expiresMinutes = (int) config('auth.email_otp_expire_minutes', 3);
        DB::table('email_otps')->where('email', $user->email)->where('purpose', 'email_verification')->whereNull('verified_at')->update(['verified_at' => now(), 'updated_at' => now()]);
        DB::table('email_otps')->insert([
            'email' => $user->email,
            'purpose' => 'email_verification',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresMinutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Mail::to($user->email)->send(new EmailVerificationOtp($user->name, $code, $expiresMinutes));
    }

    public function login(Request $request, PurgeExpiredUnverifiedAccounts $purger): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $purger->handle();
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

    public function tokenLogin(Request $request, PurgeExpiredUnverifiedAccounts $purger): JsonResponse
    {
        $purger->handle();
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

        PasswordPolicy::ensureDifferent($data['password'], $user->password);
        $user->password = $data['password'];
        $user->save();
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $user->tokens()->where('id', '!=', $token->getKey())->delete();
        } else {
            $user->tokens()->delete();
        }
        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            $sessions = DB::table('sessions')->where('user_id', $user->id);
            if ($request->hasSession()) $sessions->where('id', '!=', $request->session()->getId());
            $sessions->delete();
        }

        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }

    public function sendPasswordResetOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->whereNotNull('email_verified_at')->where('is_deleted', false)->first();
        $message = 'Nếu email thuộc tài khoản đã xác thực, mã đặt lại mật khẩu sẽ được gửi đến hộp thư.';
        if (! $user) return response()->json(['message' => $message] + $this->otpTiming('', 'password_reset'));

        $latestOtp = DB::table('email_otps')->where('email', $email)->where('purpose', 'password_reset')->latest('id')->first();
        if ($latestOtp) {
            $availableAt = \Illuminate\Support\Carbon::parse($latestOtp->created_at)
                ->addSeconds((int) config('auth.email_otp_resend_seconds', 180));
            if (now()->lessThan($availableAt)) {
                return response()->json(['message' => $message] + $this->otpTiming($email, 'password_reset'));
            }
        }

        $code = (string) random_int(100000, 999999);
        $expiresMinutes = (int) config('auth.email_otp_expire_minutes', 3);
        try {
            DB::transaction(function () use ($user, $code, $expiresMinutes) {
                DB::table('email_otps')->where('email', $user->email)->where('purpose', 'password_reset')
                    ->whereNull('verified_at')->update(['verified_at' => now(), 'updated_at' => now()]);
                DB::table('email_otps')->insert([
                    'email' => $user->email,
                    'purpose' => 'password_reset',
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes($expiresMinutes),
                    'verified_at' => null,
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Mail::to($user->email)->send(new EmailVerificationOtp($user->name, $code, $expiresMinutes, true));
            });
        } catch (TransportExceptionInterface $exception) {
            return response()->json(['message' => $message] + $this->otpTiming('', 'password_reset'));
        }

        RateLimiter::clear('password-reset:' . hash('sha256', $email));
        return response()->json(['message' => $message] + $this->otpTiming($email, 'password_reset'));
    }

    public function resetPasswordWithOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = Str::lower(trim($data['email']));
        $attemptKey = 'password-reset:' . hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            throw ValidationException::withMessages(['otp' => 'Bạn đã nhập sai mã quá nhiều lần. Hãy yêu cầu mã mới rồi thử lại.']);
        }

        $user = User::where('email', $email)->whereNotNull('email_verified_at')->where('is_deleted', false)->first();
        $otp = DB::table('email_otps')->where('email', $email)->where('purpose', 'password_reset')
            ->whereNull('verified_at')->latest('id')->first();
        if ($otp && now()->greaterThanOrEqualTo($otp->expires_at)) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP đã hết hạn. Bấm “Gửi lại mã OTP” để nhận mã mới.']);
        }
        if (! $user || ! $otp) {
            $this->rejectExpiredCode($email, 'password_reset', $data['otp']);
            throw ValidationException::withMessages(['otp' => 'Mã OTP không hợp lệ hoặc đã hết hạn. Hãy yêu cầu mã mới.']);
        }
        if ((int) $otp->attempts >= 5 || ! Hash::check($data['otp'], $otp->code_hash)) {
            $this->rejectExpiredCode($email, 'password_reset', $data['otp']);
            DB::table('email_otps')->where('id', $otp->id)->increment('attempts');
            RateLimiter::hit($attemptKey, 600);
            throw ValidationException::withMessages(['otp' => 'Mã OTP không đúng hoặc đã hết lượt thử.']);
        }

        DB::transaction(function () use ($user, $otp, $data, $email) {
            $lockedOtp = DB::table('email_otps')->where('id', $otp->id)->lockForUpdate()->first();
            if ($lockedOtp && now()->greaterThanOrEqualTo($lockedOtp->expires_at)) {
                throw ValidationException::withMessages(['otp' => 'Mã OTP đã hết hạn. Bấm “Gửi lại mã OTP” để nhận mã mới.']);
            }
            if (
                ! $lockedOtp || $lockedOtp->verified_at || (int) $lockedOtp->attempts >= 5
                || now()->greaterThanOrEqualTo($lockedOtp->expires_at)
                || ! Hash::check($data['otp'], $lockedOtp->code_hash)
            ) {
                throw ValidationException::withMessages(['otp' => 'Mã OTP không hợp lệ hoặc đã hết hạn. Hãy yêu cầu mã mới.']);
            }
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            PasswordPolicy::ensureDifferent($data['password'], $lockedUser->password);
            $lockedUser->password = $data['password'];
            $lockedUser->save();
            DB::table('email_otps')->where('id', $lockedOtp->id)->update(['verified_at' => now(), 'updated_at' => now()]);
            DB::table('email_otps')->where('email', $email)->where('purpose', 'password_reset')->whereNull('verified_at')
                ->update(['verified_at' => now(), 'updated_at' => now()]);
            $lockedUser->tokens()->delete();
            if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            }
        });

        RateLimiter::clear($attemptKey);
        ActivityLog::record($request, 'account.password_reset', $user, 'Người dùng đặt lại mật khẩu bằng OTP.');
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Đặt lại mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.']);
    }
}
