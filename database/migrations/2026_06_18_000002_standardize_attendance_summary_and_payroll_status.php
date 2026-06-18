<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_summaries')) {
            Schema::table('attendance_summaries', function (Blueprint $table): void {
                if (! Schema::hasColumn('attendance_summaries', 'required_days')) {
                    $table->decimal('required_days', 8, 2)->default(0)->after('payroll_period_id');
                }
                if (! Schema::hasColumn('attendance_summaries', 'required_hours')) {
                    $table->decimal('required_hours', 10, 2)->default(0)->after('required_days');
                }
                if (! Schema::hasColumn('attendance_summaries', 'required_minutes')) {
                    $table->integer('required_minutes')->default(0)->after('required_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'worked_days')) {
                    $table->decimal('worked_days', 8, 2)->default(0)->after('required_minutes');
                }
                if (! Schema::hasColumn('attendance_summaries', 'worked_hours')) {
                    $table->decimal('worked_hours', 10, 2)->default(0)->after('worked_days');
                }
                if (! Schema::hasColumn('attendance_summaries', 'worked_minutes')) {
                    $table->integer('worked_minutes')->default(0)->after('worked_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'failure_reason')) {
                    $table->text('failure_reason')->nullable()->after('meta');
                }
                if (! Schema::hasColumn('attendance_summaries', 'status')) {
                    $table->string('status')->default('calculated')->after('failure_reason');
                }
            });
        }

        if (Schema::hasTable('payroll_calculations') && ! Schema::hasColumn('payroll_calculations', 'failure_reason')) {
            Schema::table('payroll_calculations', function (Blueprint $table): void {
                $table->text('failure_reason')->nullable()->after('rejection_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_calculations') && Schema::hasColumn('payroll_calculations', 'failure_reason')) {
            Schema::table('payroll_calculations', fn (Blueprint $table) => $table->dropColumn('failure_reason'));
        }

        if (Schema::hasTable('attendance_summaries')) {
            Schema::table('attendance_summaries', function (Blueprint $table): void {
                foreach (['required_days', 'required_hours', 'required_minutes', 'worked_days', 'worked_hours', 'failure_reason', 'status'] as $column) {
                    if (Schema::hasColumn('attendance_summaries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
