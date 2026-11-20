<?php

namespace App\Services\Support;

use App\Models\Category;
use App\Models\SupportCommand;
use App\Models\SupportSession;
use Illuminate\Support\Str;

class SupportDiagnosticFlow
{
    // Observation questions only: no repair procedure or inferred component failure.
    private const QUESTIONS = [
        'power_connection' => ['label' => 'Kết nối nguồn bên ngoài', 'text' => 'Nguồn điện tại phòng có hoạt động bình thường và dây nguồn bên ngoài đã được cắm chắc chưa? Chỉ quan sát bên ngoài, không chạm phần điện hở.'],
        'power_indicator' => ['label' => 'Đèn báo khi bật thiết bị', 'text' => 'Khi bấm nút bật, đèn báo trên thiết bị có sáng hoặc nhấp nháy không?'],
        'startup_sound' => ['label' => 'Tiếng quạt hoặc âm thanh khởi động', 'text' => 'Bạn có nghe tiếng quạt hoặc âm thanh khởi động từ thiết bị không?'],
        'display_indicator' => ['label' => 'Đèn nguồn màn hình', 'text' => 'Đèn nguồn trên màn hình hoặc máy chiếu có sáng không?'],
        'display_message' => ['label' => 'Thông báo trên màn hình', 'text' => 'Màn hình đang hiện thông báo gì, hay chỉ tối đen?'],
        'network_visible' => ['label' => 'Nhìn thấy mạng cần kết nối', 'text' => 'Thiết bị có nhìn thấy tên mạng Wi-Fi cần dùng không? Không gửi mật khẩu Wi-Fi trong chat.'],
        'network_scope' => ['label' => 'Phạm vi lỗi kết nối', 'text' => 'Các thiết bị khác tại cùng phòng có gặp lỗi kết nối tương tự không?'],
        'audio_symptom' => ['label' => 'Dấu hiệu âm thanh', 'text' => 'Âm thanh mất hẳn hay vẫn nghe nhưng bị nhỏ hoặc rè?'],
        'audio_scope' => ['label' => 'Phạm vi lỗi âm thanh', 'text' => 'Lỗi âm thanh xảy ra với mọi nội dung hay chỉ một ứng dụng?'],
    ];

    public function state(SupportSession $session): array
    {
        $messages = $session->messages()->orderBy('id')->get(['id', 'role', 'content_text']);
        // A known room/category change starts a fresh observation scope. Completing
        // an unknown context keeps the original symptom. Receipts remain unchanged.
        $boundary = 0;
        $receipts = SupportCommand::where('session_id', $session->id)->whereNotNull('response_data')->orderByDesc('version')->get(['response_data']);
        $questionIds = [];
        foreach ($receipts as $command) {
            $data = $command->response_data;
            $questionId = $data['diagnosis']['next_question']['id'] ?? null;
            $lastMessage = end($data['messages']);
            if ($questionId && ($lastMessage['role'] ?? null) === 'assistant') {
                $questionIds[$lastMessage['id']] = $questionId;
            }
        }
        foreach ($receipts as $command) {
            $old = $command->response_data['session'] ?? [];
            $changed = false;
            foreach (['room_id', 'category_id'] as $field) {
                $changed = $changed || (! empty($old[$field]) && $old[$field] !== $session->$field);
            }
            if ($changed) {
                $boundary = max(array_column($command->response_data['messages'] ?? [], 'id') ?: [0]);
                break;
            }
        }
        $messages = $messages->filter(fn ($message) => $message->id > $boundary)->values();
        $category = Category::find($session->category_id)?->name ?? '';
        $family = $this->family($this->normalize($category));
        $kind = null;
        $start = 0;
        $context = app(ConversationContext::class);
        foreach ($messages as $index => $message) {
            if ($message->role !== 'user' || $context->wantsReport($message->content_text, commandOnly: true)) {
                continue;
            }
            $text = $this->normalize($message->content_text);
            $mentioned = $this->family($text);
            if ($mentioned && $family && $mentioned !== $family) {
                $start = $index;
                $kind = null;
            }
            $family = $mentioned ?? $family;
            $symptom = $this->symptom($text, $family);
            if ($symptom && $kind && $symptom !== $kind) {
                $start = $index;
            }
            $kind = $symptom ?? $kind;
        }
        $steps = match ($kind) {
            'power' => ['power_connection', 'power_indicator', 'startup_sound'],
            'display' => ['display_indicator', 'display_message', 'startup_sound'],
            'network' => ['network_visible', 'network_scope'],
            'audio' => ['audio_symptom', 'audio_scope'],
            default => [],
        };
        $observations = [];
        $asked = [];
        $answered = [];
        $pending = null;
        $danger = false;
        foreach ($messages->slice($start) as $message) {
            if ($message->role === 'assistant') {
                foreach ($steps as $step) {
                    if (($questionIds[$message->id] ?? null) === $step || str_contains($message->content_text, self::QUESTIONS[$step]['text'])) {
                        $asked[$step] = true;
                        $pending = $step;
                    }
                }

                continue;
            }
            if ($message->role !== 'user') {
                continue;
            }
            $text = $this->normalize($message->content_text);
            $hazardText = preg_replace('/\bkhong (?:co )?(?:khoi|mui khet|dien giat|chay)\b/', '', $text);
            if (preg_match('/\b(khoi|mui khet|dien giat|boc chay|chay no)\b/', $hazardText)) {
                $danger = true;
                $observations['safety_alert'] = ['id' => 'safety_alert', 'label' => 'Dấu hiệu nguy hiểm được nêu', 'status' => 'reported', 'evidence' => mb_substr($message->content_text, 0, 300)];
            }
            if ($context->wantsReport($message->content_text, commandOnly: true)
                || $context->cancelsReport($message->content_text) || $context->isResolved($message->content_text)) {
                continue;
            }
            foreach ($steps as $step) {
                $status = $this->explicitResult($step, $text);
                if ($status) {
                    $observations[$step] = $this->observation($step, $status, $message->content_text);
                }
            }
            if ($pending) {
                if (! isset($observations[$pending]) || ! $this->explicitResult($pending, $text)) {
                    $status = $this->shortResult($text);
                    if ($status === 'unknown' && in_array($pending, ['display_message', 'network_scope', 'audio_symptom', 'audio_scope'], true)
                        && ! preg_match('/\b(khong biet|khong ro|chua biet|chua kiem tra)\b/', $text)) {
                        $status = 'reported';
                    }
                    // An unrelated or uncertain reply is not a positive result.
                    $observations[$pending] = $this->observation($pending, $status, $message->content_text);
                }
                $answered[$pending] = true;
                $pending = null;
            }
        }
        $next = null;
        $powerAbsent = ($observations['power_connection']['status'] ?? null) === 'reported_absent';
        if (! $danger && ! $powerAbsent && ! in_array($session->status, ['handed_off', 'resolved'], true)) {
            if ($pending && ! isset($answered[$pending])) {
                $next = $pending;
            } else {
                foreach ($steps as $step) {
                    if (! isset($asked[$step]) && ! isset($observations[$step])) {
                        $next = $step;
                        break;
                    }
                }
            }
        }
        $labels = ['computer' => 'Máy tính', 'projector' => 'Máy chiếu', 'network' => 'Kết nối mạng', 'audio' => 'Âm thanh'];
        $symptoms = ['power' => 'không khởi động', 'display' => 'không có hình', 'network' => 'lỗi kết nối', 'audio' => 'lỗi âm thanh'];
        $suggestions = match ($kind) {
            'power' => ['KTV đối chiếu các kết quả người dùng báo với đèn báo và trạng thái khởi động thực tế tại phòng.', 'Phân biệt thiết bị chưa khởi động với thiết bị chạy nhưng không xuất hình; chưa kết luận linh kiện hỏng.'],
            'display' => ['KTV phân biệt thiết bị chưa khởi động và lỗi xuất/nhận hình theo đèn báo, âm thanh và thông báo thực tế.', 'Đối chiếu đường tín hiệu hình ảnh theo quy trình kỹ thuật; chưa kết luận nguyên nhân chỉ từ lời báo.'],
            'network' => ['KTV đối chiếu phạm vi lỗi trên các thiết bị cùng phòng trước khi phân biệt lỗi thiết bị và hạ tầng mạng.', 'Kiểm tra trạng thái kết nối/xác thực theo quy trình IT; không thu mật khẩu qua hội thoại, chưa kết luận nguyên nhân.'],
            'audio' => ['KTV phân biệt mất tiếng, nhỏ/rè và phạm vi ứng dụng theo lời báo và kiểm tra thực tế.', 'Đối chiếu thiết bị âm thanh, nguồn phát và cấu hình theo quy trình IT; chưa kết luận linh kiện hỏng.'],
            default => [],
        };
        if ($danger) {
            $suggestions = ['Nhân viên có chuyên môn đánh giá nguy cơ an toàn tại phòng trước mọi kiểm tra kỹ thuật; chưa kết luận nguyên nhân.'];
        } elseif ($powerAbsent) {
            $suggestions = ['Ưu tiên xác minh tình trạng cấp điện tại phòng theo quy trình an toàn; chưa kết luận lỗi nằm ở thiết bị.', 'Chỉ tiếp tục kiểm tra thiết bị sau khi người có chuyên môn xác minh điều kiện cấp điện.'];
        }

        $branch = match ($kind) {
            'network' => 'network_connection', 'audio' => 'audio_output', default => $family.'_'.$kind
        };
        $stage = match (true) {
            $danger => 'safety_stop',
            $session->status === 'handed_off' => 'handed_off',
            $session->status === 'resolved' => 'resolved',
            ! $steps => 'unclassified',
            $next !== null => 'collecting_observations',
            default => 'ready_for_it',
        };

        return ['branch' => $steps ? $branch : null,
            'label' => $danger ? 'Nguy cơ an toàn theo lời báo' : ($steps ? ($labels[$family] ?? 'Thiết bị').' — '.$symptoms[$kind] : null),
            'stage' => $stage,
            'observations' => array_values($observations), 'asked_steps' => array_keys($asked),
            'unknowns' => array_values(array_map(fn ($step) => self::QUESTIONS[$step]['label'], array_filter($steps, fn ($step) => ! isset($observations[$step]) || $observations[$step]['status'] === 'unknown'))),
            'next_question' => $next ? ['id' => $next, 'text' => self::QUESTIONS[$next]['text']] : null,
            'technical_suggestions' => $suggestions];
    }

    public function reply(array $state): ?string
    {
        if ($state['stage'] === 'safety_stop') {
            return 'Có dấu hiệu nguy hiểm theo lời bạn báo. Hãy tránh xa thiết bị, không chạm hoặc tự kiểm tra thêm và báo ngay cho nhân viên nhà trường. Bạn có thể yêu cầu chuyển kỹ thuật viên ngay trong chat.';
        }
        if (! $state['branch']) {
            return null;
        }

        return $state['next_question']['text'] ?? 'Mình đã ghi nhận các dấu hiệu bạn mô tả. Chưa thể xác định nguyên nhân chỉ qua chat. Bạn có thể yêu cầu chuyển kỹ thuật viên hoặc bấm “Gửi báo cáo giúp tôi”; hồ sơ sẽ kèm kết quả đã báo và thông tin còn thiếu.';
    }

    private function observation(string $step, string $status, string $evidence): array
    {
        return ['id' => $step, 'label' => self::QUESTIONS[$step]['label'], 'status' => $status, 'evidence' => mb_substr($evidence, 0, 300)];
    }

    private function shortResult(string $text): string
    {
        return match (true) {
            (bool) preg_match('/^(co|co sang|co quay|binh thuong|van sang|van quay)( roi)?$/', $text) => 'reported_ok',
            (bool) preg_match('/^(khong|khong co|khong sang|khong quay)( a)?$/', $text) => 'reported_absent',
            default => 'unknown',
        };
    }

    private function explicitResult(string $step, string $text): ?string
    {
        if (preg_match('/\b(chua kiem tra|khong biet|khong ro|chua biet|khong chac|chua chac|co le|hinh nhu|chac la|neu|gia su|ban noi|ai noi|ban bao|ai bao)\b|\b(khong|chua)$/', $text)) {
            return null;
        }
        if ($step === 'power_connection') {
            if (preg_match('/\bnguon dien khong (co )?(van de|loi)\b/', $text)) {
                return 'reported_ok';
            }
            if (preg_match('/\b(nguon dien (khong binh thuong|bi mat|khong co)|khong co (nguon )?dien|chua cam (dien|nguon)|mat dien)\b/', $text)) {
                return 'reported_absent';
            }
            if (preg_match('/\b(nguon dien (binh thuong|on|tot|oke?|van co)|da cam (dien|nguon)|day nguon.*cam chac)\b/', $text)) {
                return 'reported_ok';
            }
        }
        if (in_array($step, ['power_indicator', 'display_indicator'], true)) {
            if (preg_match('/\b(den.*khong (sang|len)|khong (thay )?(co )?(sang den|den sang|den bao))\b/', $text)) {
                return 'reported_absent';
            }
            if (preg_match('/\b(den.*(van sang|co sang|nhap nhay|dang sang)|co den|den sang)\b/', $text)) {
                return 'reported_ok';
            }
        }
        if ($step === 'startup_sound') {
            if (preg_match('/\b(khong (nghe|co).*?(quat|khoi dong)|quat.*khong (quay|chay))\b/', $text)) {
                return 'reported_absent';
            }
            if (preg_match('/\b(quat (van )?(quay|chay)|co tieng quat|co am thanh khoi dong)\b/', $text)) {
                return 'reported_ok';
            }
        }
        if ($step === 'display_message' && preg_match('/\b(no signal|man hinh (den|toi)|bao loi)\b/', $text)) {
            return 'reported';
        }
        if ($step === 'network_visible') {
            if (preg_match('/\b(khong (thay|tim thay).*?(wifi|wi fi|ten mang))\b/', $text)) {
                return 'reported_absent';
            }
            if (preg_match('/\b(co (thay )?(ten mang|wifi|wi fi))\b/', $text)) {
                return 'reported_ok';
            }
        }
        if ($step === 'audio_symptom' && preg_match('/\b(mat tieng|khong co tieng|loa.*(re|nho))\b/', $text)) {
            return 'reported';
        }

        return null;
    }

    private function family(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(may chieu|projector)\b/', $text) => 'projector',
            (bool) preg_match('/\b(may tinh|computer|pc)\b/', $text) => 'computer',
            (bool) preg_match('/\b(wifi|wi fi|internet|mang)\b/', $text) => 'network',
            (bool) preg_match('/\b(loa|micro)\b/', $text) => 'audio',
            default => null,
        };
    }

    private function symptom(string $text, ?string $family): ?string
    {
        if (in_array($family, ['computer', 'projector'], true)) {
            if (preg_match('/\b(khong (co |len )?hinh|no signal|man hinh (den|toi))\b/', $text)) {
                return 'display';
            }
            if (preg_match('/\b(khong (mo len|khoi dong|bat len|len nguon|len)|bat (khong len|ma.*khong len))\b/', $text)) {
                return 'power';
            }
        }
        if ($family === 'network' && preg_match('/\b(khong|loi|mat|yeu|cham)\b/', $text)) {
            return 'network';
        }
        if ($family === 'audio' && preg_match('/\b(mat|khong|re|nho|loi)\b/', $text)) {
            return 'audio';
        }

        return null;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($text))));
    }
}
