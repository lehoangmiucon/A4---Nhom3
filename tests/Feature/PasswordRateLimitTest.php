<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = [
        'current_password' => 'Wrong123!',
        'password' => 'Changed456!',
        'password_confirmation' => 'Changed456!',
    ];

    public static function endpointsAndRoles(): array
    {
        $cases = [];
        foreach (['/account/password', '/api/account/password'] as $endpoint) {
            foreach (['admin', 'staff', 'student', 'teacher'] as $role) {
                $cases[] = [$endpoint, $role];
            }
        }

        return $cases;
    }

    #[DataProvider('endpointsAndRoles')]
    public function test_sixth_attempt_is_blocked_without_mutation_and_quota_expires(string $endpoint, string $role): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => $role, 'password' => 'Current123!']);
        $hash = $user->password;
        $token = $user->createToken('phone');
        if ($endpoint === '/account/password') {
            $this->actingAs($user, 'web');
        } else {
            $this->withToken($token->plainTextToken);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($endpoint, self::PAYLOAD)->assertUnprocessable();
        }
        $correct = array_replace(self::PAYLOAD, ['current_password' => 'Current123!']);
        $response = $this->postJson($endpoint, $correct)->assertTooManyRequests()
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '5');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->travel(61)->seconds();
        $response = $this->postJson($endpoint, $correct);
        if ($endpoint === '/account/password') {
            $response->assertRedirect();
        } else {
            $response->assertOk();
        }
        $this->assertTrue(Hash::check('Changed456!', $user->fresh()->password));
    }

    public function test_quota_is_shared_between_web_api_tokens_and_ip_addresses(): void
    {
        $user = User::factory()->create(['role' => 'student', 'password' => 'Current123!']);
        $first = $user->createToken('phone')->plainTextToken;
        $second = $user->createToken('tablet')->plainTextToken;
        $this->actingAs($user, 'web');
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/account/password', self::PAYLOAD)->assertUnprocessable();
        }
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['session']->flush();
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)])
                ->withToken($first)->postJson('/api/account/password', self::PAYLOAD)->assertUnprocessable();
        }
        Auth::forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->withToken($second)
            ->postJson('/api/account/password', self::PAYLOAD)->assertTooManyRequests();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->flushHeaders();
        $this->actingAs($user, 'web')->post('/account/password', self::PAYLOAD)
            ->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_one_account_does_not_exhaust_another_accounts_quota_on_the_same_ip(): void
    {
        $first = User::factory()->create(['role' => 'student']);
        $second = User::factory()->create(['role' => 'student']);
        $this->withToken($first->createToken('phone')->plainTextToken);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/account/password', self::PAYLOAD)->assertUnprocessable();
        }
        $this->postJson('/api/account/password', self::PAYLOAD)->assertTooManyRequests();
        Auth::forgetGuards();
        $this->withToken($second->createToken('phone')->plainTextToken)
            ->postJson('/api/account/password', self::PAYLOAD)->assertUnprocessable();
    }

    public function test_guests_are_rejected_before_password_throttling(): void
    {
        foreach (['/account/password', '/api/account/password'] as $endpoint) {
            for ($i = 0; $i < 6; $i++) {
                $this->postJson($endpoint, self::PAYLOAD)->assertUnauthorized();
            }
        }
    }
}
