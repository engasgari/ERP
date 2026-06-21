<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fiscal_periods', 'is_active')) {
            Schema::table('fiscal_periods', function (Blueprint $table): void {
                $table->boolean('is_active')->default(false)->after('status');
            });
        }

        DB::table('fiscal_periods')
            ->where('status', 'open')
            ->orderByDesc('id')
            ->limit(1)
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('fiscal_periods', 'is_active')) {
            Schema::table('fiscal_periods', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
