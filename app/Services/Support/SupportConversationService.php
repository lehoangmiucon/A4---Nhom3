<?php

namespace App\Services\Support;

use App\Models\Category;
use App\Models\Room;
use App\Models\SupportCommand;
use App\Models\SupportReport;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\HelpdeskAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupportConversationService
{
    public function execute(User $actor, ?SupportSession $session, array $input, string $key): array
    {
        $fingerprint = hash('sha256', json_encode([$session?->id, $input['content'], $input['room_id'] ?? null, $input['category_id'] ?? null, $input['action'] ?? 'chat']));
        $command = DB::transaction(function () use ($actor, $session, $input, $key, $fingerprint) {
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize('create', SupportSession::class);
            if ($existing = SupportCommand::where('actor_id', $actor->id)->where('key_hash', hash('sha256', $key))->first()) {
                Gate::forUser($lockedActor)->authorize('view', SupportSession::findOrFail($existing->session_id));
                if (! hash_equals($existing->request_hash, $fingerprint)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'IDEMPOTENCY_KEY_REUSED']);
                }

                return $existing;
            }
            $current = $session ? SupportSession::whereKey($session->id)->lockForUpdate()->firstOrFail()
                : SupportSession::create(['user_id' => $actor->id, 'room_label' => '', 'room_code' => '', 'status' => 'collecting', 'version' => 1, 'last_activity_at' => now()]);
            Gate::forUser($lockedActor)->authorize('view', $current);
            if ($session && $current->status !== 'handed_off') {
                abort_unless($current->version === (int) $input['expected_version'], 409, 'Phiên đã thay đổi. Hãy tải lại hội thoại.');
            }
            $context = app(ConversationContext::class);
            if ($current->status === 'handed_off') {
                return SupportCommand::create(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'session_id' => $current->id,
                    'key_hash' => hash('sha256', $key), 'request_hash' => $fingerprint, 'operation' => 'report', 'status' => 'completed', 'version' => $current->version,
                    'response_data' => $this->snapshot($current), 'response_status' => 200]);
            }
            $requested = ($input['action'] ?? '') === 'report' || $context->wantsReport($input['content']);
            if (! $requested && ! $current->report_requested) {
                abort_if(SupportCommand::where('actor_id', $actor->id)->where('status', 'processing')->where('created_at', '>', now()->subSeconds(30))->exists(), 409, 'Một lượt trả lời đang được xử lý.');
            }
            $messageLimit = max(1, (int) config('services.helpdesk_ai.max_messages_per_session', 200));
            abort_if($current->messages()->count() >= $messageLimit && ! $requested && ! $current->report_requested, 422, 'Phiên đã đạt giới hạn tin nhắn. Hãy bắt đầu phiên mới hoặc gửi báo cáo.');
            $context->update($current, $input);
            if ($context->cancelsReport($input['content'])) {
                $current->report_requested = false;
            } elseif ($requested) {
                $current->report_requested = true;
            }
            $current->messages()->create(['role' => 'user', 'content_text' => $input['content'], 'client_message_id' => (string) Str::uuid()]);
            $current->version++;
            $current->last_activity_at = now();
            $ready = $current->report_requested && $current->room_id && $current->category_id && $context->incidentFacts($current)->isNotEmpty();
            $resolved = ! $requested && (($input['action'] ?? '') === 'resolve' || $context->isResolved($input['content']));
            if ($resolved) {
                $current->report_requested = false;
            }
            $current->status = $ready && ! $resolved ? 'handoff_pending' : ($resolved ? 'resolved' : 'collecting');
            $current->save();

            $clarify = ! $resolved && ! $current->report_requested && $context->clarification($current) !== null;

            return SupportCommand::create(['id' => (string) Str::uuid(), 'session_id' => $current->id, 'actor_id' => $actor->id,
                'key_hash' => hash('sha256', $key), 'request_hash' => $fingerprint, 'operation' => $resolved ? 'resolve' : ($ready ? 'report' : ($clarify ? 'clarify' : 'chat')),
                'status' => $ready && ! $resolved ? 'handoff_pending' : 'processing', 'version' => $current->version]);
        });
        if ($command->status === 'completed') {
            return ['status' => $command->response_status, 'data' => $command->response_data];
        }
        if ($command->status === 'handoff_pending') {
            return $this->completeReport($command, $actor);
        }
        // A concurrent replay only reads the receipt; never invoke the provider twice for one intent.
        if (! $command->wasRecentlyCreated) {
            if ($command->created_at->gt(now()->subSeconds(30))) {
                return ['status' => 202, 'data' => $this->snapshot(SupportSession::findOrFail($command->session_id), $command)];
            }

            return $this->finish($command, 'Lượt AI trước chưa có kết quả. Bạn có thể gửi báo cáo ngay tại đây.');
        }
        $current = SupportSession::findOrFail($command->session_id);
        $flow = app(SupportDiagnosticFlow::class);
        $diagnosis = $flow->state($current);
        $safetyNotice = $diagnosis['stage'] === 'safety_stop' ? $flow->reply($diagnosis).' ' : '';
        if ($command->operation === 'resolve') {
            return $this->finish($command, 'Bạn đã xác nhận sự cố được khắc phục. Hội thoại được lưu, chưa tạo phiếu. Nếu lỗi còn, bạn vẫn có thể tiếp tục trao đổi hoặc gửi báo cáo.');
        }
        if ($current->report_requested) {
            if (app(ConversationContext::class)->incidentFacts($current)->isEmpty()) {
                return $this->finish($command, $safetyNotice.'Bạn mô tả dấu hiệu sự cố cần báo trước nhé. Mình sẽ dùng nội dung bạn cung cấp để gửi phiếu ngay trong chat.');
            }

            return $this->finish($command, $safetyNotice.'Để gửi báo cáo, bạn cho biết '.(! $current->room_id ? 'phòng học (mã phòng)' : 'danh mục thiết bị gặp sự cố').'. Bạn có thể trả lời trong chat hoặc chọn bên dưới.');
        }
        if ($safetyNotice !== '') {
            return $this->finish($command, trim($safetyNotice));
        }
        if ($question = app(ConversationContext::class)->clarification($current)) {
            return $this->finish($command, $question);
        }
        $sessionLimit = max(1, (int) config('services.helpdesk_ai.max_turns_per_session', 40));
        $minuteLimit = max(1, (int) config('services.helpdesk_ai.max_turns_per_minute', 12));
        if (SupportCommand::where('session_id', $current->id)->where('operation', 'chat')->count() > $sessionLimit) {
            return $this->finish($command, 'Phiên đã đạt giới hạn hỗ trợ AI. Bạn vẫn có thể yêu cầu gửi báo cáo ngay trong chat.');
        }
        if (SupportCommand::where('actor_id', $actor->id)->where('operation', 'chat')->where('created_at', '>=', now()->subMinute())->count() > $minuteLimit) {
            return $this->finish($command, 'Bạn đang gửi tin khá nhanh. Hãy chờ khoảng một phút rồi tiếp tục; bạn vẫn có thể gửi báo cáo ngay trong chat.');
        }
        $history = $this->modelHistory($current);
        $result = app(HelpdeskAiService::class)->reply($history, Room::find($current->room_id), prepareHandoff: true,
            selectedCategory: Category::find($current->category_id), diagnosticContext: $diagnosis);
        $reply = $result['reply'] ?? 'AI hiện chưa phản hồi. Nội dung của bạn đã được lưu. Bạn có thể bấm “Gửi báo cáo” ngay tại đây, không cần điền lại form.';
        if (preg_match('/\bda\s+(gui|tao|lap|tiep nhan)\b|\bHD-[A-Z0-9-]+\b/i', Str::ascii($reply))) {
            $reply = 'Mình chưa gửi phiếu. Nếu sự cố vẫn còn, bạn có thể yêu cầu “Gửi báo cáo giúp tôi”.';
            unset($result['handoff_brief']);
        }

        $reply = $flow->reply($diagnosis) ?? $reply;

        return $this->finish($command, $reply, $result['handoff_brief'] ?? null);
    }

    private function modelHistory(SupportSession $session): array
    {
        $anchors = $session->messages()->where('role', 'user')->orderBy('id')->limit(2)->get(['id', 'role', 'content_text']);
        $recent = $session->messages()->orderByDesc('id')->limit(9)->get(['id', 'role', 'content_text']);
        $history = $anchors->merge($recent)->unique('id')->sortBy('id')->values();
        $protected = $anchors->pluck('id')->push($recent->first()->id);
        $length = $history->sum(fn ($message) => mb_strlen($message->content_text));
        // Retain original reports and the current message, dropping older middle turns first.
        foreach ($history as $key => $message) {
            if ($length <= 6000) {
                break;
            }
            if (! $protected->contains($message->id)) {
                $length -= mb_strlen($message->content_text);
                $history->forget($key);
            }
        }

        return $history->map(fn ($message) => ['role' => $message->role, 'content' => $message->content_text])->values()->all();
    }

    private function finish(SupportCommand $command, string $reply, ?array $brief = null): array
    {
        return DB::transaction(function () use ($command, $reply, $brief) {
            $session = SupportSession::whereKey($command->session_id)->lockForUpdate()->firstOrFail();
            $locked = SupportCommand::whereKey($command->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed') {
                return ['status' => $locked->response_status, 'data' => $locked->response_data];
            }
            if ($session->version === $command->version && in_array($session->status, ['collecting', 'resolved'], true)) {
                $session->messages()->create(['role' => 'assistant', 'content_text' => $reply,
                    'handoff_brief' => $brief ? $brief + ['source_hash' => app(ConversationBrief::class)->sourceHash($session),
                        'source_context' => ['room_id' => $session->room_id, 'category_id' => $session->category_id],
                        'provider' => config('services.helpdesk_ai.provider', 'openai'),
                        'model' => config('services.helpdesk_ai.provider', 'openai') === 'ollama' ? config('services.ollama.model') : config('services.openai.model')] : null]);
                $session->update(['version' => $session->version + 1, 'last_activity_at' => now()]);
            }
            $data = $this->snapshot($session);
            $status = $session->messages()->where('role', 'user')->count() === 1 ? 201 : 200;
            $locked->update(['status' => 'completed', 'response_data' => $data, 'response_status' => $status]);

            return ['status' => $status, 'data' => $data];
        });
    }

    private function completeReport(SupportCommand $command, User $actor): array
    {
        try {
            return DB::transaction(function () use ($command, $actor) {
                $session = SupportSession::whereKey($command->session_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('view', $session);
                $locked = SupportCommand::whereKey($command->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'completed') {
                    return ['status' => $locked->response_status, 'data' => $locked->response_data];
                }
                $report = app(ConversationReportService::class)->create($session, $actor);
                if ($report) {
                    $number = Ticket::findOrFail($report->ticket_id)->ticket_number;
                    $reply = "Đã gửi báo cáo $number. Phiếu đang chờ quản lý tiếp nhận. Bạn không cần điền lại form.";
                    $status = 201;
                } else {
                    // Do not link or reveal another reporter's ticket through this conversation.
                    $reply = 'Sự cố cùng phòng và danh mục thiết bị đã được ghi nhận nhưng chưa được xử lý xong. Không tạo thêm phiếu trùng. Hội thoại của bạn vẫn được lưu.';
                    $session->fill(['status' => 'collecting', 'report_requested' => false]);
                    $status = 200;
                }
                $session->messages()->create(['role' => 'assistant', 'content_text' => $reply]);
                $session->update(['version' => $session->version + 1, 'last_activity_at' => now()]);
                $data = $this->snapshot($session);
                $locked->update(['status' => 'completed', 'response_data' => $data, 'response_status' => $status]);

                return ['status' => $status, 'data' => $data];
            });
        } catch (Throwable $error) {
            // TX-A's receipt remains durable; no ticket/notification can survive a failed TX-B.
            Log::warning('Support report completion failed', ['command_id' => $command->id, 'exception_type' => $error::class]);

            return ['status' => 503, 'data' => $this->snapshot(SupportSession::findOrFail($command->session_id), $command)];
        }
    }

    public function snapshot(SupportSession $session, ?SupportCommand $command = null): array
    {
        $session->load(['initialMessage', 'report.ticket']);
        $report = SupportReport::where('session_id', $session->id)->first();
        $ticket = $report ? Ticket::find($report->ticket_id) : null;

        return [
            'session' => $session->toSupportArray() + ['category_id' => $session->category_id, 'report_requested' => $session->report_requested],
            'messages' => $session->messages()->orderBy('id')->get(['id', 'role', 'content_text', 'created_at'])->toArray(),
            'needs_context' => ! $session->room_id || ! $session->category_id,
            'needs_description' => app(ConversationContext::class)->incidentFacts($session)->isEmpty(),
            'diagnosis' => app(SupportDiagnosticFlow::class)->state($session),
            'category_label' => $session->category_id ? Category::find($session->category_id)?->name : null,
            'report' => $ticket ? ['id' => $report->id, 'ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status, 'url' => url('/customer/ticket-detail?ticket='.$ticket->id)] : null,
            'command' => $command ? ['id' => $command->id, 'status' => $command->status, 'status_url' => url("/api/support-sessions/{$session->id}/commands/{$command->id}")] : null,
        ];
    }
}
