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
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkLog;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NewAttendanceEngineService
{
    public function processPeriod(PayrollPeriod $period, ?int $employeeId = null, bool $includeAllActive = false): Collection
    {
        if ($employeeId) {
            $employeeIds = collect([$employeeId]);
        } elseif ($includeAllActive) {
            $employeeIds = Employee::query()
                ->where(function ($query): void {
                    $query->where('is_active', true)->orWhere('status', 'active');
                })
                ->pluck('id');
        } else {
            $employeeIds = WorkLog::query()
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

        return Employee::query()
            ->whereIn('id', $employeeIds)
            ->with(['party'])
            ->get()
            ->map(fn (Employee $employee) => $this->processEmployee($period, $employee));
    }

    public function processEmployee(PayrollPeriod $period, Employee $employee): MonthlyAttendance
    {
        $requiredTime = app(RequiredWorkingTimeService::class);
        $order = $requiredTime->activeDecree($employee, $period);
        $isHourly = $this->isHourlyEmployee($employee, $order);
        $monthRequiredTime = $requiredTime->month($employee, $period, $isHourly);
        $normalMinutes = $overtimeMinutes = $delayMinutes = $earlyLeaveMinutes = 0;
        $holidayMinutes = $nightMinutes = $leaveMinutes = $missionMinutes = $absenceMinutes = 0;
        $dailyLeaveDays = $dailyMissionDays = 0.0;
        $presentDays = $workDays = 0;
        $plannedMinutes = 0;
        $daily = [];

        $workLogsByDate = WorkLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WorkLog $log) => $log->work_date?->toDateString() ?? (string) $log->work_date);

        $rawLogsByDate = AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$period->starts_at, $period->ends_at])
            ->orderBy('logged_at')
            ->get()
            ->groupBy(fn (AttendanceRawLog $log) => $log->logged_at->toDateString());

        $leaves = AttendanceLeave::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($period): void {
                $query->where(function ($dated) use ($period): void {
                    $dated->whereDate('start_date', '<=', $period->ends_at)
                        ->whereDate('end_date', '>=', $period->starts_at);
                })->orWhereBetween('leave_date', [$period->starts_at, $period->ends_at]);
            })
            ->get();

        $missions = AttendanceMission::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($period): void {
                $query->where(function ($dated) use ($period): void {
                    $dated->whereDate('start_date', '<=', $period->ends_at)
                        ->whereDate('end_date', '>=', $period->starts_at);
                })->orWhereBetween('mission_date', [$period->starts_at, $period->ends_at]);
            })
            ->get();

        $date = $period->starts_at->copy();
        while ($date->lte($period->ends_at)) {
            $dateKey = $date->toDateString();
            $dayRequired = $monthRequiredTime['daily'][$dateKey];
            $workGroup = $dayRequired['work_group'];
            $shift = $dayRequired['shift'];
            $dailyMinutes = $dayRequired['daily_minutes'];
            $isWorkingDay = $dayRequired['is_working_day'];
            $dayPlanned = $dayRequired['planned_minutes'];

            if ($dayPlanned > 0) {
                $workDays++;
                $plannedMinutes += $dayPlanned;
            }

            $dayLogs = $workLogsByDate->get($dateKey, collect());
            $worked = (int) round((float) $dayLogs->sum('hours') * 60);
            $explicitOvertime = (int) round((float) $dayLogs->sum('overtime_hours') * 60);
            $dayDelay = (int) round((float) $dayLogs->sum('delay_hours') * 60);
            $dayEarly = (int) round((float) $dayLogs->sum('early_leave_hours') * 60);
            $firstLog = $dayLogs->sortBy(fn (WorkLog $log) => $log->check_in_time ?: $log->start_time)->first();
            $lastLog = $dayLogs->sortByDesc(fn (WorkLog $log) => $log->check_out_time ?: $log->end_time)->first();

            if ($worked === 0) {
                [$worked, $dayDelay, $dayEarly, $firstLog, $lastLog] = $this->rawLogWork($date, $rawLogsByDate->get($dateKey, collect()), $shift);
            }

            $dayLeave = $this->approvedMinutesForDate($leaves, $date, $dailyMinutes);
            $dayMission = $this->approvedMinutesForDate($missions, $date, $dailyMinutes);
            $leaveMinutes += $dayLeave['minutes'];
            $missionMinutes += $dayMission['minutes'];
            $dailyLeaveDays += $dayLeave['days'];
            $dailyMissionDays += $dayMission['days'];

            $availablePlanned = max(0, $dayPlanned - $dayLeave['minutes'] - $dayMission['minutes']);
            if ($isHourly) {
                $dayNormal = $worked;
                $dayOvertime = 0;
                $dayHoliday = 0;
                $dayAbsence = 0;
                $dayDelay = 0;
                $dayEarly = 0;
            } else {
                $dayNormal = $isWorkingDay ? min($worked, $dailyMinutes) : 0;
                $dayOvertime = $explicitOvertime > 0 ? $explicitOvertime : max(0, $worked - $availablePlanned);
                $dayHoliday = $isWorkingDay ? 0 : $worked + $dayMission['minutes'];
                $dayAbsence = max(0, $dayPlanned - $worked - $dayLeave['minutes'] - $dayMission['minutes']);
            }

            $normalMinutes += $dayNormal;
            $overtimeMinutes += $dayOvertime;
            $holidayMinutes += $dayHoliday;
            $delayMinutes += $dayDelay;
            $earlyLeaveMinutes += $dayEarly;
            $absenceMinutes += $dayAbsence;
            $nightMinutes += (int) round($this->nightHoursForLogs($date, $firstLog, $lastLog) * 60);

            if ($worked > 0 || $dayMission['minutes'] > 0) {
                $presentDays++;
            }

            $daily[] = [
                'date' => $dateKey,
                'work_group_id' => $workGroup?->id,
                'shift_id' => $shift?->id,
                'planned_minutes' => $dayPlanned,
                'worked_minutes' => $worked,
                'leave_minutes' => $dayLeave['minutes'],
                'mission_minutes' => $dayMission['minutes'],
                'overtime_minutes' => $dayOvertime,
                'absence_minutes' => $dayAbsence,
            ];

            $date->addDay();
        }

        $summary = AttendanceSummary::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $period->year, 'month' => $period->month],
            [
                'payroll_period_id' => $period->id,
                'required_days' => $monthRequiredTime['required_days'],
                'required_hours' => $monthRequiredTime['required_hours'],
                'required_minutes' => $monthRequiredTime['required_minutes'],
                'worked_days' => $presentDays,
                'worked_hours' => round($normalMinutes / 60, 2),
                'planned_minutes' => $plannedMinutes,
                'worked_minutes' => $normalMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'holiday_minutes' => $holidayMinutes,
                'delay_minutes' => $delayMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'absence_minutes' => $absenceMinutes,
                'hourly_leave_minutes' => $leaveMinutes,
                'daily_leave_days' => $dailyLeaveDays,
                'hourly_mission_minutes' => $missionMinutes,
                'daily_mission_days' => $dailyMissionDays,
                'status' => 'calculated',
                'failure_reason' => null,
                'meta' => [
                    'employee_salary_type' => $isHourly ? 'hourly' : 'monthly',
                    'decree_id' => $order?->id,
                    'calculation_rule' => $isHourly
                        ? 'hourly_actual_work_only'
                        : 'planned_shift_calendar_with_overtime_and_absence',
                    'daily' => $daily,
                ],
            ]
        );

        return MonthlyAttendance::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'work_days' => $workDays,
                'present_days' => $presentDays,
                'required_hours' => round($plannedMinutes / 60, 2),
                'normal_hours' => round($normalMinutes / 60, 2),
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'delay_hours' => round($delayMinutes / 60, 2),
                'early_leave_hours' => round($earlyLeaveMinutes / 60, 2),
                'absence_hours' => round($absenceMinutes / 60, 2),
                'leave_hours' => round($leaveMinutes / 60, 2),
                'mission_hours' => round($missionMinutes / 60, 2),
                'night_hours' => round($nightMinutes / 60, 2),
                'holiday_hours' => round($holidayMinutes / 60, 2),
                'payable_hours' => round(($normalMinutes + $overtimeMinutes + $missionMinutes + $holidayMinutes + $nightMinutes) / 60, 2),
                'status' => 'processed',
                'meta' => [
                    'attendance_summary_id' => $summary->id,
                    'employee_salary_type' => $isHourly ? 'hourly' : 'monthly',
                    'decree_id' => $order?->id,
                    'calculation_rule' => $isHourly
                        ? 'hourly_actual_work_only'
                        : 'planned_shift_calendar_with_overtime_and_absence',
                    'daily' => $daily,
                ],
            ]
        );
    }

    private function approvedOrderFor(Employee $employee, PayrollPeriod $period): ?EmploymentOrder
    {
        return $employee->employmentOrders()
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', $period->ends_at)
            ->where(function ($query) use ($period): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->starts_at);
            })
            ->latest('effective_date')
            ->first();
    }

    private function isHourlyEmployee(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        if ($order) {
            return in_array($order->employment_type, ['hourly', 'hourly_contract'], true)
                || ((float) ($order->hourly_rate ?? 0) > 0 && (float) ($order->base_salary ?? 0) <= 0);
        }

        return $employee->salary_type === 'hourly'
            || $employee->employment_type === 'hourly';
    }

    private function activeWorkGroupFor(Employee $employee, Carbon $date): ?WorkGroup
    {
        $assignment = $employee->workGroupAssignments()
            ->with(['workGroup.shift', 'workGroup.calendar'])
            ->where(function ($query) use ($date): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date->toDateString());
            })
            ->latest('start_date')
            ->latest('id')
            ->first();

        return $assignment?->workGroup;
    }

    private function isWorkingDay(Carbon $date, ?WorkCalendar $calendar): bool
    {
        $jalaliDate = formatJalaliDateSafe($date, '');
        $holidays = $calendar?->holidays ?: [];
        if (in_array($date->toDateString(), $holidays, true) || in_array($jalaliDate, $holidays, true)) {
            return false;
        }

        $dayOfWeek = (int) $date->dayOfWeek;
        $weekends = $calendar?->weekend_days ?: [];
        if (in_array($dayOfWeek, $weekends, true)) {
            return false;
        }

        $workingDays = $calendar?->working_days ?: [];
        if ($workingDays !== []) {
            return in_array($dayOfWeek, $workingDays, true);
        }

        return $dayOfWeek !== Carbon::FRIDAY;
    }

    private function approvedMinutesForDate(Collection $requests, Carbon $date, int $dailyMinutes): array
    {
        $minutes = 0;
        $days = 0.0;

        foreach ($requests as $request) {
            $start = Carbon::parse($request->start_date ?: $request->{$request instanceof AttendanceLeave ? 'leave_date' : 'mission_date'});
            $end = Carbon::parse($request->end_date ?: $start);

            if ($date->lt($start) || $date->gt($end)) {
                continue;
            }

            if (($request->request_type ?? 'hourly') === 'daily') {
                $minutes += $dailyMinutes;
                $days += 1.0;
                continue;
            }

            $minutes += (int) ($request->duration_minutes ?: round((float) $request->hours * 60));
        }

        return ['minutes' => min($minutes, $dailyMinutes), 'days' => $days];
    }

    private function rawLogWork(Carbon $date, Collection $logs, ?WorkShift $shift): array
    {
        if ($logs->isEmpty()) {
            return [0, 0, 0, null, null];
        }

        $first = $logs->first()->logged_at;
        $last = $logs->last()->logged_at;
        $worked = max(0, $last->diffInMinutes($first));
        $startTime = $shift?->start_time ?: '08:00:00';
        $endTime = $shift?->end_time ?: '17:00:00';
        $lateTolerance = (int) ($shift?->late_tolerance_minutes ?: 0);
        $earlyTolerance = (int) ($shift?->early_leave_tolerance_minutes ?: 0);
        $scheduledStart = Carbon::parse($date->toDateString() . ' ' . $startTime);
        $scheduledEnd = Carbon::parse($date->toDateString() . ' ' . $endTime);
        $delay = max(0, $scheduledStart->diffInMinutes($first, false) - $lateTolerance);
        $early = max(0, $last->diffInMinutes($scheduledEnd, false) - $earlyTolerance);

        return [$worked, $delay, $early, null, null];
    }

    private function nightHours(Carbon $first, Carbon $last): float
    {
        $nightStart = Carbon::parse($first->toDateString() . ' 22:00:00');
        $nightEnd = Carbon::parse($first->copy()->addDay()->toDateString() . ' 06:00:00');
        $start = $first->greaterThan($nightStart) ? $first : $nightStart;
        $end = $last->lessThan($nightEnd) ? $last : $nightEnd;

        return $end->greaterThan($start) ? $end->diffInMinutes($start) / 60 : 0.0;
    }

    private function nightHoursForLogs(Carbon $date, ?WorkLog $firstLog, ?WorkLog $lastLog): float
    {
        $startTime = $firstLog?->check_in_time ?: $firstLog?->start_time;
        $endTime = $lastLog?->check_out_time ?: $lastLog?->end_time;

        if (! $startTime || ! $endTime) {
            return 0.0;
        }

        $first = Carbon::parse($date->toDateString() . ' ' . $startTime);
        $last = Carbon::parse($date->toDateString() . ' ' . $endTime);

        if ($last->lte($first)) {
            $last->addDay();
        }

        return $this->nightHours($first, $last);
    }
}
