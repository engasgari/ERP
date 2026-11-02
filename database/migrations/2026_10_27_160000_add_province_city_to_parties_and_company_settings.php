<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parties')) {
            Schema::table('parties', function (Blueprint $table): void {
                if (! Schema::hasColumn('parties', 'province')) {
                    $table->string('province', 100)->nullable()->after('postal_code');
                }
                if (! Schema::hasColumn('parties', 'city')) {
                    $table->string('city', 100)->nullable()->after('province');
                }
            });
        }

        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('company_settings', 'province')) {
                    $table->string('province', 100)->nullable()->after('postal_code');
                }
                if (! Schema::hasColumn('company_settings', 'city')) {
                    $table->string('city', 100)->nullable()->after('province');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('parties')) {
            Schema::table('parties', function (Blueprint $table): void {
                if (Schema::hasColumn('parties', 'city')) {
                    $table->dropColumn('city');
                }
                if (Schema::hasColumn('parties', 'province')) {
                    $table->dropColumn('province');
                }
            });
        }

        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table): void {
                if (Schema::hasColumn('company_settings', 'city')) {
                    $table->dropColumn('city');
                }
                if (Schema::hasColumn('company_settings', 'province')) {
                    $table->dropColumn('province');
                }
            });
        }
    }
};
