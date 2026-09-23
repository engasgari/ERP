<?php

namespace App\Support;

use App\Models\FiscalYear;
use Carbon\Carbon;

class SalesReportFilters
{
    public static function normalize(array $input): array
    {
        $filters = $input;

        foreach (['date_from', 'date_to'] as $key) {
            if (! empty($filters[$key])) {
                $filters[$key] = jalaliToGregorianDate($filters[$key]) ?: $filters[$key];
            }
        }

        foreach (['party_id', 'project_id', 'item_id', 'created_by', 'fiscal_year_id', 'per_page'] as $key) {
            if (! empty($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }

        if (! empty($filters['category'])) {
            $filters['category'] = trim((string) $filters['category']);
        }

        if (! empty($filters['search'])) {
            $filters['search'] = trim((string) $filters['search']);
        }

        if (! empty($filters['sort'])) {
            $filters['sort'] = trim((string) $filters['sort']);
        }

        if (! empty($filters['direction']) && ! in_array($filters['direction'], ['asc', 'desc'], true)) {
            unset($filters['direction']);
        }

        if (! empty($filters['date_preset'])) {
            $filters = array_merge($filters, self::resolvePreset((string) $filters['date_preset']));
        }

        return $filters;
    }

    /**
     * @return array{date_from?: string, date_to?: string}
     */
    public static function resolvePreset(string $preset): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'today' => [
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'this_week' => [
                'date_from' => $today->copy()->startOfWeek(Carbon::SATURDAY)->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'this_month' => [
                'date_from' => $today->copy()->startOfMonth()->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'last_month' => [
                'date_from' => $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'date_to' => $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'last_3_months' => [
                'date_from' => $today->copy()->subMonthsNoOverflow(3)->startOfMonth()->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'this_year' => self::currentFiscalYearFilters(),
            'current_fiscal_year' => self::currentFiscalYearRange(),
            default => [],
        };
    }

    /**
     * @return array{date_from?: string, date_to?: string, fiscal_year_id?: int}
     */
    public static function currentFiscalYearFilters(): array
    {
        $year = self::currentFiscalYear();
        if (! $year) {
            return [];
        }

        $today = Carbon::today()->toDateString();

        return [
            'fiscal_year_id' => $year->id,
            'date_from' => $year->start_date->toDateString(),
            'date_to' => min($today, $year->end_date->toDateString()),
        ];
    }

    public static function currentFiscalYear(): ?FiscalYear
    {
        $today = Carbon::today()->toDateString();

        return FiscalYear::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * @return array{date_from?: string, date_to?: string}
     */
    private static function currentFiscalYearRange(): array
    {
        $filters = self::currentFiscalYearFilters();

        return array_intersect_key($filters, array_flip(['date_from', 'date_to']));
    }
}
