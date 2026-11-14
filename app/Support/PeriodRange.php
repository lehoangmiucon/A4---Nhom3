<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PeriodRange
{
    public const OPTIONS = [
        'day' => 'Ngày',
        'week' => 'Tuần',
        'month' => 'Tháng',
        'year' => 'Năm',
    ];

    public static function normalize(?string $period): string
    {
        return array_key_exists($period ?? '', self::OPTIONS) ? $period : 'month';
    }

    public static function bounds(string $period): array
    {
        $now = now();

        $start = match ($period) {
            'day' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'year' => $now->copy()->startOfYear(),
            default => $now->copy()->startOfMonth(),
        };
        $end = match ($period) {
            'day' => $now->copy()->endOfDay(),
            'week' => $start->copy()->addDays(5)->endOfDay(),
            'year' => $now->copy()->endOfYear(),
            default => $now->copy()->endOfMonth(),
        };

        return [$start, $end];
    }

    public static function title(string $period): string
    {
        return [
            'day' => 'hôm nay',
            'week' => 'tuần này',
            'month' => 'tháng này',
            'year' => 'năm nay',
        ][$period] ?? 'tháng này';
    }
}
