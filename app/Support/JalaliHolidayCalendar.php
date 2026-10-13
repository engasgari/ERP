<?php

namespace App\Support;

use Carbon\Carbon;

final class JalaliHolidayCalendar
{
    private const MONTH_NAMES = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    /**
     * @return array<int, string>
     */
    public static function monthNames(): array
    {
        return self::MONTH_NAMES;
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            return 0;
        }

        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return self::isLeapYear($year) ? 30 : 29;
    }

    public static function isLeapYear(int $year): bool
    {
        $cycle = (($year - 474) % 2820 + 2820) % 2820 % 33;

        return in_array($cycle, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    /**
     * @param  array<int, int|string>  $weekendDays  Carbon dayOfWeek values
     * @return list<array{
     *     month:int,
     *     name:string,
     *     leading:int,
     *     days:list<array{day:int, jalali:string, dow:int, is_weekend:bool}>
     * }>
     */
    public static function yearGrid(int $year, array $weekendDays = [4, 5]): array
    {
        $weekends = WorkCalendarDefaults::normalizeDayList($weekendDays, WorkCalendarDefaults::weekendDays());
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $daysInMonth = self::daysInMonth($year, $month);
            $firstGregorian = jalaliToGregorianDateSafe($year, $month, 1);
            $firstDow = $firstGregorian ? (int) Carbon::parse($firstGregorian)->dayOfWeek : 6;
            // شنبه اول هفته در UI (شاخص ۰)
            $leading = ($firstDow + 1) % 7;
            $days = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $gregorian = jalaliToGregorianDateSafe($year, $month, $day);
                $dow = $gregorian ? (int) Carbon::parse($gregorian)->dayOfWeek : 0;
                $days[] = [
                    'day' => $day,
                    'jalali' => sprintf('%04d/%02d/%02d', $year, $month, $day),
                    'dow' => $dow,
                    'is_weekend' => in_array($dow, $weekends, true),
                ];
            }

            $months[] = [
                'month' => $month,
                'name' => self::MONTH_NAMES[$month],
                'leading' => $leading,
                'days' => $days,
            ];
        }

        return $months;
    }

    /**
     * Convert stored holidays (Gregorian or Jalali) to latin Jalali Y/m/d list.
     *
     * @param  array<int, string>  $holidays
     * @return list<string>
     */
    public static function toJalaliList(array $holidays): array
    {
        return collect($holidays)
            ->map(function ($holiday) {
                $value = trim((string) normalizePersianDigits((string) $holiday));
                if ($value === '') {
                    return null;
                }

                $normalized = str_replace(['.', '/'], '-', $value);
                if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $matches) !== 1) {
                    return null;
                }

                $year = (int) $matches[1];
                if ($year >= 1700) {
                    $jalali = normalizePersianDigits(gregorianToJalaliDate($value));

                    return $jalali !== '' ? $jalali : null;
                }

                return sprintf('%04d/%02d/%02d', $year, (int) $matches[2], (int) $matches[3]);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Count official working days for a Jalali month.
     *
     * @param  array<int, int|string>  $workingDays
     * @param  array<int, int|string>  $weekendDays
     * @param  array<int, string>  $holidayJalaliList
     */
    public static function workingDaysInMonth(
        int $year,
        int $month,
        array $workingDays,
        array $weekendDays,
        array $holidayJalaliList,
    ): int {
        $working = WorkCalendarDefaults::normalizeDayList($workingDays, WorkCalendarDefaults::workingDays());
        $weekends = WorkCalendarDefaults::normalizeDayList($weekendDays, WorkCalendarDefaults::weekendDays());
        $holidays = array_fill_keys(self::toJalaliList($holidayJalaliList), true);
        $count = 0;

        for ($day = 1; $day <= self::daysInMonth($year, $month); $day++) {
            $jalali = sprintf('%04d/%02d/%02d', $year, $month, $day);
            if (isset($holidays[$jalali])) {
                continue;
            }

            $gregorian = jalaliToGregorianDateSafe($year, $month, $day);
            if (! $gregorian) {
                continue;
            }

            $dow = (int) Carbon::parse($gregorian)->dayOfWeek;
            if (in_array($dow, $weekends, true)) {
                continue;
            }

            if (in_array($dow, $working, true)) {
                $count++;
            }
        }

        return $count;
    }
}
