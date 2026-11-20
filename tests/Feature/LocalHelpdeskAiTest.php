<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\HelpdeskAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalHelpdeskAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.helpdesk_ai.provider' => 'ollama', 'services.openai.key' => null,
            'services.ollama.url' => 'http://127.0.0.1:11434', 'services.ollama.model' => 'qwen2.5:7b']);
        Http::preventStrayRequests();
    }

    public function test_local_model_replies_without_a_cloud_key_and_uses_structured_output(): void
    {
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true, 'message' => ['content' => json_encode(['reply' => 'Hãy kiểm tra chế độ trình chiếu trên laptop.', 'draft' => null])],
        ])]);
        $messages = [['role' => 'user', 'content' => 'Máy chiếu không có hình.']];

        $result = app(HelpdeskAiService::class)->reply($messages);

        $this->assertSame('Hãy kiểm tra chế độ trình chiếu trên laptop.', $result['reply'] ?? null);
        $this->assertNull($result['draft']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
            && ! $request->hasHeader('Authorization') && $request['model'] === 'qwen2.5:7b'
            && $request['stream'] === false && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1] === $messages[0]
            && $request['format']['type'] === 'object' && $request['options']['num_ctx'] === 4096);
    }

    public function test_local_failure_never_falls_back_to_the_cloud(): void
    {
        config(['services.openai.key' => 'unused-test-key']);
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::failedConnection()]);

        $result = app(HelpdeskAiService::class)->reply([['role' => 'user', 'content' => 'Máy chiếu bị lỗi.']]);

        $this->assertArrayHasKey('error', $result);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'openai.com'));
    }

    public function test_incomplete_and_invalid_local_responses_are_not_shown_as_replies(): void
    {
        foreach ([['done' => false, 'message' => ['content' => '{"reply":"incomplete"}']],
            ['done' => true, 'message' => ['content' => 'not json']], ['error' => 'model not found']] as $body) {
            Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response($body)]);
            $result = app(HelpdeskAiService::class)->reply([['role' => 'user', 'content' => 'Máy chiếu bị lỗi.']]);
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function test_unknown_provider_fails_without_sending_any_request(): void
    {
        config(['services.helpdesk_ai.provider' => 'typo', 'services.openai.key' => 'unused-test-key']);
        Http::fake();

        $this->assertArrayHasKey('error', app(HelpdeskAiService::class)->reply([['role' => 'user', 'content' => 'Xin chào.']]));
        Http::assertNothingSent();
    }

    public static function leakedReplies(): array
    {
        return [['Reported: Không vào được mạng (U2).'], ['Bạn kiểm tra kết nối nhé (U1).'], ['user_reports: U2'], ['handoff_brief: summary']];
    }

    #[DataProvider('leakedReplies')]
    public function test_support_replies_hide_internal_references_without_losing_a_valid_brief(string $reply): void
    {
        $brief = ['summary' => 'Không vào được mạng.', 'reported_checks' => [], 'suggestions' => ['Kiểm tra kết nối tại phòng.']];
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true, 'message' => ['content' => json_encode(['reply' => $reply, 'draft' => null, 'handoff_brief' => $brief])],
        ])]);
        $result = app(HelpdeskAiService::class)->reply([['role' => 'user', 'content' => 'Mình không vào được mạng']], prepareHandoff: true);

        $this->assertNotSame($reply, $result['reply']);
        $this->assertDoesNotMatchRegularExpression('/\bU[1-9][0-9]*\b|Reported:|user_reports|handoff_brief/', $result['reply']);
        $this->assertStringContainsString('gửi báo cáo', $result['reply']);
        $this->assertSame($brief, $result['handoff_brief']);
        Http::assertSentCount(1);
    }

    public function test_local_model_receives_the_selected_category_and_keeps_normal_support_reply(): void
    {
        $category = Category::create(['name' => 'Mạng / Wi-Fi', 'color' => '#123456']);
        $reply = 'Bạn kiểm tra xem thiết bị khác có kết nối được Wi-Fi không nhé.';
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true, 'message' => ['content' => json_encode(['reply' => $reply, 'draft' => null])],
        ])]);
        $result = app(HelpdeskAiService::class)->reply([['role' => 'user', 'content' => 'Không vào được mạng']], prepareHandoff: true, selectedCategory: $category);
        $this->assertSame($reply, $result['reply']);
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], '"danh_muc_da_chon":'.json_encode(['id' => $category->id, 'name' => $category->name], JSON_UNESCAPED_UNICODE)));
    }
}
