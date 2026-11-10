<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
 * Repair: COA standardization previously deactivated accounts that still had
 * posted/document lines. Accounts with ledger movement must remain selectable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_accounts') || ! Schema::hasTable('accounting_document_lines')) {
            return;
        }

        $ids = DB::table('chart_accounts as a')
            ->join('accounting_document_lines as l', function ($join) {
                $join->on('l.chart_account_id', '=', 'a.id')
                    ->orOn('l.detail_account_id', '=', 'a.id');
            })
            ->where('a.is_active', false)
            ->distinct()
            ->pluck('a.id');

        if ($ids->isEmpty()) {
            return;
        }

        $updated = DB::table('chart_accounts')
            ->whereIn('id', $ids->all())
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);

        Log::info('Reactivated chart accounts that still have ledger usage.', [
            'database' => DB::connection()->getDatabaseName(),
            'account_ids' => $ids->values()->all(),
            'updated' => $updated,
        ]);
    }

    public function down(): void
    {
        // Irreversible repair — do not re-deactivate accounts with ledger usage.
    }
};
