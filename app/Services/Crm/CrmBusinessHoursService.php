<?php

namespace App\Services\Crm;

use Illuminate\Support\Carbon;

class CrmBusinessHoursService
{
    public const OFFICE_START_HOUR = 8;

    public const OFFICE_START_MINUTE = 30;

    public const OFFICE_END_HOUR = 17;

    public const OFFICE_END_MINUTE = 0;

    public function isWorkingDay(Carbon $date): bool
    {
        return $date->dayOfWeek !== Carbon::FRIDAY;
    }

    public function snapToBusinessHours(Carbon $dateTime): Carbon
    {
        $current = $dateTime->copy();

        while (! $this->isWorkingDay($current)) {
            $current->addDay()->setTime(self::OFFICE_START_HOUR, self::OFFICE_START_MINUTE, 0);
        }

        $dayStart = $current->copy()->setTime(self::OFFICE_START_HOUR, self::OFFICE_START_MINUTE, 0);
        $dayEnd = $current->copy()->setTime(self::OFFICE_END_HOUR, self::OFFICE_END_MINUTE, 0);

        if ($current->lt($dayStart)) {
            return $dayStart;
        }

        if ($current->gte($dayEnd)) {
            return $this->snapToBusinessHours($current->copy()->addDay()->setTime(self::OFFICE_START_HOUR, self::OFFICE_START_MINUTE, 0));
        }

        return $current;
    }

    public function addBusinessHours(Carbon $from, int $hours): Carbon
    {
        $remainingMinutes = max(0, $hours) * 60;
        $current = $this->snapToBusinessHours($from->copy());

        while ($remainingMinutes > 0) {
            $dayEnd = $current->copy()->setTime(self::OFFICE_END_HOUR, self::OFFICE_END_MINUTE, 0);
            $minutesLeftToday = $current->diffInMinutes($dayEnd, false);

            if ($minutesLeftToday <= 0) {
                $current = $this->snapToBusinessHours(
                    $current->copy()->addDay()->setTime(self::OFFICE_START_HOUR, self::OFFICE_START_MINUTE, 0)
                );

                continue;
            }

            if ($remainingMinutes <= $minutesLeftToday) {
                return $current->copy()->addMinutes($remainingMinutes);
            }

            $remainingMinutes -= $minutesLeftToday;
            $current = $this->snapToBusinessHours(
                $current->copy()->addDay()->setTime(self::OFFICE_START_HOUR, self::OFFICE_START_MINUTE, 0)
            );
        }

        return $current;
    }

    public function nextBusinessDayAt(Carbon $from, int $businessDays, int $hour, int $minute = 0): Carbon
    {
        $cursor = $from->copy()->startOfDay();
        $found = 0;

        while ($found < max(1, $businessDays)) {
            if ($this->isWorkingDay($cursor)) {
                $candidate = $cursor->copy()->setTime($hour, $minute, 0);

                if ($candidate->gt($from)) {
                    $found++;

                    if ($found >= max(1, $businessDays)) {
                        return $this->snapToBusinessHours($candidate);
                    }
                }
            }

            $cursor->addDay();
        }

        return $this->snapToBusinessHours($cursor->setTime($hour, $minute, 0));
    }

    public function sameOrNextBusinessDayAt(Carbon $from, int $hour, int $minute = 0): Carbon
    {
        $now = $this->snapToBusinessHours($from->copy());

        if ($this->isWorkingDay($now)) {
            $todayAt = $now->copy()->setTime($hour, $minute, 0);
            $dayEnd = $now->copy()->setTime(self::OFFICE_END_HOUR, self::OFFICE_END_MINUTE, 0);

            if ($todayAt->gt($now) && $todayAt->lte($dayEnd)) {
                return $todayAt;
            }
        }

        return $this->nextBusinessDayAt($from, 1, $hour, $minute);
    }
}
