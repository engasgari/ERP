<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_items')) {
            Schema::create('salary_items', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->string('type'); // earning|deduction|employer
                $table->string('category')->default('allowance'); // wage|allowance|overtime|bonus|insurance|tax|leave|system|other
                $table->string('calculation_type')->default('manual'); // fixed|manual|percentage|formula
                $table->decimal('default_amount', 18, 2)->default(0);
                $table->decimal('default_rate', 10, 4)->default(0);
                $table->boolean('is_insurable')->default(false);
                $table->boolean('is_taxable')->default(false);
                $table->boolean('is_editable')->default(true);
                $table->boolean('is_removable')->default(true);
                $table->boolean('appears_on_decree')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('legacy_order_field')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employment_order_lines')) {
            Schema::create('employment_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employment_order_id')->constrained('employment_orders')->cascadeOnDelete();
                $table->foreignId('salary_item_id')->nullable()->constrained('salary_items')->nullOnDelete();
                $table->string('code')->nullable();
                $table->string('title');
                $table->string('type')->default('earning');
                $table->decimal('amount', 18, 2)->default(0);
                $table->boolean('is_insurable')->default(false);
                $table->boolean('is_taxable')->default(false);
                $table->boolean('is_editable')->default(true);
                $table->boolean('is_removable')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['employment_order_id', 'type']);
            });
        }

        $this->seedSalaryItems();
        $this->backfillOrderLines();
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_order_lines');
        Schema::dropIfExists('salary_items');
    }

    private function seedSalaryItems(): void
    {
        $items = [
            ['code' => 'base_salary', 'title' => 'حقوق پایه / مزد مبنا', 'type' => 'earning', 'category' => 'wage', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => false, 'appears_on_decree' => true, 'sort_order' => 10, 'legacy_order_field' => 'base_salary'],
            ['code' => 'seniority_monthly', 'title' => 'پایه سنوات', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 20, 'legacy_order_field' => 'seniority_pay'],
            ['code' => 'housing_allowance', 'title' => 'کمک‌هزینه مسکن', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 30, 'legacy_order_field' => 'housing_allowance', 'default_amount' => 30000000],
            ['code' => 'food_allowance', 'title' => 'بن خواربار / اقلام مصرفی', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 40, 'legacy_order_field' => 'food_allowance', 'default_amount' => 22000000],
            ['code' => 'child_allowance', 'title' => 'کمک‌هزینه اولاد', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 50, 'legacy_order_field' => 'child_allowance'],
            ['code' => 'marriage_allowance', 'title' => 'حق تاهل / عائله‌مندی', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 60, 'legacy_order_field' => 'marriage_allowance'],
            ['code' => 'job_allowance', 'title' => 'فوق‌العاده شغل', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 70, 'legacy_order_field' => 'job_allowance'],
            ['code' => 'hardship_allowance', 'title' => 'فوق‌العاده سختی کار', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 80, 'legacy_order_field' => 'hardship_allowance'],
            ['code' => 'shift_allowance', 'title' => 'فوق‌العاده نوبت‌کاری', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 90, 'legacy_order_field' => 'shift_allowance'],
            ['code' => 'transportation_allowance', 'title' => 'ایاب و ذهاب', 'type' => 'earning', 'category' => 'allowance', 'is_insurable' => false, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 100, 'legacy_order_field' => 'transportation_allowance'],
            ['code' => 'other_insurable_benefits', 'title' => 'سایر مزایای مشمول بیمه', 'type' => 'earning', 'category' => 'other', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 110, 'legacy_order_field' => 'other_insurable_benefits'],
            ['code' => 'other_non_insurable_benefits', 'title' => 'سایر مزایای غیرمشمول بیمه', 'type' => 'earning', 'category' => 'other', 'is_insurable' => false, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 120, 'legacy_order_field' => 'other_non_insurable_benefits'],
            ['code' => 'overtime', 'title' => 'اضافه‌کاری', 'type' => 'earning', 'category' => 'overtime', 'is_insurable' => true, 'is_taxable' => true, 'is_editable' => false, 'is_removable' => false, 'appears_on_decree' => false, 'sort_order' => 200],
            ['code' => 'eid_bonus', 'title' => 'عیدی', 'type' => 'earning', 'category' => 'bonus', 'is_insurable' => false, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => false, 'sort_order' => 210],
            ['code' => 'severance', 'title' => 'سنوات پایان خدمت', 'type' => 'earning', 'category' => 'bonus', 'is_insurable' => false, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => false, 'sort_order' => 220],
            ['code' => 'leave_buyout', 'title' => 'بازخرید مرخصی', 'type' => 'earning', 'category' => 'leave', 'is_insurable' => false, 'is_taxable' => true, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => false, 'sort_order' => 230],
            ['code' => 'loan', 'title' => 'قسط وام', 'type' => 'deduction', 'category' => 'other', 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 300],
            ['code' => 'advance', 'title' => 'مساعده', 'type' => 'deduction', 'category' => 'other', 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 310],
            ['code' => 'penalty', 'title' => 'کسورات انضباطی', 'type' => 'deduction', 'category' => 'other', 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => true, 'is_removable' => true, 'appears_on_decree' => true, 'sort_order' => 320],
            ['code' => 'employee_insurance', 'title' => 'بیمه سهم کارمند', 'type' => 'deduction', 'category' => 'insurance', 'calculation_type' => 'percentage', 'default_rate' => 7, 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => false, 'is_removable' => false, 'appears_on_decree' => false, 'sort_order' => 400],
            ['code' => 'salary_tax', 'title' => 'مالیات حقوق', 'type' => 'deduction', 'category' => 'tax', 'calculation_type' => 'percentage', 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => false, 'is_removable' => false, 'appears_on_decree' => false, 'sort_order' => 410],
            ['code' => 'employer_insurance', 'title' => 'بیمه سهم کارفرما', 'type' => 'employer', 'category' => 'insurance', 'calculation_type' => 'percentage', 'default_rate' => 20, 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => false, 'is_removable' => false, 'appears_on_decree' => false, 'sort_order' => 420],
            ['code' => 'unemployment_insurance', 'title' => 'بیمه بیکاری سهم کارفرما', 'type' => 'employer', 'category' => 'insurance', 'calculation_type' => 'percentage', 'default_rate' => 3, 'is_insurable' => false, 'is_taxable' => false, 'is_editable' => false, 'is_removable' => false, 'appears_on_decree' => false, 'sort_order' => 430],
        ];

        foreach ($items as $item) {
            DB::table('salary_items')->updateOrInsert(
                ['code' => $item['code']],
                array_merge([
                    'calculation_type' => 'manual',
                    'default_amount' => 0,
                    'default_rate' => 0,
                    'is_active' => true,
                    'meta' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $item)
            );
        }
    }

    private function backfillOrderLines(): void
    {
        if (! Schema::hasTable('employment_orders') || ! Schema::hasTable('employment_order_lines')) {
            return;
        }

        $items = DB::table('salary_items')
            ->whereNotNull('legacy_order_field')
            ->get()
            ->keyBy('legacy_order_field');

        DB::table('employment_orders')->orderBy('id')->chunkById(100, function ($orders) use ($items): void {
            foreach ($orders as $order) {
                $exists = DB::table('employment_order_lines')
                    ->where('employment_order_id', $order->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $sort = 0;
                foreach ($items as $field => $item) {
                    $amount = (float) ($order->{$field} ?? 0);
                    if ($amount <= 0 && $item->code !== 'base_salary') {
                        continue;
                    }

                    DB::table('employment_order_lines')->insert([
                        'employment_order_id' => $order->id,
                        'salary_item_id' => $item->id,
                        'code' => $item->code,
                        'title' => $item->title,
                        'type' => $item->type,
                        'amount' => $amount,
                        'is_insurable' => (bool) $item->is_insurable,
                        'is_taxable' => (bool) $item->is_taxable,
                        'is_editable' => (bool) $item->is_editable,
                        'is_removable' => (bool) $item->is_removable,
                        'sort_order' => ++$sort,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
};
