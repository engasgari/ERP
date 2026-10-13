<?php

namespace App\Services;

use App\Models\AttendanceLeave;
use App\Models\AttendanceMission;
use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\WorkLog;
use App\Support\WorkCalendarDefaults;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceDailySummaryService
{
    public function __construct(
        private readonly AttendanceDayCalculatorService $dayCalculator,
        private readonly WorkGroupAssignmentResolver $workGroupResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function daysFor(Employee $employee, int $jalaliYear, int $jalaliMonth): array
    {
        $range = jalaliMonthRangeGregorianSafe($jalaliYear, $jalaliMonth);
        /** @var Carbon $start */
        $start = $range['start']->copy()->startOfDay();
        /** @var Carbon $end */
        $end = $range['end']->copy()->startOfDay();

        $employee->loadMissing(['workGroupAssignments.workGroup.shift', 'workGroupAssignments.workGroup.calendar']);

        $workLogs = WorkLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WorkLog $log) => $log->work_date?->toDateString() ?? (string) $log->work_date);

        $rawLogs = AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$start, $end->copy()->endOfDay()])
            ->orderBy('logged_at')
            ->get()
            ->groupBy(fn (AttendanceRawLog $log) => $log->logged_at->toDateString());

        $leaves = AttendanceLeave::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($start, $end): void {
                $query->where(function ($dated) use ($start, $end): void {
                    $dated->whereDate('start_date', '<=', $end->toDateString())
                        ->whereDate('end_date', '>=', $start->toDateString());
                })->orWhereBetween('leave_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        $missions = AttendanceMission::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($start, $end): void {
                $query->where(function ($dated) use ($start, $end): void {
                    $dated->whereDate('start_date', '<=', $end->toDateString())
                        ->whereDate('end_date', '>=', $start->toDateString());
                })->orWhereBetween('mission_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        $rows = [];
        $date = $start->copy();

        while ($date->lte($end)) {
            $dateKey = $date->toDateString();
            $dayWorkLogs = $workLogs->get($dateKey, collect());
            $dayRawLogs = $rawLogs->get($dateKey, collect());
            $dayLogs = $dayWorkLogs->isNotEmpty() ? $dayWorkLogs : $dayRawLogs;

            $workGroup = $this->workGroupResolver->resolve($employee, $date);
            $metrics = $this->dayCalculator->calculate(
                $date,
                $workGroup,
                $dayLogs,
                $leaves,
                $missions,
            );

            $incomplete = $this->isIncomplete($dayLogs);
            $isWorking = (bool) ($metrics['is_working_day'] ?? false);
            $workedMinutes = (int) ($metrics['worked_minutes'] ?? 0);
            $leaveMinutes = (int) ($metrics['leave_minutes'] ?? 0);
            $missionMinutes = (int) ($metrics['mission_minutes'] ?? 0);
            $absenceMinutes = (int) ($metrics['absence_minutes'] ?? 0);

            $calendar = $workGroup?->calendar;
            $weekends = WorkCalendarDefaults::normalizeDayList(
                $calendar?->weekend_days,
                WorkCalendarDefaults::weekendDays()
            );
            $isWeekend = in_array((int) $date->dayOfWeek, $weekends, true);
            $isOfficialHoliday = $this->isOfficialHoliday($date, $calendar);
            $isOffDay = ! $isWorking;
            $isAbsence = $isWorking
                && ! $incomplete
                && $workedMinutes <= 0
                && $leaveMinutes <= 0
                && $missionMinutes <= 0;

            $dayType = $this->dayTypeLabel(
                $isWorking,
                $isWeekend,
                $isOfficialHoliday,
                $workedMinutes,
                $leaveMinutes,
                $missionMinutes,
                $incomplete,
            );

            $rows[] = [
                'work_date' => $dateKey,
                'work_date_jalali' => formatJalaliDateSafe($dateKey),
                'day_name' => WorkCalendarDefaults::dayLabel((int) $date->dayOfWeek),
                'day_type' => $dayType,
                'is_working_day' => $isWorking,
                'is_weekend' => $isWeekend,
                'is_official_holiday' => $isOfficialHoliday,
                'is_off_day' => $isOffDay,
                'is_holiday' => $isOffDay,
                'is_absence' => $isAbsence,
                'is_incomplete' => $incomplete,
                'shift_start' => $this->formatTime($metrics['shift_start_time'] ?? null),
                'shift_end' => $this->formatTime($metrics['shift_end_time'] ?? null),
                'first_in' => $this->formatDateTimeTime($metrics['first_log_at'] ?? null),
                'last_out' => $this->formatDateTimeTime($metrics['last_log_at'] ?? null),
                'worked_minutes' => $workedMinutes,
                'delay_minutes' => (int) ($metrics['delay_minutes'] ?? 0),
                'early_leave_minutes' => (int) ($metrics['early_leave_minutes'] ?? 0),
                'absence_minutes' => $absenceMinutes,
                'leave_minutes' => $leaveMinutes,
                'mission_minutes' => $missionMinutes,
                'work_log_id' => $dayWorkLogs->first()?->id,
            ];

            $date->addDay();
        }

        return $rows;
    }

    /**
     * @param  Collection<int, WorkLog|AttendanceRawLog>  $logs
     */
    private function isIncomplete(Collection $logs): bool
    {
        if ($logs->isEmpty()) {
            return false;
        }

        foreach ($logs as $log) {
            if ($log instanceof WorkLog && $log->is_incomplete) {
                return true;
            }

            $in = $log->check_in_time ?? $log->start_time ?? null;
            $out = $log->check_out_time ?? $log->end_time ?? null;
            if ($in && ! $out) {
                return true;
            }
            if ($out && ! $in) {
                return true;
            }
        }

        return false;
    }

    private function isOfficialHoliday(Carbon $date, mixed $calendar): bool
    {
        $holidays = collect($calendar?->holidays ?: [])
            ->map(fn ($holiday) => normalizePersianDigits((string) $holiday))
            ->filter()
            ->all();

        if ($holidays === []) {
            return false;
        }

        $gregorian = $date->toDateString();
        $jalali = normalizePersianDigits(formatJalaliDateSafe($date, ''));

        return in_array($gregorian, $holidays, true) || in_array($jalali, $holidays, true);
    }

    private function dayTypeLabel(
        bool $isWorking,
        bool $isWeekend,
        bool $isOfficialHoliday,
        int $worked,
        int $leave,
        int $mission,
        bool $incomplete,
    ): string {
        if ($isOfficialHoliday && $isWeekend) {
            return 'تعطیل رسمی / هفتگی';
        }

        if ($isOfficialHoliday) {
            return 'تعطیل رسمی';
        }

        if ($isWeekend || ! $isWorking) {
            return 'تعطیل هفتگی';
        }

        if ($incomplete) {
            return 'تردد ناقص';
        }

        if ($worked <= 0 && $leave <= 0 && $mission <= 0) {
            return 'غیبت';
        }

        if ($leave > 0 && $worked <= 0) {
            return 'مرخصی';
        }

        if ($mission > 0 && $worked <= 0) {
            return 'ماموریت';
        }

        return 'کارکرد';
    }

    private function formatTime(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        $raw = (string) $value;
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $raw;
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
}
