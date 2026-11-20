<?php

namespace Tests\Feature\Support;

use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\ConversationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportDiagnosticFlowTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $categoryName = 'Máy tính'): array
    {
        $this->actingAs(User::create(['name' => 'Diagnostic test', 'email' => 'diagnostic@example.test', 'password' => 'password', 'role' => 'teacher', 'email_verified_at' => now()]));
        $building = Building::create(['code' => 'A', 'name' => 'Tòa A']);
        $room = Room::create(['building_id' => $building->id, 'code' => 'A201', 'name' => 'Phòng A201']);
        $category = Category::create(['name' => $categoryName, 'color' => '#123456']);
        Notification::fake();
        Http::preventStrayRequests();
        config(['services.openai.key' => 'fake-key']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['reply' => 'Hãy kiểm tra lại nguồn điện và dây nguồn.', 'draft' => null])]]]]])]);

        return ['room_id' => $room->id, 'category_id' => $category->id];
    }

    private function start(array $input)
    {
        return $this->postJson('/api/support-conversations', $input, ['Idempotency-Key' => (string) Str::uuid()]);
    }

    private function send(int $id, string $content, array $extra = [], ?string $key = null)
    {
        return $this->postJson("/api/support-sessions/$id/messages", ['content' => $content, 'expected_version' => SupportSession::findOrFail($id)->version] + $extra, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public static function handoffRequests(): array
    {
        return [['hãy báo cáo lên KTV cho mình'], ['báo lên kỹ thuật viên giúp tôi'], ['nhờ IT xử lý giúp mình'],
            ['mình muốn gặp kỹ thuật viên'], ['gọi bộ phận IT giúp tôi'], ['Chuyển sự cố cho KTV giúp mình'],
            ['Máy không lên, hãy báo cáo lên KTV cho mình'], ['Gửi báo cáo giúp tôi, nguồn điện bình thường nhưng máy không lên'],
            ['Báo lên KTV giúp mình được không?']];
    }

    #[DataProvider('handoffRequests')]
    public function test_natural_handoff_requests_create_one_report_without_another_model_call(string $request): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên, đã cắm điện'])->assertCreated()->json('data.session.id');
        $key = (string) Str::uuid();
        $this->send($id, $request, key: $key)->assertCreated()->assertJsonPath('data.session.status', 'handed_off');
        $this->send($id, $request, key: $key)->assertCreated();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('support_reports', 1);
        Http::assertSentCount(1);
    }

    public static function nonRequests(): array
    {
        return [['Đừng báo lên KTV cho mình'], ['Tôi chưa cần gọi IT'], ['Nếu vẫn lỗi thì báo lên KTV'],
            ['Bạn đã báo cáo lên KTV chưa?'], ['Tại sao phải báo lên KTV?'], ['Gửi báo cáo, nhưng đừng gửi cho IT bây giờ'],
            ['Báo cáo lên KTV là gì?'], ['Gọi IT có mất phí không?'], ['Gửi báo cáo bằng cách nào?']];
    }

    #[DataProvider('nonRequests')]
    public function test_negative_hypothetical_and_status_questions_are_not_handoff_requests(string $content): void
    {
        $this->assertFalse(app(ConversationContext::class)->wantsReport($content));
    }

    public function test_power_confirmed_normal_advances_to_observation_instead_of_repeating_the_model(): void
    {
        $context = $this->context();
        $first = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated();
        $id = $first->json('data.session.id');
        $next = $this->send($id, 'nguồn điện bình thường')->assertOk();
        $reply = collect($next->json('data.messages'))->last()['content_text'];
        $this->assertStringNotContainsString('kiểm tra lại nguồn điện', $reply);
        $this->assertStringContainsString('đèn báo', $reply);
        $next->assertJsonPath('data.diagnosis.branch', 'computer_power')->assertJsonPath('data.diagnosis.next_question.id', 'power_indicator');
        $observations = collect($next->json('data.diagnosis.observations'))->keyBy('id');
        $this->assertSame('reported_ok', $observations['power_connection']['status']);
        $this->assertSame('nguồn điện bình thường', $observations['power_connection']['evidence']);
    }

    public function test_short_answers_are_bound_to_the_last_question_and_questions_are_not_repeated(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên, nguồn điện bình thường'])->assertCreated()->json('data.session.id');
        $this->send($id, 'không')->assertOk()->assertJsonPath('data.diagnosis.next_question.id', 'startup_sound');
        $last = $this->send($id, 'không nghe tiếng quạt')->assertOk()->assertJsonPath('data.diagnosis.next_question', null);
        $this->assertStringContainsString('kỹ thuật viên', collect($last->json('data.messages'))->last()['content_text']);
        $this->assertDatabaseCount('tickets', 0);
        $again = $this->send($id, 'vẫn không được')->assertOk()->assertJsonPath('data.diagnosis.next_question', null);
        $this->assertStringNotContainsString('Hãy kiểm tra lại', collect($again->json('data.messages'))->last()['content_text']);
    }

    public function test_assistant_suggestions_do_not_count_as_confirmed_user_checks(): void
    {
        $context = $this->context();
        $response = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated();
        $this->assertEmpty($response->json('data.diagnosis.observations'));
        $response->assertJsonPath('data.diagnosis.next_question.id', 'power_connection');
    }

    public function test_unknown_answer_does_not_become_a_confirmation_or_restart_the_same_step(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated()->json('data.session.id');
        $res = $this->send($id, 'mình không biết')->assertOk();
        $res->assertJsonPath('data.diagnosis.next_question.id', 'power_indicator');
        $this->assertNotContains('reported_ok', array_column($res->json('data.diagnosis.observations'), 'status'));
    }

    public function test_handoff_includes_observed_results_unknowns_and_unverified_it_suggestions(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated()->json('data.session.id');
        $this->send($id, 'nguồn điện bình thường')->assertOk();
        $this->send($id, 'hãy báo cáo lên KTV cho mình')->assertCreated();
        $description = Ticket::sole()->description;
        $this->assertStringContainsString('PHÂN NHÁNH SƠ BỘ', $description);
        $this->assertStringContainsString('nguồn điện bình thường', $description);
        $this->assertStringContainsString('CHƯA XÁC ĐỊNH', $description);
        $this->assertStringContainsString('chưa kết luận', $description);
        $this->assertStringNotContainsString('hỏng bộ nguồn', $description);
        $this->assertNotContains('hãy báo cáo lên KTV cho mình', app(ConversationContext::class)->incidentFacts(SupportSession::findOrFail($id))->all());
    }

    public function test_changing_room_does_not_reuse_observations_from_the_previous_room(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên, nguồn điện bình thường'])->assertCreated()->json('data.session.id');
        $other = Room::create(['building_id' => Building::sole()->id, 'code' => 'A202', 'name' => 'Phòng A202']);
        $res = $this->send($id, 'Máy tính phòng khác cũng không mở lên', ['room_id' => $other->id])->assertOk();
        $res->assertJsonPath('data.diagnosis.next_question.id', 'power_connection');
        $this->assertEmpty($res->json('data.diagnosis.observations'));
    }

    public static function branches(): array
    {
        return [['Máy chiếu', 'Máy chiếu không lên nguồn', 'projector_power', 'power_connection'],
            ['Máy tính', 'Máy tính màn hình đen, báo No signal', 'computer_display', 'display_indicator'],
            ['Mạng / Wi-Fi', 'Wi-Fi không kết nối được', 'network_connection', 'network_visible'],
            ['Loa', 'Loa mất tiếng hoàn toàn', 'audio_output', 'audio_scope']];
    }

    #[DataProvider('branches')]
    public function test_observation_branch_follows_the_device_and_symptom(string $category, string $problem, string $branch, string $question): void
    {
        $context = $this->context($category);
        $this->start($context + ['content' => $problem])->assertCreated()->assertJsonPath('data.diagnosis.branch', $branch)->assertJsonPath('data.diagnosis.next_question.id', $question);
    }

    public function test_free_text_answer_to_an_open_question_is_preserved_as_a_reported_observation(): void
    {
        $context = $this->context('Loa');
        $id = $this->start($context + ['content' => 'Loa mất tiếng hoàn toàn'])->assertCreated()->json('data.session.id');
        $res = $this->send($id, 'Chỉ xảy ra với ứng dụng trình chiếu')->assertOk();
        $observation = collect($res->json('data.diagnosis.observations'))->firstWhere('id', 'audio_scope');
        $this->assertSame('reported', $observation['status']);
        $this->assertSame('Chỉ xảy ra với ứng dụng trình chiếu', $observation['evidence']);
    }

    public function test_hazard_stops_observation_questions_before_calling_the_model_even_without_intake(): void
    {
        $this->context();
        $res = $this->start(['content' => 'Máy tính có mùi khét và bốc khói'])->assertCreated();
        $res->assertJsonPath('data.diagnosis.stage', 'safety_stop')->assertJsonPath('data.diagnosis.next_question', null);
        $this->assertStringContainsString('tránh xa', collect($res->json('data.messages'))->last()['content_text']);
        Http::assertNothingSent();
    }

    public function test_model_receives_structured_confirmations_even_when_the_old_message_leaves_bounded_history(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated()->json('data.session.id');
        $this->send($id, 'Mình không rõ')->assertOk();
        $this->send($id, 'nguồn điện bình thường')->assertOk();
        $session = SupportSession::findOrFail($id);
        for ($i = 0; $i < 12; $i++) {
            $session->messages()->create(['role' => 'user', 'content_text' => 'Thông tin bổ sung '.$i]);
            $session->messages()->create(['role' => 'assistant', 'content_text' => 'Đã ghi nhận thông tin.']);
        }
        $this->send($id, 'vẫn như vậy')->assertOk();
        $request = Http::recorded()->last()[0];
        $this->assertStringNotContainsString('nguồn điện bình thường', json_encode($request['input'], JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('diagnostic_context', $request['instructions']);
        $this->assertStringContainsString('nguồn điện bình thường', $request['instructions']);
    }

    public static function powerResults(): array
    {
        return [['nguồn điện bình thường', 'reported_ok'], ['nguồn điện không có vấn đề', 'reported_ok'],
            ['không có nguồn điện', 'reported_absent'], ['nguồn điện không bình thường', 'reported_absent'],
            ['chưa kiểm tra nguồn điện', 'unknown'], ['có lẽ nguồn điện bình thường', 'unknown'],
            ['AI nói nguồn điện bình thường', 'unknown'], ['nguồn điện bình thường không?', 'unknown']];
    }

    #[DataProvider('powerResults')]
    public function test_power_results_distinguish_negation_uncertainty_and_quoted_advice(string $answer, string $status): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated()->json('data.session.id');
        $res = $this->send($id, $answer)->assertOk();
        $item = collect($res->json('data.diagnosis.observations'))->firstWhere('id', 'power_connection');
        $this->assertSame($status, $item['status']);
        if ($status === 'reported_absent') {
            $res->assertJsonPath('data.diagnosis.next_question', null);
        }
    }

    public function test_cancelling_a_report_does_not_hide_a_new_safety_observation(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated()->json('data.session.id');
        $res = $this->send($id, 'Đừng gửi báo cáo, máy có mùi khét')->assertOk()->assertJsonPath('data.diagnosis.stage', 'safety_stop');
        $alert = collect($res->json('data.diagnosis.observations'))->firstWhere('id', 'safety_alert');
        $this->assertNotNull($alert);
        $this->assertStringContainsString('mùi khét', $alert['evidence']);
        Http::assertSentCount(1);
        $this->send($id, 'hãy báo cáo lên KTV cho mình')->assertCreated();
        $sections = explode('LỜI BÁO GỐC', Ticket::sole()->description);
        $this->assertStringContainsString('mùi khét', $sections[0]);
        Http::assertSentCount(1);
    }

    public function test_receipt_question_ids_preserve_answer_binding_when_question_wording_changes(): void
    {
        $context = $this->context();
        $id = $this->start($context + ['content' => 'Máy tính không mở lên'])->assertCreated()->json('data.session.id');
        // Simulate a previous deployment's wording with the same persisted step ID.
        SupportSession::findOrFail($id)->messages()->where('role', 'assistant')->update(['content_text' => 'Nguồn cấp bên ngoài có hoạt động không?']);
        $res = $this->send($id, 'có')->assertOk()->assertJsonPath('data.diagnosis.next_question.id', 'power_indicator');
        $item = collect($res->json('data.diagnosis.observations'))->firstWhere('id', 'power_connection');
        $this->assertSame('reported_ok', $item['status']);
    }
}
