<?php

namespace App\Services;

use App\Exceptions\AttendancePrerequisiteException;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\PayrollPeriod;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Support\WorkCalendarDefaults;
use Carbon\Carbon;

class RequiredWorkingTimeService
{
    public function __construct(
        private readonly WorkGroupAssignmentResolver $workGroupResolver,
    ) {
    }

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
        $workGroup = $this->workGroupResolver->resolve($employee, $date);

        if (! $workGroup) {
            throw new AttendancePrerequisiteException('گروه کاری فعال برای پرسنل تخصیص داده نشده است.');
        }

        if (! $workGroup->relationLoaded('shift')) {
            $workGroup->load(['shift', 'calendar']);
        }

        if (! $workGroup->shift) {
            throw new AttendancePrerequisiteException('برای گروه کاری پرسنل شیفت تعریف نشده است.');
        }

        if (! $workGroup->calendar) {
            throw new AttendancePrerequisiteException('تقویم کاری شمسی برای گروه کاری پرسنل تعریف نشده است.');
        }

        return $workGroup;
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
        $holidays = collect($calendar->holidays ?: [])
            ->map(fn ($holiday) => normalizePersianDigits((string) $holiday))
            ->filter()
            ->all();

        $gregorian = $date->toDateString();
        $jalali = normalizePersianDigits(formatJalaliDateSafe($date, ''));

        if (in_array($gregorian, $holidays, true) || in_array($jalali, $holidays, true)) {
            return false;
        }

        $dayOfWeek = (int) $date->dayOfWeek;
        $weekends = WorkCalendarDefaults::normalizeDayList(
            $calendar->weekend_days,
            WorkCalendarDefaults::weekendDays()
        );

        if (in_array($dayOfWeek, $weekends, true)) {
            return false;
        }

        $workingDays = WorkCalendarDefaults::normalizeDayList(
            $calendar->working_days,
            WorkCalendarDefaults::workingDays()
        );

        return in_array($dayOfWeek, $workingDays, true);
    }
}
