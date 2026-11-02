<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_items')) {
            return;
        }

        $payload = [
            'title' => 'جمعه‌کاری / استراحت هفتگی',
            'type' => 'earning',
            'calculation_type' => 'hourly',
            'default_amount' => 0,
            'taxable' => true,
            'insurable' => true,
            'is_statutory' => false,
            'sort_order' => 91,
            'source_title' => 'آیتم سیستمی — متمایز از تعطیل رسمی و اضافه‌کاری',
            'updated_at' => now(),
        ];

        $existing = DB::table('payroll_items')->where('code', 'friday_work')->first();
        if ($existing) {
            DB::table('payroll_items')->where('code', 'friday_work')->update($payload);
        } else {
            DB::table('payroll_items')->insert($payload + [
                'code' => 'friday_work',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep historical payroll_item; do not delete.
    }
};
