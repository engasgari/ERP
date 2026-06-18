<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employment_orders') || ! Schema::hasColumn('employment_orders', 'job_id')) {
            return;
        }

        Schema::table('employment_orders', function (Blueprint $table) {
            try {
                $table->dropForeign(['job_id']);
            } catch (Throwable) {
            }

            $table->foreign('job_id')->references('id')->on('hr_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employment_orders') || ! Schema::hasColumn('employment_orders', 'job_id')) {
            return;
        }

        Schema::table('employment_orders', function (Blueprint $table) {
            try {
                $table->dropForeign(['job_id']);
            } catch (Throwable) {
            }

            if (Schema::hasTable('jobs')) {
                $table->foreign('job_id')->references('id')->on('jobs')->nullOnDelete();
            }
        });
    }
};
