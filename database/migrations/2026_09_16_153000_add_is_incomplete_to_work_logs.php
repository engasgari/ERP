<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_logs')) {
            return;
        }

        Schema::table('work_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('work_logs', 'is_incomplete')) {
                $table->boolean('is_incomplete')->default(false)->after('hours');
            }
        });

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE work_logs MODIFY end_time TIME NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_logs')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("UPDATE work_logs SET end_time = COALESCE(end_time, start_time) WHERE end_time IS NULL");
            DB::statement('ALTER TABLE work_logs MODIFY end_time TIME NOT NULL');
        }

        Schema::table('work_logs', function (Blueprint $table) {
            if (Schema::hasColumn('work_logs', 'is_incomplete')) {
                $table->dropColumn('is_incomplete');
            }
        });
    }
};
