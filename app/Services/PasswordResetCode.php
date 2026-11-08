<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetCode
{
    public function send(string $email): void
    {
        $key = 'password-code-send:'.hash('sha256', $email);
        // Respond identically for unknown accounts and cooldowns.
        if (RateLimiter::tooManyAttempts($key, 1)) return;
        RateLimiter::hit($key, 60);
        $user = User::where('email', $email)->first();
        if (!$user) return;
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= chr(random_int(65, 90));
        DB::transaction(function () use ($email, $user, $code) {
            // Lock the account to serialize concurrent code requests.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], ['token' => Hash::make($code), 'created_at' => now()]);
            Mail::send(['html' => 'emails.password-reset', 'text' => 'emails.password-reset-text'], ['code' => $code, 'name' => $user->name], function ($message) use ($email) {
                $message->to($email)->subject('Mã đặt lại mật khẩu · Helpdesk UTH');
            });
        });
    }

    public function reset(string $email, string $code, string $password): void
    {
        // Shared by web and mobile, and not reset by requesting a new code.
        $key = 'password-code-attempt:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['token' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau 10 phút.']);
        }
        RateLimiter::hit($key, 600);
        DB::transaction(function () use ($email, $code, $password) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            $record = DB::table('password_reset_tokens')->where('email', $email)->lockForUpdate()->first();
            if (!$user || !$record || !$record->created_at || \Illuminate\Support\Carbon::parse($record->created_at)->lte(now()->subMinutes(10)) || !Hash::check($code, $record->token)) {
                throw ValidationException::withMessages(['token' => 'Email hoặc mã đặt lại không đúng, đã dùng hoặc đã hết hạn.']);
            }
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });
    }
}
