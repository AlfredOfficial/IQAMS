<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class LocalTimeRange
{
    /**
     * Return the half-open range for one calendar day in the application timezone.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function day(CarbonInterface|string $date, ?string $timezone = null): array
    {
        $start = self::startOfLocalDay($date, $timezone);

        return [$start, $start->copy()->addDay()];
    }

    /**
     * Return the half-open range covering both calendar dates inclusively.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dates(CarbonInterface|string $from, CarbonInterface|string $to, ?string $timezone = null): array
    {
        $start = self::startOfLocalDay($from, $timezone);
        $end = self::startOfLocalDay($to, $timezone);

        return [$start, $end->copy()->addDay()];
    }

    /**
     * Return the half-open range covering the local calendar month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function month(CarbonInterface|string $date, ?string $timezone = null): array
    {
        $start = self::startOfLocalDay($date, $timezone)->startOfMonth();

        return [$start, $start->copy()->addMonth()];
    }

    private static function startOfLocalDay(CarbonInterface|string $date, ?string $timezone = null): Carbon
    {
        $timezone ??= (string) config('app.timezone', 'UTC');
        $carbon = $date instanceof CarbonInterface
            ? Carbon::instance($date)
            : Carbon::parse($date, $timezone);

        return $carbon->copy()->timezone($timezone)->startOfDay();
    }
}
