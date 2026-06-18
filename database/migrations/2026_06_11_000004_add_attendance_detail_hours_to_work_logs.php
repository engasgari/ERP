<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('work_logs', 'delay_hours')) {
                $table->decimal('delay_hours', 8, 2)->default(0)->after('overtime_hours');
            }
            if (! Schema::hasColumn('work_logs', 'early_leave_hours')) {
                $table->decimal('early_leave_hours', 8, 2)->default(0)->after('delay_hours');
            }
            if (! Schema::hasColumn('work_logs', 'mission_hours')) {
                $table->decimal('mission_hours', 8, 2)->default(0)->after('early_leave_hours');
            }
        });

        Schema::table('attendance_calculations', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_calculations', 'mission_hours')) {
                $table->decimal('mission_hours', 10, 2)->default(0)->after('early_leave_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_calculations', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_calculations', 'mission_hours')) {
                $table->dropColumn('mission_hours');
            }
        });

        Schema::table('work_logs', function (Blueprint $table) {
            foreach (['mission_hours', 'early_leave_hours', 'delay_hours'] as $column) {
                if (Schema::hasColumn('work_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
