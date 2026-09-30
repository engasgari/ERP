<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make CRM activity morph columns nullable without doctrine/dbal (portable on Ubuntu/MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_activities')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_activities` MODIFY `activitable_type` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `crm_activities` MODIFY `activitable_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_activities')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_activities` MODIFY `activitable_type` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `crm_activities` MODIFY `activitable_id` BIGINT UNSIGNED NOT NULL');
    }
};
