<?php

namespace App\Services;

use App\Models\AttendanceCalculation;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceCalculationService
{
    public function __construct(private readonly NewAttendanceEngineService $attendanceEngine)
    {
    }

    public function calculatePeriod(PayrollPeriod $period): Collection
    {
        $attendances = $this->attendanceEngine->processPeriod($period)->loadMissing('employee.party');

        return DB::transaction(function () use ($period, $attendances) {
            AttendanceCalculation::where('payroll_period_id', $period->id)->delete();

            return $attendances->map(function (MonthlyAttendance $attendance) use ($period) {
                $employee = $attendance->employee ?: Employee::find($attendance->employee_id);
                $hourlyRate = $this->resolveHourlyRate($employee);
                $overtimeRate = $this->resolveOvertimeRate($employee, $hourlyRate);
                $workedHours = (float) ($attendance->worked_hours ?? $attendance->normal_hours ?? 0);
                $overtimeHours = (float) ($attendance->overtime_hours ?? 0);
                $missionHours = (float) ($attendance->mission_hours ?? 0);
                $delayHours = (float) ($attendance->delay_hours ?? 0);
                $earlyLeaveHours = (float) ($attendance->early_leave_hours ?? 0);
                $absenceHours = (float) ($attendance->absence_hours ?? 0);
                $leaveHours = (float) ($attendance->leave_hours ?? 0);
                $payableHours = (float) ($attendance->payable_hours ?? 0);
                $laborCost = (($attendance->net_payable_hours ?? $workedHours) * $hourlyRate)
                    + ($overtimeHours * $overtimeRate)
                    + ($missionHours * $hourlyRate);

                return AttendanceCalculation::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $attendance->employee_id,
                    'project_id' => null,
                    'normal_hours' => $workedHours,
                    'overtime_hours' => $overtimeHours,
                    'delay_hours' => $delayHours,
                    'early_leave_hours' => $earlyLeaveHours,
                    'mission_hours' => $missionHours,
                    'absence_hours' => $absenceHours,
                    'leave_hours' => $leaveHours,
                    'holiday_hours' => (float) ($attendance->holiday_hours ?? 0),
                    'night_hours' => (float) ($attendance->night_hours ?? 0),
                    'payable_hours' => $payableHours,
                    'hourly_rate' => $hourlyRate,
                    'labor_cost' => $laborCost,
                    'status' => 'calculated',
                    'meta' => [
                        'work_day_count' => count((array) ($attendance->meta['daily'] ?? [])),
                        'working_days' => (int) ($attendance->working_days ?? $attendance->work_days ?? 0),
                        'overtime_rate' => $overtimeRate,
                        'net_payable_hours' => (float) ($attendance->net_payable_hours ?? 0),
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
