<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('company_settings', 'registration_number')) {
                    $table->string('registration_number')->nullable()->after('company_name');
                }

                if (!Schema::hasColumn('company_settings', 'postal_code')) {
                    $table->string('postal_code')->nullable()->after('national_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (Schema::hasColumn('company_settings', 'postal_code')) {
                    $table->dropColumn('postal_code');
                }

                if (Schema::hasColumn('company_settings', 'registration_number')) {
                    $table->dropColumn('registration_number');
                }
            });
        }
    }
};
