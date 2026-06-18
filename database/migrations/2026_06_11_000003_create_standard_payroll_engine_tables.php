<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('title');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status')->default('draft');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('type');
            $table->string('calculation_type')->default('fixed');
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->decimal('default_rate', 8, 4)->default(0);
            $table->boolean('taxable')->default(false);
            $table->boolean('insurable')->default(false);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('source_title')->nullable();
            $table->string('source_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('normal_hours', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);
            $table->decimal('delay_hours', 10, 2)->default(0);
            $table->decimal('early_leave_hours', 10, 2)->default(0);
            $table->decimal('absence_hours', 10, 2)->default(0);
            $table->decimal('leave_hours', 10, 2)->default(0);
            $table->decimal('holiday_hours', 10, 2)->default(0);
            $table->decimal('night_hours', 10, 2)->default(0);
            $table->decimal('payable_hours', 10, 2)->default(0);
            $table->decimal('hourly_rate', 15, 2)->default(0);
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->string('status')->default('calculated');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id', 'project_id'], 'attendance_calc_period_employee_project_unique');
        });

        Schema::create('salary_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('title');
            $table->string('type');
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('salaries', 'payroll_period_id')) {
                $table->foreignId('payroll_period_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('salaries', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('locked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            if (Schema::hasColumn('salaries', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('salaries', 'payroll_period_id')) {
                $table->dropConstrainedForeignId('payroll_period_id');
            }
        });

        Schema::dropIfExists('salary_lines');
        Schema::dropIfExists('attendance_calculations');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
    }
};
