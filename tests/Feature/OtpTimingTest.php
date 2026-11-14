<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OtpTimingTest extends TestCase
{
    use RefreshDatabase;

    public static function flows(): array
    {
        return [
            ['/', false], ['/api/auth/', false],
            ['/', true], ['/api/auth/', true],
        ];
    }

    private function startFlow(string $prefix, bool $reset): array
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
        Mail::fake();
        $user = User::create([
            'name' => 'OTP tester', 'email' => 'otp@ut.edu.vn', 'password' => 'Current123!',
            'role' => 'student', 'email_verified_at' => $reset ? now() : null,
        ]);
        $send = $prefix.($reset ? 'password/forgot' : 'email/resend-otp');
        $verify = $prefix.($reset ? 'password/reset' : 'email/verify-otp');
        $response = $this->postJson($send, ['email' => $user->email])->assertOk();
        $this->assertSame(now()->addMinutes(3)->toIso8601String(), $response->json('otp_expires_at'));
        $this->assertSame($response->json('otp_expires_at'), $response->json('otp_resend_available_at'));
        $code = Mail::sent(EmailVerificationOtp::class)->last()->code;
        $this->assertSame(3, Mail::sent(EmailVerificationOtp::class)->last()->expiresMinutes);
        return [$user, $send, $verify, $code];
    }

    private function verifyPayload(User $user, string $code): array
    {
        return ['email' => $user->email, 'otp' => $code, 'password' => 'Changed456!', 'password_confirmation' => 'Changed456!'];
    }

    #[DataProvider('flows')]
    public function test_code_remains_valid_just_before_three_minutes(string $prefix, bool $reset): void
    {
        [$user, , $verify, $code] = $this->startFlow($prefix, $reset);
        $this->travel(179)->seconds();
        $this->postJson($verify, $this->verifyPayload($user, $code))->assertOk();
        $reset ? $this->assertTrue(Hash::check('Changed456!', $user->fresh()->password))
            : $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[DataProvider('flows')]
    public function test_failed_attempts_stay_locked_until_a_new_code_is_sent(string $prefix, bool $reset): void
    {
        [$user, $send, $verify, $code] = $this->startFlow($prefix, $reset);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson($verify, $this->verifyPayload($user, '000000'))->assertUnprocessable();
            $this->assertSame($attempt, DB::table('email_otps')->latest('id')->value('attempts'));
        }
        $this->postJson($verify, $this->verifyPayload($user, $code))->assertUnprocessable();
        $this->travel(180)->seconds();
        $this->postJson($send, ['email' => $user->email])->assertOk();
        $this->assertSame(0, DB::table('email_otps')->latest('id')->value('attempts'));
        $newCode = Mail::sent(EmailVerificationOtp::class)->last()->code;
        $this->postJson($verify, $this->verifyPayload($user, $newCode))->assertOk();
    }

    #[DataProvider('flows')]
    public function test_resend_waits_three_minutes_and_expired_codes_are_rejected_even_after_resending(string $prefix, bool $reset): void
    {
        [$user, $send, $verify, $code] = $this->startFlow($prefix, $reset);
        $first = DB::table('email_otps')->first();
        $this->travel(179)->seconds();
        $early = $this->postJson($send, ['email' => $user->email]);
        $reset ? $early->assertOk()->assertJsonPath('otp_resend_available_at', '2026-11-14T10:03:00+00:00')
            : $early->assertUnprocessable()->assertJsonValidationErrors('email');
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('email_otps', 1);
        $this->travel(1)->seconds();
        $this->postJson($verify, $this->verifyPayload($user, $code))->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Mã OTP đã hết hạn. Bấm “Gửi lại mã OTP” để nhận mã mới.');
        $this->postJson($send, ['email' => $user->email])->assertOk()
            ->assertJsonPath('otp_expires_at', '2026-11-14T10:06:00+00:00');
        Mail::assertSentCount(2);
        $newCode = Mail::sent(EmailVerificationOtp::class)->last()->code;
        // Keep the old code distinct from the new one, including random collisions.
        $expiredCode = $newCode === '123456' ? '654321' : '123456';
        DB::table('email_otps')->where('id', $first->id)->update(['code_hash' => Hash::make($expiredCode)]);
        $this->postJson($verify, $this->verifyPayload($user, $expiredCode))->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Mã OTP đã hết hạn. Bấm “Gửi lại mã OTP” để nhận mã mới.');
        $this->postJson($verify, $this->verifyPayload($user, $newCode))->assertOk();
    }
}
