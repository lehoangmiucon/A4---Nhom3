<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $otpId;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Mail::fake();
        $this->user = User::factory()->unverified()->create(['email' => 'otp@ut.edu.vn', 'role' => 'student']);
        $this->key = 'verify-email:'.hash('sha256', $this->user->email);
        $this->otpId = DB::table('email_otps')->insertGetId([
            'email' => $this->user->email,
            'purpose' => 'email_verification',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now(),
        ]);
    }

    private function verify(string $code = '999999', ?string $email = null): TestResponse
    {
        return $this->postJson('/email/verify-otp', ['email' => $email ?? $this->user->email, 'otp' => $code]);
    }

    public function test_wrong_codes_persist_attempts_and_report_remaining_tries(): void
    {
        for ($attempt = 1; $attempt < 5; $attempt++) {
            $remaining = 5 - $attempt;
            $this->verify()->assertUnprocessable()->assertJsonPath('errors.otp.0', "Mã OTP không đúng. Bạn còn {$remaining} lần thử.");
            $this->assertDatabaseHas('email_otps', ['id' => $this->otpId, 'attempts' => $attempt]);
        }
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_fifth_failure_locks_even_the_correct_code(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->verify();
        }
        $response->assertUnprocessable()->assertJsonPath('errors.otp.0', 'Mã OTP đã bị khóa do nhập sai 5 lần. Bấm “Gửi lại mã OTP” để nhận mã mới.');
        $this->verify('123456')->assertUnprocessable();
        $this->assertDatabaseHas('email_otps', ['id' => $this->otpId, 'attempts' => 5, 'verified_at' => null]);
        $this->assertTrue(RateLimiter::tooManyAttempts($this->key, 5));
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_database_lock_survives_cache_expiration(): void
    {
        DB::table('email_otps')->where('id', $this->otpId)->update(['attempts' => 5, 'expires_at' => now()->addHour()]);
        RateLimiter::increment($this->key, 600, 5);
        $this->travel(601)->seconds();
        $this->verify('123456')->assertUnprocessable();
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_email_limiter_blocks_correct_code_until_ten_minutes_pass(): void
    {
        DB::table('email_otps')->where('id', $this->otpId)->update(['expires_at' => now()->addHour()]);
        RateLimiter::increment($this->key, 600, 5);
        $this->verify('123456', 'OTP@UT.EDU.VN')->assertUnprocessable();
        $this->assertNull($this->user->fresh()->email_verified_at);
        $this->travel(601)->seconds();
        $this->verify('123456')->assertOk();
    }

    public function test_success_after_four_failures_clears_limiter_and_consumes_code(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->verify()->assertUnprocessable();
        }
        $this->assertSame(4, RateLimiter::attempts($this->key));
        $this->verify('123456')->assertOk()->assertSessionHas('registration_email', $this->user->email);
        $this->assertNotNull($this->user->fresh()->email_verified_at);
        $this->assertNotNull(DB::table('email_otps')->where('id', $this->otpId)->value('verified_at'));
        $this->assertSame(0, RateLimiter::attempts($this->key));
        $this->verify('123456')->assertUnprocessable();
    }

    public function test_successful_resend_resets_limiter_and_invalidates_old_code(): void
    {
        DB::table('email_otps')->where('id', $this->otpId)->update(['attempts' => 5]);
        RateLimiter::increment($this->key, 600, 5);
        $this->postJson('/email/resend-otp', ['email' => 'OTP@UT.EDU.VN'])->assertOk();
        $this->assertSame(0, RateLimiter::attempts($this->key));
        $latest = DB::table('email_otps')->latest('id')->first();
        $this->assertSame(0, (int) $latest->attempts);
        $this->assertNotSame($this->otpId, $latest->id);
        $this->assertNotNull(DB::table('email_otps')->where('id', $this->otpId)->value('verified_at'));
        $code = null;
        Mail::assertSent(EmailVerificationOtp::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return $mail->hasTo($this->user->email);
        });
        $this->verify($code)->assertOk();
    }

    public function test_failed_mail_delivery_preserves_old_code_and_limiter(): void
    {
        RateLimiter::increment($this->key, 600, 5);
        Mail::shouldReceive('to')->once()->with($this->user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('Mail unavailable'));

        $this->postJson('/email/resend-otp', ['email' => $this->user->email])->assertUnprocessable();

        $this->assertSame(5, RateLimiter::attempts($this->key));
        $this->assertDatabaseCount('email_otps', 1);
        $this->assertDatabaseHas('email_otps', ['id' => $this->otpId, 'verified_at' => null]);
    }

    public function test_limiter_is_isolated_by_email(): void
    {
        RateLimiter::increment('verify-email:'.hash('sha256', 'other@ut.edu.vn'), 600, 5);
        $this->verify('123456')->assertOk();
        $this->assertNotNull($this->user->fresh()->email_verified_at);
    }

    public function test_resend_cooldown_does_not_clear_limiter(): void
    {
        DB::table('email_otps')->where('id', $this->otpId)->update(['created_at' => now()]);
        RateLimiter::increment($this->key, 600, 5);
        $this->postJson('/email/resend-otp', ['email' => $this->user->email])->assertUnprocessable();
        $this->assertTrue(RateLimiter::tooManyAttempts($this->key, 5));
        Mail::assertNothingSent();
    }

    public function test_expired_or_missing_code_cannot_verify_account(): void
    {
        DB::table('email_otps')->where('id', $this->otpId)->update(['expires_at' => now()]);
        $this->verify('123456')->assertUnprocessable();
        DB::table('email_otps')->where('id', $this->otpId)->delete();
        $this->verify('123456')->assertUnprocessable();
        $this->assertNull($this->user->fresh()->email_verified_at);
    }
}
