<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align holiday/Friday work coefficients with company payroll rule:
 * overtime 1.4, holiday/Friday work line 2.4.
 * Does not rewrite posted payslips — only future parameter resolution.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_legal_parameter_sets')) {
            return;
        }

        $rows = DB::table('payroll_legal_parameter_sets')->get(['id', 'parameters']);
        foreach ($rows as $row) {
            $parameters = json_decode($row->parameters ?? '{}', true);
            if (! is_array($parameters)) {
                continue;
            }

            $parameters['overtime_multiplier'] = (float) ($parameters['overtime_multiplier'] ?? 1.4);
            $parameters['holiday_work_multiplier'] = 2.4;
            $parameters['friday_work_multiplier'] = 2.4;
            $parameters['notes'] = array_merge(
                is_array($parameters['notes'] ?? null) ? $parameters['notes'] : [],
                [
                    'holiday_friday_coefficient' => 'ضریب خط تعطیل/جمعه‌کاری ۲.۴ (۱ + ۱.۴)؛ اضافه‌کاری روز عادی ۱.۴ — سیاست کارگاه / رویه رایج نرم‌افزار حقوق ایران',
                ]
            );

            DB::table('payroll_legal_parameter_sets')->where('id', $row->id)->update([
                'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_legal_parameter_sets')) {
            return;
        }

        $rows = DB::table('payroll_legal_parameter_sets')->get(['id', 'parameters']);
        foreach ($rows as $row) {
            $parameters = json_decode($row->parameters ?? '{}', true);
            if (! is_array($parameters)) {
                continue;
            }
            $parameters['holiday_work_multiplier'] = 1.4;
            $parameters['friday_work_multiplier'] = 1.4;
            DB::table('payroll_legal_parameter_sets')->where('id', $row->id)->update([
                'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }
};
