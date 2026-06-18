<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCoreTables();
        $this->createBusinessTables();
        $this->createAccessTables();
        $this->repairExistingColumns();
        $this->seedAccessDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_employee_access');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('employee_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('warehouse_transactions');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }

    private function createCoreTables(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    private function createBusinessTables(): void
    {
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('national_code')->nullable()->unique();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('position');
                $table->decimal('salary', 15, 2)->nullable();
                $table->decimal('hourly_rate', 15, 2)->nullable();
                $table->text('address')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('financial_transactions')) {
            Schema::create('financial_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['income', 'expense']);
                $table->string('category');
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('description')->nullable();
                $table->string('reference_number')->nullable();
                $table->string('attachment')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('work_logs')) {
            Schema::create('work_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->date('work_date');
                $table->time('start_time');
                $table->time('end_time');
                $table->decimal('hours', 8, 2);
                $table->text('description')->nullable();
                $table->decimal('hourly_rate', 15, 2);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salaries')) {
            Schema::create('salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->integer('year');
                $table->integer('month');
                $table->decimal('total_hours', 8, 2)->default(0);
                $table->decimal('hourly_rate', 15, 2);
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('overtime_hours', 8, 2)->default(0);
                $table->decimal('overtime_rate', 15, 2)->default(0);
                $table->decimal('overtime_salary', 15, 2)->default(0);
                $table->decimal('bonus', 15, 2)->default(0);
                $table->decimal('deduction', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->decimal('advance_payment', 15, 2)->default(0);
                $table->decimal('final_salary', 15, 2)->default(0);
                $table->enum('status', ['draft', 'calculated', 'paid', 'partial'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'year', 'month']);
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->enum('payment_method', ['cash', 'bank', 'card'])->default('cash');
                $table->string('reference_number')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_transactions')) {
            Schema::create('employee_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['debit', 'credit']);
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('transaction_date');
                $table->text('description');
                $table->enum('reference_type', ['salary', 'payment', 'advance', 'bonus', 'deduction', 'other'])->default('other');
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamps();
                $table->index('type');
                $table->index('transaction_date');
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    private function createAccessTables(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('title');
                $table->string('group')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->primary(['role_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('user_employee_access')) {
            Schema::create('user_employee_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->boolean('can_view_work_logs')->default(false);
                $table->boolean('can_manage_work_logs')->default(false);
                $table->boolean('can_view_salaries')->default(false);
                $table->boolean('can_manage_salaries')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'employee_id']);
            });
        }
    }

    private function repairExistingColumns(): void
    {
        $this->addMissingColumn('projects', 'start_date', fn (Blueprint $table) => $table->date('start_date')->nullable()->after('description'));
        $this->addMissingColumn('projects', 'end_date', fn (Blueprint $table) => $table->date('end_date')->nullable()->after('start_date'));
        $this->addMissingColumn('employees', 'hourly_rate', fn (Blueprint $table) => $table->decimal('hourly_rate', 15, 2)->nullable()->after('salary'));
    }

    private function addMissingColumn(string $table, string $column, callable $definition): void
    {
        if (!Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $definition);
    }

    private function seedAccessDefaults(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $permissions = [
            ['key' => 'users.manage', 'title' => 'مدیریت کاربران و دسترسی‌ها', 'group' => 'مدیریت'],
            ['key' => 'projects.view', 'title' => 'مشاهده پروژه‌ها', 'group' => 'پروژه‌ها'],
            ['key' => 'projects.manage', 'title' => 'مدیریت پروژه‌ها', 'group' => 'پروژه‌ها'],
            ['key' => 'employees.view', 'title' => 'مشاهده پرسنل', 'group' => 'پرسنل'],
            ['key' => 'employees.manage', 'title' => 'مدیریت پرسنل', 'group' => 'پرسنل'],
            ['key' => 'worklogs.view', 'title' => 'مشاهده کارکرد', 'group' => 'کارکرد'],
            ['key' => 'worklogs.manage', 'title' => 'مدیریت کارکرد', 'group' => 'کارکرد'],
            ['key' => 'salaries.view', 'title' => 'مشاهده حقوق', 'group' => 'حقوق'],
            ['key' => 'salaries.manage', 'title' => 'مدیریت حقوق و پرداخت‌ها', 'group' => 'حقوق'],
            ['key' => 'warehouse.view', 'title' => 'مشاهده انبار', 'group' => 'انبار'],
            ['key' => 'warehouse.manage', 'title' => 'مدیریت انبار', 'group' => 'انبار'],
            ['key' => 'financial.view', 'title' => 'مشاهده مالی', 'group' => 'مالی'],
            ['key' => 'financial.manage', 'title' => 'مدیریت مالی', 'group' => 'مالی'],
            ['key' => 'reports.view', 'title' => 'مشاهده گزارش‌ها', 'group' => 'گزارش‌ها'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $roles = [
            'admin' => ['title' => 'مدیر سیستم', 'description' => 'دسترسی کامل به همه بخش‌ها', 'is_system' => true],
            'hr_manager' => ['title' => 'مدیر منابع انسانی', 'description' => 'مدیریت پرسنل و کارکرد', 'is_system' => false],
            'payroll_viewer' => ['title' => 'مشاهده‌گر حقوق', 'description' => 'مشاهده حقوق کارمندهای مجاز', 'is_system' => false],
            'worklog_manager' => ['title' => 'مدیر کارکرد', 'description' => 'ثبت و مدیریت کارکرد کارمندهای مجاز', 'is_system' => false],
            'warehouse_manager' => ['title' => 'مدیر انبار', 'description' => 'مدیریت انبار', 'is_system' => false],
            'accountant' => ['title' => 'حسابدار', 'description' => 'مدیریت مالی و مشاهده حقوق', 'is_system' => false],
            'report_viewer' => ['title' => 'مشاهده‌گر گزارش‌ها', 'description' => 'مشاهده گزارش‌های مدیریتی', 'is_system' => false],
        ];

        foreach ($roles as $name => $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                $role + ['name' => $name, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $rolePermissions = [
            'admin' => array_column($permissions, 'key'),
            'hr_manager' => ['employees.view', 'employees.manage', 'worklogs.view', 'worklogs.manage'],
            'payroll_viewer' => ['salaries.view'],
            'worklog_manager' => ['worklogs.view', 'worklogs.manage'],
            'warehouse_manager' => ['warehouse.view', 'warehouse.manage'],
            'accountant' => ['financial.view', 'financial.manage', 'salaries.view'],
            'report_viewer' => ['reports.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissionKeys) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('key', $permissionKeys)->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        if (Schema::hasTable('users') && Schema::hasTable('role_user')) {
            $firstUserId = DB::table('users')->orderBy('id')->value('id');
            $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

            if ($firstUserId && $adminRoleId && !DB::table('role_user')->where('user_id', $firstUserId)->exists()) {
                DB::table('role_user')->insert([
                    'role_id' => $adminRoleId,
                    'user_id' => $firstUserId,
                ]);
            }
        }
    }
};
