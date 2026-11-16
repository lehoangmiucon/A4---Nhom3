<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_log_in_with_valid_credentials(): void
    {
        // Arrange: tạo sinh viên đã xác thực email.
        $student = User::factory()->create(['role' => 'student']);

        // Act: gửi đúng email và mật khẩu mặc định của factory.
        $response = $this->postJson('/login', [
            'email' => $student->email,
            'password' => 'password',
        ]);

        // Assert: đúng tài khoản, đúng trang đích và đã đăng nhập.
        $response->assertOk()
            ->assertJsonPath('user.id', $student->id)
            ->assertJsonPath('redirect', url('/customer/index'));

        $this->assertAuthenticatedAs($student, 'web');
    }

    public function test_wrong_password_does_not_log_the_user_in(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->postJson('/login', [
            'email' => $student->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_student_cannot_access_admin_api(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/tickets')->assertForbidden();
    }

    public function test_student_cannot_view_another_students_ticket(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $viewer = User::factory()->create(['role' => 'student']);

        $ticket = Ticket::create([
            'ticket_number' => 'HD-TEST-001',
            'reporter_id' => $owner->id,
            'reporter_name' => $owner->name,
            'title' => 'Máy chiếu không lên',
            'description' => 'Đã bật nguồn nhưng máy chiếu không hiển thị.',
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/tickets/{$ticket->id}")->assertForbidden();
    }
}