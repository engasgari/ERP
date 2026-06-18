<?php

namespace App\Services;

use App\Models\AttendanceCalculation;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\WorkLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceCalculationService
{
    public function calculatePeriod(PayrollPeriod $period): Collection
    {
        $logs = WorkLog::query()
            ->with('employee')
            ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
            ->get()
            ->groupBy('employee_id');

        return DB::transaction(function () use ($period, $logs) {
            AttendanceCalculation::where('payroll_period_id', $period->id)->delete();

            return $logs->map(function (Collection $employeeLogs, int $employeeId) use ($period) {
                $employee = $employeeLogs->first()->employee ?: Employee::find($employeeId);
                $normalHours = 0.0;
                $overtimeHours = 0.0;

                foreach ($employeeLogs as $log) {
                    $hours = (float) $log->hours;
                    $explicitOvertime = (float) ($log->overtime_hours ?? 0);

                    if ($explicitOvertime > 0) {
                        $overtimeHours += $explicitOvertime;
                        $normalHours += max(0, $hours - $explicitOvertime);
                    } else {
                        $normalHours += min($hours, 8);
                        $overtimeHours += max(0, $hours - 8);
                    }
                }

                $hourlyRate = $this->resolveHourlyRate($employee);
                $overtimeRate = $this->resolveOvertimeRate($employee, $hourlyRate);
                $missionHours = (float) $employeeLogs->sum('mission_hours');
                $delayHours = (float) $employeeLogs->sum('delay_hours');
                $earlyLeaveHours = (float) $employeeLogs->sum('early_leave_hours');
                $absenceHours = (float) $employeeLogs->sum('absence_hours');
                $laborCost = ($normalHours * $hourlyRate) + ($overtimeHours * $overtimeRate) + ($missionHours * $hourlyRate);

                return AttendanceCalculation::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'project_id' => null,
                    'normal_hours' => $normalHours,
                    'overtime_hours' => $overtimeHours,
                    'delay_hours' => $delayHours,
                    'early_leave_hours' => $earlyLeaveHours,
                    'mission_hours' => $missionHours,
                    'absence_hours' => $absenceHours,
                    'leave_hours' => (float) $employeeLogs->sum('leave_hours'),
                    'holiday_hours' => 0,
                    'night_hours' => 0,
                    'payable_hours' => $normalHours + $overtimeHours + $missionHours,
                    'hourly_rate' => $hourlyRate,
                    'labor_cost' => $laborCost,
                    'status' => 'calculated',
                    'meta' => [
                        'work_log_count' => $employeeLogs->count(),
                        'work_days' => $employeeLogs->pluck('work_date')->unique()->count(),
                        'overtime_rate' => $overtimeRate,
                    ],
                ]);
            })->values();
        });
    }

    public function resolveHourlyRate(?Employee $employee): float
    {
        if (! $employee) {
            return 0.0;
        }

        $order = $employee->employmentOrders()
            ->where('status', 'approved')
            ->latest('effective_date')
            ->first();

        if ($order && (float) $order->hourly_rate > 0) {
            return (float) $order->hourly_rate;
        }

        if ((float) $employee->hourly_rate > 0) {
            return (float) $employee->hourly_rate;
        }

        $monthlySalary = (float) ($order?->base_salary ?: $employee->base_salary ?: $employee->salary);

        return $monthlySalary > 0 ? round($monthlySalary / 220, 2) : 0.0;
    }

    public function resolveOvertimeRate(?Employee $employee, float $hourlyRate): float
    {
        if ($employee && (float) $employee->overtime_rate > 0) {
            return (float) $employee->overtime_rate;
        }

        return round($hourlyRate * 1.4, 2);
    }
}
