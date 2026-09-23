<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_logs') || ! Schema::hasColumn('work_logs', 'project_id')) {
            return;
        }

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Keep column as-is; SQLite does not enforce NOT NULL the same way after FK drop.
        } else {
            DB::statement('ALTER TABLE work_logs MODIFY project_id BIGINT UNSIGNED NULL');
        }

        Schema::table('work_logs', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_logs') || ! Schema::hasColumn('work_logs', 'project_id')) {
            return;
        }

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE work_logs MODIFY project_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('work_logs', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
