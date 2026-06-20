<?php

namespace App\Services;

use App\Exceptions\AttendancePrerequisiteException;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\PayrollPeriod;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkShift;
use Carbon\Carbon;

class RequiredWorkingTimeService
{
    public function activeDecree(Employee $employee, PayrollPeriod $period): EmploymentOrder
    {
        $decree = $employee->employmentOrders()
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', $period->ends_at)
            ->where(function ($query) use ($period): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->starts_at);
            })
            ->latest('effective_date')
            ->first();

        if (! $decree) {
            throw new AttendancePrerequisiteException('حکم کارگزینی فعال برای این دوره پیدا نشد.');
        }

        return $decree;
    }

    public function activeWorkGroup(Employee $employee, Carbon $date): WorkGroup
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

        if (! $assignment?->workGroup) {
            $legacyGroup = $employee->workGroups()
                ->with(['shift', 'calendar'])
                ->whereNotNull('work_shift_id')
                ->whereNotNull('work_calendar_id')
                ->orderByDesc('work_group_employee.id')
                ->first();

            if ($legacyGroup) {
                return $legacyGroup;
            }

            throw new AttendancePrerequisiteException('گروه کاری فعال برای پرسنل تخصیص داده نشده است.');
        }

        if (! $assignment->workGroup->shift) {
            throw new AttendancePrerequisiteException('برای گروه کاری پرسنل شیفت تعریف نشده است.');
        }

        if (! $assignment->workGroup->calendar) {
            throw new AttendancePrerequisiteException('تقویم کاری شمسی برای گروه کاری پرسنل تعریف نشده است.');
        }

        return $assignment->workGroup;
    }

    public function month(Employee $employee, PayrollPeriod $period, bool $isHourly): array
    {
        $requiredDays = 0;
        $requiredMinutes = 0;
        $monthlyCapMinutes = $isHourly ? PHP_INT_MAX : 192 * 60;
        $daily = [];
        $date = $period->starts_at->copy();

        while ($date->lte($period->ends_at)) {
            $workGroup = $this->activeWorkGroup($employee, $date);
            $shift = $workGroup->shift;
            $calendar = $workGroup->calendar;

            if ((int) $calendar->jalali_year !== (int) $period->year) {
                throw new AttendancePrerequisiteException('تقویم کاری شمسی برای سال انتخابی تعریف نشده است.');
            }

            $dailyMinutes = (int) round(((float) ($shift?->daily_work_hours ?: 8)) * 60);
            $isWorkingDay = $this->isWorkingDay($date, $calendar);
            $remainingMinutes = max(0, $monthlyCapMinutes - $requiredMinutes);
            $plannedMinutes = (! $isHourly && $isWorkingDay) ? min($dailyMinutes, $remainingMinutes) : 0;

            if ($plannedMinutes > 0) {
                $requiredDays++;
                $requiredMinutes += $plannedMinutes;
            }

            $daily[$date->toDateString()] = [
                'work_group' => $workGroup,
                'shift' => $shift,
                'calendar' => $calendar,
                'is_working_day' => $isWorkingDay,
                'daily_minutes' => $dailyMinutes,
                'planned_minutes' => $plannedMinutes,
            ];

            $date->addDay();
        }

        return [
            'required_days' => $requiredDays,
            'required_hours' => round($requiredMinutes / 60, 2),
            'required_minutes' => $requiredMinutes,
            'daily' => $daily,
        ];
    }

    public function isWorkingDay(Carbon $date, WorkCalendar $calendar): bool
    {
        $jalaliDate = formatJalaliDateSafe($date, '');
        $holidays = $calendar->holidays ?: [];

        if (in_array($date->toDateString(), $holidays, true) || in_array($jalaliDate, $holidays, true)) {
            return false;
        }

        $dayOfWeek = (int) $date->dayOfWeek;
        $weekends = $calendar->weekend_days ?: [];

        if (in_array($dayOfWeek, $weekends, true)) {
            return false;
        }

        $workingDays = $calendar->working_days ?: [];

        return $workingDays === [] || in_array($dayOfWeek, $workingDays, true);
    }
}
