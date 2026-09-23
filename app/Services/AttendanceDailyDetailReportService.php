<?php

namespace App\Services;

use App\Repositories\AttendanceDailyDetailRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceDailyDetailReportService
{
    public function __construct(
        private readonly AttendanceDailyDetailRepository $repository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   filters: array<string, mixed>,
     *   employees: Collection,
     *   periods: Collection,
     *   rows: Collection<int, array<string, mixed>>,
     *   summary: array<string, float|int>
     * }
     */
    public function report(array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $summaries = $this->repository->summaries($normalized);

        $rows = collect();
        foreach ($summaries as $summary) {
            $daily = collect($summary->meta['daily'] ?? []);
            foreach ($daily as $day) {
                $row = $this->mapDayRow($summary, is_array($day) ? $day : []);
                if (! $this->passesDayFilters($row, $normalized)) {
                    continue;
                }
                $rows->push($row);
            }
        }

        $rows = $rows
            ->sortBy([
                ['employee_name', 'asc'],
                ['work_date', 'asc'],
            ])
            ->values();

        return [
            'filters' => $normalized,
            'employees' => $this->repository->activeEmployees(),
            'periods' => $this->repository->recentPeriods(),
            'rows' => $rows,
            'summary' => [
                'days' => $rows->count(),
                'worked_hours' => round($rows->sum('worked_hours'), 2),
                'delay_hours' => round($rows->sum('delay_hours'), 2),
                'early_leave_hours' => round($rows->sum('early_leave_hours'), 2),
                'absence_hours' => round($rows->sum('absence_hours'), 2),
                'overtime_hours' => round($rows->sum('overtime_hours'), 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'employee_id' => filled($filters['employee_id'] ?? null) ? (int) $filters['employee_id'] : null,
            'year' => filled($filters['year'] ?? null) ? (int) $filters['year'] : null,
            'month' => filled($filters['month'] ?? null) ? (int) $filters['month'] : null,
            'payroll_period_id' => filled($filters['payroll_period_id'] ?? null) ? (int) $filters['payroll_period_id'] : null,
            'date_from' => $this->normalizeDate($filters['date_from'] ?? null),
            'date_to' => $this->normalizeDate($filters['date_to'] ?? null),
            'only_exceptions' => filter_var($filters['only_exceptions'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'only_working_days' => filter_var($filters['only_working_days'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $raw)) {
            $gregorian = jalaliToGregorianDateSafe(
                (int) substr($raw, 0, 4),
                (int) substr($raw, 5, 2),
                (int) substr($raw, 8, 2)
            );

            return $gregorian ?: null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    private function mapDayRow(object $summary, array $day): array
    {
        $workDate = (string) ($day['work_date'] ?? $day['date'] ?? '');
        $delayMinutes = (int) ($day['delay_minutes'] ?? 0);
        $earlyMinutes = (int) ($day['early_leave_minutes'] ?? 0);
        $absenceMinutes = (int) ($day['absence_minutes'] ?? 0);
        $workedMinutes = (int) ($day['worked_minutes'] ?? 0);
        $overtimeMinutes = (int) ($day['overtime_minutes'] ?? 0);
        $plannedMinutes = (int) ($day['planned_minutes'] ?? 0);

        $firstLog = $day['first_log_at'] ?? null;
        $lastLog = $day['last_log_at'] ?? null;

        return [
            'employee_id' => $summary->employee_id,
            'employee_name' => $summary->employee?->full_name ?? '-',
            'personnel_code' => $summary->employee?->personnel_code ?? '-',
            'year' => $summary->year,
            'month' => $summary->month,
            'period_title' => $summary->period?->persian_title ?? ($summary->year . '/' . str_pad((string) $summary->month, 2, '0', STR_PAD_LEFT)),
            'work_date' => $workDate,
            'work_date_jalali' => $workDate ? formatJalaliDateSafe($workDate) : '-',
            'is_working_day' => (bool) ($day['is_working_day'] ?? false),
            'shift_code' => $day['shift_code'] ?? '-',
            'shift_start' => $this->formatTime($day['shift_start_time'] ?? null),
            'shift_end' => $this->formatTime($day['shift_end_time'] ?? null),
            'first_in' => $this->formatDateTimeTime($firstLog),
            'last_out' => $this->formatDateTimeTime($lastLog),
            'planned_hours' => round($plannedMinutes / 60, 2),
            'worked_hours' => round($workedMinutes / 60, 2),
            'break_hours' => round(((int) ($day['break_minutes'] ?? 0)) / 60, 2),
            'delay_hours' => round($delayMinutes / 60, 2),
            'delay_minutes' => $delayMinutes,
            'delay_range' => $this->formatRange($day['delay_from'] ?? null, $day['delay_to'] ?? null),
            'early_leave_hours' => round($earlyMinutes / 60, 2),
            'early_leave_minutes' => $earlyMinutes,
            'early_leave_range' => $this->formatRange($day['early_leave_from'] ?? null, $day['early_leave_to'] ?? null),
            'absence_hours' => round($absenceMinutes / 60, 2),
            'absence_minutes' => $absenceMinutes,
            'absence_range' => $this->formatRange($day['absence_from'] ?? null, $day['absence_to'] ?? null),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'leave_hours' => round(((int) ($day['leave_minutes'] ?? 0)) / 60, 2),
            'mission_hours' => round(((int) ($day['mission_minutes'] ?? 0)) / 60, 2),
            'net_payable_hours' => round(((int) ($day['net_payable_minutes'] ?? 0)) / 60, 2),
            'has_exception' => $delayMinutes > 0 || $earlyMinutes > 0 || $absenceMinutes > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private function passesDayFilters(array $row, array $filters): bool
    {
        if ($filters['only_working_days'] && ! $row['is_working_day']) {
            return false;
        }

        if ($filters['only_exceptions'] && ! $row['has_exception']) {
            return false;
        }

        if ($filters['date_from'] && $row['work_date'] < $filters['date_from']) {
            return false;
        }

        if ($filters['date_to'] && $row['work_date'] > $filters['date_to']) {
            return false;
        }

        return true;
    }

    private function formatTime(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        $raw = (string) $value;

        return strlen($raw) >= 5 ? substr($raw, 0, 5) : $raw;
    }

    private function formatDateTimeTime(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)->format('H:i');
        } catch (\Throwable) {
            return $this->formatTime($value);
        }
    }

    private function formatRange(mixed $from, mixed $to): string
    {
        if (! filled($from) || ! filled($to)) {
            return '-';
        }

        return $this->formatTime($from) . ' تا ' . $this->formatTime($to);
    }
}
