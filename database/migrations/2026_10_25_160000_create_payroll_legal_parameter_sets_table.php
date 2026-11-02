<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_legal_parameter_sets')) {
            Schema::create('payroll_legal_parameter_sets', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('jalali_year');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('title');
                $table->string('source_ref', 500)->nullable();
                $table->string('status', 32)->default('active'); // active|draft|superseded
                $table->json('parameters');
                $table->timestamps();

                $table->unique(['jalali_year', 'effective_from']);
                $table->index(['jalali_year', 'status']);
            });
        }

        if (Schema::hasTable('payroll_legal_parameter_sets')
            && ! DB::table('payroll_legal_parameter_sets')->where('jalali_year', 1404)->exists()) {
            DB::table('payroll_legal_parameter_sets')->insert([
                'jalali_year' => 1404,
                'effective_from' => '2025-03-21', // 1404/01/01
                'effective_to' => null,
                'title' => 'پارامترهای حقوقی سال ۱۴۰۴',
                'source_ref' => 'قانون بودجه ۱۴۰۴ بند ز تبصره ۱ (مالیات حقوق)؛ بخشنامه ۱۰۰۰/۱۴۰۴/۱۹۳ تأمین اجتماعی؛ مواد ۵۱/۵۳/۵۹ قانون کار',
                'status' => 'active',
                'parameters' => json_encode([
                    'currency' => 'IRR',
                    'min_daily_wage' => 3463656,
                    'insurance_ceiling_daily_multiplier' => 7,
                    'employee_insurance_rate' => 7,
                    'employer_insurance_rate' => 20,
                    'unemployment_insurance_rate' => 3,
                    // Labor Law Art.59: overtime pay = regular + 40%
                    'overtime_multiplier' => 1.4,
                    // Labor Law Art.53: night work premium 35% of regular wage for night hours
                    'night_premium_rate' => 0.35,
                    // Work on weekly rest / official holiday: overtime premium (Art.59 / Art.62 practice)
                    // Line coefficient on top of monthly salary: 2.4 (=1.0 + 1.4); OT stays 1.4
                    'holiday_work_multiplier' => 2.4,
                    'friday_work_multiplier' => 2.4,
                    'food_allowance_monthly' => 22000000,
                    'housing_allowance_monthly' => 9000000,
                    'marriage_allowance_monthly' => 5000000,
                    'seniority_daily' => 94000,
                    'monthly_hours_basis' => 220,
                    'tax_brackets_monthly' => [
                        ['up_to' => 240000000, 'rate' => 0],
                        ['up_to' => 300000000, 'rate' => 10],
                        ['up_to' => 380000000, 'rate' => 15],
                        ['up_to' => 500000000, 'rate' => 20],
                        ['up_to' => 666666667, 'rate' => 25],
                        ['up_to' => null, 'rate' => 30],
                    ],
                    'notes' => [
                        'tax' => 'جدول ماهانه از تقسیم حدود سالانه بند ز تبصره ۱ بودجه ۱۴۰۴',
                        'insurance_ceiling' => '۷ برابر حداقل دستمزد روزانه × تعداد روز ماه',
                        'year_1405' => 'برای ۱۴۰۵ تا ابلاغ بودجه/بخشنامه جدید ردیف جداگانه ثبت شود؛ از کپی خودکار خودداری شود',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Align default catalog allowances with 1404 circular when empty/outdated seeds exist.
        if (Schema::hasTable('payroll_items')) {
            DB::table('payroll_items')->where('code', 'food_allowance')->update([
                'default_amount' => 22000000,
                'updated_at' => now(),
            ]);
            DB::table('payroll_items')->where('code', 'housing_allowance')->update([
                'default_amount' => 9000000,
                'updated_at' => now(),
            ]);
            DB::table('payroll_items')->where('code', 'child_allowance')->update([
                'insurable' => true,
                'taxable' => true,
                'updated_at' => now(),
            ]);
            DB::table('payroll_items')->where('code', 'mission')->update([
                'insurable' => false,
                'taxable' => true,
                'updated_at' => now(),
            ]);

            if (! DB::table('payroll_items')->where('code', 'friday_work')->exists()) {
                DB::table('payroll_items')->insert([
                    'code' => 'friday_work',
                    'title' => 'جمعه‌کاری / استراحت هفتگی',
                    'type' => 'earning',
                    'calculation_type' => 'hourly',
                    'default_amount' => 0,
                    'taxable' => true,
                    'insurable' => true,
                    'is_statutory' => false,
                    'sort_order' => 91,
                    'source_title' => 'آیتم سیستمی — متمایز از تعطیل رسمی و اضافه‌کاری',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('payroll_items')->where('code', 'friday_work')->update([
                    'insurable' => true,
                    'taxable' => true,
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('monthly_attendances') && ! Schema::hasColumn('monthly_attendances', 'friday_hours')) {
            Schema::table('monthly_attendances', function (Blueprint $table) {
                $table->decimal('friday_hours', 10, 2)->default(0)->after('holiday_hours');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_attendances') && Schema::hasColumn('monthly_attendances', 'friday_hours')) {
            Schema::table('monthly_attendances', function (Blueprint $table) {
                $table->dropColumn('friday_hours');
            });
        }

        Schema::dropIfExists('payroll_legal_parameter_sets');
    }
};
