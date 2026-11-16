<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'Current123!';
    private const NEW_PASSWORD = 'Changed456!';
    private const REUSE_ERROR = 'Mật khẩu mới không được trùng với mật khẩu hiện tại.';

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], ['admin', 'staff', 'student', 'teacher']);
    }

    public static function resetRoutesAndRoles(): array
    {
        $cases = [];
        foreach (['/password/reset', '/api/auth/password/reset'] as $route) {
            foreach (self::roles() as [$role]) $cases[] = [$role, $route];
        }
        return $cases;
    }

    private function account(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => uniqid($role).'@example.com',
            'password' => self::OLD_PASSWORD, 'role' => $role, 'email_verified_at' => now(),
        ]);
    }

    private function resetOtp(User $user): int
    {
        return DB::table('email_otps')->insertGetId([
            'email' => $user->email, 'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'), 'attempts' => 0,
            'expires_at' => now()->addMinutes(5), 'verified_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[DataProvider('roles')]
    public function test_web_account_rejects_reuse_and_accepts_a_different_password(string $role): void
    {
        $user = $this->account($role);
        $oldHash = $user->password;
        $token = $user->createToken('existing-device');
        $page = match ($role) {
            'admin' => '/admin/pages/account', 'staff' => '/staff/account', default => '/customer/account',
        };
        $this->actingAs($user, 'web')->from($page)->post('/account/password', [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD,
        ])->assertRedirect($page)->assertSessionHasErrors(['password' => self::REUSE_ERROR]);
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->get($page)->assertOk()->assertSee(self::REUSE_ERROR)
            ->assertSee('/shared/js/password-validation.js');
        $this->post('/account/password', [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    #[DataProvider('roles')]
    public function test_api_account_rejects_reuse_without_revoking_tokens(string $role): void
    {
        $user = $this->account($role);
        $oldHash = $user->password;
        $current = $user->createToken('current-device');
        $other = $user->createToken('other-device');
        $this->withToken($current->plainTextToken)->postJson('/api/account/password', [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD,
        ])->assertUnprocessable()->assertJsonPath('message', self::REUSE_ERROR)
            ->assertJsonPath('errors.password.0', self::REUSE_ERROR);
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertSame(2, $user->tokens()->count());
        Auth::forgetGuards();
        $this->postJson('/api/account/password', [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    #[DataProvider('resetRoutesAndRoles')]
    public function test_reset_rejects_reuse_and_keeps_otp_available_for_a_new_password(string $role, string $route): void
    {
        $user = $this->account($role);
        $oldHash = $user->password;
        $otpId = $this->resetOtp($user);
        $token = $user->createToken('existing-device');
        $payload = ['email' => $user->email, 'otp' => '123456', 'password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD];
        $this->postJson($route, $payload)->assertUnprocessable()
            ->assertJsonPath('message', self::REUSE_ERROR)->assertJsonPath('errors.password.0', self::REUSE_ERROR);
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertDatabaseHas('email_otps', ['id' => $otpId, 'verified_at' => null, 'attempts' => 0]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->postJson($route, array_replace($payload, [
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ]))->assertOk();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertNotNull(DB::table('email_otps')->where('id', $otpId)->value('verified_at'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_invalid_otp_does_not_disclose_whether_the_password_matches(): void
    {
        $user = $this->account('admin');
        $otpId = $this->resetOtp($user);
        $this->postJson('/api/auth/password/reset', [
            'email' => $user->email, 'otp' => '000000',
            'password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('otp')->assertJsonMissingPath('errors.password');
        $this->assertDatabaseHas('email_otps', ['id' => $otpId, 'attempts' => 1, 'verified_at' => null]);
    }

    public function test_password_validation_is_vietnamese_and_checks_the_current_password_first(): void
    {
        $user = $this->account('student');
        $this->withToken($user->createToken('test-device')->plainTextToken);
        foreach ([
            ['', self::NEW_PASSWORD, self::NEW_PASSWORD, 'current_password', 'Vui lòng nhập mật khẩu hiện tại.'],
            [self::OLD_PASSWORD, 'short', 'short', 'password', 'Mật khẩu phải có ít nhất 8 ký tự.'],
            [self::OLD_PASSWORD, self::NEW_PASSWORD, 'WrongConfirm!', 'password', 'Mật khẩu xác nhận chưa khớp.'],
            ['Incorrect123!', self::OLD_PASSWORD, self::OLD_PASSWORD, 'current_password', 'Mật khẩu hiện tại không chính xác.'],
        ] as [$current, $password, $confirmation, $field, $message]) {
            Auth::forgetGuards();
            $this->postJson('/api/account/password', [
                'current_password' => $current, 'password' => $password, 'password_confirmation' => $confirmation,
            ])->assertUnprocessable()->assertJsonPath('errors.'.$field.'.0', $message);
        }
    }

    public function test_admin_cannot_set_staff_password_to_its_existing_value(): void
    {
        $staff = $this->account('staff');
        $oldHash = $staff->password;
        $category = Category::create(['name' => 'Network', 'color' => '#008689']);
        $staff->categories()->sync([$category->id]);
        $admin = $this->account('admin');
        $this->withToken($admin->createToken('admin-device')->plainTextToken)
            ->putJson('/api/admin/staff/'.$staff->id, [
                'name' => 'Changed name', 'category_ids' => [$category->id], 'password' => self::OLD_PASSWORD,
            ])->assertUnprocessable()->assertJsonPath('errors.password.0', self::REUSE_ERROR);
        $this->assertSame($oldHash, $staff->fresh()->password);
        $this->assertSame('Staff', $staff->fresh()->name);
    }
}
