<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Room;
use App\Models\SupportCommand;
use App\Models\SupportReport;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportSessionDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Notification::fake();
    }

    private function user(string $role = 'teacher'): User
    {
        return User::create(['name' => 'Deletion test', 'email' => Str::uuid().'@example.test',
            'password' => 'password', 'role' => $role, 'email_verified_at' => now()]);
    }

    private function reported(User $owner, string $ticketStatus = 'closed', string $sessionStatus = 'handed_off'): array
    {
        $session = SupportSession::create(['user_id' => $owner->id, 'room_label' => 'A203', 'room_code' => 'A203', 'status' => $sessionStatus, 'version' => 1, 'last_activity_at' => now()]);
        $session->messages()->create(['role' => 'user', 'content_text' => 'Máy chiếu không có hình']);
        $ticket = Ticket::create(['ticket_number' => 'HD-'.Str::upper(Str::random(8)), 'reporter_id' => $owner->id,
            'reporter_name' => $owner->name, 'reporter_email' => $owner->email, 'room_label' => 'A203',
            'title' => 'Máy chiếu', 'description' => 'Không có hình', 'priority' => 'normal', 'status' => $ticketStatus]);
        $report = SupportReport::create(['session_id' => $session->id, 'ticket_id' => $ticket->id, 'transcript' => [['role' => 'user', 'content_text' => 'Máy chiếu không có hình']]]);

        return [$session, $ticket, $report];
    }

    public function test_owner_can_soft_delete_completed_report_without_losing_ticket_or_history(): void
    {
        $owner = $this->user();
        [$session, $ticket, $report] = $this->reported($owner);
        $this->actingAs($owner)->getJson('/api/support-sessions')->assertOk()
            ->assertJsonPath('data.0.can_delete', true)->assertJsonPath('data.0.is_completed', true)->assertJsonPath('data.0.ticket_status', 'closed');
        $this->deleteJson("/api/support-sessions/$session->id")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('support_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'closed']);
        $this->assertDatabaseHas('support_reports', ['id' => $report->id, 'ticket_id' => $ticket->id]);
        $this->assertDatabaseCount('support_messages', 1);
        $this->getJson('/api/support-sessions')->assertOk()->assertJsonCount(0, 'data');
        foreach (['', '/messages', '/conversation', '/commands/old-command'] as $suffix) {
            $this->getJson("/api/support-sessions/$session->id$suffix")->assertNotFound();
        }
        $this->deleteJson("/api/support-sessions/$session->id")->assertOk();
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public static function incompleteStates(): array
    {
        return [['new', 'handed_off'], ['assigned', 'handed_off'], ['in_progress', 'handed_off'],
            ['resolved', 'handed_off'], ['reopened', 'handed_off'], ['closed', 'collecting'], ['closed', 'resolved'], ['closed', 'handoff_pending']];
    }

    #[DataProvider('incompleteStates')]
    public function test_incomplete_or_not_handed_off_sessions_cannot_be_deleted(string $ticketStatus, string $sessionStatus): void
    {
        $owner = $this->user();
        [$session] = $this->reported($owner, $ticketStatus, $sessionStatus);
        $this->actingAs($owner)->getJson('/api/support-sessions')->assertOk()->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson("/api/support-sessions/$session->id", ['can_delete' => true, 'is_completed' => true])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertNotNull(SupportSession::find($session->id));
    }

    public function test_resolved_session_without_report_cannot_be_deleted(): void
    {
        $owner = $this->user();
        $session = SupportSession::create(['user_id' => $owner->id, 'room_label' => '', 'room_code' => '', 'status' => 'resolved', 'version' => 1, 'last_activity_at' => now()]);
        $this->actingAs($owner)->deleteJson("/api/support-sessions/$session->id")->assertUnprocessable();
    }

    public function test_other_owner_and_guest_cannot_delete_even_a_completed_session(): void
    {
        [$session] = $this->reported($this->user());
        $this->deleteJson("/api/support-sessions/$session->id")->assertUnauthorized();
        $this->actingAs($this->user('student'))->deleteJson("/api/support-sessions/$session->id")->assertNotFound();
        $this->assertNotNull(SupportSession::find($session->id));
    }

    public function test_reopened_ticket_revokes_previously_advertised_delete_permission(): void
    {
        $owner = $this->user();
        [$session, $ticket] = $this->reported($owner);
        $this->actingAs($owner)->getJson("/api/support-sessions/$session->id")->assertOk()->assertJsonPath('data.can_delete', true);
        $ticket->update(['status' => 'reopened']);
        $this->deleteJson("/api/support-sessions/$session->id")->assertUnprocessable();
        $this->getJson("/api/support-sessions/$session->id/conversation")->assertOk()->assertJsonPath('data.session.can_delete', false);
    }

    public function test_deleted_session_cannot_be_restored_through_a_completed_command_replay(): void
    {
        $owner = $this->user();
        [$session] = $this->reported($owner);
        $input = ['content' => 'Gửi báo cáo giúp tôi', 'action' => 'report'];
        SupportCommand::create(['id' => (string) Str::uuid(), 'actor_id' => $owner->id, 'session_id' => $session->id,
            'key_hash' => hash('sha256', 'old-report'), 'request_hash' => hash('sha256', json_encode([null, $input['content'], null, null, 'report'])),
            'operation' => 'report', 'status' => 'completed', 'version' => 1, 'response_data' => ['session' => ['id' => $session->id]], 'response_status' => 201]);
        $this->actingAs($owner)->deleteJson("/api/support-sessions/$session->id")->assertOk();
        $this->postJson('/api/support-conversations', $input, ['Idempotency-Key' => 'old-report'])->assertNotFound();
        $this->assertDatabaseCount('support_commands', 1);
    }

    public function test_legacy_creation_key_cannot_restore_a_deleted_session(): void
    {
        $owner = $this->user();
        $building = Building::create(['code' => 'A', 'name' => 'Tòa A']);
        $room = Room::create(['building_id' => $building->id, 'code' => 'A203', 'name' => 'Phòng A203', 'qr_code' => 'QR_A203']);
        $payload = ['room_id' => $room->id, 'description' => 'Máy chiếu không có hình'];
        $headers = ['Idempotency-Key' => 'legacy-create'];
        $id = $this->actingAs($owner)->postJson('/api/support-sessions', $payload, $headers)->assertCreated()->json('data.id');
        [, , $report] = $this->reported($owner);
        $report->update(['session_id' => $id]);
        SupportSession::findOrFail($id)->update(['status' => 'handed_off']);
        $this->deleteJson("/api/support-sessions/$id")->assertOk();
        $this->postJson('/api/support-sessions', $payload, $headers)->assertNotFound();
        $this->assertDatabaseCount('support_sessions', 2);
    }

    public function test_student_bearer_can_delete_own_completed_session_but_not_another_users_deleted_session(): void
    {
        $owner = $this->user('student');
        [$session] = $this->reported($owner);
        $this->withToken($owner->createToken('mobile')->plainTextToken)->deleteJson("/api/support-sessions/$session->id")->assertOk();
        $this->actingAs($this->user('student'))->deleteJson("/api/support-sessions/$session->id")->assertNotFound();
    }

    public function test_openapi_describes_deletion_eligibility_and_errors(): void
    {
        $operation = $this->get('/api')->assertOk()->viewData('spec')['paths']['/support-sessions/{supportSession}']['delete'];
        $this->assertStringContainsString('closed', $operation['description']);
        $this->assertStringContainsString('422', $operation['description']);
        $this->assertArrayHasKey('200', $operation['responses']);
        $this->assertArrayNotHasKey('requestBody', $operation);
    }
}
