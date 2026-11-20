<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::create(['name' => 'Intake test', 'email' => 'intake@example.test', 'password' => 'password', 'role' => 'teacher', 'email_verified_at' => now()]);
        $this->actingAs($user);
        $building = Building::create(['code' => 'A', 'name' => 'Tòa A']);
        $room = Room::create(['building_id' => $building->id, 'code' => 'A203', 'name' => 'Phòng A203']);
        $category = Category::create(['name' => 'Máy chiếu', 'color' => '#123456']);
        config(['services.openai.key' => 'fake-key']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['reply' => 'Hướng dẫn sau khi đã biết phòng và công việc.', 'draft' => null])]]]]])]);

        return ['room_id' => $room->id, 'category_id' => $category->id];
    }

    private function start(array $input, string $key = 'intake-start')
    {
        return $this->postJson('/api/support-conversations', $input, ['Idempotency-Key' => $key]);
    }

    private function send(int $id, array $input)
    {
        return $this->postJson("/api/support-sessions/$id/messages", $input + ['expected_version' => SupportSession::findOrFail($id)->version], ['Idempotency-Key' => (string) Str::uuid()]);
    }

    public function test_greeting_asks_for_room_and_work_without_calling_ai_or_consuming_an_ai_turn(): void
    {
        $this->context();
        $response = $this->start(['content' => 'Xin chào'])->assertCreated()->assertJsonPath('data.needs_context', true)->assertJsonPath('data.needs_description', true);
        $reply = collect($response->json('data.messages'))->last()['content_text'];
        $this->assertStringContainsString('phòng', $reply);
        $this->assertStringContainsString('công việc', $reply);
        $this->assertDatabaseHas('support_commands', ['operation' => 'clarify', 'status' => 'completed']);
        Http::assertNothingSent();
        $again = $this->start(['content' => 'Xin chào'])->assertCreated();
        $this->assertSame($response->json('data'), $again->json('data'));
        $this->assertDatabaseCount('support_messages', 2);
    }

    public function test_known_work_only_asks_for_missing_room_then_starts_ai_with_saved_problem(): void
    {
        $context = $this->context();
        $response = $this->start(['content' => 'Máy chiếu không có hình'])->assertCreated();
        $reply = collect($response->json('data.messages'))->last()['content_text'];
        $this->assertStringContainsString('phòng', $reply);
        $response->assertJsonPath('data.session.category_id', $context['category_id']);
        Http::assertNothingSent();
        $this->send($response->json('data.session.id'), ['content' => 'Mình ở phòng A203'])->assertOk()->assertJsonPath('data.needs_context', false);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains(json_encode($request['input'], JSON_UNESCAPED_UNICODE), 'Máy chiếu không có hình'));
    }

    public function test_selected_or_qr_room_is_kept_and_only_missing_work_is_requested(): void
    {
        $context = $this->context();
        $response = $this->start(['room_id' => $context['room_id'], 'content' => 'Mình cần hỗ trợ'])->assertCreated()
            ->assertJsonPath('data.session.room_id', $context['room_id'])->assertJsonPath('data.needs_description', true);
        $reply = collect($response->json('data.messages'))->last()['content_text'];
        $this->assertStringContainsString('công việc', $reply);
        $this->assertStringNotContainsString('phòng nào', $reply);
        Http::assertNothingSent();
        $this->send($response->json('data.session.id'), ['content' => 'Máy chiếu không có hình'])->assertOk()->assertJsonPath('data.needs_context', false);
        Http::assertSentCount(1);
    }

    public static function noProblem(): array
    {
        return [['hi'], ['Xin chào bạn!'], ['Mình cần hỗ trợ'], ['Tôi cần bạn giúp'], ['Mình ở phòng A203'], ['A203'], ['Máy chiếu'], ['Ở phòng A203, máy chiếu']];
    }

    #[DataProvider('noProblem')]
    public function test_room_and_category_alone_are_not_a_description_of_the_work(string $content): void
    {
        $context = $this->context();
        $response = $this->start($context + ['content' => $content])->assertCreated()->assertJsonPath('data.needs_description', true);
        $this->assertStringContainsString('mô tả', collect($response->json('data.messages'))->last()['content_text']);
        Http::assertNothingSent();
        $this->send($response->json('data.session.id'), ['content' => 'Máy chiếu bị mất hình'])->assertOk();
        Http::assertSentCount(1);
    }

    public function test_complete_explicit_context_and_problem_start_ai_immediately(): void
    {
        $context = $this->context();
        $this->start($context + ['content' => 'Máy chiếu không lên hình'])->assertCreated()->assertJsonPath('data.needs_context', false)->assertJsonPath('data.needs_description', false);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_room_does_not_start_guidance(): void
    {
        $this->context();
        Room::create(['building_id' => Building::sole()->id, 'code' => 'A204', 'name' => 'Phòng A204']);
        $this->start(['content' => 'Máy chiếu không lên ở A203 hoặc A204'])->assertCreated()->assertJsonPath('data.session.room_id', null);
        Http::assertNothingSent();
    }

    public function test_greeting_does_not_become_a_report_when_user_only_selects_context(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'hi'])->assertCreated()->json('data.session.id');
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertOk()->assertJsonPath('data.needs_description', true);
        $this->assertDatabaseCount('tickets', 0);
        Http::assertNothingSent();
    }
}
