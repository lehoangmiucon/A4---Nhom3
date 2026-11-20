<?php

namespace App\Services\Support;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationFallback
{
    public function build(Collection $facts, string $selectedCategory): array
    {
        // Extract from user reports only. Keep raw history unchanged for comparison.
        $details = $facts->map(fn ($text) => trim(preg_replace('/\s+/u', ' ', $text)))
            ->reject(fn ($text) => preg_match('/^(?:(?:xin )?chao(?: ban)?|(?:toi|minh|em) (?:can|muon) (?:ban )?(?:giup|ho tro)(?: do)?(?: minh| toi)?|giup (?:toi|minh)(?: voi)?)[.!\s]*$/', $this->normalize($text)))
            ->filter()->unique()->values();
        $main = $details->first() ?? 'Chưa có mô tả sự cố cụ thể.';
        $recent = $details->slice(1)->take(-2)->map(fn ($text) => '«'.mb_substr($text, 0, 120).'»')->implode('; ');
        $summary = 'Sự cố được báo: «'.mb_substr($main, 0, 280).'».';
        if ($recent !== '') {
            $summary .= ' Thông tin bổ sung gần nhất: '.$recent.'.';
        }
        $checks = $details->filter(fn ($text) => preg_match('/\b(kiem tra|da (thu|doi|cam|bat)|nguon dien.*(oke?|on))\b/', $this->normalize($text)))
            ->take(-4)->map(fn ($text) => mb_substr($text, 0, 240))->values()->all();

        $normalized = $this->normalize($details->implode(' '));
        $mentioned = Category::orderBy('id')->pluck('name')->filter(function ($name) use ($normalized) {
            $label = $this->normalize($name);

            return $label !== '' && str_contains(' '.$normalized.' ', ' '.$label.' ');
        })->unique()->values();
        $note = '';
        if ($mentioned->count() === 1 && $mentioned->first() !== $selectedCategory) {
            $note = 'CẦN ĐỐI CHIẾU DANH MỤC: Lời báo đề cập '.$mentioned->first()
                .', trong khi danh mục đang chọn là '.$selectedCategory.'. KTV xác nhận lại trước khi phân loại/xử lý.';
        }

        $suggestions = ['KTV đối chiếu dấu hiệu lỗi và các kết quả người dùng đã nêu với tình trạng thực tế tại phòng; chưa kết luận nguyên nhân.'];
        if (str_contains($normalized, 'may chieu') && preg_match('/\b(khong len|khong bat|khong khoi dong|khong (thay )?sang den)\b/', $normalized)) {
            $suggestions = [
                'KTV xác minh khả năng khởi động và trạng thái đèn báo của máy chiếu tại phòng.',
                'Kiểm tra đường cấp nguồn và kết nối nguồn theo quy trình kỹ thuật; xác minh trực tiếp, chưa kết luận hỏng bộ nguồn từ lời báo.',
            ];
        }

        return ['title' => mb_substr($main, 0, 180), 'summary' => mb_substr($summary, 0, 600),
            'reported_checks' => $checks, 'suggestions' => $suggestions, 'category_note' => $note];
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($text))));
    }
}
