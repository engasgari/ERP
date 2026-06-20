<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monthly_attendances')) {
            Schema::table('monthly_attendances', function (Blueprint $table): void {
                if (! Schema::hasColumn('monthly_attendances', 'calendar_days')) {
                    $table->unsignedSmallInteger('calendar_days')->default(0)->after('employee_id');
                }
                if (! Schema::hasColumn('monthly_attendances', 'working_days')) {
                    $table->unsignedSmallInteger('working_days')->default(0)->after('calendar_days');
                }
                if (! Schema::hasColumn('monthly_attendances', 'worked_hours')) {
                    $table->decimal('worked_hours', 10, 2)->default(0)->after('required_hours');
                }
                if (! Schema::hasColumn('monthly_attendances', 'break_minutes')) {
                    $table->unsignedInteger('break_minutes')->default(0)->after('worked_hours');
                }
                if (! Schema::hasColumn('monthly_attendances', 'delay_minutes')) {
                    $table->unsignedInteger('delay_minutes')->default(0)->after('break_minutes');
                }
                if (! Schema::hasColumn('monthly_attendances', 'early_leave_minutes')) {
                    $table->unsignedInteger('early_leave_minutes')->default(0)->after('delay_minutes');
                }
                if (! Schema::hasColumn('monthly_attendances', 'absence_minutes')) {
                    $table->unsignedInteger('absence_minutes')->default(0)->after('early_leave_minutes');
                }
                if (! Schema::hasColumn('monthly_attendances', 'net_payable_hours')) {
                    $table->decimal('net_payable_hours', 10, 2)->default(0)->after('payable_hours');
                }
            });
        }

        if (Schema::hasTable('attendance_summaries')) {
            Schema::table('attendance_summaries', function (Blueprint $table): void {
                if (! Schema::hasColumn('attendance_summaries', 'calendar_days')) {
                    $table->unsignedSmallInteger('calendar_days')->default(0)->after('payroll_period_id');
                }
                if (! Schema::hasColumn('attendance_summaries', 'working_days')) {
                    $table->unsignedSmallInteger('working_days')->default(0)->after('calendar_days');
                }
                if (! Schema::hasColumn('attendance_summaries', 'worked_hours')) {
                    $table->decimal('worked_hours', 10, 2)->default(0)->after('worked_days');
                }
                if (! Schema::hasColumn('attendance_summaries', 'break_minutes')) {
                    $table->unsignedInteger('break_minutes')->default(0)->after('worked_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'net_payable_hours')) {
                    $table->decimal('net_payable_hours', 10, 2)->default(0)->after('worked_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'overtime_hours')) {
                    $table->decimal('overtime_hours', 10, 2)->default(0)->after('net_payable_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'leave_hours')) {
                    $table->decimal('leave_hours', 10, 2)->default(0)->after('overtime_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'mission_hours')) {
                    $table->decimal('mission_hours', 10, 2)->default(0)->after('leave_hours');
                }
                if (! Schema::hasColumn('attendance_summaries', 'absence_hours')) {
                    $table->decimal('absence_hours', 10, 2)->default(0)->after('mission_hours');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_attendances')) {
            Schema::table('monthly_attendances', function (Blueprint $table): void {
                foreach (['calendar_days', 'working_days', 'worked_hours', 'break_minutes', 'delay_minutes', 'early_leave_minutes', 'absence_minutes', 'net_payable_hours'] as $column) {
                    if (Schema::hasColumn('monthly_attendances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('attendance_summaries')) {
            Schema::table('attendance_summaries', function (Blueprint $table): void {
                foreach (['calendar_days', 'working_days', 'worked_hours', 'break_minutes', 'net_payable_hours', 'overtime_hours', 'leave_hours', 'mission_hours', 'absence_hours'] as $column) {
                    if (Schema::hasColumn('attendance_summaries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
