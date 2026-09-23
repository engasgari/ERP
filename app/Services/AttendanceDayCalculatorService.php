<?php

namespace App\Services;

use App\Models\AttendanceLeave;
use App\Models\AttendanceMission;
use App\Models\AttendanceRawLog;
use App\Models\WorkGroup;
use App\Models\WorkLog;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceDayCalculatorService
{
    /**
     * Calculate one employee/day using the assigned shift configuration.
     *
     * @param  Collection<int, WorkLog|AttendanceRawLog>  $logs
     * @param  Collection<int, AttendanceLeave>  $leaves
     * @param  Collection<int, AttendanceMission>  $missions
     * @return array<string, mixed>
     */
    public function calculate(
        Carbon $date,
        ?WorkGroup $workGroup,
        Collection $logs,
        Collection $leaves,
        Collection $missions,
    ): array {
        $shift = $workGroup?->shift;
        $calendar = $workGroup?->calendar;
        $config = $this->resolvedShiftConfig($shift, $workGroup?->payroll_rules ?? []);
        $isWorkingDay = $this->isWorkingDay($date, $calendar);
        $plannedMinutes = $isWorkingDay ? $this->plannedMinutes($config['start_time'], $config['end_time']) - (int) $config['break_minutes'] : 0;
        $plannedMinutes = max(0, $plannedMinutes);

        $workMetrics = $this->logMetrics($date, $logs, $config, $plannedMinutes);
        $workedMinutes = $workMetrics['worked_minutes'];
        $firstLog = $workMetrics['first_log'];
        $lastLog = $workMetrics['last_log'];

        $leaveMetrics = $this->requestMetrics($date, $leaves, $plannedMinutes, $config, true);
        $missionMetrics = $this->requestMetrics($date, $missions, $plannedMinutes, $config, false);
        $requestCoverageMinutes = $leaveMetrics['minutes'] + $missionMetrics['minutes'];

        $delayMinutes = 0;
        $earlyMinutes = 0;
        $delayFrom = null;
        $delayTo = null;
        $earlyFrom = null;
        $earlyTo = null;
        $scheduledStart = $this->dateTime($date, $config['start_time']);
        $scheduledEnd = $this->dateTime($date, $config['end_time'], $this->isOvernightShift($config['start_time'], $config['end_time']));

        if ($isWorkingDay && $shift && $firstLog && $lastLog) {
            if ($firstLog->gt($scheduledStart)) {
                $rawLate = $scheduledStart->diffInMinutes($firstLog);
                $coveredLate = $this->requestEdgeCoverageMinutes($date, $leaves->concat($missions), $scheduledStart, $firstLog, $config);
                $delayMinutes = max(0, $rawLate - (int) $config['late_tolerance_minutes'] - $coveredLate);
                if ($delayMinutes > 0) {
                    $delayFrom = $scheduledStart->copy()->addMinutes($coveredLate)->format('H:i');
                    $delayTo = $firstLog->format('H:i');
                }
            }
            if ($lastLog->lt($scheduledEnd)) {
                $rawEarly = $lastLog->diffInMinutes($scheduledEnd);
                $coveredEarly = $this->requestEdgeCoverageMinutes($date, $leaves->concat($missions), $lastLog, $scheduledEnd, $config);
                $earlyMinutes = max(0, $rawEarly - (int) $config['early_leave_tolerance_minutes'] - $coveredEarly);
                if ($earlyMinutes > 0) {
                    $earlyFrom = $lastLog->format('H:i');
                    $earlyTo = $scheduledEnd->format('H:i');
                }
            }
        }

        $breakMinutes = $this->breakMinutesForDay($date, $logs, $shift, $config, $plannedMinutes);
        $workedMinutes = max(0, $workedMinutes - $breakMinutes);

        $holidayMinutes = $isWorkingDay ? 0 : $workedMinutes;
        $absenceMinutes = max(0, $plannedMinutes - $workedMinutes - $requestCoverageMinutes);
        $overtimeMinutes = $isWorkingDay ? max(0, $workedMinutes - $plannedMinutes) : $workedMinutes;
        $attendanceDeductionMinutes = min(
            $plannedMinutes,
            max($absenceMinutes, $delayMinutes + $earlyMinutes)
        );
        $netPayableMinutes = max(0, $plannedMinutes - $attendanceDeductionMinutes);
        $payableMinutes = $netPayableMinutes + $overtimeMinutes;

        $absenceFrom = null;
        $absenceTo = null;
        if ($isWorkingDay && $absenceMinutes > 0) {
            if (! $firstLog || ! $lastLog) {
                $absenceFrom = $scheduledStart->format('H:i');
                $absenceTo = $scheduledEnd->format('H:i');
            } elseif ($delayMinutes > 0 || $earlyMinutes > 0) {
                // فاصله‌های تأخیر/تعجیل به‌صورت جدا گزارش می‌شوند؛ کسری مانده به‌عنوان غیبت بدون بازه ساعت.
                $absenceFrom = null;
                $absenceTo = null;
            } else {
                $absenceFrom = $scheduledStart->format('H:i');
                $absenceTo = $scheduledEnd->format('H:i');
            }
        }

        return [
            'date' => $date->toDateString(),
            'is_working_day' => $isWorkingDay,
            'calendar_day' => $date->day,
            'planned_minutes' => $plannedMinutes,
            'worked_minutes' => $workedMinutes,
            'break_minutes' => $breakMinutes,
            'delay_minutes' => $delayMinutes,
            'early_leave_minutes' => $earlyMinutes,
            'leave_minutes' => $leaveMetrics['minutes'],
            'mission_minutes' => $missionMetrics['minutes'],
            'absence_minutes' => $absenceMinutes,
            'attendance_deduction_minutes' => $attendanceDeductionMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'holiday_minutes' => $holidayMinutes,
            'net_payable_minutes' => $netPayableMinutes,
            'payable_minutes' => $payableMinutes,
            'leave_days' => $leaveMetrics['days'],
            'mission_days' => $missionMetrics['days'],
            'work_group_id' => $workGroup?->id,
            'shift_id' => $shift?->id,
            'shift_code' => $shift?->code,
            'shift_start_time' => $config['start_time'],
            'shift_end_time' => $config['end_time'],
            'overtime_multiplier' => $config['overtime_multiplier'],
            'late_tolerance_minutes' => $config['late_tolerance_minutes'],
            'early_leave_tolerance_minutes' => $config['early_leave_tolerance_minutes'],
            'break_window' => [
                'start' => $config['break_start_time'],
                'end' => $config['break_end_time'],
            ],
            'first_log_at' => $firstLog?->toDateTimeString(),
            'last_log_at' => $lastLog?->toDateTimeString(),
            'delay_from' => $delayFrom,
            'delay_to' => $delayTo,
            'early_leave_from' => $earlyFrom,
            'early_leave_to' => $earlyTo,
            'absence_from' => $absenceFrom,
            'absence_to' => $absenceTo,
        ];
    }

    /**
     * @param array<string, mixed> $payrollRules
     * @return array<string, mixed>
     */
    private function resolvedShiftConfig(?WorkShift $shift, array $payrollRules): array
    {
        $startTime = $payrollRules['start_time'] ?? $shift?->start_time ?? '08:00:00';
        $endTime = $payrollRules['end_time'] ?? $shift?->end_time ?? '17:00:00';
        $breakMinutes = (int) ($payrollRules['break_minutes'] ?? $shift?->break_minutes ?? 0);
        $overtimeMultiplier = (float) ($payrollRules['overtime_multiplier'] ?? $shift?->overtime_multiplier ?? 1.4);
        $lateTolerance = (int) ($payrollRules['late_tolerance_minutes'] ?? $shift?->late_tolerance_minutes ?? 0);
        $earlyTolerance = (int) ($payrollRules['early_leave_tolerance_minutes'] ?? $shift?->early_leave_tolerance_minutes ?? 0);

        [$breakStart, $breakEnd] = $this->breakWindow($startTime, $endTime, $breakMinutes, $payrollRules);

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'break_minutes' => $breakMinutes,
            'break_start_time' => $breakStart,
            'break_end_time' => $breakEnd,
            'overtime_multiplier' => $overtimeMultiplier,
            'late_tolerance_minutes' => $lateTolerance,
            'early_leave_tolerance_minutes' => $earlyTolerance,
        ];
    }

    /**
     * @param array<string, mixed> $payrollRules
     * @return array{0: ?string, 1: ?string}
     */
    private function breakWindow(string $startTime, string $endTime, int $breakMinutes, array $payrollRules): array
    {
        $explicitStart = $payrollRules['break_start_time'] ?? null;
        $explicitEnd = $payrollRules['break_end_time'] ?? null;

        if (is_string($explicitStart) && is_string($explicitEnd)) {
            return [$explicitStart, $explicitEnd];
        }

        if ($breakMinutes <= 0) {
            return [null, null];
        }

        $start = Carbon::parse('2000-01-01 ' . $startTime);
        $end = Carbon::parse('2000-01-01 ' . $endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        $shiftSpan = max(1, $start->diffInMinutes($end));
        $offset = max(0, (int) floor(($shiftSpan - $breakMinutes) / 2));
        $breakStart = $start->copy()->addMinutes($offset);
        $breakEnd = $breakStart->copy()->addMinutes($breakMinutes);

        return [$breakStart->format('H:i:s'), $breakEnd->format('H:i:s')];
    }

    private function plannedMinutes(string $startTime, string $endTime): int
    {
        $start = Carbon::parse('2000-01-01 ' . $startTime);
        $end = Carbon::parse('2000-01-01 ' . $endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    private function isWorkingDay(Carbon $date, ?\App\Models\WorkCalendar $calendar): bool
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

    /**
     * @param  Collection<int, WorkLog|AttendanceRawLog>  $logs
     * @return array{worked_minutes:int, first_log:?Carbon, last_log:?Carbon}
     */
    private function logMetrics(Carbon $date, Collection $logs, array $config, int $plannedMinutes): array
    {
        if ($logs->isEmpty()) {
            return ['worked_minutes' => 0, 'first_log' => null, 'last_log' => null];
        }

        if ($logs->first() instanceof AttendanceRawLog && $logs->every(fn (AttendanceRawLog $log): bool => ! $log->check_in_time && ! $log->start_time && ! $log->check_out_time && ! $log->end_time)) {
            $first = $logs->sortBy('logged_at')->first()?->logged_at;
            $last = $logs->sortByDesc('logged_at')->first()?->logged_at;

            if ($first && $last) {
                return [
                    'worked_minutes' => max(0, $last->diffInMinutes($first)),
                    'first_log' => Carbon::parse($first),
                    'last_log' => Carbon::parse($last),
                ];
            }
        }

        $intervals = $logs
            ->map(function (WorkLog|AttendanceRawLog $log) use ($date): ?array {
                $start = $this->logDateTime($date, $log, true);
                $end = $this->logDateTime($date, $log, false);

                if (! $start || ! $end) {
                    return null;
                }

                if ($end->lte($start)) {
                    $end->addDay();
                }

                return [$start, $end];
            })
            ->filter()
            ->values();

        if ($intervals->isEmpty()) {
            $workedMinutes = (int) round($logs->sum(fn (WorkLog|AttendanceRawLog $log) => (float) ($log->hours ?? 0) * 60));

            return ['worked_minutes' => $workedMinutes, 'first_log' => null, 'last_log' => null];
        }

        $firstInterval = $intervals->sortBy(fn (array $interval) => $interval[0])->first();
        $lastInterval = $intervals->sortByDesc(fn (array $interval) => $interval[1])->first();
        $firstLog = $firstInterval[0] ?? null;
        $lastLog = $lastInterval[1] ?? null;

        // Prefer punch/check-in span so net `hours` values do not double-count the shift break later.
        $workedMinutes = (int) $intervals->sum(
            fn (array $interval): int => max(0, $interval[0]->diffInMinutes($interval[1]))
        );

        return [
            'worked_minutes' => $workedMinutes,
            'first_log' => $firstLog,
            'last_log' => $lastLog,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $requests
     * @return array{minutes:int, days:float}
     */
    private function requestMetrics(Carbon $date, Collection $requests, int $plannedMinutes, array $config, bool $isLeave): array
    {
        $minutes = 0;
        $days = 0.0;
        [$shiftStart, $shiftEnd] = $this->shiftWindow($date, $config);

        foreach ($requests as $request) {
            $startRaw = $request->start_date ?: ($request->leave_date ?? $request->mission_date ?? null);
            $endRaw = $request->end_date ?: $startRaw;

            if (! $startRaw || ! $endRaw) {
                continue;
            }

            $start = Carbon::parse($startRaw)->startOfDay();
            $end = Carbon::parse($endRaw)->startOfDay();

            if ($date->lt($start) || $date->gt($end)) {
                continue;
            }

            $requestMinutes = $this->requestDurationMinutes($request, $plannedMinutes, $start, $end);
            $spanDays = max(1, $start->diffInDays($end) + 1);

            if (($request->request_type ?? 'hourly') === 'daily') {
                $minutes += $plannedMinutes;
                $days += $this->requestDayShare($request, $spanDays);
                continue;
            }

            $minutes += min($plannedMinutes, $this->requestCoveredMinutes($date, $request, $shiftStart, $shiftEnd));

            if ((float) ($request->total_days ?? 0) > 0) {
                $days += $this->requestDayShare($request, $spanDays);
            } elseif ($requestMinutes > 0) {
                $days += min(1, round($requestMinutes / max(1, $plannedMinutes), 2));
            }
        }

        return [
            'minutes' => min($minutes, $plannedMinutes),
            'days' => $days,
        ];
    }

    private function requestDurationMinutes(object $request, int $plannedMinutes, Carbon $start, Carbon $end): int
    {
        if ((int) ($request->duration_minutes ?? 0) > 0) {
            return (int) $request->duration_minutes;
        }

        if (! empty($request->start_time) && ! empty($request->end_time)) {
            $requestStart = Carbon::parse($start->toDateString() . ' ' . $request->start_time);
            $requestEnd = Carbon::parse($end->toDateString() . ' ' . $request->end_time);

            if ($requestEnd->lte($requestStart)) {
                $requestEnd->addDay();
            }

            return $requestEnd->diffInMinutes($requestStart);
        }

        if ((float) ($request->hours ?? 0) > 0) {
            return (int) round(((float) $request->hours) * 60);
        }

        return $plannedMinutes;
    }

    private function requestCoveredMinutes(Carbon $date, object $request, Carbon $shiftStart, Carbon $shiftEnd): int
    {
        $window = $this->requestWindowForDate($date, $request, $shiftStart, $shiftEnd);

        if ($window === null) {
            return 0;
        }

        return $this->overlapMinutes($window[0], $window[1], $shiftStart->copy(), $shiftEnd->copy());
    }

    private function requestEdgeCoverageMinutes(
        Carbon $date,
        Collection $requests,
        Carbon $boundaryStart,
        Carbon $boundaryEnd,
        array $config,
    ): int {
        if ($requests->isEmpty()) {
            return 0;
        }

        $coverage = 0;
        $shiftWindow = $this->shiftWindow($date, $config);

        foreach ($requests as $request) {
            $window = $this->requestWindowForDate($date, $request, $shiftWindow[0], $shiftWindow[1]);

            if ($window === null) {
                continue;
            }

            $coverage += $this->overlapMinutes($window[0], $window[1], $boundaryStart->copy(), $boundaryEnd->copy());
        }

        return $coverage;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function shiftWindow(Carbon $date, array $config): array
    {
        $start = $this->dateTime($date, $config['start_time']);
        $end = $this->dateTime($date, $config['end_time'], $this->isOvernightShift($config['start_time'], $config['end_time']));

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function requestWindowForDate(Carbon $date, object $request, Carbon $shiftStart, Carbon $shiftEnd): ?array
    {
        $startRaw = $request->start_date ?: ($request->leave_date ?? $request->mission_date ?? null);
        $endRaw = $request->end_date ?: $startRaw;

        if (! $startRaw || ! $endRaw) {
            return null;
        }

        $startDate = Carbon::parse($startRaw)->startOfDay();
        $endDate = Carbon::parse($endRaw)->startOfDay();

        if ($date->lt($startDate) || $date->gt($endDate)) {
            return null;
        }

        if (($request->request_type ?? 'hourly') === 'daily') {
            return [$shiftStart->copy(), $shiftEnd->copy()];
        }

        if (! empty($request->start_time) && ! empty($request->end_time)) {
            $windowStart = Carbon::parse($date->toDateString() . ' ' . $request->start_time);
            $windowEnd = Carbon::parse($date->toDateString() . ' ' . $request->end_time);

            if ($windowEnd->lte($windowStart)) {
                $windowEnd->addDay();
            }

            return [$windowStart, $windowEnd];
        }

        $durationMinutes = (int) ($request->duration_minutes ?? 0);
        if ($durationMinutes <= 0 && (float) ($request->hours ?? 0) > 0) {
            $durationMinutes = (int) round(((float) $request->hours) * 60);
        }

        if ($durationMinutes <= 0) {
            return [$shiftStart->copy(), $shiftEnd->copy()];
        }

        $windowStart = $shiftStart->copy();
        $windowEnd = $windowStart->copy()->addMinutes($durationMinutes);

        return [$windowStart, $windowEnd];
    }

    private function overlapMinutes(Carbon $startA, Carbon $endA, Carbon $startB, Carbon $endB): int
    {
        $start = $startA->greaterThan($startB) ? $startA : $startB;
        $end = $endA->lessThan($endB) ? $endA : $endB;

        return $end->greaterThan($start) ? $start->diffInMinutes($end) : 0;
    }

    private function requestDayShare(object $request, int $spanDays): float
    {
        $totalDays = (float) ($request->total_days ?? 0);

        if ($totalDays > 0) {
            return round($totalDays / max(1, $spanDays), 2);
        }

        return round(1 / max(1, $spanDays), 2);
    }

    private function breakMinutesForDay(Carbon $date, Collection $logs, ?WorkShift $shift, array $config, int $plannedMinutes): int
    {
        if (! $shift || (int) $config['break_minutes'] <= 0 || $logs->isEmpty() || $plannedMinutes <= 0) {
            return 0;
        }

        $lastLog = $this->lastLogMoment($date, $logs);
        if (! $lastLog || $lastLog->lte(Carbon::parse($date->toDateString() . ' 13:00:00'))) {
            return 0;
        }

        return min((int) $config['break_minutes'], $plannedMinutes);
    }

    private function dateTime(Carbon $date, string $time, bool $nextDay = false): Carbon
    {
        $moment = Carbon::parse($date->toDateString() . ' ' . $time);
        if ($nextDay) {
            $moment->addDay();
        }

        return $moment;
    }

    private function isOvernightShift(string $startTime, string $endTime): bool
    {
        return Carbon::parse('2000-01-01 ' . $endTime)->lte(Carbon::parse('2000-01-01 ' . $startTime));
    }

    private function logDateTime(Carbon $date, WorkLog|AttendanceRawLog $log, bool $start): ?Carbon
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

        return $moment;
    }

    private function lastLogMoment(Carbon $date, Collection $logs): ?Carbon
    {
        $lastLog = $logs->last();

        if (! $lastLog instanceof WorkLog && ! $lastLog instanceof AttendanceRawLog) {
            return null;
        }

        return $this->logDateTime($date, $lastLog, false) ?? $this->logDateTime($date, $lastLog, true);
    }
}
