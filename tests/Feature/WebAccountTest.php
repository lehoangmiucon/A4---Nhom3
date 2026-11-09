<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class WebAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'mail.default' => 'array']);
        DB::purge('sqlite');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach (glob(database_path('migrations/*.php')) as $file) (require $file)->up();
    }

    private function member(array $attributes = []): User
    {
        $user = new User();
        $user->forceFill(array_merge(['name' => 'Sinh viên thử nghiệm', 'email' => 'student@ut.edu.vn',
            'password' => 'OldPassword123', 'role' => 'student', 'member_type' => 'student',
            'member_code' => '0012345678', 'email_verified_at' => now()], $attributes))->save();
        return $user;
    }

    public function test_web_login_accepts_email_or_student_code_and_enforces_account_status(): void
    {
        $user = $this->member();
        foreach (['0012345678', ' STUDENT@UT.EDU.VN '] as $identifier) {
            $this->postJson('/login', ['email' => $identifier, 'password' => 'OldPassword123'])->assertOk()->assertJsonPath('redirect', url('/customer/index'));
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
        $this->postJson('/login', ['email' => '0012345678', 'password' => 'wrong'])->assertUnprocessable();
        $user->update(['is_deleted' => true]);
        $this->postJson('/login', ['email' => '0012345678', 'password' => 'OldPassword123'])->assertUnprocessable();
        $this->assertGuest();
        $user->update(['is_deleted' => false, 'email_verified_at' => null]);
        $this->postJson('/login', ['email' => '0012345678', 'password' => 'OldPassword123'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_web_registration_is_shared_and_cannot_self_assign_teacher(): void
    {
        $data = ['name' => 'Thành viên', 'email' => 'new@ut.edu.vn', 'password' => 'Password123', 'password_confirmation' => 'Password123'];
        $this->postJson('/register', [...$data, 'member_type' => 'lecturer', 'role' => 'teacher'])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'new@ut.edu.vn', 'role' => 'student', 'member_type' => 'student', 'member_code' => null]);
        $this->postJson('/register', [...$data, 'email' => 'sv@ut.edu.vn', 'member_code' => '000123'])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'sv@ut.edu.vn', 'member_code' => '000123', 'email_verified_at' => null]);
    }

    public function test_profile_updates_code_phone_and_name_without_changing_email_or_role(): void
    {
        $user = $this->member();
        $other = $this->member(['email' => 'other@ut.edu.vn', 'member_code' => 'occupied']);
        $this->actingAs($user)->post('/account/profile', ['name' => 'Tên mới', 'member_code' => '00999', 'phone' => '0912345678', 'email' => 'wrong@ut.edu.vn', 'role' => 'admin'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Tên mới', 'member_code' => '00999', 'phone' => '0912345678', 'email' => 'student@ut.edu.vn', 'role' => 'student']);
        $this->postJson('/account/profile', ['name' => 'Tên mới', 'member_code' => 'occupied'])->assertUnprocessable()->assertJsonValidationErrors('member_code');
        $other->forceFill(['role' => 'teacher', 'member_type' => 'lecturer'])->save();
        $this->actingAs($other)->postJson('/account/profile', ['name' => 'GV', 'member_code' => 'bad'])->assertUnprocessable();
    }

    public function test_staff_and_admin_change_password_without_email_code_while_members_cannot_bypass_it(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = $this->member(['email' => $role.'@example.test', 'role' => $role, 'member_code' => $role]);
            $this->actingAs($user)->postJson('/account/password', ['current_password' => 'wrong', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertUnprocessable();
            $this->post('/account/password', ['current_password' => 'OldPassword123', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertRedirect();
            $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        }
        $student = $this->member();
        $this->actingAs($student)->postJson('/account/password', [])->assertForbidden();
        $this->postJson('/api/account/password', [])->assertForbidden();
    }

    public function test_school_password_code_is_bound_to_logged_in_member_and_signs_out_after_reset(): void
    {
        $user = $this->member(['role' => 'teacher', 'member_type' => 'lecturer', 'member_code' => null]);
        $user->createToken('test');
        $this->actingAs($user)->postJson('/account/password-code', ['email' => 'other@ut.edu.vn'])->assertOk();
        $mail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame($user->email, $mail->getTo()[0]->getAddress());
        preg_match('/Mã xác nhận: ([A-Z]{6})/', $mail->getTextBody(), $matches);
        $data = ['token' => $matches[1], 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'];
        $this->postJson('/account/password-confirm', [...$data, 'token' => 'WRONG'])->assertUnprocessable();
        $this->postJson('/account/password-confirm', $data)->assertOk()->assertJsonPath('redirect', url('/auth/login'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_account_pages_and_admin_user_search_render_member_fields(): void
    {
        $student = $this->member();
        $this->actingAs($student)->get('/customer/account')->assertOk()->assertSee('name="member_code"', false)->assertSee('data-password-code-form', false);
        $student->forceFill(['role' => 'teacher', 'member_type' => 'lecturer'])->save();
        $this->get('/customer/account')->assertOk()->assertSee('Giảng viên')->assertDontSee('name="member_code"', false);
        foreach (['staff' => '/staff/account', 'admin' => '/admin/pages/account'] as $role => $page) {
            $user = $this->member(['email' => $role.'@example.test', 'role' => $role, 'member_code' => $role]);
            $this->actingAs($user)->get($page)->assertOk()->assertSee('name="phone"', false)->assertSee('name="current_password"', false);
        }
        $this->get('/admin/pages/users')->assertOk()->assertSee('mã sinh viên hoặc email')->assertSee('0012345678');
        $team = $this->get('/admin/pages/team')->assertOk()->assertSee('team-ticket-count')->assertSee('Đổi mật khẩu của bạn');
        $this->assertSame(1, substr_count($team->getContent(), 'type="button" data-open-self-password'));
        $team->assertSee('action="'.url('/account/password').'"', false);
    }
}
