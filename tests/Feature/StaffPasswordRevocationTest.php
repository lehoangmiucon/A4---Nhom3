<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StaffPasswordRevocationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    private Category $category;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'staff', 'password' => 'Current123!']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Category::create(['name' => 'Network', 'color' => '#008689']);
        $this->staff->categories()->sync([$this->category->id]);
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;
    }

    private function resetStaff(array $changes = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['session']->flush();

        return $this->withToken($this->adminToken)->putJson('/api/admin/staff/'.$this->staff->id, array_replace([
            'name' => 'Updated staff', 'category_ids' => [$this->category->id], 'password' => 'Changed456!',
        ], $changes));
    }

    public function test_all_old_tokens_are_rejected_and_only_new_password_can_log_in(): void
    {
        $tokens = [$this->staff->createToken('phone')->plainTextToken, $this->staff->createToken('tablet')->plainTextToken];
        $other = User::factory()->create(['role' => 'staff']);
        $otherToken = $other->createToken('other')->plainTextToken;
        $oldRemember = $this->staff->remember_token;
        $this->resetStaff()->assertOk()->assertJsonPath('message', 'Đã đổi mật khẩu và đăng xuất nhân viên khỏi các thiết bị.');
        $this->assertSame(0, $this->staff->tokens()->count());
        $this->assertNotSame($oldRemember, $this->staff->fresh()->remember_token);
        foreach ($tokens as $token) {
            Auth::forgetGuards();
            $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        }
        foreach ([$otherToken, $this->adminToken] as $token) {
            Auth::forgetGuards();
            $this->withToken($token)->getJson('/api/me')->assertOk();
        }
        $credentials = ['email' => $this->staff->email, 'device_name' => 'phone', 'password' => 'Current123!'];
        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable();
        $login = $this->postJson('/api/auth/login', array_replace($credentials, ['password' => 'Changed456!']))->assertOk();
        Auth::forgetGuards();
        $this->withToken($login->json('access_token'))->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $this->staff->id);
    }

    public function test_profile_edits_and_rejected_passwords_preserve_credentials(): void
    {
        $token = $this->staff->createToken('phone');
        $oldHash = $this->staff->password;
        $oldRemember = $this->staff->remember_token;
        foreach ([null, ''] as $password) {
            $this->resetStaff(['password' => $password])->assertOk();
            $this->assertSame($oldHash, $this->staff->fresh()->password);
            $this->assertSame($oldRemember, $this->staff->fresh()->remember_token);
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        }
        foreach (['short', 'Current123!'] as $password) {
            $this->resetStaff(['password' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertSame($oldHash, $this->staff->fresh()->password);
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        }
    }

    public function test_database_sessions_are_deleted_only_for_the_target_staff(): void
    {
        config(['session.driver' => 'database']);
        foreach ([$this->staff, $this->admin] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
        $this->resetStaff()->assertOk();
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->staff->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $this->admin->id]);
    }

    private function staffSession(): array
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->flushHeaders();
        $this->app['session']->flush();
        $this->postJson('/login', ['email' => $this->staff->email, 'password' => 'Current123!'])->assertOk();

        return $this->app['session']->all();
    }

    public function test_old_web_session_is_rejected_after_reset_even_without_database_sessions(): void
    {
        $session = $this->staffSession();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withSession($session)->get('/staff/index')->assertOk();
        $this->withSession($session)->getJson('/api/me', ['Origin' => 'http://localhost'])->assertOk();
        $session = $this->app['session']->all();
        $this->resetStaff()->assertOk();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->flushHeaders();
        $this->withSession($session)->getJson('/api/me', ['Origin' => 'http://localhost'])->assertUnauthorized();
        Auth::forgetGuards();
        $this->withSession($session)->get('/staff/index')->assertRedirect('/login');
    }

    public function test_legacy_staff_session_without_credential_marker_requires_login(): void
    {
        $session = [Auth::guard('web')->getName() => $this->staff->id];
        $this->withSession($session)
            ->get('/staff/index')->assertRedirect('/login');
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withSession($session)->getJson('/api/me', ['Origin' => 'http://localhost'])->assertUnauthorized();
    }

    public function test_staff_self_password_change_keeps_current_web_session_usable(): void
    {
        $this->staffSession();
        $this->postJson('/account/password', [
            'current_password' => 'Current123!', 'password' => 'Changed456!', 'password_confirmation' => 'Changed456!',
        ])->assertRedirect();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get('/staff/index')->assertOk();
    }

    public function test_admin_web_session_remains_usable_after_staff_reset(): void
    {
        $this->postJson('/login', ['email' => $this->admin->email, 'password' => 'password'])->assertOk();
        $this->putJson('/api/admin/staff/'.$this->staff->id, [
            'name' => 'Updated staff', 'category_ids' => [$this->category->id], 'password' => 'Changed456!',
        ])->assertOk();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get('/admin/index')->assertOk();
    }

    public function test_failed_revocation_rolls_back_password_and_profile_changes(): void
    {
        $token = $this->staff->createToken('phone');
        $oldHash = $this->staff->password;
        $oldRemember = $this->staff->remember_token;
        $newCategory = Category::create(['name' => 'Hardware', 'color' => '#008689']);
        DB::unprepared("CREATE TRIGGER reject_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'test revocation failure'); END");
        try {
            $this->resetStaff(['category_ids' => [$newCategory->id]])->assertStatus(500);
            $this->assertSame($oldHash, $this->staff->fresh()->password);
            $this->assertSame($oldRemember, $this->staff->fresh()->remember_token);
            $this->assertSame($this->staff->name, $this->staff->fresh()->name);
            $this->assertSame([$this->category->id], $this->staff->fresh()->categories->modelKeys());
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        } finally {
            DB::unprepared('DROP TRIGGER reject_token_delete');
        }
    }

    public function test_edit_without_password_preserves_staff_login(): void
    {
        $session = $this->staffSession();
        $token = $this->staff->createToken('phone');
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['session']->flush();
        $this->withToken($this->adminToken)->putJson('/api/admin/staff/'.$this->staff->id, [
            'name' => 'Updated staff', 'category_ids' => [$this->category->id],
        ])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->flushHeaders();
        $this->withSession($session)->get('/staff/index')->assertOk();
    }
}
