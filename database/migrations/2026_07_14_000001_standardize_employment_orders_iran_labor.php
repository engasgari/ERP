<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employment_orders')) {
            return;
        }

        Schema::table('employment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('employment_orders', 'issue_date')) {
                $table->date('issue_date')->nullable()->after('number');
            }
            if (! Schema::hasColumn('employment_orders', 'decree_reason')) {
                $table->string('decree_reason')->nullable()->after('order_type');
            }
            if (! Schema::hasColumn('employment_orders', 'workshop_code')) {
                $table->string('workshop_code', 20)->nullable()->after('cost_center_code');
            }
            if (! Schema::hasColumn('employment_orders', 'job_group')) {
                $table->unsignedTinyInteger('job_group')->nullable()->after('job_id');
            }
            if (! Schema::hasColumn('employment_orders', 'job_rank')) {
                $table->unsignedTinyInteger('job_rank')->nullable()->after('job_group');
            }
            if (! Schema::hasColumn('employment_orders', 'job_base')) {
                $table->unsignedTinyInteger('job_base')->nullable()->after('job_rank');
            }
            if (! Schema::hasColumn('employment_orders', 'daily_wage')) {
                $table->decimal('daily_wage', 18, 2)->default(0)->after('base_salary');
            }
            if (! Schema::hasColumn('employment_orders', 'monthly_work_hours')) {
                $table->decimal('monthly_work_hours', 8, 2)->default(220)->after('hourly_rate');
            }
            if (! Schema::hasColumn('employment_orders', 'daily_work_hours')) {
                $table->decimal('daily_work_hours', 8, 2)->default(7.33)->after('monthly_work_hours');
            }
            if (! Schema::hasColumn('employment_orders', 'job_allowance')) {
                $table->decimal('job_allowance', 18, 2)->default(0)->after('seniority_pay');
            }
            if (! Schema::hasColumn('employment_orders', 'hardship_allowance')) {
                $table->decimal('hardship_allowance', 18, 2)->default(0)->after('job_allowance');
            }
            if (! Schema::hasColumn('employment_orders', 'shift_allowance')) {
                $table->decimal('shift_allowance', 18, 2)->default(0)->after('hardship_allowance');
            }
            if (! Schema::hasColumn('employment_orders', 'other_insurable_benefits')) {
                $table->decimal('other_insurable_benefits', 18, 2)->default(0)->after('transportation_allowance');
            }
            if (! Schema::hasColumn('employment_orders', 'other_non_insurable_benefits')) {
                $table->decimal('other_non_insurable_benefits', 18, 2)->default(0)->after('other_insurable_benefits');
            }
            if (! Schema::hasColumn('employment_orders', 'marital_status')) {
                $table->string('marital_status', 20)->nullable()->after('insurance_status');
            }
        });

        // Backfill issue_date from effective_date for existing rows.
        if (Schema::hasColumn('employment_orders', 'issue_date')) {
            \DB::table('employment_orders')
                ->whereNull('issue_date')
                ->update(['issue_date' => \DB::raw('effective_date')]);
        }

        // Derive daily wage when missing.
        if (Schema::hasColumn('employment_orders', 'daily_wage')) {
            \DB::table('employment_orders')
                ->where('daily_wage', 0)
                ->where('base_salary', '>', 0)
                ->update(['daily_wage' => \DB::raw('ROUND(base_salary / 30, 2)')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employment_orders')) {
            return;
        }

        Schema::table('employment_orders', function (Blueprint $table) {
            foreach ([
                'issue_date',
                'decree_reason',
                'workshop_code',
                'job_group',
                'job_rank',
                'job_base',
                'daily_wage',
                'monthly_work_hours',
                'daily_work_hours',
                'job_allowance',
                'hardship_allowance',
                'shift_allowance',
                'other_insurable_benefits',
                'other_non_insurable_benefits',
                'marital_status',
            ] as $column) {
                if (Schema::hasColumn('employment_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
