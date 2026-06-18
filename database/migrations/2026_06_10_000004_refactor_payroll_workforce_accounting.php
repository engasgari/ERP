<?php

use App\Models\ChartAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'employee_code')) {
                $table->string('employee_code')->nullable()->after('id');
            }
            if (! Schema::hasColumn('employees', 'personnel_number')) {
                $table->string('personnel_number')->nullable()->after('employee_code');
            }
            if (! Schema::hasColumn('employees', 'department')) {
                $table->string('department')->nullable()->after('email');
            }
            if (! Schema::hasColumn('employees', 'employment_type')) {
                $table->string('employment_type')->default('full_time')->after('position');
            }
            if (! Schema::hasColumn('employees', 'salary_type')) {
                $table->string('salary_type')->default('hourly')->after('employment_type');
            }
            if (! Schema::hasColumn('employees', 'base_salary')) {
                $table->decimal('base_salary', 15, 2)->default(0)->after('salary');
            }
            if (! Schema::hasColumn('employees', 'overtime_rate')) {
                $table->decimal('overtime_rate', 15, 2)->default(0)->after('hourly_rate');
            }
            if (! Schema::hasColumn('employees', 'default_cost_center')) {
                $table->string('default_cost_center')->nullable()->after('overtime_rate');
            }
            if (! Schema::hasColumn('employees', 'default_project_id')) {
                $table->foreignId('default_project_id')->nullable()->after('default_cost_center')->constrained('projects')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('default_project_id');
            }
            if (! Schema::hasColumn('employees', 'iban')) {
                $table->string('iban')->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('employees', 'tax_number')) {
                $table->string('tax_number')->nullable()->after('iban');
            }
            if (! Schema::hasColumn('employees', 'insurance_number')) {
                $table->string('insurance_number')->nullable()->after('tax_number');
            }
            if (! Schema::hasColumn('employees', 'status')) {
                $table->string('status')->default('active')->after('is_active');
            }
            if (! Schema::hasColumn('employees', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('status');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            try {
                $table->unique('employee_code');
            } catch (Throwable) {
            }
        });

        DB::table('employees')
            ->whereNull('employee_code')
            ->orderBy('id')
            ->get(['id', 'salary', 'hourly_rate', 'is_active'])
            ->each(function ($employee): void {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'employee_code' => 'EMP-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
                        'personnel_number' => 'P-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
                        'salary_type' => ((float) $employee->hourly_rate > 0) ? 'hourly' : 'monthly',
                        'base_salary' => (float) ($employee->salary ?? 0),
                        'status' => $employee->is_active ? 'active' : 'inactive',
                    ]);
            });

        Schema::table('work_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('work_logs', 'check_in_time')) {
                $table->time('check_in_time')->nullable()->after('work_date');
            }
            if (! Schema::hasColumn('work_logs', 'check_out_time')) {
                $table->time('check_out_time')->nullable()->after('check_in_time');
            }
            if (! Schema::hasColumn('work_logs', 'overtime_hours')) {
                $table->decimal('overtime_hours', 8, 2)->default(0)->after('hours');
            }
            if (! Schema::hasColumn('work_logs', 'leave_hours')) {
                $table->decimal('leave_hours', 8, 2)->default(0)->after('overtime_hours');
            }
            if (! Schema::hasColumn('work_logs', 'absence_hours')) {
                $table->decimal('absence_hours', 8, 2)->default(0)->after('leave_hours');
            }
            if (! Schema::hasColumn('work_logs', 'cost_center')) {
                $table->string('cost_center')->nullable()->after('project_id');
            }
            if (! Schema::hasColumn('work_logs', 'attendance_source')) {
                $table->string('attendance_source')->default('manual')->after('total_amount');
            }
            if (! Schema::hasColumn('work_logs', 'import_batch')) {
                $table->string('import_batch')->nullable()->after('attendance_source');
            }
        });

        DB::table('work_logs')
            ->whereNull('check_in_time')
            ->update([
                'check_in_time' => DB::raw('start_time'),
                'check_out_time' => DB::raw('end_time'),
            ]);

        Schema::table('salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('salaries', 'gross_salary')) {
                $table->decimal('gross_salary', 15, 2)->default(0)->after('bonus');
            }
            if (! Schema::hasColumn('salaries', 'benefits')) {
                $table->decimal('benefits', 15, 2)->default(0)->after('bonus');
            }
            if (! Schema::hasColumn('salaries', 'insurance_amount')) {
                $table->decimal('insurance_amount', 15, 2)->default(0)->after('deduction');
            }
            if (! Schema::hasColumn('salaries', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('insurance_amount');
            }
            if (! Schema::hasColumn('salaries', 'loan_amount')) {
                $table->decimal('loan_amount', 15, 2)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('salaries', 'penalty_amount')) {
                $table->decimal('penalty_amount', 15, 2)->default(0)->after('loan_amount');
            }
            if (! Schema::hasColumn('salaries', 'total_deductions')) {
                $table->decimal('total_deductions', 15, 2)->default(0)->after('penalty_amount');
            }
            if (! Schema::hasColumn('salaries', 'accounting_document_id')) {
                $table->foreignId('accounting_document_id')->nullable()->after('status')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('salaries', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('accounting_document_id');
            }
            if (! Schema::hasColumn('salaries', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('posted_at');
            }
        });

        DB::table('salaries')
            ->where('gross_salary', 0)
            ->update([
                'gross_salary' => DB::raw('base_salary + overtime_salary + bonus'),
                'total_deductions' => DB::raw('deduction + advance_payment'),
            ]);

        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->decimal('daily_work_hours', 5, 2)->default(8);
            $table->decimal('overtime_multiplier', 5, 2)->default(1.4);
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('work_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('jalali_year')->nullable();
            $table->json('working_days')->nullable();
            $table->json('weekend_days')->nullable();
            $table->json('holidays')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('work_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_calendar_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payroll_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_work_group', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_id', 'work_group_id']);
        });

        Schema::create('payroll_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('account_code');
            $table->foreignId('chart_account_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $settings = [
            ['key' => 'salary_expense', 'title' => 'هزینه حقوق و دستمزد', 'account_code' => '5202'],
            ['key' => 'salary_payable', 'title' => 'حقوق و دستمزد پرداختنی', 'account_code' => '2104'],
            ['key' => 'overtime_expense', 'title' => 'هزینه اضافه کاری', 'account_code' => '5202'],
            ['key' => 'bonus_expense', 'title' => 'هزینه مزایا و پاداش', 'account_code' => '5202'],
            ['key' => 'deduction_payable', 'title' => 'کسورات حقوق', 'account_code' => '2104'],
            ['key' => 'tax_payable', 'title' => 'مالیات حقوق پرداختنی', 'account_code' => '2104'],
            ['key' => 'insurance_payable', 'title' => 'بیمه حقوق پرداختنی', 'account_code' => '2104'],
        ];

        foreach ($settings as $setting) {
            DB::table('payroll_accounting_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + [
                    'chart_account_id' => ChartAccount::where('code', $setting['account_code'])->value('id'),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        Schema::create('payroll_audits', function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable');
            $table->string('event');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audits');
        Schema::dropIfExists('payroll_accounting_settings');
        Schema::dropIfExists('payroll_import_logs');
        Schema::dropIfExists('employee_work_group');
        Schema::dropIfExists('work_groups');
        Schema::dropIfExists('work_calendars');
        Schema::dropIfExists('work_shifts');
    }
};
