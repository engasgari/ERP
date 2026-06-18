<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_group_employee') || ! Schema::hasTable('work_groups') || ! Schema::hasTable('employees')) {
            return;
        }

        $activeGroups = DB::table('work_groups')
            ->where('is_active', true)
            ->whereNotNull('work_shift_id')
            ->whereNotNull('work_calendar_id')
            ->pluck('id');

        if ($activeGroups->count() !== 1) {
            return;
        }

        $groupId = (int) $activeGroups->first();
        $startDate = function_exists('jalaliToGregorianDateSafe')
            ? jalaliToGregorianDateSafe(1404, 1, 1)
            : '2025-03-21';

        DB::table('employees')
            ->where(function ($query): void {
                $query->where('is_active', true)->orWhere('status', 'active');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('work_group_employee')
                    ->whereColumn('work_group_employee.employee_id', 'employees.id');
            })
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($employeeId) use ($groupId, $startDate): void {
                DB::table('work_group_employee')->insert([
                    'employee_id' => $employeeId,
                    'work_group_id' => $groupId,
                    'start_date' => $startDate,
                    'end_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Data-preserving migration: do not remove generated assignments automatically.
    }
};
