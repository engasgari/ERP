<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkGroup;
use App\Models\WorkGroupEmployee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the work group for an employee on a given date.
 *
 * Priority:
 * 1) Assignment whose start/end window covers the date
 * 2) Carry-forward: latest assignment that started on/before the date
 *    (keeps shift+calendar after an ended assignment until a new one starts)
 */
class WorkGroupAssignmentResolver
{
    public function resolve(Employee $employee, Carbon $date): ?WorkGroup
    {
        $assignments = $this->assignments($employee);

        $covering = $this->pick(
            $assignments->filter(function (WorkGroupEmployee $assignment) use ($date): bool {
                return (! $assignment->start_date || $assignment->start_date->lte($date))
                    && (! $assignment->end_date || $assignment->end_date->gte($date));
            })
        );

        if ($covering?->workGroup) {
            return $covering->workGroup;
        }

        $carryForward = $this->pick(
            $assignments->filter(function (WorkGroupEmployee $assignment) use ($date): bool {
                return ! $assignment->start_date || $assignment->start_date->lte($date);
            })
        );

        return $carryForward?->workGroup;
    }

    /**
     * @return Collection<int, WorkGroupEmployee>
     */
    private function assignments(Employee $employee): Collection
    {
        if ($employee->relationLoaded('workGroupAssignments')) {
            return $employee->workGroupAssignments;
        }

        return $employee->workGroupAssignments()
            ->with(['workGroup.shift', 'workGroup.calendar'])
            ->get();
    }

    /**
     * @param  Collection<int, WorkGroupEmployee>  $assignments
     */
    private function pick(Collection $assignments): ?WorkGroupEmployee
    {
        return $assignments
            ->sortByDesc(fn (WorkGroupEmployee $assignment) => $assignment->start_date?->timestamp ?? 0)
            ->sortByDesc('id')
            ->first();
    }
}
