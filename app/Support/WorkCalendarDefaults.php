<?php

namespace App\Support;

final class WorkCalendarDefaults
{
    /** Carbon dayOfWeek: Sat=6 … Fri=5 */
    private const WORKING_DAYS = [6, 0, 1, 2, 3];

    /** پنجشنبه و جمعه */
    private const WEEKEND_DAYS = [4, 5];

    private const DAY_LABELS = [
        6 => 'شنبه',
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
    ];

    public static function workingDays(): array
    {
        return self::WORKING_DAYS;
    }

    public static function weekendDays(): array
    {
        return self::WEEKEND_DAYS;
    }

    /**
     * @return array<int, string>
     */
    public static function dayLabels(): array
    {
        return self::DAY_LABELS;
    }

    public static function dayLabel(int $dayOfWeek): string
    {
        return self::DAY_LABELS[$dayOfWeek] ?? (string) $dayOfWeek;
    }

    /**
     * @param  array<int, int|string>|null  $days
     * @return list<int>
     */
    public static function normalizeDayList(?array $days, ?array $fallback = null): array
    {
        $source = $days ?: ($fallback ?? []);

        return array_values(array_unique(array_map(
            static fn ($day): int => (int) $day,
            $source
        )));
    }

    /**
     * @param  array<int, int|string>|null  $days
     */
    public static function formatDayLabels(?array $days): string
    {
        $normalized = self::normalizeDayList($days);

        if ($normalized === []) {
            return '-';
        }

        return implode('، ', array_map(
            static fn (int $day): string => self::dayLabel($day),
            $normalized
        ));
    }

    public static function calendar(int $jalaliYear): array
    {
        return match ($jalaliYear) {
            1404 => self::calendar1404(),
            1405 => self::calendar1405(),
            default => [
                'working_days' => self::WORKING_DAYS,
                'weekend_days' => self::WEEKEND_DAYS,
                'holidays' => [],
            ],
        };
    }

    public static function defaultCalendar(int $jalaliYear): array
    {
        return self::calendar($jalaliYear);
    }

    private static function calendar1404(): array
    {
        return [
            'working_days' => self::WORKING_DAYS,
            'weekend_days' => self::WEEKEND_DAYS,
            'holidays' => self::holidayDates([
                // Fixed Jalali holidays.
                jalaliToGregorianDateSafe(1404, 1, 1),
                jalaliToGregorianDateSafe(1404, 1, 2),
                jalaliToGregorianDateSafe(1404, 1, 3),
                jalaliToGregorianDateSafe(1404, 1, 4),
                jalaliToGregorianDateSafe(1404, 1, 12),
                jalaliToGregorianDateSafe(1404, 1, 13),
                jalaliToGregorianDateSafe(1404, 3, 14),
                jalaliToGregorianDateSafe(1404, 3, 15),
                jalaliToGregorianDateSafe(1404, 11, 22),
                jalaliToGregorianDateSafe(1404, 12, 29),

                // Sample official holidays observed in 1404.
                '2025-03-31',
                '2025-04-01',
                '2025-06-06',
                '2025-06-14',
                '2025-07-05',
                '2025-07-06',
                '2025-08-14',
                '2025-08-22',
                '2025-08-30',
                '2025-09-08',
                '2025-12-27',
                '2026-01-15',
                '2026-02-03',
            ]),
        ];
    }

    private static function calendar1405(): array
    {
        return [
            'working_days' => self::WORKING_DAYS,
            'weekend_days' => self::WEEKEND_DAYS,
            'holidays' => self::holidayDates([
                // Fixed Jalali holidays.
                jalaliToGregorianDateSafe(1405, 1, 1),
                jalaliToGregorianDateSafe(1405, 1, 2),
                jalaliToGregorianDateSafe(1405, 1, 3),
                jalaliToGregorianDateSafe(1405, 1, 4),
                jalaliToGregorianDateSafe(1405, 1, 12),
                jalaliToGregorianDateSafe(1405, 1, 13),
                jalaliToGregorianDateSafe(1405, 3, 14),
                jalaliToGregorianDateSafe(1405, 3, 15),
                jalaliToGregorianDateSafe(1405, 11, 22),
                jalaliToGregorianDateSafe(1405, 12, 29),

                // Sample official holidays observed in 1405.
                '2026-06-16',
                '2026-06-24',
                '2026-06-25',
                '2026-08-03',
                '2026-08-11',
                '2026-08-13',
                '2026-08-21',
                '2026-08-30',
                '2026-11-13',
                '2026-12-22',
                '2027-01-05',
                '2027-01-24',
                '2027-02-28',
                '2027-03-09',
                '2027-03-10',
                '2026-05-27',
                '2026-06-04',
            ]),
        ];
    }

    /**
     * @param array<int, string|null> $dates
     * @return array<int, string>
     */
    private static function holidayDates(array $dates): array
    {
        return array_values(array_unique(array_filter($dates)));
    }
}
