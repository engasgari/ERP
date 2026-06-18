<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attendance_card_number')->nullable();
            $table->dateTime('logged_at');
            $table->string('direction')->default('in');
            $table->string('device_code')->nullable();
            $table->string('source')->default('seed');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'logged_at']);
        });

        Schema::create('attendance_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('leave_date');
            $table->decimal('hours', 8, 2)->default(8);
            $table->string('type')->default('استحقاقی');
            $table->string('status')->default('approved');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->date('mission_date');
            $table->decimal('hours', 8, 2)->default(8);
            $table->string('status')->default('approved');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('monthly_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('work_days')->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->decimal('required_hours', 10, 2)->default(0);
            $table->decimal('normal_hours', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);
            $table->decimal('delay_hours', 10, 2)->default(0);
            $table->decimal('early_leave_hours', 10, 2)->default(0);
            $table->decimal('absence_hours', 10, 2)->default(0);
            $table->decimal('leave_hours', 10, 2)->default(0);
            $table->decimal('mission_hours', 10, 2)->default(0);
            $table->decimal('night_hours', 10, 2)->default(0);
            $table->decimal('holiday_hours', 10, 2)->default(0);
            $table->decimal('payable_hours', 10, 2)->default(0);
            $table->string('status')->default('processed');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payroll_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monthly_attendance_id')->nullable()->constrained('monthly_attendances')->nullOnDelete();
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('total_benefits', 15, 2)->default(0);
            $table->decimal('insurance_employee', 15, 2)->default(0);
            $table->decimal('insurance_employer', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->string('status')->default('calculated');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payroll_calculation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('type');
            $table->decimal('hours', 10, 2)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('insurance_days')->default(30);
            $table->decimal('insurance_wage', 15, 2)->default(0);
            $table->decimal('employee_share', 15, 2)->default(0);
            $table->decimal('employer_share', 15, 2)->default(0);
            $table->decimal('unemployment_share', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tax_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('exemption_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->json('brackets')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->timestamp('issued_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('status')->default('issued');
            $table->timestamps();
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('method')->default('bank');
            $table->string('reference_number')->nullable();
            $table->string('status')->default('paid');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('tax_records');
        Schema::dropIfExists('insurance_records');
        Schema::dropIfExists('payroll_calculation_lines');
        Schema::dropIfExists('payroll_calculations');
        Schema::dropIfExists('monthly_attendances');
        Schema::dropIfExists('attendance_missions');
        Schema::dropIfExists('attendance_leaves');
        Schema::dropIfExists('attendance_raw_logs');
        Schema::dropIfExists('cost_centers');
    }
};
