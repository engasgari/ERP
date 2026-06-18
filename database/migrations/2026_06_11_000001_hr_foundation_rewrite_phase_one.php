<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_units')) {
            Schema::create('organization_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('organization_units')->nullOnDelete();
                $table->string('code')->unique();
                $table->string('title');
                $table->string('type')->default('department');
                $table->string('cost_center_code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('hr_jobs')) {
            Schema::create('hr_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->foreignId('job_id')->nullable()->constrained('hr_jobs')->nullOnDelete();
                $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
                $table->foreignId('supervisor_position_id')->nullable()->constrained('positions')->nullOnDelete();
                $table->unsignedSmallInteger('capacity')->default(1);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('id')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'personnel_code')) {
                $table->string('personnel_code')->nullable()->after('party_id');
            }
            if (! Schema::hasColumn('employees', 'attendance_card_number')) {
                $table->string('attendance_card_number')->nullable()->after('personnel_code');
            }
            if (! Schema::hasColumn('employees', 'hire_date')) {
                $table->date('hire_date')->nullable()->after('attendance_card_number');
            }
            if (! Schema::hasColumn('employees', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('hire_date');
            }
            if (! Schema::hasColumn('employees', 'position_id')) {
                $table->foreignId('position_id')->nullable()->after('position')->constrained('positions')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'organization_unit_id')) {
                $table->foreignId('organization_unit_id')->nullable()->after('position_id')->constrained('organization_units')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('employment_orders')) {
            Schema::create('employment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->string('cost_center_code')->nullable();
            $table->foreignId('default_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('order_type')->default('hire');
            $table->string('employment_type')->default('monthly_contract');
            $table->string('insurance_status')->default('insured');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('hourly_rate', 15, 2)->default(0);
            $table->decimal('housing_allowance', 15, 2)->default(0);
            $table->decimal('food_allowance', 15, 2)->default(0);
            $table->decimal('child_allowance', 15, 2)->default(0);
            $table->decimal('transportation_allowance', 15, 2)->default(0);
            $table->json('fixed_benefits')->nullable();
            $table->json('fixed_deductions')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employment_contracts')) {
            Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_type')->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->longText('body')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employee_history')) {
            Schema::create('employee_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->string('event');
            $table->string('title');
            $table->date('effective_date')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            });
        }

        $this->migrateEmployeesToParties();
        $this->seedPermissions();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['organization_unit_id', 'position_id', 'party_id'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    try {
                        $table->dropConstrainedForeignId($column);
                    } catch (Throwable) {
                    }
                }
            }

            foreach (['personnel_code', 'attendance_card_number', 'hire_date', 'termination_date'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('employee_history');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employment_contracts');
        Schema::dropIfExists('employment_orders');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('hr_jobs');
        Schema::dropIfExists('organization_units');
    }

    private function migrateEmployeesToParties(): void
    {
        DB::table('employees')
            ->whereNull('party_id')
            ->orderBy('id')
            ->get()
            ->each(function ($employee): void {
                $name = trim((string) (($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')));
                $partyId = Party::create([
                    'code' => 'P-EMP-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
                    'detail_code' => 'D-EMP-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
                    'kind' => 'person',
                    'name' => $name !== '' ? $name : 'پرسنل #' . $employee->id,
                    'national_id' => $employee->national_code ?? null,
                    'phone' => $employee->phone ?? null,
                    'mobile' => $employee->phone ?? null,
                    'email' => $employee->email ?? null,
                    'address' => $employee->address ?? null,
                    'is_active' => (bool) ($employee->is_active ?? true),
                    'notes' => $employee->notes ?? null,
                ])->id;

                DB::table('employees')->where('id', $employee->id)->update([
                    'party_id' => $partyId,
                    'personnel_code' => $employee->personnel_code ?? $employee->employee_code ?? ('EMP-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT)),
                    'hire_date' => $employee->hire_date ?? $employee->start_date ?? now()->toDateString(),
                    'termination_date' => $employee->termination_date ?? $employee->end_date ?? null,
                ]);
            });
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['key' => 'hr.view', 'title' => 'مشاهده منابع انسانی', 'group' => 'منابع انسانی'],
            ['key' => 'hr.manage', 'title' => 'مدیریت منابع انسانی', 'group' => 'منابع انسانی'],
            ['key' => 'employment-orders.view', 'title' => 'مشاهده احکام کارگزینی', 'group' => 'منابع انسانی'],
            ['key' => 'employment-orders.manage', 'title' => 'مدیریت احکام کارگزینی', 'group' => 'منابع انسانی'],
            ['key' => 'employment-orders.approve', 'title' => 'تایید احکام کارگزینی', 'group' => 'منابع انسانی'],
            ['key' => 'contracts.view', 'title' => 'مشاهده قراردادها', 'group' => 'منابع انسانی'],
            ['key' => 'contracts.manage', 'title' => 'مدیریت قراردادها', 'group' => 'منابع انسانی'],
            ['key' => 'attendance.view', 'title' => 'مشاهده حضور و غیاب', 'group' => 'حضور و غیاب'],
            ['key' => 'attendance.manage', 'title' => 'مدیریت حضور و غیاب', 'group' => 'حضور و غیاب'],
            ['key' => 'attendance.import', 'title' => 'ورود اطلاعات تردد', 'group' => 'حضور و غیاب'],
            ['key' => 'payroll.view', 'title' => 'مشاهده حقوق و دستمزد', 'group' => 'حقوق و دستمزد'],
            ['key' => 'payroll.manage', 'title' => 'مدیریت حقوق و دستمزد', 'group' => 'حقوق و دستمزد'],
            ['key' => 'payroll.approve', 'title' => 'تایید حقوق', 'group' => 'حقوق و دستمزد'],
            ['key' => 'payroll.post', 'title' => 'صدور سند حقوق', 'group' => 'حقوق و دستمزد'],
            ['key' => 'salary-payments.view', 'title' => 'مشاهده پرداخت حقوق', 'group' => 'مالی'],
            ['key' => 'salary-payments.manage', 'title' => 'مدیریت پرداخت حقوق', 'group' => 'مالی'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['key' => $permission['key']], $permission + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
