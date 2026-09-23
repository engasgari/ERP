<?php

namespace App\Support;

use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;

class ExecutiveDashboardPeriod
{
    public const PRESETS = [
        'today' => 'امروز',
        'this_week' => 'این هفته',
        'this_month' => 'این ماه',
        'this_quarter' => 'این فصل',
        'this_year' => 'امسال',
        'custom' => 'بازه سفارشی',
    ];

    /**
     * @return array{date_from: string, date_to: string, preset: string, label: string}
     */
    public static function resolve(string $preset, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $preset = $preset !== '' ? $preset : 'this_month';

        if ($preset === 'custom' && $dateFrom && $dateTo) {
            $from = jalaliToGregorianDate($dateFrom) ?: $dateFrom;
            $to = jalaliToGregorianDate($dateTo) ?: $dateTo;

            return [
                'preset' => 'custom',
                'label' => self::PRESETS['custom'],
                'date_from' => $from,
                'date_to' => $to,
            ];
        }

        if ($preset === 'today') {
            $today = Carbon::today()->toDateString();

            return [
                'preset' => 'today',
                'label' => self::PRESETS['today'],
                'date_from' => $today,
                'date_to' => $today,
            ];
        }

        if ($preset === 'this_week') {
            $from = Carbon::today()->startOfWeek(Carbon::SATURDAY);
            $to = $from->copy()->addDays(6);

            return [
                'preset' => 'this_week',
                'label' => self::PRESETS['this_week'],
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ];
        }

        if ($preset === 'this_month') {
            $now = Verta::now();
            $range = getPersianMonthRange((int) $now->year, (int) $now->month);

            return [
                'preset' => 'this_month',
                'label' => self::PRESETS['this_month'],
                'date_from' => $range['start']->toDateString(),
                'date_to' => $range['end']->toDateString(),
            ];
        }

        if ($preset === 'this_quarter') {
            $now = Verta::now();
            $month = (int) $now->month;
            $seasonStartMonth = (int) (floor(($month - 1) / 3) * 3 + 1);
            $seasonEndMonth = $seasonStartMonth + 2;
            $from = getPersianMonthRange((int) $now->year, $seasonStartMonth)['start'];
            $to = getPersianMonthRange((int) $now->year, $seasonEndMonth)['end'];

            return [
                'preset' => 'this_quarter',
                'label' => self::PRESETS['this_quarter'],
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ];
        }

        if ($preset === 'this_year') {
            $range = SalesReportFilters::resolvePreset('this_year');

            return [
                'preset' => 'this_year',
                'label' => self::PRESETS['this_year'],
                'date_from' => $range['date_from'] ?? Carbon::today()->startOfYear()->toDateString(),
                'date_to' => $range['date_to'] ?? Carbon::today()->toDateString(),
            ];
        }

        $today = Carbon::today();

        return [
            'preset' => $preset,
            'label' => self::PRESETS[$preset] ?? $preset,
            'date_from' => $today->toDateString(),
            'date_to' => $today->toDateString(),
        ];
    }

    /**
     * @param  array{date_from: string, date_to: string}  $period
     * @return array{date_from: string, date_to: string}
     */
    public static function previous(array $period): array
    {
        $from = Carbon::parse($period['date_from'])->startOfDay();
        $to = Carbon::parse($period['date_to'])->startOfDay();
        $days = max(1, $from->diffInDays($to) + 1);

        $previousEnd = $from->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [
            'date_from' => $previousStart->toDateString(),
            'date_to' => $previousEnd->toDateString(),
        ];
    }

    public static function growthPercent(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
