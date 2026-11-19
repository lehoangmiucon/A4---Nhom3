<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SupportSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_teacher_can_persist_and_restore_a_session_without_calling_ai_or_creating_a_ticket(): void
    {
        Http::fake();
        $user = $this->user();
        $room = $this->room();
        $equipment = Equipment::create(['room_id' => $room->id, 'name' => 'Máy chiếu', 'status' => 'active']);
        $response = $this->actingAs($user)->createSession($room, ['equipment_id' => $equipment->id]);
        $response->assertCreated()->assertJsonPath('data.status', 'collecting')
            ->assertJsonPath('data.version', 1)->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.equipment_id', $equipment->id)
            ->assertJsonPath('data.permitted_actions', [])
            ->assertJsonStructure(['data' => ['id', 'initial_message_id', 'room_label', 'created_at'], 'meta' => ['request_id']]);

        $id = $response->json('data.id');
        $initialId = $response->json('data.initial_message_id');
        $this->getJson("/api/support-sessions/$id")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson("/api/support-sessions/$id/messages")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $initialId)
            ->assertJsonPath('data.0.role', 'user')->assertJsonPath('data.0.content_text', 'Máy chiếu không có hình.');
        $this->assertDatabaseHas('support_sessions', ['id' => $id, 'user_id' => $user->id]);
        $this->assertDatabaseCount('tickets', 0);
        Http::assertNothingSent();
    }

    public function test_mobile_bearer_can_create_and_read_the_same_persistent_session(): void
    {
        $user = $this->user('student');
        $room = $this->room();
        Auth::forgetGuards();
        $this->withToken($user->createToken('mobile')->plainTextToken);
        $id = $this->createSession($room)->assertCreated()->json('data.id');
        $this->getJson("/api/support-sessions/$id")->assertOk()->assertJsonPath('data.id', $id);
    }

    public function test_session_creation_replays_the_original_response_for_the_same_intent(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $key = (string) Str::uuid();
        $first = $this->createSession($room, [], $key)->assertCreated();
        $second = $this->createSession($room, [], $key)->assertCreated();
        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertDatabaseCount('support_sessions', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('support_idempotency_keys', 1);
    }

    public function test_same_key_cannot_be_reused_for_a_different_payload(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $key = (string) Str::uuid();
        $this->createSession($room, [], $key)->assertCreated();
        $this->createSession($room, ['description' => 'Micro không có tiếng.'], $key)
            ->assertUnprocessable()->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('support_sessions', 1);
    }

    public function test_distinct_intents_and_users_do_not_share_idempotency_results(): void
    {
        $room = $this->room();
        $key = (string) Str::uuid();
        $this->actingAs($this->user())->createSession($room, [], $key)->assertCreated();
        $this->createSession($room)->assertCreated();
        $this->actingAs($this->user())->createSession($room, [], $key)->assertCreated();
        $this->assertDatabaseCount('support_sessions', 3);
    }

    public function test_other_users_cannot_read_a_session_or_its_messages_and_lists_are_scoped(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $room = $this->room();
        $id = $this->actingAs($owner)->createSession($room)->assertCreated()->json('data.id');
        $this->actingAs($other)->getJson("/api/support-sessions/$id")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson("/api/support-sessions/$id/messages")->assertNotFound();
        $this->getJson('/api/support-sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/support-sessions/999999')->assertNotFound();
    }

    public function test_authentication_and_active_verified_customer_role_are_required(): void
    {
        $room = $this->room();
        $this->createSession($room)->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs($this->user($role))->createSession($room)->assertForbidden();
        }
        $locked = $this->user();
        $locked->update(['is_deleted' => true]);
        $this->actingAs($locked)->getJson('/api/support-sessions')->assertForbidden();
        $unverified = $this->user();
        $unverified->update(['email_verified_at' => null]);
        $this->actingAs($unverified)->createSession($room)->assertForbidden();
    }

    public function test_invalid_location_and_oversized_or_blank_descriptions_never_persist_a_session(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        foreach ([['room_id' => 999999], ['description' => '  '], ['description' => str_repeat('a', 1001)]] as $payload) {
            $this->createSession($room, $payload)->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
        $this->assertDatabaseCount('support_sessions', 0);
        $this->assertDatabaseCount('support_idempotency_keys', 0);
    }

    public function test_equipment_must_belong_to_the_selected_room(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $otherRoom = $this->room();
        $equipment = Equipment::create(['room_id' => $otherRoom->id, 'name' => 'Micro', 'status' => 'active']);
        $this->createSession($room, ['equipment_id' => $equipment->id])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseCount('support_sessions', 0);
    }

    public function test_missing_idempotency_key_and_spoofed_server_fields_are_rejected(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $this->postJson('/api/support-sessions', ['room_id' => $room->id, 'description' => 'Lỗi máy chiếu.'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->createSession($room, ['user_id' => 999, 'status' => 'resolved', 'version' => 99])
            ->assertUnprocessable();
        $this->assertDatabaseCount('support_sessions', 0);
    }

    public function test_lists_and_message_history_are_paginated_and_can_be_resumed(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $id = $this->createSession($room)->assertCreated()->json('data.id');
        $this->createSession($room)->assertCreated();
        $this->getJson('/api/support-sessions?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 2);
        $this->getJson('/api/support-sessions?status=resolved')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/support-sessions?per_page=51')->assertUnprocessable();
        $this->getJson('/api/support-sessions?status=invalid')->assertUnprocessable();
        $firstMessage = $this->getJson("/api/support-sessions/$id/messages")->json('data.0.id');
        $this->getJson("/api/support-sessions/$id/messages?after_id=$firstMessage")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/support-sessions/$id/messages?per_page=51")->assertUnprocessable();
    }

    public function test_database_constraints_protect_idempotency_and_location_deletion_preserves_history(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $id = $this->createSession($room)->assertCreated()->json('data.id');
        $record = (array) DB::table('support_idempotency_keys')->first();
        unset($record['id']);
        try {
            DB::table('support_idempotency_keys')->insert($record);
            $this->fail('Duplicate idempotency records must be rejected by the database.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('support_idempotency_keys', 1);
        }
        $room->delete();
        $this->getJson("/api/support-sessions/$id")->assertOk()
            ->assertJsonPath('data.room_id', null)->assertJsonPath('data.room_label', 'Phòng thử nghiệm');
        $this->assertDatabaseCount('support_messages', 1);
    }

    public function test_swagger_exposes_the_creation_header_body_and_pagination_contract(): void
    {
        $spec = $this->get('/api')->assertOk()->viewData('spec');
        $operation = $spec['paths']['/support-sessions']['post'] ?? [];
        $header = collect($operation['parameters'] ?? [])->firstWhere('name', 'Idempotency-Key');
        $this->assertSame('header', $header['in'] ?? null);
        $this->assertTrue($header['required'] ?? false);
        $schema = $operation['requestBody']['content']['application/json']['schema'] ?? [];
        $this->assertContains('room_id', $schema['required'] ?? []);
        $this->assertContains('description', $schema['required'] ?? []);
        $parameters = collect($spec['paths']['/support-sessions']['get']['parameters'] ?? []);
        $this->assertSame(50, $parameters->firstWhere('name', 'per_page')['schema']['maximum'] ?? null);
    }

    public function test_failed_message_storage_rolls_back_the_entire_intent_and_retry_can_succeed(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $key = (string) Str::uuid();
        SupportMessage::creating(static function () {
            throw new \RuntimeException('Sensitive persistence error');
        });
        try {
            $this->createSession($room, [], $key)->assertStatus(500)
                ->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE')
                ->assertDontSee('Sensitive persistence error');
        } finally {
            SupportMessage::flushEventListeners();
        }
        $this->assertDatabaseCount('support_sessions', 0);
        $this->assertDatabaseCount('support_messages', 0);
        $this->assertDatabaseCount('support_idempotency_keys', 0);
        $this->createSession($room, [], $key)->assertCreated();
        $this->assertDatabaseCount('support_sessions', 1);
    }

    public function test_replay_survives_location_deletion_and_does_not_revalidate_a_completed_intent(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $key = (string) Str::uuid();
        $original = $this->createSession($room, [], $key)->assertCreated()->json('data');
        $room->delete();
        $response = $this->createSession($room, [], $key)->assertCreated();
        $this->assertSame($original, $response->json('data'));
        $this->assertDatabaseCount('support_sessions', 1);
        $this->getJson('/api/support-sessions/'.$original['id'])->assertOk()->assertJsonPath('data.room_id', null);
        $this->createSession($room)->assertUnprocessable();
    }

    public function test_key_is_validated_and_sensitive_internal_fields_are_never_exposed(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        foreach (['invalid key!', str_repeat('x', 129)] as $key) {
            $this->createSession($room, [], $key)->assertUnprocessable();
        }
        $first = $this->createSession($room, [], 'Intent-A')->assertCreated();
        $second = $this->createSession($room, [], 'intent-a')->assertCreated();
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertArrayNotHasKey('user_id', $first->json('data'));
        $this->assertArrayNotHasKey('idempotency_key', $first->json('data'));
        $this->assertStringNotContainsString('Intent-A', json_encode(DB::table('support_idempotency_keys')->get()));
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->getJson('/api/support-sessions')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    }

    private function createSession(Room $room, array $overrides = [], ?string $key = null): TestResponse
    {
        return $this->postJson('/api/support-sessions', array_replace([
            'room_id' => $room->id,
            'description' => 'Máy chiếu không có hình.',
        ], $overrides), ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function user(string $role = 'teacher'): User
    {
        return User::create([
            'name' => 'Người báo', 'email' => Str::uuid().'@example.com',
            'password' => 'Password123!', 'role' => $role, 'email_verified_at' => now(),
        ]);
    }

    private function room(): Room
    {
        $building = Building::firstOrCreate(['code' => 'SUPPORT'], ['name' => 'Tòa thử nghiệm']);

        return Room::create(['building_id' => $building->id, 'code' => (string) Str::uuid(), 'name' => 'Phòng thử nghiệm']);
    }
}
