<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align payroll_items catalog defaults with documented ۱۴۰۴ legal parameter set.
 * Does not invent ۱۴۰۵ rates. Does not rewrite posted payslips/calculations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_items')) {
            return;
        }

        $updates = [
            'minimum_daily_wage' => [
                'title' => 'حداقل مزد روزانه (مرجع کاتالوگ ۱۴۰۴)',
                'default_amount' => 3463656,
                'source_title' => 'مرجع کاتالوگ ۱۴۰۴ — منبع اجرایی: payroll_legal_parameter_sets',
                'source_url' => null,
            ],
            'minimum_monthly_wage' => [
                'title' => 'حداقل حقوق ماهانه (مرجع کاتالوگ ۱۴۰۴)',
                'default_amount' => 103909680,
                'source_title' => 'مرجع کاتالوگ ۱۴۰۴ — منبع اجرایی: payroll_legal_parameter_sets',
                'source_url' => null,
            ],
            'housing_allowance' => [
                'default_amount' => 9000000,
                'taxable' => true,
                'insurable' => true,
                'source_title' => 'مرجع کاتالوگ ۱۴۰۴ — منبع اجرایی: payroll_legal_parameter_sets',
                'source_url' => null,
            ],
            'food_allowance' => [
                'default_amount' => 22000000,
                'taxable' => true,
                'insurable' => true,
                'source_title' => 'مرجع کاتالوگ ۱۴۰۴ — منبع اجرایی: payroll_legal_parameter_sets',
                'source_url' => null,
            ],
            'marriage_allowance' => [
                'default_amount' => 5000000,
                'taxable' => true,
                'insurable' => true,
            ],
            'child_allowance' => [
                'default_amount' => 10390968,
                'taxable' => true,
                'insurable' => true,
                'source_title' => 'مرجع کاتالوگ ۱۴۰۴ — ماده ۸۶ قانون کار / حداقل مزد روزانه',
                'source_url' => null,
            ],
            'seniority_monthly' => [
                'default_amount' => 2820000,
                'taxable' => true,
                'insurable' => true,
            ],
        ];

        foreach ($updates as $code => $payload) {
            if (! DB::table('payroll_items')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('payroll_items')->where('code', $code)->update($payload + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Non-destructive: keep aligned catalog values.
    }
};
