<?php

namespace App\Repositories;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

class AttendanceDailyDetailRepository
{
    /**
     * @param  array{
     *   employee_id?: int|null,
     *   year?: int|null,
     *   month?: int|null,
     *   payroll_period_id?: int|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   only_exceptions?: bool,
     *   only_working_days?: bool
     * }  $filters
     * @return Collection<int, AttendanceSummary>
     */
    public function summaries(array $filters): Collection
    {
        $query = AttendanceSummary::query()
            ->with(['employee.party', 'period'])
            ->where('status', 'calculated')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('employee_id');

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['payroll_period_id'])) {
            $query->where('payroll_period_id', (int) $filters['payroll_period_id']);
        }

        if (! empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function activeEmployees(): Collection
    {
        return Employee::query()
            ->with('party')
            ->where(function ($query): void {
                $query->where('is_active', true)->orWhere('status', 'active');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, PayrollPeriod>
     */
    public function recentPeriods(int $limit = 36): Collection
    {
        return PayrollPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($limit)
            ->get();
    }
}
