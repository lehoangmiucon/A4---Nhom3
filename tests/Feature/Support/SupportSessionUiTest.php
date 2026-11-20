<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportSessionUiTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $role = 'student', array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Nguyễn Văn '.ucfirst($role),
            'email' => $role.'_'.Str::random(6).'@school.edu.vn',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
            'is_deleted' => false,
        ], $attributes));
    }

    private function room(string $buildingCode = 'A', string $roomCode = 'A101'): Room
    {
        $building = Building::firstOrCreate(['code' => $buildingCode], ['name' => 'Tòa nhà '.$buildingCode]);

        return Room::create([
            'building_id' => $building->id,
            'code' => $roomCode,
            'name' => 'Phòng học '.$roomCode,
            'qr_code' => 'QR_'.$roomCode,
            'status' => 'active',
        ]);
    }

    public function test_customer_portal_renders_support_session_components_and_config(): void
    {
        $user = $this->customer('student');
        $room = $this->room('A', 'A203');
        Equipment::create(['room_id' => $room->id, 'name' => 'Máy chiếu A203', 'status' => 'active']);
        Category::create(['name' => 'Máy chiếu', 'color' => '#123456']);

        $response = $this->actingAs($user)->get('/customer/portal');

        $response->assertOk();
        $response->assertSee('report-ai-view', false);
        $response->assertSee('report-mode-ai', false);
        $response->assertSee('conversation-history-list', false);
        $response->assertSee('conversation-form', false);
        $response->assertSee('conversation-log', false);
        $response->assertSee('support-conversation.js', false);
        $response->assertSee('currentUserId', false);
        $response->assertSee('csrf-token', false);
        $response->assertSee('conversation-category', false);
        // Hai cách báo sự cố cùng có mặt, form thủ công vẫn gửi tới API cũ.
        $response->assertSee('conversation-escalate', false);
        $response->assertSee('Gửi báo cáo sự cố');
    }

    public function test_teacher_can_also_access_support_session_portal(): void
    {
        $teacher = $this->customer('teacher');
        $this->room('B', 'B101');

        $response = $this->actingAs($teacher)->get('/customer/portal');
        $response->assertOk();
        $response->assertSee('report-ai-view', false);
    }

    public function test_portal_has_manual_and_ai_modes_with_reporting_inside_the_conversation(): void
    {
        $response = $this->actingAs($this->customer())->get('/customer/portal')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " portal-chat-launch ")]')->length);
        $this->assertSame(0, $xpath->query('//*[@popover]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="report-ai-view"]//*[@id="conversation-form"]')->length);
        $response->assertSee('report-mode-manual', false);
        $response->assertSee('conversation-escalate', false);
    }

    public function test_user_session_list_endpoint_scopes_data_to_current_user_with_pagination(): void
    {
        $userA = $this->customer('student');
        $userB = $this->customer('teacher');
        $room = $this->room('A', 'A101');

        // Tạo 3 session cho userA
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($userA)->postJson('/api/support-sessions', [
                'room_id' => $room->id,
                'description' => "Mô tả sự cố số $i của sinh viên A",
            ], ['Idempotency-Key' => (string) Str::uuid()]);
        }

        // Tạo 1 session cho userB
        $this->actingAs($userB)->postJson('/api/support-sessions', [
            'room_id' => $room->id,
            'description' => 'Mô tả của giảng viên B',
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        // User A đọc danh sách
        $responseA = $this->actingAs($userA)->getJson('/api/support-sessions?per_page=2');
        $responseA->assertOk();
        $responseA->assertJsonPath('meta.pagination.total', 3);
        $responseA->assertJsonCount(2, 'data');
        $responseA->assertJsonPath('data.0.room_code', 'A101');

        // Đảm bảo không thấy session của userB trong response của userA
        $descriptionsA = collect($responseA->json('data'))->pluck('initial_description');
        $this->assertFalse($descriptionsA->contains('Mô tả của giảng viên B'));

        // User B đọc danh sách
        $responseB = $this->actingAs($userB)->getJson('/api/support-sessions');
        $responseB->assertOk();
        $responseB->assertJsonPath('meta.pagination.total', 1);
        $responseB->assertJsonPath('data.0.initial_description', 'Mô tả của giảng viên B');
    }

    public function test_user_can_create_session_with_idempotency_key_and_retrieve_details(): void
    {
        $user = $this->customer('student');
        $room = $this->room('A', 'A202');
        $equipment = Equipment::create(['room_id' => $room->id, 'name' => 'Loa treo tường', 'status' => 'active']);
        $key = (string) Str::uuid();

        $payload = [
            'room_id' => $room->id,
            'equipment_id' => $equipment->id,
            'description' => 'Loa bị rè khi bật âm lượng lớn',
        ];

        // Lần gửi 1
        $createRes = $this->actingAs($user)->postJson('/api/support-sessions', $payload, ['Idempotency-Key' => $key]);
        $createRes->assertCreated();
        $sessionId = $createRes->json('data.id');
        $this->assertSame('Phòng học A202', $createRes->json('data.room_label'));
        $this->assertSame('A202', $createRes->json('data.room_code'));
        $this->assertSame('Loa treo tường', $createRes->json('data.equipment_label'));

        // Giả lập retry cùng key và payload (ví dụ mạng chập chờn)
        $retryRes = $this->actingAs($user)->postJson('/api/support-sessions', $payload, ['Idempotency-Key' => $key]);
        $retryRes->assertCreated();
        $this->assertSame($sessionId, $retryRes->json('data.id'));

        // Đọc lại chi tiết phiên
        $detailRes = $this->getJson("/api/support-sessions/{$sessionId}");
        $detailRes->assertOk();
        $detailRes->assertJsonPath('data.id', $sessionId);
        $detailRes->assertJsonPath('data.room_id', $room->id);

        // Đọc lại messages của phiên
        $msgRes = $this->getJson("/api/support-sessions/{$sessionId}/messages");
        $msgRes->assertOk();
        $msgRes->assertJsonCount(1, 'data');
        $msgRes->assertJsonPath('data.0.role', 'user');
        $msgRes->assertJsonPath('data.0.content_text', 'Loa bị rè khi bật âm lượng lớn');
    }

    public function test_user_cannot_access_other_users_session_details_or_messages(): void
    {
        $userA = $this->customer('student');
        $userB = $this->customer('teacher');
        $room = $this->room();

        $res = $this->actingAs($userA)->postJson('/api/support-sessions', [
            'room_id' => $room->id,
            'description' => 'Phiên bí mật của A',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $sessionId = $res->json('data.id');

        // User B cố đọc phiên của User A
        $this->actingAs($userB)->getJson("/api/support-sessions/{$sessionId}")->assertNotFound();
        $this->actingAs($userB)->getJson("/api/support-sessions/{$sessionId}/messages")->assertNotFound();
    }

    public function test_traditional_ticket_reporting_form_remains_intact_and_usable(): void
    {
        $user = $this->customer('student');
        $room = $this->room('C', 'C301');
        $category = Category::create(['name' => 'Mạng Internet', 'color' => '#445566']);

        $response = $this->actingAs($user)->get('/customer/portal');
        $response->assertOk();

        // Form POST /api/tickets vẫn còn
        $response->assertSee('data-api-endpoint="/api/tickets"', false);
        $response->assertSee('name="room_id"', false);
        $response->assertSee('name="category_id"', false);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="description"', false);
    }
}
