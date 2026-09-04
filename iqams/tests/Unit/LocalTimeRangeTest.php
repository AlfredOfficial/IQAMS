<?php

namespace Tests\Unit;

use App\Support\LocalTimeRange;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocalTimeRangeTest extends TestCase
{
    public function test_day_range_uses_the_application_timezone_and_excludes_next_midnight(): void
    {
        [$start, $end] = LocalTimeRange::day(Carbon::parse('2026-08-10 15:59:59', 'UTC'), 'Asia/Manila');

        $this->assertSame('2026-08-10 00:00:00', $start->toDateTimeString());
        $this->assertSame('Asia/Manila', $start->getTimezone()->getName());
        $this->assertSame('2026-08-11 00:00:00', $end->toDateTimeString());
    }

    public function test_dates_and_month_ranges_are_half_open(): void
    {
        [$datesStart, $datesEnd] = LocalTimeRange::dates('2026-08-01', '2026-08-31', 'Asia/Manila');
        [$monthStart, $monthEnd] = LocalTimeRange::month('2026-08-17', 'Asia/Manila');

        $this->assertSame('2026-08-01 00:00:00', $datesStart->toDateTimeString());
        $this->assertSame('2026-09-01 00:00:00', $datesEnd->toDateTimeString());
        $this->assertSame('2026-08-01 00:00:00', $monthStart->toDateTimeString());
        $this->assertSame('2026-09-01 00:00:00', $monthEnd->toDateTimeString());
    }
}
