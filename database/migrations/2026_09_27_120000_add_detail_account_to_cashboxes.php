<?php

use App\Services\CashboxCodingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds cashbox detail accounts under 1201 (e.g. 1201-CASH-001).
 * Idempotent and portable for Ubuntu/MySQL: no doctrine/dbal, no fragile column ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cashboxes') || ! Schema::hasTable('chart_accounts')) {
            return;
        }

        if (! Schema::hasColumn('cashboxes', 'detail_account_id')) {
            Schema::table('cashboxes', function (Blueprint $table) {
                $table->unsignedBigInteger('detail_account_id')->nullable();
            });

            Schema::table('cashboxes', function (Blueprint $table) {
                $table->foreign('detail_account_id')
                    ->references('id')
                    ->on('chart_accounts')
                    ->nullOnDelete();
            });
        }

        app(CashboxCodingService::class)->syncAllCashboxDetailAccounts();
    }

    public function down(): void
    {
        if (! Schema::hasTable('cashboxes') || ! Schema::hasColumn('cashboxes', 'detail_account_id')) {
            return;
        }

        Schema::table('cashboxes', function (Blueprint $table) {
            $table->dropForeign(['detail_account_id']);
            $table->dropColumn('detail_account_id');
        });
    }
};
