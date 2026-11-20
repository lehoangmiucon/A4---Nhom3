<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\SupportReport;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\ConversationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportConversationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'teacher'): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => Str::uuid().'@example.test', 'password' => 'password', 'role' => $role, 'email_verified_at' => now()]);
    }

    private function context(): array
    {
        $building = Building::create(['code' => 'A', 'name' => 'Tòa A']);
        $room = Room::create(['building_id' => $building->id, 'code' => 'A203', 'name' => 'Phòng A203', 'qr_code' => 'QR_A203']);
        $category = Category::create(['name' => 'Máy chiếu', 'color' => '#123456']);

        return ['room_id' => $room->id, 'category_id' => $category->id];
    }

    private function ai(): void
    {
        config(['services.openai.key' => 'fake-key']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['reply' => 'Bạn kiểm tra chế độ trình chiếu trên máy tính nhé. Nếu vẫn lỗi, tôi có thể gửi báo cáo giúp bạn.', 'draft' => null])]]]]])]);
    }

    public function test_changed_category_is_sent_to_ai_and_preserved_on_following_turns(): void
    {
        $this->ai();
        $context = $this->context();
        $network = Category::create(['name' => 'Mạng / Wi-Fi', 'color' => '#123456']);
        $this->actingAs($this->actor());
        $id = $this->start($context + ['content' => 'Máy chiếu không có hình'])->assertCreated()->json('data.session.id');
        $this->send($id, ['content' => 'Mình không vào được mạng', 'category_id' => $network->id])
            ->assertOk()->assertJsonPath('data.category_label', $network->name);
        $this->send($id, ['content' => 'Điện thoại cũng không kết nối được'])->assertOk();

        $requests = Http::recorded()->pluck(0);
        foreach ([$requests[1], $requests[2]] as $request) {
            $this->assertStringContainsString('"danh_muc_da_chon":'.json_encode(['id' => $network->id, 'name' => $network->name], JSON_UNESCAPED_UNICODE), $request['instructions']);
        }
        $this->assertDatabaseCount('support_sessions', 1);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertSame($network->id, SupportSession::findOrFail($id)->category_id);
    }

    private function start(array $input = [], ?string $key = null)
    {
        return $this->postJson('/api/support-conversations', $input + ['content' => 'Máy chiếu không có hình'], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function send(int $id, array $input = [], ?string $key = null)
    {
        return $this->postJson("/api/support-sessions/$id/messages", $input + ['content' => 'Vẫn chưa được', 'expected_version' => SupportSession::findOrFail($id)->version], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_chat_persists_server_history_and_user_can_ask_for_a_real_report(): void
    {
        $this->ai();
        $context = $this->context();
        $admin = $this->actor('admin');
        $this->actingAs($this->actor());
        $start = $this->start($context)->assertCreated();
        $id = $start->json('data.session.id');
        $this->assertDatabaseCount('tickets', 0);
        $this->send($id)->assertOk();
        $sent = $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $sent->assertJsonPath('data.session.status', 'handed_off');
        $ticket = Ticket::sole();
        $this->assertSame('high', $ticket->priority);
        $this->assertStringContainsString('Vẫn chưa được', $ticket->description);
        $this->assertStringNotContainsString('chế độ trình chiếu', $ticket->description);
        $sent->assertJsonPath('data.report.ticket_number', $ticket->ticket_number);
        $this->assertDatabaseCount('support_reports', 1);
        $this->assertSame(1, $admin->notifications()->count());
        Http::assertSentCount(2);
        $this->getJson("/api/support-sessions/$id/messages")->assertOk()->assertJsonCount(6, 'data');
    }

    public function test_report_works_without_ai_and_does_not_repeat_on_retry_or_new_key(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $admin = $this->actor('admin');
        $this->actingAs($this->actor('student'));
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $key = (string) Str::uuid();
        $first = $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'], $key)->assertCreated();
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'], $key)->assertCreated()->assertJsonPath('data.report.id', $first->json('data.report.id'));
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertOk();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('support_reports', 1);
        $this->assertSame(1, $admin->notifications()->count());
        Http::assertNothingSent();
    }

    public function test_chat_collects_missing_room_and_category_without_the_manual_form(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start(['content' => 'Thiết bị không hoạt động'])->assertCreated()->json('data.session.id');
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertOk()->assertJsonPath('data.needs_context', true);
        $this->assertDatabaseCount('tickets', 0);
        $result = $this->send($id, ['content' => 'Ở phòng A203, máy chiếu'])->assertCreated();
        $result->assertJsonPath('data.session.room_id', $context['room_id']);
        $this->assertDatabaseCount('tickets', 1);
        Http::assertNothingSent();
    }

    public function test_cancelled_or_negated_report_request_does_not_create_a_ticket(): void
    {
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($id, ['content' => 'Đừng gửi báo cáo, tôi muốn thử thêm'])->assertOk();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_idempotency_covers_initial_session_and_message_payload_mismatch(): void
    {
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $key = (string) Str::uuid();
        $first = $this->start($context, $key)->assertCreated();
        $this->start($context, $key)->assertCreated()->assertJsonPath('data.session.id', $first->json('data.session.id'));
        $this->start($context + ['content' => 'Nội dung khác'], $key)->assertUnprocessable();
        $this->assertDatabaseCount('support_sessions', 1);
        Http::assertSentCount(1);
    }

    public function test_client_cannot_submit_forged_facts_or_use_another_users_conversation(): void
    {
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($id, ['messages' => [['role' => 'user', 'content' => 'fake']], 'user_id' => 999])->assertUnprocessable();
        $this->actingAs($this->actor())->postJson("/api/support-sessions/$id/escalations", ['expected_version' => 1], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_stale_version_is_rejected_without_persisting_a_new_message(): void
    {
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $count = DB::table('support_messages')->count();
        $this->send($id, ['expected_version' => 0])->assertStatus(409);
        $this->assertSame($count, DB::table('support_messages')->count());
    }

    public function test_ai_cannot_claim_that_it_created_a_ticket(): void
    {
        config(['services.openai.key' => 'fake-key']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['reply' => 'Đã gửi báo cáo và tạo phiếu HD-FAKE cho bạn.', 'draft' => null])]]]]])]);
        $this->actingAs($this->actor());
        $res = $this->start($this->context())->assertCreated();
        $this->assertStringNotContainsString('HD-FAKE', $res->json('data.messages.1.content_text'));
        $this->assertDatabaseCount('tickets', 0);
    }

    public static function activeIncidents(): array
    {
        $cases = [];
        foreach (['new', 'reopened', 'assigned', 'in_progress'] as $status) {
            foreach ([false, true] as $otherReporter) {
                $cases[] = [$status, $otherReporter];
            }
        }

        return $cases;
    }

    #[DataProvider('activeIncidents')]
    public function test_existing_active_incident_prevents_duplicate_ai_reports(string $status, bool $otherReporter): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $admin = $this->actor('admin');
        $this->actingAs($this->actor());
        $first = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($first, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $ticket = Ticket::sole();
        $ticket->update(['status' => $status]);
        if ($otherReporter) {
            $this->actingAs($this->actor());
        }
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $key = (string) Str::uuid();
        $payload = ['content' => 'Gửi báo cáo giúp tôi', 'expected_version' => SupportSession::findOrFail($id)->version];
        $response = $this->send($id, $payload, $key)->assertOk()
            ->assertJsonPath('data.report', null)
            ->assertJsonPath('data.session.status', 'collecting')
            ->assertJsonPath('data.session.report_requested', false);
        $messages = $response->json('data.messages');
        $this->assertStringContainsString('chưa được xử lý xong', end($messages)['content_text']);
        $this->assertStringNotContainsString($ticket->ticket_number, json_encode($response->json('data')));
        $this->send($id, $payload, $key)->assertOk()->assertJsonPath('data.messages', $messages);
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertOk();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('support_reports', 1);
        $this->assertSame(1, $admin->notifications()->count());
        Http::assertNothingSent();
    }

    public static function completedIncidents(): array
    {
        return [['resolved'], ['closed']];
    }

    public function test_ai_also_blocks_a_duplicate_of_a_manual_report(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $this->actingAs($this->actor());
        $this->postJson('/api/tickets', $context + ['room_code' => 'A203', 'title' => 'Máy chiếu không có hình',
            'description' => 'Máy chiếu không có hình'])->assertCreated();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertOk()->assertJsonPath('data.report', null);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('support_reports', 0);
        Http::assertNothingSent();
    }

    public static function distinctIncidents(): array
    {
        return [['room_id'], ['category_id']];
    }

    #[DataProvider('distinctIncidents')]
    public function test_ai_does_not_block_a_report_for_a_different_room_or_category(string $field): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $this->actingAs($this->actor());
        $first = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($first, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $replacement = $field === 'room_id'
            ? Room::create(['building_id' => Building::sole()->id, 'code' => 'A204', 'name' => 'Phòng A204', 'qr_code' => 'QR_A204'])
            : Category::create(['name' => 'Micro', 'color' => '#123456']);
        $context[$field] = $replacement->id;
        $second = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($second, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('support_reports', 2);
        Http::assertNothingSent();
    }

    #[DataProvider('completedIncidents')]
    public function test_ai_can_report_again_after_the_previous_incident_is_completed(string $status): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $this->actingAs($this->actor());
        $first = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($first, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $second = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($second, ['content' => 'Gửi báo cáo giúp tôi'])->assertOk();
        Ticket::sole()->update(['status' => $status]);
        $this->send($second, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated()->assertJsonPath('data.session.status', 'handed_off');
        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('support_reports', 2);
        Http::assertNothingSent();
    }

    public function test_resolved_confirmation_does_not_send_a_report_or_call_ai_again(): void
    {
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $this->send($id, ['content' => 'Đã khắc phục được', 'action' => 'resolve'])->assertOk()->assertJsonPath('data.session.status', 'resolved');
        $this->assertDatabaseCount('tickets', 0);
        Http::assertSentCount(1);
    }

    public function test_failed_handoff_keeps_receipt_and_retry_completes_once(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $admin = $this->actor('admin');
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $this->app->bind(ConversationReportService::class, fn () => new class extends ConversationReportService
        {
            public function create(SupportSession $session, User $actor): SupportReport
            {
                parent::create($session, $actor);
                throw new \RuntimeException('Simulated crash before completion commit');
            }
        });
        $key = (string) Str::uuid();
        $pending = $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'], $key)->assertStatus(503)->assertJsonPath('data.session.status', 'handoff_pending');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('support_reports', 0);
        $this->assertSame(0, $admin->notifications()->count());
        $this->getJson($pending->json('data.command.status_url'))->assertOk()->assertJsonPath('data.command.status', 'handoff_pending');
        $this->app->bind(ConversationReportService::class, fn () => new ConversationReportService);
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'], $key)->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_late_ai_reply_cannot_revive_a_handed_off_conversation(): void
    {
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        Http::fake(['api.openai.com/*' => function () use ($id) {
            $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();

            return Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['reply' => 'LATE_AI_REPLY', 'draft' => null])]]]]]);
        }]);
        $this->send($id)->assertOk()->assertJsonPath('data.session.status', 'handed_off');
        $this->assertDatabaseMissing('support_messages', ['content_text' => 'LATE_AI_REPLY']);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_ai_limit_does_not_block_the_report_path(): void
    {
        config(['services.helpdesk_ai.max_turns_per_session' => 5, 'services.helpdesk_ai.max_turns_per_minute' => 50]);
        $this->ai();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        for ($i = 0; $i < 5; $i++) {
            $this->send($id)->assertOk();
        }
        Http::assertSentCount(5);
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
        Http::assertSentCount(5);
    }

    public function test_default_session_allows_forty_ai_turns_and_then_keeps_report_available(): void
    {
        $this->ai();
        config(['services.helpdesk_ai.max_turns_per_minute' => 100]);
        $this->actingAs($this->actor());
        $id = $this->start($this->context())->assertCreated()->json('data.session.id');
        for ($i = 1; $i < 40; $i++) {
            if ($i % 10 === 0) {
                $this->travel(61)->seconds();
            }
            $this->send($id, ['content' => 'Thông tin kiểm tra lượt '.$i])->assertOk();
        }
        Http::assertSentCount(40);
        $this->send($id)->assertOk();
        Http::assertSentCount(40);
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_per_minute_limit_recovers_without_ending_the_session(): void
    {
        $this->ai();
        config(['services.helpdesk_ai.max_turns_per_minute' => 2]);
        $this->actingAs($this->actor());
        $id = $this->start($this->context())->assertCreated()->json('data.session.id');
        $this->send($id)->assertOk();
        $limited = $this->send($id)->assertOk();
        $messages = $limited->json('data.messages');
        $this->assertStringContainsString('một phút', end($messages)['content_text']);
        Http::assertSentCount(2);
        $this->travel(61)->seconds();
        $this->send($id)->assertOk();
        Http::assertSentCount(3);
    }

    public function test_long_chat_keeps_the_initial_problem_and_latest_message_in_bounded_model_history(): void
    {
        $this->ai();
        $this->actingAs($this->actor());
        $problem = 'Máy chiếu A203 bật không lên, đèn báo không sáng.';
        $id = $this->start($this->context() + ['content' => $problem])->assertCreated()->json('data.session.id');
        for ($i = 0; $i < 8; $i++) {
            $last = 'Kết quả lượt '.$i.': '.str_repeat('đã kiểm tra. ', 75);
            $this->send($id, ['content' => $last])->assertOk();
        }
        $request = Http::recorded()->last()[0];
        $history = $request['input'];
        $this->assertSame($problem, $history[0]['content']);
        $this->assertSame(trim($last), end($history)['content']);
        $this->assertLessThanOrEqual(6000, array_sum(array_map(fn ($message) => mb_strlen($message['content']), $history)));
        $this->assertLessThan(11, count($history));
        Http::assertSentCount(9);
    }

    public function test_default_message_cap_is_two_hundred_and_report_still_bypasses_it(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $this->actingAs($this->actor());
        $id = $this->start($this->context())->assertCreated()->json('data.session.id');
        $session = SupportSession::findOrFail($id);
        for ($i = 0; $i < 148; $i++) {
            $session->messages()->create(['role' => 'assistant', 'content_text' => 'Lịch sử kiểm thử']);
        }
        $this->send($id)->assertOk();
        for ($i = 0; $i < 48; $i++) {
            $session->messages()->create(['role' => 'assistant', 'content_text' => 'Lịch sử kiểm thử']);
        }
        $this->assertSame(200, $session->messages()->count());
        $this->send($id)->assertUnprocessable();
        $this->assertSame(200, $session->messages()->count());
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
        Http::assertNothingSent();
    }

    public function test_report_request_without_incident_description_asks_for_the_problem(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $this->actingAs($this->actor());
        $res = $this->start($context + ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $this->assertDatabaseCount('tickets', 0);
        $res->assertJsonPath('data.needs_description', true);
        $this->send($res->json('data.session.id'), ['content' => 'Máy chiếu không có hình'])->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
        Http::assertNothingSent();
    }

    public function test_message_limit_does_not_prevent_direct_report(): void
    {
        config(['services.helpdesk_ai.max_messages_per_session' => 100]);
        config(['services.openai.key' => null]);
        Http::fake();
        $context = $this->context();
        $this->actingAs($this->actor());
        $id = $this->start($context)->assertCreated()->json('data.session.id');
        $session = SupportSession::findOrFail($id);
        for ($i = 0; $i < 100; $i++) {
            $session->messages()->create(['role' => 'assistant', 'content_text' => 'Lịch sử kiểm thử']);
        }
        $this->send($id, ['content' => 'Gửi báo cáo giúp tôi'])->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
        Http::assertNothingSent();
    }
}
