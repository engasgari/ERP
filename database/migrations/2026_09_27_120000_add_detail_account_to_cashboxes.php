<?php

use App\Services\CashboxCodingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cashboxes', 'detail_account_id')) {
            Schema::table('cashboxes', function (Blueprint $table) {
                $table->foreignId('detail_account_id')->nullable()->after('chart_account_id')->constrained('chart_accounts')->nullOnDelete();
            });
        }

        app(CashboxCodingService::class)->syncAllCashboxDetailAccounts();
    }

    public function down(): void
    {
        if (Schema::hasColumn('cashboxes', 'detail_account_id')) {
            Schema::table('cashboxes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('detail_account_id');
            });
        }
    }
};
