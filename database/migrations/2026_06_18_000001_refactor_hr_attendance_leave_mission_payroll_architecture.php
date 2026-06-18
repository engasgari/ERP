<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_group_employee')) {
            Schema::create('work_group_employee', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('work_group_id')->constrained()->cascadeOnDelete();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'start_date', 'end_date']);
            });
        }

        if (Schema::hasTable('employee_work_group') && Schema::hasTable('work_group_employee')) {
            DB::table('employee_work_group')
                ->orderBy('employee_id')
                ->get()
                ->each(function ($row): void {
                    DB::table('work_group_employee')->updateOrInsert(
                        [
                            'employee_id' => $row->employee_id,
                            'work_group_id' => $row->work_group_id,
                            'start_date' => null,
                        ],
                        [
                            'end_date' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                });
        }

        if (Schema::hasTable('attendance_leaves')) {
            Schema::table('attendance_leaves', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'request_type', fn () => $table->string('request_type')->default('hourly')->after('employee_id'));
                $this->addColumnIfMissing($table, 'start_date', fn () => $table->date('start_date')->nullable()->after('leave_date'));
                $this->addColumnIfMissing($table, 'end_date', fn () => $table->date('end_date')->nullable()->after('start_date'));
                $this->addColumnIfMissing($table, 'start_time', fn () => $table->time('start_time')->nullable()->after('end_date'));
                $this->addColumnIfMissing($table, 'end_time', fn () => $table->time('end_time')->nullable()->after('start_time'));
                $this->addColumnIfMissing($table, 'duration_minutes', fn () => $table->unsignedInteger('duration_minutes')->default(0)->after('hours'));
                $this->addColumnIfMissing($table, 'total_days', fn () => $table->decimal('total_days', 8, 2)->default(0)->after('duration_minutes'));
                $this->addColumnIfMissing($table, 'reason', fn () => $table->text('reason')->nullable()->after('type'));
                $this->addColumnIfMissing($table, 'requested_by', fn () => $table->foreignId('requested_by')->nullable()->after('status')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'approved_by', fn () => $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'approved_at', fn () => $table->timestamp('approved_at')->nullable()->after('approved_by'));
                $this->addColumnIfMissing($table, 'rejection_reason', fn () => $table->text('rejection_reason')->nullable()->after('approved_at'));
            });

            DB::table('attendance_leaves')
                ->whereNull('start_date')
                ->update([
                    'start_date' => DB::raw('leave_date'),
                    'end_date' => DB::raw('leave_date'),
                    'duration_minutes' => DB::raw('ROUND(hours * 60)'),
                    'total_days' => DB::raw('CASE WHEN hours >= 8 THEN 1 ELSE 0 END'),
                    'approved_at' => DB::raw("CASE WHEN status = 'approved' THEN updated_at ELSE approved_at END"),
                ]);
        }

        if (Schema::hasTable('attendance_missions')) {
            Schema::table('attendance_missions', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'request_type', fn () => $table->string('request_type')->default('hourly')->after('employee_id'));
                $this->addColumnIfMissing($table, 'start_date', fn () => $table->date('start_date')->nullable()->after('mission_date'));
                $this->addColumnIfMissing($table, 'end_date', fn () => $table->date('end_date')->nullable()->after('start_date'));
                $this->addColumnIfMissing($table, 'start_time', fn () => $table->time('start_time')->nullable()->after('end_date'));
                $this->addColumnIfMissing($table, 'end_time', fn () => $table->time('end_time')->nullable()->after('start_time'));
                $this->addColumnIfMissing($table, 'duration_minutes', fn () => $table->unsignedInteger('duration_minutes')->default(0)->after('hours'));
                $this->addColumnIfMissing($table, 'total_days', fn () => $table->decimal('total_days', 8, 2)->default(0)->after('duration_minutes'));
                $this->addColumnIfMissing($table, 'destination', fn () => $table->string('destination')->nullable()->after('cost_center_id'));
                $this->addColumnIfMissing($table, 'requested_by', fn () => $table->foreignId('requested_by')->nullable()->after('status')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'approved_by', fn () => $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'approved_at', fn () => $table->timestamp('approved_at')->nullable()->after('approved_by'));
                $this->addColumnIfMissing($table, 'rejection_reason', fn () => $table->text('rejection_reason')->nullable()->after('approved_at'));
            });

            DB::table('attendance_missions')
                ->whereNull('start_date')
                ->update([
                    'start_date' => DB::raw('mission_date'),
                    'end_date' => DB::raw('mission_date'),
                    'duration_minutes' => DB::raw('ROUND(hours * 60)'),
                    'total_days' => DB::raw('CASE WHEN hours >= 8 THEN 1 ELSE 0 END'),
                    'approved_at' => DB::raw("CASE WHEN status = 'approved' THEN updated_at ELSE approved_at END"),
                ]);
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->decimal('earned_days', 8, 2)->default(0);
                $table->decimal('used_days', 8, 2)->default(0);
                $table->decimal('remaining_days', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['employee_id', 'year']);
            });
        }

        if (! Schema::hasTable('attendance_summaries')) {
            Schema::create('attendance_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->foreignId('payroll_period_id')->nullable()->constrained()->cascadeOnDelete();
                $table->integer('planned_minutes')->default(0);
                $table->integer('worked_minutes')->default(0);
                $table->integer('overtime_minutes')->default(0);
                $table->integer('holiday_minutes')->default(0);
                $table->integer('delay_minutes')->default(0);
                $table->integer('early_leave_minutes')->default(0);
                $table->integer('absence_minutes')->default(0);
                $table->integer('hourly_leave_minutes')->default(0);
                $table->decimal('daily_leave_days', 8, 2)->default(0);
                $table->integer('hourly_mission_minutes')->default(0);
                $table->decimal('daily_mission_days', 8, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'year', 'month']);
            });
        }

        if (Schema::hasTable('employment_orders')) {
            Schema::table('employment_orders', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'marriage_allowance', fn () => $table->decimal('marriage_allowance', 15, 2)->default(0)->after('transportation_allowance'));
                $this->addColumnIfMissing($table, 'children_allowance', fn () => $table->decimal('children_allowance', 15, 2)->default(0)->after('marriage_allowance'));
                $this->addColumnIfMissing($table, 'children_count', fn () => $table->unsignedTinyInteger('children_count')->default(0)->after('children_allowance'));
                $this->addColumnIfMissing($table, 'seniority_pay', fn () => $table->decimal('seniority_pay', 15, 2)->default(0)->after('children_count'));
            });
        }

        if (Schema::hasTable('payroll_calculations')) {
            Schema::table('payroll_calculations', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'personnel_decree_id', fn () => $table->foreignId('personnel_decree_id')->nullable()->after('employee_id')->constrained('employment_orders')->nullOnDelete());
                $this->addColumnIfMissing($table, 'attendance_summary_id', fn () => $table->foreignId('attendance_summary_id')->nullable()->after('monthly_attendance_id')->constrained('attendance_summaries')->nullOnDelete());
                $this->addColumnIfMissing($table, 'approved_by', fn () => $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'rejection_reason', fn () => $table->text('rejection_reason')->nullable()->after('approved_by'));
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('work_group_employee');
    }

    private function addColumnIfMissing(Blueprint $table, string $column, Closure $definition): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $definition();
        }
    }
};
