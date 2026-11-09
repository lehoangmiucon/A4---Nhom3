<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;

class SchoolOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise email delivery independently of the existing per-IP route throttles.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        // Isolated in-memory database: never migrate or modify the local MySQL database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'mail.default' => 'array']);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table.php', '2026_11_09_140000_add_deleted_at_to_users_table.php',
            '2026_11_02_135001_create_email_otps_table.php',
            '2026_11_03_110000_add_is_deleted_to_users_table.php',
            '2026_11_05_120000_add_phone_to_users_table.php',
            '2026_11_08_100000_add_member_identity_to_users_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    private function registerStudent(string $email = 'trong@ut.edu.vn')
    {
        return $this->postJson('/register', [
            'name' => 'Trọng', 'email' => $email, 'member_type' => 'student', 'member_code' => 'SV0001',
            'password' => 'Example-password-123', 'password_confirmation' => 'Example-password-123',
        ]);
    }

    private function latestCode(): string
    {
        $message = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('trong@ut.edu.vn', $message->getTo()[0]->getAddress());
        preg_match('/\b[0-9]{6}\b/', $message->getTextBody(), $matches);

        return $matches[0];
    }

    public function test_registration_sends_to_normalized_school_email_and_code_verifies_once(): void
    {
        $this->registerStudent(' TRONG@UT.EDU.VN ')->assertCreated()->assertJsonPath('email', 'trong@ut.edu.vn');
        $code = $this->latestCode();
        $otp = DB::table('email_otps')->first();
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertNull(User::first()->email_verified_at);
        $this->postJson('/email/verify-otp', ['email' => 'TRONG@UT.EDU.VN', 'otp' => $code])->assertOk();
        $this->assertNotNull(User::first()->email_verified_at);
        $this->postJson('/email/verify-otp', ['email' => 'trong@ut.edu.vn', 'otp' => $code])->assertUnprocessable();
    }

    public static function invalidEmails(): array
    {
        return [['trong@gmail.com'], ['trong@sub.ut.edu.vn'], ['trong@ut.edu.vn.evil.com'], ['trong@other.edu.vn']];
    }

    #[DataProvider('invalidEmails')]
    public function test_other_domains_are_rejected_on_all_otp_endpoints(string $email): void
    {
        $this->registerStudent($email)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/email/resend-otp', ['email' => $email])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/email/verify-otp', ['email' => $email, 'otp' => '123456'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_resend_invalidates_old_otp_and_sends_a_new_one(): void
    {
        $this->registerStudent()->assertCreated();
        $oldId = DB::table('email_otps')->first()->id;
        $this->postJson('/email/resend-otp', ['email' => 'TRONG@UT.EDU.VN'])->assertOk();
        $this->assertNotNull(DB::table('email_otps')->find($oldId)->verified_at);
        $this->assertCount(2, Mail::mailer()->getSymfonyTransport()->messages());
        $this->postJson('/email/verify-otp', ['email' => 'trong@ut.edu.vn', 'otp' => $this->latestCode()])->assertOk();
    }

    public function test_expired_or_wrong_otp_does_not_verify_account(): void
    {
        $this->registerStudent()->assertCreated();
        $code = $this->latestCode();
        $this->postJson('/email/verify-otp', ['email' => 'trong@ut.edu.vn', 'otp' => '000000'])->assertUnprocessable();
        $this->travel(11)->minutes();
        $this->postJson('/email/verify-otp', ['email' => 'trong@ut.edu.vn', 'otp' => $code])->assertUnprocessable();
        $this->assertNull(User::first()->email_verified_at);
    }

    public function test_failed_delivery_rolls_back_registration_so_email_can_retry(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
        $this->registerStudent()->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_otps', 0);
    }

    public function test_log_mailer_reports_missing_real_email_configuration(): void
    {
        config(['mail.default' => 'log']);
        $this->registerStudent()->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_otps', 0);
    }

    public function test_failed_resend_preserves_previous_otp(): void
    {
        $this->registerStudent()->assertCreated();
        $code = $this->latestCode();
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
        $this->postJson('/email/resend-otp', ['email' => 'trong@ut.edu.vn'])->assertUnprocessable();
        $this->assertDatabaseCount('email_otps', 1);
        $this->assertNull(DB::table('email_otps')->first()->verified_at);
        $this->postJson('/email/verify-otp', ['email' => 'trong@ut.edu.vn', 'otp' => $code])->assertOk();
    }

    public function test_resend_does_not_send_to_verified_or_unknown_accounts(): void
    {
        $this->registerStudent()->assertCreated();
        $this->postJson('/email/verify-otp', ['email' => 'trong@ut.edu.vn', 'otp' => $this->latestCode()])->assertOk();
        $this->postJson('/email/resend-otp', ['email' => 'trong@ut.edu.vn'])->assertOk();
        $this->postJson('/email/resend-otp', ['email' => 'unknown@ut.edu.vn'])->assertOk();
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_email_contains_autofill_link_and_no_fallback_paragraph(): void
    {
        $this->registerStudent()->assertCreated();
        $code = $this->latestCode();
        $message = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $html = $message->getHtmlBody();
        $this->assertStringContainsString($code, $html);
        $this->assertStringContainsString('10 phút', $html);
        $this->assertStringContainsString('Mở trang nhập OTP', $html);
        $this->assertStringContainsString('/auth/verify-otp?email=trong%40ut.edu.vn', $html);
        $this->assertStringContainsString('#otp='.$code, $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('Nếu nút không hoạt động', $html);
        $this->assertStringContainsString($code, $message->getTextBody());
        $this->assertStringContainsString('/auth/verify-otp?email=trong%40ut.edu.vn', $message->getTextBody());
        $this->assertStringNotContainsString('<script', $html);
    }
}
