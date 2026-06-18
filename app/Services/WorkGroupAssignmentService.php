<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkGroup;
use App\Models\WorkGroupEmployee;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkGroupAssignmentService
{
    public function assign(Employee $employee, WorkGroup $workGroup, ?string $startDate = null, ?string $endDate = null): WorkGroupEmployee
    {
        return DB::transaction(function () use ($employee, $workGroup, $startDate, $endDate): WorkGroupEmployee {
            $overlap = WorkGroupEmployee::query()
                ->where('employee_id', $employee->id)
                ->where(function ($query) use ($startDate): void {
                    $query->whereNull('end_date');
                    if ($startDate) {
                        $query->orWhereDate('end_date', '>=', $startDate);
                    }
                })
                ->where(function ($query) use ($endDate): void {
                    if ($endDate) {
                        $query->whereNull('start_date')->orWhereDate('start_date', '<=', $endDate);
                    }
                })
                ->exists();

            if ($overlap) {
                throw new InvalidArgumentException('برای این پرسنل در بازه انتخابی گروه کاری فعال دیگری وجود دارد.');
            }

            return WorkGroupEmployee::create([
                'employee_id' => $employee->id,
                'work_group_id' => $workGroup->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        });
    }
}
