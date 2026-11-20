<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\SupportReport;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\HelpdeskAiService;
use App\Services\Support\ConversationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportHandoffBriefTest extends TestCase
{
    use RefreshDatabase;

    private const PROBLEM = 'Máy chiếu không có hình. Đã đổi dây HDMI nhưng vẫn báo No Signal.';

    private function actor(string $role = 'teacher'): User
    {
        return User::create(['name' => 'Brief '.$role, 'email' => Str::uuid().'@example.test', 'password' => 'password', 'role' => $role, 'email_verified_at' => now()]);
    }

    private function start(bool $withContext = true, string $content = self::PROBLEM): SupportSession
    {
        $building = Building::create(['code' => 'A', 'name' => 'Tòa A']);
        $room = Room::create(['building_id' => $building->id, 'code' => 'A203', 'name' => 'Phòng A203', 'qr_code' => 'QR_A203']);
        $category = Category::create(['name' => 'Máy chiếu', 'color' => '#123456']);
        $response = $this->actingAs($this->actor())->postJson('/api/support-conversations', [
            'content' => $content,
            ...($withContext ? ['room_id' => $room->id, 'category_id' => $category->id] : []),
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        return SupportSession::findOrFail($response->json('data.session.id'));
    }

    private function fakeAi(mixed $brief): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text',
            'text' => json_encode(['reply' => 'Bạn thử chọn nguồn vào phù hợp trên laptop nhé.', 'draft' => null, 'handoff_brief' => $brief])]]]]])]);
    }

    private function brief(): array
    {
        return ['summary' => 'Máy chiếu mất tín hiệu hình ảnh; thay dây HDMI chưa khắc phục được.',
            'reported_checks' => ['Đã đổi dây HDMI nhưng vẫn báo No Signal.'],
            'suggestions' => ['KTV đối chiếu nguồn vào đã chọn với cổng kết nối thực tế.']];
    }

    private function report(SupportSession $session, ?string $key = null)
    {
        return $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => 'Gửi báo cáo giúp tôi', 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => $key ?? (string) Str::uuid()])->assertCreated();
    }

    public function test_assigned_technician_sees_summary_checks_and_suggestions_with_original_facts(): void
    {
        $this->fakeAi($this->brief());
        $session = $this->start();
        $key = (string) Str::uuid();
        $this->report($session, $key);
        $this->report($session, $key);
        $ticket = Ticket::sole();
        $this->assertStringContainsString('TÓM TẮT AI — CHƯA ĐƯỢC KTV XÁC MINH', $ticket->description);
        $this->assertStringContainsString($this->brief()['summary'], $ticket->description);
        $this->assertStringContainsString('KIỂM TRA/KẾT QUẢ NGƯỜI DÙNG ĐÃ NÊU', $ticket->description);
        $this->assertStringContainsString($this->brief()['suggestions'][0], $ticket->description);
        $this->assertStringContainsString('LỜI BÁO GỐC', $ticket->description);
        $this->assertStringContainsString(self::PROBLEM, $ticket->description);
        $this->assertSame(self::PROBLEM, SupportReport::sole()->transcript[0]['content_text']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['text']['format']['schema']['properties']['handoff_brief']['type'] === 'object');
        Http::assertSent(fn ($request) => $request['text']['format']['schema']['properties']['handoff_brief']['properties']['reported_checks']['items']['enum'] === ['U1']);
        $staff = $this->actor('staff');
        $ticket->update(['assigned_to_id' => $staff->id, 'status' => 'assigned']);
        $this->actingAs($staff)->getJson('/api/tickets/'.$ticket->id)->assertOk()->assertJsonPath('data.description', $ticket->description);
        $this->get('/staff/ticket-detail?ticket='.$ticket->id)->assertOk()->assertSee($this->brief()['summary']);
        $this->actingAs($this->actor('staff'))->getJson('/api/tickets/'.$ticket->id)->assertForbidden();
    }

    public function test_new_facts_invalidate_a_previous_ai_brief_without_calling_ai_at_handoff(): void
    {
        $this->fakeAi($this->brief());
        $session = $this->start();
        config(['services.openai.key' => null]);
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => 'Thực ra micro cũng mất tiếng, cần kiểm tra thêm.', 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $this->report($session);
        $description = Ticket::sole()->description;
        $this->assertStringNotContainsString($this->brief()['summary'], $description);
        $this->assertStringContainsString('TỔNG HỢP TỪ LỜI BÁO', $description);
        $this->assertStringContainsString('micro cũng mất tiếng', $description);
        $this->assertStringContainsString('GỢI Ý KIỂM TRA BAN ĐẦU', $description);
        Http::assertSentCount(1);
    }

    public static function reportCommands(): array
    {
        return [
            ['Gửi báo cáo giúp tôi'],
            ['bạn hãy báo cáo giúp mình nhá'],
            ['AI ơi gửi báo cáo giúp mình nhé!'],
            ['Làm ơn tạo giúp tôi phiếu'],
        ];
    }

    #[DataProvider('reportCommands')]
    public function test_report_commands_preserve_the_brief_and_do_not_become_incident_facts(string $content): void
    {
        $this->fakeAi($this->brief());
        $session = $this->start();
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => $content, 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $this->assertSame([self::PROBLEM], app(ConversationContext::class)->incidentFacts($session)->all());
        $this->assertStringContainsString($this->brief()['summary'], Ticket::sole()->description);
        $this->assertStringContainsString($this->brief()['suggestions'][0], Ticket::sole()->description);
        $this->assertSame($content, collect(SupportReport::sole()->transcript)->where('role', 'user')->last()['content_text']);
        Http::assertSentCount(1);
    }

    public static function incompleteContexts(): array
    {
        return [[self::PROBLEM], ['Thiết bị không hoạt động.']];
    }

    #[DataProvider('incompleteContexts')]
    public function test_completing_missing_context_for_a_direct_report_uses_fallback_without_calling_ai(string $content): void
    {
        $brief = ['summary' => 'Thiết bị không hoạt động; cần KTV kiểm tra.', 'reported_checks' => [],
            'suggestions' => ['KTV đối chiếu dấu hiệu lỗi tại phòng.']];
        $this->fakeAi($brief);
        $session = $this->start(withContext: false, content: $content);
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => 'bạn hãy báo cáo giúp mình nhá', 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.needs_context', true);
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => 'Ở phòng A203, máy chiếu', 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $this->assertStringContainsString('TỔNG HỢP TỪ LỜI BÁO', Ticket::sole()->description);
        $this->assertStringContainsString($content, Ticket::sole()->description);
        $this->assertStringNotContainsString('TÓM TẮT AI', Ticket::sole()->description);
        Http::assertNothingSent();
    }

    public static function changedContextFields(): array
    {
        return [['room_id'], ['category_id']];
    }

    #[DataProvider('changedContextFields')]
    public function test_changing_previously_known_context_still_invalidates_the_brief(string $field): void
    {
        $this->fakeAi($this->brief());
        $session = $this->start();
        $replacement = $field === 'room_id'
            ? Room::create(['building_id' => Room::findOrFail($session->room_id)->building_id, 'code' => 'A204', 'name' => 'Phòng A204', 'qr_code' => 'QR_A204'])
            : Category::create(['name' => 'Micro', 'color' => '#123456']);
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => 'Gửi báo cáo giúp tôi', $field => $replacement->id, 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $this->assertStringNotContainsString($this->brief()['summary'], Ticket::sole()->description);
        $this->assertStringContainsString('TỔNG HỢP TỪ LỜI BÁO', Ticket::sole()->description);
        Http::assertSentCount(1);
    }

    public static function nonCommands(): array
    {
        return [
            ['Đừng gửi báo cáo, tôi muốn thử thêm'],
            ['Nếu vẫn lỗi thì gửi báo cáo giúp tôi'],
            ['Bạn đã gửi báo cáo giúp tôi chưa?'],
        ];
    }

    #[DataProvider('nonCommands')]
    public function test_negated_conditional_questions_and_mixed_facts_are_not_pure_report_commands(string $content): void
    {
        $this->assertFalse(app(ConversationContext::class)->wantsReport($content));
    }

    public function test_new_incident_facts_in_a_report_message_are_preserved_and_invalidate_the_brief(): void
    {
        $this->fakeAi($this->brief());
        $session = $this->start();
        $content = 'Gửi báo cáo giúp tôi, máy chiếu có mùi khét';
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => $content, 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $this->assertStringContainsString($content, Ticket::sole()->description);
        $this->assertStringNotContainsString($this->brief()['summary'], Ticket::sole()->description);
        Http::assertSentCount(1);
    }

    public function test_ai_cannot_claim_an_unconfirmed_check_as_a_user_result(): void
    {
        $brief = $this->brief();
        $brief['reported_checks'][] = 'Đã thay bóng đèn và xác nhận bo nguồn bị cháy.';
        $this->fakeAi($brief);
        $session = $this->start();
        $this->report($session);
        $description = Ticket::sole()->description;
        $this->assertStringNotContainsString('bo nguồn bị cháy', $description);
        $this->assertStringContainsString('Đã đổi dây HDMI nhưng vẫn báo No Signal.', $description);
        $this->assertStringNotContainsString($this->brief()['summary'], $description);
        $this->assertStringContainsString('TỔNG HỢP TỪ LỜI BÁO', $description);
    }

    public function test_bad_brief_does_not_break_chat_or_report(): void
    {
        $this->fakeAi(['summary' => str_repeat('a', 601), 'reported_checks' => [], 'suggestions' => 'not-an-array']);
        $session = $this->start();
        $this->assertStringContainsString('đèn nguồn', mb_strtolower($session->messages()->where('role', 'assistant')->value('content_text')));
        $this->report($session);
        $this->assertStringContainsString('TỔNG HỢP TỪ LỜI BÁO', Ticket::sole()->description);
    }

    public function test_quota_fallback_summarizes_session_seven_instead_of_the_greeting(): void
    {
        config(['services.helpdesk_ai.max_turns_per_session' => 5, 'services.helpdesk_ai.max_turns_per_minute' => 50]);
        $this->fakeAi(['summary' => 'Máy chiếu không bật lên.', 'reported_checks' => [], 'suggestions' => ['Kiểm tra máy chiếu.']]);
        $session = $this->start(content: 'tôi cần bạn giúp');
        $contents = ["phòng mình bị hư máy chiéu , mình bật mà nó không lên \n'", 'kiểm tra rồi',
            'kiểm tra rồi đã cấm điện', 'nguồn điện oke hêt rồi', 'không tháy sáng đèn'];
        foreach ($contents as $content) {
            $this->postJson("/api/support-sessions/{$session->id}/messages", [
                'content' => $content, 'expected_version' => $session->fresh()->version,
            ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        }
        $electrical = Category::create(['name' => 'Điện & chiếu sáng', 'color' => '#123456']);
        $this->postJson("/api/support-sessions/{$session->id}/messages", [
            'content' => 'Gửi báo cáo giúp tôi', 'category_id' => $electrical->id, 'expected_version' => $session->fresh()->version,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $ticket = Ticket::sole();
        $sections = explode('LỜI BÁO GỐC', $ticket->description);
        $this->assertStringContainsString('mình bật mà nó không lên', $sections[0]);
        $this->assertStringContainsString('không tháy sáng đèn', $sections[0]);
        $this->assertStringContainsString('kiểm tra rồi đã cấm điện', $sections[0]);
        $this->assertStringContainsString('nguồn điện oke hêt rồi', $sections[0]);
        $checks = explode('GỢI Ý KIỂM TRA BAN ĐẦU', explode('KIỂM TRA/KẾT QUẢ NGƯỜI DÙNG ĐÃ NÊU', $sections[0])[1])[0];
        $this->assertStringContainsString('nguồn điện oke hêt rồi', $checks);
        $this->assertStringNotContainsString('tôi cần bạn giúp', $sections[0]);
        $this->assertStringNotContainsString('tôi cần bạn giúp', $ticket->title);
        $this->assertStringContainsString('Máy chiếu', $sections[0]);
        $this->assertStringContainsString('CẦN ĐỐI CHIẾU DANH MỤC', $sections[0]);
        $this->assertStringContainsString('đèn báo', $sections[0]);
        $this->assertStringContainsString('tôi cần bạn giúp', $sections[1]);
        $this->assertSame($electrical->id, $ticket->category_id);
        Http::assertSentCount(5);
    }

    public function test_whitespace_normalized_source_quotes_remain_valid_at_handoff(): void
    {
        $problem = "Máy chiếu không bật.\nĐã thử cắm điện.  Không sáng đèn.";
        $brief = ['summary' => 'Máy chiếu không khởi động.', 'reported_checks' => ['U1'], 'suggestions' => ['KTV kiểm tra đèn báo.']];
        $this->fakeAi($brief);
        $session = $this->start(content: $problem);
        $this->report($session);
        $this->assertStringContainsString('TÓM TẮT AI', Ticket::sole()->description);
        $this->assertStringContainsString('Máy chiếu không khởi động.', Ticket::sole()->description);
        Http::assertSentCount(1);
    }

    public function test_fallback_does_not_present_assistant_advice_as_completed_checks(): void
    {
        $this->fakeAi(null);
        $session = $this->start(content: 'Máy chiếu không bật lên.');
        $session->messages()->create(['role' => 'assistant', 'content_text' => 'Bạn đã thay dây nguồn và đo điện áp chưa?']);
        $this->report($session);
        $checks = explode('GỢI Ý KIỂM TRA BAN ĐẦU', explode('KIỂM TRA/KẾT QUẢ NGƯỜI DÙNG ĐÃ NÊU', Ticket::sole()->description)[1])[0];
        $this->assertStringNotContainsString('thay dây nguồn', $checks);
        $this->assertStringNotContainsString('đo điện áp', $checks);
        $this->assertStringContainsString('Chưa có', $checks);
    }

    public function test_brief_contract_is_requested_from_the_local_model(): void
    {
        config(['services.helpdesk_ai.provider' => 'ollama', 'services.ollama.url' => 'http://127.0.0.1:11434', 'services.ollama.model' => 'qwen2.5:7b']);
        $modelBrief = $this->brief();
        $modelBrief['reported_checks'] = ['U1'];
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response(['done' => true, 'message' => [
            'content' => json_encode(['reply' => 'Mình sẽ hỗ trợ kiểm tra.', 'draft' => null, 'handoff_brief' => $modelBrief]),
        ]])]);
        $result = app(HelpdeskAiService::class)->reply([['role' => 'user', 'content' => self::PROBLEM]]);
        $expected = $this->brief();
        $expected['reported_checks'] = [self::PROBLEM];
        $this->assertSame($expected, $result['handoff_brief'] ?? null);
        Http::assertSent(fn ($request) => isset($request['format']['properties']['handoff_brief']));
    }

    public function test_long_original_conversation_is_preserved_while_ticket_description_is_bounded(): void
    {
        $this->fakeAi($this->brief());
        $session = $this->start();
        for ($i = 0; $i < 8; $i++) {
            $session->messages()->create(['role' => 'user', 'content_text' => str_repeat('Thông tin kiểm tra bổ sung. ', 30)]);
        }
        $this->report($session);
        $this->assertLessThanOrEqual(5000, mb_strlen(Ticket::sole()->description));
        $this->assertStringContainsString('Phần trích đã được rút gọn', Ticket::sole()->description);
        $this->assertCount(11, SupportReport::sole()->transcript);
        Http::assertSentCount(1);
    }
}
