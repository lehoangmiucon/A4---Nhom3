<?php

namespace Tests\Feature;

use App\Support\PeriodRange;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodRangeTest extends TestCase
{
    #[DataProvider('calendarPeriods')]
    public function test_periods_use_calendar_boundaries(string $period, string $current, string $first, string $last): void
    {
        $this->travelTo(Carbon::parse($current));

        [$start, $end] = PeriodRange::bounds($period);

        $this->assertSame($first.' 00:00:00.000000', $start->format('Y-m-d H:i:s.u'));
        $this->assertSame($last.' 23:59:59.999999', $end->format('Y-m-d H:i:s.u'));
        $this->assertSame($current, now()->format('Y-m-d H:i:s'));
    }

    public static function calendarPeriods(): array
    {
        return [
            'day' => ['day', '2026-11-14 12:30:00', '2026-11-14', '2026-11-14'],
            'week on Monday' => ['week', '2026-11-09 12:30:00', '2026-11-09', '2026-11-14'],
            'week on Friday' => ['week', '2026-11-13 12:30:00', '2026-11-09', '2026-11-14'],
            'week on Saturday' => ['week', '2026-11-14 12:30:00', '2026-11-09', '2026-11-14'],
            'week on Sunday' => ['week', '2026-11-15 12:30:00', '2026-11-09', '2026-11-14'],
            'next week' => ['week', '2026-11-16 12:30:00', '2026-11-16', '2026-11-21'],
            'week across years' => ['week', '2027-01-01 12:30:00', '2026-12-28', '2027-01-02'],
            '30 day month' => ['month', '2026-11-14 12:30:00', '2026-11-01', '2026-11-30'],
            '31 day month' => ['month', '2026-12-14 12:30:00', '2026-12-01', '2026-12-31'],
            'leap February' => ['month', '2024-02-14 12:30:00', '2024-02-01', '2024-02-29'],
            'February' => ['month', '2026-02-14 12:30:00', '2026-02-01', '2026-02-28'],
            'year' => ['year', '2026-11-14 12:30:00', '2026-01-01', '2026-12-31'],
        ];
    }
}
