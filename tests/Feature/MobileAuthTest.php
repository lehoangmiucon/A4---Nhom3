<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class MobileAuthTest extends TestCase {
    public function test_student_can_change_code_but_cannot_take_another_code(): void {
        $user=User::create(['name'=>'Student','email'=>'code@ut.edu.vn','password'=>'Password123','role'=>'student','email_verified_at'=>now()]);
        $other=User::create(['name'=>'Other','email'=>'other-code@ut.edu.vn','password'=>'Password123','role'=>'student']);
        DB::table('users')->where('id',$other->id)->update(['member_code'=>'TAKEN']);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->patchJson('/api/account/profile',['name'=>'Student','member_code'=>'TAKEN'])->assertUnprocessable()->assertJsonValidationErrors('member_code');
        $this->patchJson('/api/account/profile',['name'=>'Student','member_code'=>'NEW123'])->assertOk()->assertJsonPath('data.member_code','NEW123');
        $this->postJson('/api/auth/login',['email'=>'NEW123','password'=>'Password123','device_name'=>'test'])->assertOk();
        $this->patchJson('/api/account/profile',['name'=>'Student','member_code'=>'NEW123'])->assertOk();
        $this->patchJson('/api/account/profile',['name'=>'Student','member_code'=>''])->assertOk();
        DB::table('users')->where('id',$user->id)->update(['role'=>'teacher','member_type'=>'lecturer']);
        $user->refresh();
        $this->patchJson('/api/account/profile',['name'=>'Lecturer','member_code'=>'OTHER'])->assertUnprocessable();
    }
    public function test_profile_only_changes_name_and_optional_phone(): void {
        $user=User::create(['name'=>'Old','email'=>'profile@ut.edu.vn','password'=>'Password123','role'=>'student','email_verified_at'=>now()]);
        DB::table('users')->where('id',$user->id)->update(['member_type'=>'student','member_code'=>'SV123']);
        $user->refresh();
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->patchJson('/api/account/profile',['name'=>'New','phone'=>null,'email'=>'evil@ut.edu.vn','role'=>'admin','member_code'=>'EVIL'])->assertOk()->assertJsonPath('data.name','New');
        $this->getJson('/api/account/profile')->assertOk()->assertJsonPath('data.member_code','EVIL')->assertJsonPath('data.email','profile@ut.edu.vn')->assertJsonPath('data.role','student');
        $this->patchJson('/api/account/profile',['name'=>'','phone'=>'bad'])->assertUnprocessable();
    }
    public function test_account_password_code_is_bound_to_own_verified_school_email(): void {
        $user=User::create(['name'=>'Member','email'=>'own@ut.edu.vn','password'=>'OldPassword123','role'=>'student','email_verified_at'=>now()]);
        $user->createToken('device');
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->postJson('/api/account/password-code',['email'=>'other@ut.edu.vn'])->assertOk();
        $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('own@ut.edu.vn',$mail->getTo()[0]->getAddress());
        preg_match('/Mã xác nhận: ([A-Z]{6})/', $mail->getTextBody(), $matches);
        $this->patchJson('/api/account/password-code',['email'=>'other@ut.edu.vn','token'=>$matches[1],'password'=>'NewPassword123','password_confirmation'=>'NewPassword123'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123',$user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens',0);
        $staff=User::create(['name'=>'KTV','email'=>'staff@ut.edu.vn','password'=>'Password123','role'=>'staff','email_verified_at'=>now()]);
        \Laravel\Sanctum\Sanctum::actingAs($staff);
        $this->postJson('/api/account/password-code')->assertForbidden();
        $this->patchJson('/api/account/password-code')->assertForbidden();
    }
    public function test_shared_registration_allows_no_code_and_ignores_role_claims(): void {
        $base=['name'=>'Test','email'=>'lecturer@ut.edu.vn','phone'=>'0901234567','password'=>'Password123!','password_confirmation'=>'Password123!'];
        $this->postJson('/api/auth/register',[...$base,'member_type'=>'lecturer','role'=>'teacher'])->assertCreated();
        $this->assertDatabaseHas('users',['email'=>'lecturer@ut.edu.vn','member_type'=>'student','member_code'=>null,'role'=>'student']);
        $this->postJson('/api/auth/register',[...$base,'email'=>'other@ut.edu.vn','role'=>'admin','member_code'=>'SV123'])->assertCreated();
        $this->assertDatabaseHas('users',['email'=>'other@ut.edu.vn','role'=>'student','member_code'=>'SV123']);
    }
    public function test_short_code_expires_resend_invalidates_old_code_and_attempts_are_shared(): void {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $user=User::create(['name'=>'Short','email'=>'short@ut.edu.vn','password'=>'OldPassword123','role'=>'student']);
        $this->postJson('/forgot-password',['email'=>$user->email])->assertOk();
        $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        preg_match('/Mã xác nhận: ([A-Z]{6})/', $mail->getTextBody(), $matches);
        $code=$matches[1];
        $this->assertTrue(Hash::check($code, DB::table('password_reset_tokens')->first()->token));
        $this->assertStringContainsString('10 phút', $mail->getHtmlBody());
        $data=['email'=>$user->email,'token'=>$code,'password'=>'NewPassword123','password_confirmation'=>'NewPassword123'];
        $this->travel(11)->minutes();
        $this->postJson('/reset-password',$data)->assertUnprocessable();
        $this->postJson('/forgot-password',['email'=>$user->email])->assertOk();
        $this->postJson('/reset-password',$data)->assertUnprocessable();
        $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        preg_match('/Mã xác nhận: ([A-Z]{6})/', $mail->getTextBody(), $matches);
        for ($i=0; $i<3; $i++) $this->postJson('/api/auth/reset-password',[...$data,'token'=>'WRONG!'])->assertUnprocessable();
        $this->postJson('/reset-password',[...$data,'token'=>$matches[1]])->assertUnprocessable()->assertJsonPath('errors.token.0','Bạn đã thử quá nhiều lần. Vui lòng thử lại sau 10 phút.');
    }
    public function test_web_password_recovery_pages_and_reset(): void {
        $this->get('/auth/login')->assertOk()->assertSee('/auth/forgot-password');
        $this->get('/auth/forgot-password')->assertOk()->assertSee('data-api-endpoint="/forgot-password"', false);
        $this->get('/auth/reset-password')->assertRedirect('/auth/forgot-password');
        $user=User::create(['name'=>'Web','email'=>'web@ut.edu.vn','password'=>'OldPassword123','role'=>'student']);
        $this->postJson('/forgot-password',['email'=>$user->email])->assertOk();
        $this->get('/auth/reset-password')->assertOk()
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="email" type="hidden"', false)
            ->assertSee('Nhập mã đặt lại tại đây')
            ->assertDontSee('label for="email"', false);
        $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        preg_match('/Mã xác nhận: ([A-Z]{6})/', $mail->getTextBody(), $matches);
        $this->assertNotEmpty($matches[1]);
        $this->postJson('/reset-password',['email'=>$user->email,'token'=>$matches[1],'password'=>'NewPassword123','password_confirmation'=>'NewPassword123'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123',$user->fresh()->password));
    }
    protected function setUp(): void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','mail.default'=>'array']); DB::purge('sqlite');
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_09_140000_add_deleted_at_to_users_table.php','2026_11_02_135001_create_email_otps_table.php','2026_11_02_160000_create_personal_access_tokens_table.php','2026_11_03_110000_add_is_deleted_to_users_table.php','2026_11_05_120000_add_phone_to_users_table.php','2026_11_08_100000_add_member_identity_to_users_table.php'] as $file) (require database_path('migrations/'.$file))->up();
    }
    public function test_register_verify_login_by_code_and_logout(): void {
        $payload=['name'=>'Sinh viên','email'=>'member@ut.edu.vn','member_type'=>'student','member_code'=>'SV001','phone'=>'0901234567','password'=>'Password123!','password_confirmation'=>'Password123!','role'=>'admin'];
        $this->postJson('/api/auth/register',$payload)->assertCreated();
        $this->assertDatabaseHas('users',['member_type'=>'student','role'=>'student','member_code'=>'SV001','phone'=>'0901234567']);
        $login=['email'=>'SV001','password'=>'Password123!','device_name'=>'test'];
        $this->postJson('/api/auth/login',$login)->assertUnprocessable();
        $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        preg_match('/\b[0-9]{6}\b/',$mail->getTextBody(),$matches);
        $this->postJson('/api/auth/verify-otp',['email'=>$payload['email'],'otp'=>$matches[0]])->assertOk();
        $this->postJson('/api/auth/verify-otp',['email'=>$payload['email'],'otp'=>$matches[0]])->assertUnprocessable();
        $token=$this->postJson('/api/auth/login',$login)->assertOk()->json('access_token');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens',0);
    }
    public function test_password_reset_is_single_use_and_revokes_tokens(): void {
        $user=User::create(['name'=>'Test','email'=>'test@ut.edu.vn','password'=>'OldPassword123','email_verified_at'=>now(),'role'=>'student']);
        $user->createToken('test');
        $this->postJson('/api/auth/forgot-password',['email'=>$user->email])->assertOk();
        $mail=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        preg_match('/Mã xác nhận: ([A-Z]{6})/', $mail->getTextBody(), $matches);
        $token=$matches[1];
        $data=['email'=>$user->email,'token'=>$token,'password'=>'NewPassword123','password_confirmation'=>'NewPassword123'];
        $this->postJson('/api/auth/reset-password',[...$data,'token'=>'wrong'])->assertUnprocessable();
        $this->postJson('/api/auth/reset-password',$data)->assertOk();
        $this->assertTrue(Hash::check('NewPassword123',$user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->postJson('/api/auth/reset-password',$data)->assertUnprocessable();
    }
    public function test_forgot_response_does_not_disclose_account_existence(): void {
        User::create(['name'=>'Test','email'=>'exists@ut.edu.vn','password'=>'OldPassword123','role'=>'student']);
        $existing=$this->postJson('/api/auth/forgot-password',['email'=>'exists@ut.edu.vn'])->assertOk()->json();
        $missing=$this->postJson('/api/auth/forgot-password',['email'=>'missing@ut.edu.vn'])->assertOk()->json();
        $this->assertSame($existing,$missing);
        $this->assertCount(1,Mail::mailer()->getSymfonyTransport()->messages());
    }
}
