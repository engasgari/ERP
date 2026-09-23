<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_attendances')) {
            return;
        }

        Schema::table('monthly_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_attendances', 'absence_days')) {
                $table->decimal('absence_days', 8, 2)->default(0)->after('present_days');
            }
        });

        if (Schema::hasTable('attendance_summaries') && ! Schema::hasColumn('attendance_summaries', 'absence_days')) {
            Schema::table('attendance_summaries', function (Blueprint $table) {
                $table->decimal('absence_days', 8, 2)->default(0)->after('worked_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_attendances') && Schema::hasColumn('monthly_attendances', 'absence_days')) {
            Schema::table('monthly_attendances', function (Blueprint $table) {
                $table->dropColumn('absence_days');
            });
        }

        if (Schema::hasTable('attendance_summaries') && Schema::hasColumn('attendance_summaries', 'absence_days')) {
            Schema::table('attendance_summaries', function (Blueprint $table) {
                $table->dropColumn('absence_days');
            });
        }
    }
};
