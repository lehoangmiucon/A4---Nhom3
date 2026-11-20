<?php

namespace App\Services\Support;

use App\Models\Category;
use App\Models\SupportSession;
use Illuminate\Support\Facades\Validator;

class ConversationBrief
{
    public function validate(mixed $brief, array $messages): ?array
    {
        if (! is_array($brief) || Validator::make($brief, [
            'summary' => ['required', 'string', 'max:600'],
            'reported_checks' => ['present', 'array', 'max:3'],
            'reported_checks.*' => ['required', 'string', 'max:300'],
            'suggestions' => ['present', 'array', 'max:3'],
            'suggestions.*' => ['required', 'string', 'max:220'],
        ])->fails()) {
            return null;
        }
        $clean = fn ($text) => trim(preg_replace('/\s+/u', ' ', $text));
        $userTexts = collect($messages)->where('role', 'user')->pluck('content')->map($clean)->values();
        $quotes = collect($brief['reported_checks'])->map(function ($reference) use ($userTexts) {
            if (preg_match('/^U([1-9][0-9]*)$/', $reference, $match)) {
                return isset($userTexts[(int) $match[1] - 1]) ? mb_substr($userTexts[(int) $match[1] - 1], 0, 300) : '';
            }

            return trim(preg_replace('/\s+/u', ' ', $reference));
        });
        $checks = $quotes->filter(fn ($quote) => $quote !== '' && $userTexts->contains(
            fn ($text) => str_contains($text, trim($quote))
        ));
        // An invented check also makes the accompanying summary unreliable.
        if ($checks->count() !== count($brief['reported_checks'])) {
            return null;
        }

        return ['summary' => $clean($brief['summary']), 'reported_checks' => $checks->map($clean)->unique()->values()->all(),
            'suggestions' => collect($brief['suggestions'])->map($clean)->unique()->values()->all()];
    }

    public function sourceHash(SupportSession $session, ?array $sourceContext = null): string
    {
        return hash('sha256', json_encode([$sourceContext ? $sourceContext['room_id'] : $session->room_id,
            $sourceContext ? $sourceContext['category_id'] : $session->category_id,
            app(ConversationContext::class)->incidentFacts($session)->all()]));
    }

    private function matchesSource(SupportSession $session, array $stored): bool
    {
        // Older briefs retain the original strict hash check.
        $context = $stored['source_context'] ?? ['room_id' => $session->room_id, 'category_id' => $session->category_id];
        foreach (['room_id', 'category_id'] as $field) {
            if (! is_array($context) || ! array_key_exists($field, $context)
                || ($context[$field] !== null && $context[$field] !== $session->$field)) {
                return false;
            }
        }

        // Filling unknown context is allowed; changing known context or facts is not.
        return hash_equals($this->sourceHash($session, $context), $stored['source_hash'] ?? '');
    }

    public function description(SupportSession $session): string
    {
        $facts = app(ConversationContext::class)->incidentFacts($session);
        $stored = $session->messages()->where('role', 'assistant')->whereNotNull('handoff_brief')->latest('id')->first()?->handoff_brief;
        $brief = $stored && $this->matchesSource($session, $stored)
            ? $this->validate($stored, $facts->map(fn ($content) => ['role' => 'user', 'content' => $content])->all()) : null;
        $category = Category::find($session->category_id)?->name ?? 'Chưa xác định danh mục';
        $fallback = app(ConversationFallback::class)->build($facts, $category);
        $context = "Phòng: {$session->room_code} — {$session->room_label}\nThiết bị/danh mục: $category";
        if ($fallback['category_note'] !== '') {
            $context .= "\n".$fallback['category_note'];
        }
        $summary = $brief ? "TÓM TẮT AI — CHƯA ĐƯỢC KTV XÁC MINH\n{$brief['summary']}"
            : 'TỔNG HỢP TỪ LỜI BÁO — CHƯA ĐƯỢC KTV XÁC MINH'."\n".$fallback['summary'];
        $reportedChecks = $brief ? $brief['reported_checks'] : $fallback['reported_checks'];
        $checks = $reportedChecks ? '- '.implode("\n- ", $reportedChecks)
            : 'Chưa có trích đoạn kiểm tra được tổng hợp; đối chiếu lời báo gốc bên dưới.';
        $suggestions = $brief && $brief['suggestions'] ? implode("\n- ", $brief['suggestions'])
            : implode("\n- ", $fallback['suggestions']);
        $diagnosis = app(SupportDiagnosticFlow::class)->state($session);
        $diagnosticBrief = '';
        if ($diagnosis['branch'] || $diagnosis['stage'] === 'safety_stop') {
            $observed = collect($diagnosis['observations'])->where('status', '!=', 'unknown')->map(fn ($item) => '- '.$item['label'].': «'.$item['evidence'].'» (người dùng báo, chưa xác minh)')->implode("\n");
            $diagnosticBrief = "\n\nPHÂN NHÁNH SƠ BỘ — CHƯA XÁC MINH\n".($diagnosis['label'] ?? 'Nguy cơ an toàn theo lời báo')
                ."\n".($observed ?: 'Chưa có kết quả quan sát được xác nhận.')
                ."\nCHƯA XÁC ĐỊNH: ".(implode('; ', $diagnosis['unknowns']) ?: 'Cần KTV xác minh nguyên nhân thực tế.');
            $suggestions = implode("\n- ", array_slice(array_unique(array_merge($diagnosis['technical_suggestions'], $brief['suggestions'] ?? [])), 0, 3));
        }
        $prefix = "$context\n\n$summary$diagnosticBrief\n\nKIỂM TRA/KẾT QUẢ NGƯỜI DÙNG ĐÃ NÊU\n$checks\n\n"
            .($brief ? 'GỢI Ý AI CHO KTV — CHỈ THAM KHẢO, CẦN KIỂM CHỨNG' : 'GỢI Ý KIỂM TRA BAN ĐẦU — MẪU HỆ THỐNG')
            ."\n- $suggestions\n\nLỜI BÁO GỐC — NGUỒN ĐỐI CHIẾU\n";
        $suffix = "\n\nNguồn: hội thoại hỗ trợ #{$session->id}. Hội thoại đầy đủ được lưu trong báo cáo hỗ trợ. Gợi ý không phải kết luận nguyên nhân hoặc xác nhận đã sửa.";
        $source = $session->messages()->where('role', 'user')->orderBy('id')->pluck('content_text')->implode("\n\n");
        $budget = max(0, 5000 - mb_strlen($prefix.$suffix) - 40);
        $source = mb_substr($source, 0, $budget).(mb_strlen($source) > $budget ? "\n[Phần trích đã được rút gọn.]" : '');

        return $prefix.$source.$suffix;
    }
}
