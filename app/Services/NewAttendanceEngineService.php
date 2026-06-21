<?php

namespace App\Services;

use App\Exceptions\AttendancePrerequisiteException;
use App\Models\AttendanceLeave;
use App\Models\AttendanceMission;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\MonthlyAttendance;
use App\Models\PayrollPeriod;
use App\Models\WorkGroup;
use App\Models\WorkGroupEmployee;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NewAttendanceEngineService
{
    public function __construct(
        private readonly AttendanceDayCalculatorService $dayCalculator,
        private readonly EmploymentContractResolver $contractResolver,
    )
    {
    }

    public function processPeriod(PayrollPeriod $period, ?int $employeeId = null, bool $includeAllActive = false): Collection
    {
        $employeeIds = $this->resolveEmployeeIds($period, $employeeId, $includeAllActive);

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->with([
                'party',
                'employmentOrders',
                'workGroupAssignments.workGroup.shift',
                'workGroupAssignments.workGroup.calendar',
            ])
            ->get()
            ->keyBy('id');

        $periodData = $this->loadPeriodData($period, $employeeIds);

        return $employees
            ->values()
            ->map(fn (Employee $employee) => $this->processEmployee($period, $employee, $periodData));
    }

    public function processEmployee(PayrollPeriod $period, Employee $employee, ?array $periodData = null): MonthlyAttendance
    {
        $periodData ??= $this->loadPeriodData($period, collect([$employee->id]));

        $requiredTime = app(RequiredWorkingTimeService::class);
        $order = $this->approvedOrderFor($employee, $period);
        $contractType = $this->contractTypeFor($employee, $order);
        $monthRequiredTime = $requiredTime->month($employee, $period, $contractType === 'hourly');

        if ($contractType !== 'monthly') {
            return $this->processWorkBasedEmployee($period, $employee, $periodData, $order, $monthRequiredTime, $contractType);
        }

        $calendarDays = $period->starts_at->daysInMonth;
        $workingDays = 0;
        $presentDays = 0;
        $plannedMinutes = 0;
        $workedMinutes = 0;
        $breakMinutes = 0;
        $delayMinutes = 0;
        $earlyLeaveMinutes = 0;
        $leaveMinutes = 0;
        $missionMinutes = 0;
        $absenceMinutes = 0;
        $overtimeMinutes = 0;
        $holidayMinutes = 0;
        $netPayableMinutes = 0;
        $payableMinutes = 0;
        $nightMinutes = 0;
        $dailyLeaveDays = 0.0;
        $dailyMissionDays = 0.0;
        $daily = [];

        $date = $period->starts_at->copy();
        while ($date->lte($period->ends_at)) {
            $dateKey = $date->toDateString();
            $workGroup = $this->workGroupForDate($employee, $date);
            $employeeWorkLogs = $periodData['work_logs'][$employee->id] ?? collect();
            $employeeRawLogs = $periodData['raw_logs'][$employee->id] ?? collect();
            $dayWorkLogs = $employeeWorkLogs[$dateKey] ?? collect();
            $dayRawLogs = $employeeRawLogs[$dateKey] ?? collect();
            $dayLogs = $dayWorkLogs->isNotEmpty() ? $dayWorkLogs : $dayRawLogs;
            $dayLeaves = $periodData['leaves'][$employee->id] ?? collect();
            $dayMissions = $periodData['missions'][$employee->id] ?? collect();

            $metrics = $this->dayCalculator->calculate(
                $date,
                $workGroup,
                $dayLogs,
                $dayLeaves,
                $dayMissions,
            );

            if ((int) $metrics['planned_minutes'] > 0) {
                $workingDays++;
                $plannedMinutes += (int) $metrics['planned_minutes'];
            }

            $workedMinutes += (int) $metrics['worked_minutes'];
            $breakMinutes += (int) $metrics['break_minutes'];
            $delayMinutes += (int) $metrics['delay_minutes'];
            $earlyLeaveMinutes += (int) $metrics['early_leave_minutes'];
            $leaveMinutes += (int) $metrics['leave_minutes'];
            $missionMinutes += (int) $metrics['mission_minutes'];
            $absenceMinutes += (int) $metrics['absence_minutes'];
            $overtimeMinutes += (int) $metrics['overtime_minutes'];
            $holidayMinutes += (int) $metrics['holiday_minutes'];
            $netPayableMinutes += (int) $metrics['net_payable_minutes'];
            $payableMinutes += (int) $metrics['payable_minutes'];
            $dailyLeaveDays += (float) $metrics['leave_days'];
            $dailyMissionDays += (float) $metrics['mission_days'];

            if ((int) $metrics['worked_minutes'] > 0 || (int) $metrics['leave_minutes'] > 0 || (int) $metrics['mission_minutes'] > 0 || (int) $metrics['holiday_minutes'] > 0) {
                $presentDays++;
            }

            $nightMinutes += $this->nightMinutesForLogs($date, $dayLogs);

            $daily[] = $metrics + [
                'work_group_id' => $metrics['work_group_id'],
                'shift_id' => $metrics['shift_id'],
                'work_date' => $dateKey,
                'worked_hours' => round(((int) $metrics['worked_minutes']) / 60, 2),
                'break_hours' => round(((int) $metrics['break_minutes']) / 60, 2),
                'delay_hours' => round(((int) $metrics['delay_minutes']) / 60, 2),
                'early_leave_hours' => round(((int) $metrics['early_leave_minutes']) / 60, 2),
                'leave_hours' => round(((int) $metrics['leave_minutes']) / 60, 2),
                'mission_hours' => round(((int) $metrics['mission_minutes']) / 60, 2),
                'absence_hours' => round(((int) $metrics['absence_minutes']) / 60, 2),
                'overtime_hours' => round(((int) $metrics['overtime_minutes']) / 60, 2),
                'holiday_hours' => round(((int) $metrics['holiday_minutes']) / 60, 2),
                'net_payable_hours' => round(((int) $metrics['net_payable_minutes']) / 60, 2),
                'payable_hours' => round(((int) $metrics['payable_minutes']) / 60, 2),
            ];

            $date->addDay();
        }

        $summary = AttendanceSummary::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $period->year, 'month' => $period->month],
            [
                'payroll_period_id' => $period->id,
                'calendar_days' => $calendarDays,
                'working_days' => $workingDays,
                'required_days' => $monthRequiredTime['required_days'],
                'required_hours' => $monthRequiredTime['required_hours'],
                'required_minutes' => $monthRequiredTime['required_minutes'],
                'worked_days' => $presentDays,
                'worked_hours' => round($workedMinutes / 60, 2),
                'planned_minutes' => $plannedMinutes,
                'worked_minutes' => $workedMinutes,
                'break_minutes' => $breakMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'holiday_minutes' => $holidayMinutes,
                'delay_minutes' => $delayMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'absence_minutes' => $absenceMinutes,
                'absence_hours' => round($absenceMinutes / 60, 2),
                'hourly_leave_minutes' => $leaveMinutes,
                'leave_hours' => round($leaveMinutes / 60, 2),
                'daily_leave_days' => $dailyLeaveDays,
                'hourly_mission_minutes' => $missionMinutes,
                'mission_hours' => round($missionMinutes / 60, 2),
                'daily_mission_days' => $dailyMissionDays,
                'net_payable_hours' => round($netPayableMinutes / 60, 2),
                'status' => 'calculated',
                'failure_reason' => null,
                'meta' => [
                    'employee_salary_type' => $contractType,
                    'decree_id' => $order?->id,
                    'calculation_rule' => 'shift_based_worked_minutes_with_leave_and_mission',
                    'daily' => $daily,
                ],
            ]
        );

        return MonthlyAttendance::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'calendar_days' => $calendarDays,
                'working_days' => $workingDays,
                'work_days' => $workingDays,
                'present_days' => $presentDays,
                'required_hours' => round($plannedMinutes / 60, 2),
                'normal_hours' => round($workedMinutes / 60, 2),
                'worked_hours' => round($workedMinutes / 60, 2),
                'break_minutes' => $breakMinutes,
                'delay_minutes' => $delayMinutes,
                'delay_hours' => round($delayMinutes / 60, 2),
                'early_leave_minutes' => $earlyLeaveMinutes,
                'early_leave_hours' => round($earlyLeaveMinutes / 60, 2),
                'absence_hours' => round($absenceMinutes / 60, 2),
                'leave_hours' => round($leaveMinutes / 60, 2),
                'mission_hours' => round($missionMinutes / 60, 2),
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'night_hours' => round($nightMinutes / 60, 2),
                'holiday_hours' => round($holidayMinutes / 60, 2),
                'payable_hours' => round($payableMinutes / 60, 2),
                'net_payable_hours' => round($netPayableMinutes / 60, 2),
                'status' => 'processed',
                'meta' => [
                    'attendance_summary_id' => $summary->id,
                    'employee_salary_type' => $contractType,
                    'decree_id' => $order?->id,
                    'calculation_rule' => 'shift_based_worked_minutes_with_leave_and_mission',
                    'daily' => $daily,
                ],
            ] + (Schema::hasColumn('monthly_attendances', 'absence_minutes') ? ['absence_minutes' => $absenceMinutes] : [])
        );
    }

    private function processWorkBasedEmployee(
        PayrollPeriod $period,
        Employee $employee,
        array $periodData,
        ?EmploymentOrder $order,
        array $monthRequiredTime,
        string $contractType,
    ): MonthlyAttendance {
        $calendarDays = $period->starts_at->daysInMonth;
        $workingDays = 0;
        $workedMinutes = 0;
        $daily = [];

        $employeeWorkLogs = $periodData['work_logs'][$employee->id] ?? collect();
        $employeeRawLogs = $periodData['raw_logs'][$employee->id] ?? collect();

        $date = $period->starts_at->copy();
        while ($date->lte($period->ends_at)) {
            $dateKey = $date->toDateString();
            $dayWorkLogs = $employeeWorkLogs[$dateKey] ?? collect();
            $dayRawLogs = $employeeRawLogs[$dateKey] ?? collect();
            $dayLogs = $dayWorkLogs->isNotEmpty() ? $dayWorkLogs : $dayRawLogs;

            $dayWorkedMinutes = (int) round($dayLogs->sum(fn (WorkLog|AttendanceRawLog $log) => (float) ($log->hours ?? 0) * 60));

            if ($dayWorkedMinutes > 0) {
                $workingDays++;
            }

            $workedMinutes += $dayWorkedMinutes;

            $daily[] = [
                'date' => $dateKey,
                'is_working_day' => $dayWorkedMinutes > 0,
                'calendar_day' => $date->day,
                'planned_minutes' => 0,
                'worked_minutes' => $dayWorkedMinutes,
                'break_minutes' => 0,
                'delay_minutes' => 0,
                'early_leave_minutes' => 0,
                'leave_minutes' => 0,
                'mission_minutes' => 0,
                'absence_minutes' => 0,
                'overtime_minutes' => 0,
                'holiday_minutes' => 0,
                'net_payable_minutes' => $dayWorkedMinutes,
                'payable_minutes' => $dayWorkedMinutes,
                'leave_days' => 0,
                'mission_days' => 0,
                'work_group_id' => null,
                'shift_id' => null,
                'shift_code' => null,
                'shift_start_time' => null,
                'shift_end_time' => null,
                'overtime_multiplier' => 1.4,
                'late_tolerance_minutes' => 0,
                'early_leave_tolerance_minutes' => 0,
                'break_window' => ['start' => null, 'end' => null],
                'first_log_at' => $dayLogs->first()?->check_in_time ? $date->toDateString() . ' ' . $dayLogs->first()->check_in_time : null,
                'last_log_at' => $dayLogs->last()?->check_out_time ? $date->toDateString() . ' ' . $dayLogs->last()->check_out_time : null,
                'worked_hours' => round($dayWorkedMinutes / 60, 2),
                'break_hours' => 0,
                'delay_hours' => 0,
                'early_leave_hours' => 0,
                'leave_hours' => 0,
                'mission_hours' => 0,
                'absence_hours' => 0,
                'overtime_hours' => 0,
                'holiday_hours' => 0,
                'net_payable_hours' => round($dayWorkedMinutes / 60, 2),
                'payable_hours' => round($dayWorkedMinutes / 60, 2),
            ];

            $date->addDay();
        }

        $summary = AttendanceSummary::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $period->year, 'month' => $period->month],
            [
                'payroll_period_id' => $period->id,
                'calendar_days' => $calendarDays,
                'working_days' => $workingDays,
                'required_days' => $monthRequiredTime['required_days'],
                'required_hours' => $monthRequiredTime['required_hours'],
                'required_minutes' => $monthRequiredTime['required_minutes'],
                'worked_days' => $workingDays,
                'worked_hours' => round($workedMinutes / 60, 2),
                'planned_minutes' => 0,
                'worked_minutes' => $workedMinutes,
                'break_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0,
                'holiday_minutes' => 0,
                'delay_minutes' => 0,
                'early_leave_minutes' => 0,
                'absence_minutes' => 0,
                'absence_hours' => 0,
                'hourly_leave_minutes' => 0,
                'leave_hours' => 0,
                'daily_leave_days' => 0,
                'hourly_mission_minutes' => 0,
                'mission_hours' => 0,
                'daily_mission_days' => 0,
                'net_payable_hours' => round($workedMinutes / 60, 2),
                'status' => 'calculated',
                'failure_reason' => null,
                'meta' => [
                    'employee_salary_type' => $contractType,
                    'decree_id' => $order?->id,
                    'calculation_rule' => 'work_log_only_by_contract_type',
                    'daily' => $daily,
                ],
            ]
        );

        return MonthlyAttendance::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'calendar_days' => $calendarDays,
                'working_days' => $workingDays,
                'work_days' => $workingDays,
                'present_days' => $workingDays,
                'required_hours' => $monthRequiredTime['required_hours'],
                'worked_hours' => round($workedMinutes / 60, 2),
                'normal_hours' => round($workedMinutes / 60, 2),
                'break_minutes' => 0,
                'delay_minutes' => 0,
                'delay_hours' => 0,
                'early_leave_minutes' => 0,
                'early_leave_hours' => 0,
                'absence_hours' => 0,
                'leave_hours' => 0,
                'mission_hours' => 0,
                'overtime_hours' => 0,
                'night_hours' => 0,
                'holiday_hours' => 0,
                'payable_hours' => round($workedMinutes / 60, 2),
                'net_payable_hours' => round($workedMinutes / 60, 2),
                'status' => 'processed',
                'meta' => [
                    'attendance_summary_id' => $summary->id,
                    'employee_salary_type' => $contractType,
                    'decree_id' => $order?->id,
                    'calculation_rule' => 'work_log_only_by_contract_type',
                    'daily' => $daily,
                ],
            ] + (Schema::hasColumn('monthly_attendances', 'absence_minutes') ? ['absence_minutes' => 0] : [])
        );
    }

    private function resolveEmployeeIds(PayrollPeriod $period, ?int $employeeId, bool $includeAllActive): Collection
    {
        if ($employeeId) {
            return collect([$employeeId]);
        }

        if ($includeAllActive) {
            return Employee::query()
                ->where(function ($query): void {
                    $query->where('is_active', true)->orWhere('status', 'active');
                })
                ->pluck('id');
        }

        return WorkLog::query()
            ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
            ->pluck('employee_id')
            ->merge(AttendanceRawLog::query()
                ->whereBetween('logged_at', [$period->starts_at, $period->ends_at])
                ->pluck('employee_id'))
            ->merge(AttendanceLeave::query()
                ->where('status', 'approved')
                ->where(function ($query) use ($period): void {
                    $query->where(function ($dated) use ($period): void {
                        $dated->whereDate('start_date', '<=', $period->ends_at)
                            ->whereDate('end_date', '>=', $period->starts_at);
                    })->orWhereBetween('leave_date', [$period->starts_at, $period->ends_at]);
                })
                ->pluck('employee_id'))
            ->merge(AttendanceMission::query()
                ->where('status', 'approved')
                ->where(function ($query) use ($period): void {
                    $query->where(function ($dated) use ($period): void {
                        $dated->whereDate('start_date', '<=', $period->ends_at)
                            ->whereDate('end_date', '>=', $period->starts_at);
                    })->orWhereBetween('mission_date', [$period->starts_at, $period->ends_at]);
                })
                ->pluck('employee_id'))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return array{
     *   work_logs: array<int, Collection<string, Collection<int, WorkLog>>>,
     *   raw_logs: array<int, Collection<string, Collection<int, AttendanceRawLog>>>,
     *   leaves: array<int, Collection<int, AttendanceLeave>>,
     *   missions: array<int, Collection<int, AttendanceMission>>
     * }
     */
    private function loadPeriodData(PayrollPeriod $period, Collection $employeeIds): array
    {
        $workLogs = WorkLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $employeeLogs) => $employeeLogs->groupBy(fn (WorkLog $log) => $log->work_date?->toDateString() ?? (string) $log->work_date));

        $rawLogs = AttendanceRawLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('logged_at', [$period->starts_at, $period->ends_at])
            ->orderBy('logged_at')
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $employeeLogs) => $employeeLogs->groupBy(fn (AttendanceRawLog $log) => $log->logged_at->toDateString()));

        $leaves = AttendanceLeave::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where(function ($query) use ($period): void {
                $query->where(function ($dated) use ($period): void {
                    $dated->whereDate('start_date', '<=', $period->ends_at)
                        ->whereDate('end_date', '>=', $period->starts_at);
                })->orWhereBetween('leave_date', [$period->starts_at, $period->ends_at]);
            })
            ->get()
            ->groupBy('employee_id');

        $missions = AttendanceMission::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where(function ($query) use ($period): void {
                $query->where(function ($dated) use ($period): void {
                    $dated->whereDate('start_date', '<=', $period->ends_at)
                        ->whereDate('end_date', '>=', $period->starts_at);
                })->orWhereBetween('mission_date', [$period->starts_at, $period->ends_at]);
            })
            ->get()
            ->groupBy('employee_id');

        return [
            'work_logs' => $workLogs->all(),
            'raw_logs' => $rawLogs->all(),
            'leaves' => $leaves->all(),
            'missions' => $missions->all(),
        ];
    }

    private function approvedOrderFor(Employee $employee, PayrollPeriod $period): ?EmploymentOrder
    {
        $orders = $employee->relationLoaded('employmentOrders')
            ? $employee->employmentOrders
            : $employee->employmentOrders()->get();

        return $orders
            ->where('status', 'approved')
            ->filter(function (EmploymentOrder $order) use ($period): bool {
                return $order->effective_date
                    && $order->effective_date->lte($period->ends_at)
                    && (! $order->end_date || $order->end_date->gte($period->starts_at));
            })
            ->sortByDesc('effective_date')
            ->sortByDesc('id')
            ->first();
    }

    private function contractTypeFor(Employee $employee, ?EmploymentOrder $order = null): string
    {
        return $this->contractResolver->resolve($employee, $order);
    }

    private function isHourlyEmployee(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        return $this->contractTypeFor($employee, $order) === 'hourly';
    }

    private function isProjectBasedEmployee(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        return $this->contractTypeFor($employee, $order) === 'project';
    }

    private function isWorkBasedEmployee(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        return in_array($this->contractTypeFor($employee, $order), ['hourly', 'project'], true);
    }

    private function workGroupForDate(Employee $employee, Carbon $date): ?WorkGroup
    {
        $assignments = $employee->workGroupAssignments ?? collect();

        return $assignments
            ->filter(function (WorkGroupEmployee $assignment) use ($date): bool {
                return (! $assignment->start_date || $assignment->start_date->lte($date))
                    && (! $assignment->end_date || $assignment->end_date->gte($date));
            })
            ->sortByDesc(fn (WorkGroupEmployee $assignment) => $assignment->start_date?->timestamp ?? 0)
            ->sortByDesc('id')
            ->first()
            ?->workGroup;
    }

    private function nightMinutesForLogs(Carbon $date, Collection $logs): int
    {
        if ($logs->isEmpty()) {
            return 0;
        }

        $first = $this->logMoment($date, $logs->first(), true);
        $last = $this->logMoment($date, $logs->last(), false);

        if (! $first || ! $last) {
            return 0;
        }

        $nightStart = Carbon::parse($date->toDateString() . ' 22:00:00');
        $nightEnd = Carbon::parse($date->copy()->addDay()->toDateString() . ' 06:00:00');
        $start = $first->greaterThan($nightStart) ? $first : $nightStart;
        $end = $last->lessThan($nightEnd) ? $last : $nightEnd;

        return $end->greaterThan($start) ? $end->diffInMinutes($start) : 0;
    }

    private function logMoment(Carbon $date, WorkLog|AttendanceRawLog $log, bool $start): ?Carbon
    {
        $time = $start
            ? ($log->check_in_time ?? $log->start_time ?? null)
            : ($log->check_out_time ?? $log->end_time ?? null);

        if (! $time && isset($log->logged_at)) {
            return Carbon::parse($log->logged_at);
        }

        if (! $time) {
            return null;
        }

        $moment = Carbon::parse($date->toDateString() . ' ' . $time);

        if (! $start && $moment->lte(Carbon::parse($date->toDateString() . ' ' . ($log->check_in_time ?? $log->start_time ?? $time)))) {
            $moment->addDay();
        }

        return $moment;
    }
}
