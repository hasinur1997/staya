<?php

namespace App\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Business dates for the mess. Timestamps are stored in UTC; "today" and
 * month boundaries are evaluated in Asia/Dhaka.
 */
class BusinessCalendar
{
    public const string TIMEZONE = 'Asia/Dhaka';

    /**
     * The start of today in Asia/Dhaka.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /**
     * The business date (Y-m-d) an instant falls on in Asia/Dhaka.
     */
    public static function dateOf(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::TIMEZONE)->toDateString();
    }

    /**
     * The first and last business dates (Y-m-d) of a month.
     *
     * @return array{0: string, 1: string}
     */
    public static function monthDates(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    /**
     * A month in Asia/Dhaka as UTC instants, for querying timestamps:
     * `created_at >= start AND created_at < end`.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public static function monthRange(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);

        return [
            'start' => $start->utc(),
            'end' => $start->addMonthNoOverflow()->utc(),
        ];
    }
}
