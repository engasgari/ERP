<?php

use App\Services\ChartOfAccountsStandardizationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
 * Idempotent: already-applied operations are detected and skipped, so it is safe on databases
 * that were standardized with `php artisan accounting:standardize-chart --execute`.
 * Posted document lines are never modified; the service rolls back if any account balance changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_accounts') || ! DB::table('chart_accounts')->exists()) {
            return;
        }

        $result = app(ChartOfAccountsStandardizationService::class)->execute(
            userId: null,
            backup: true,
        );

        if (collect($result['applied'])->contains('status', 'blocked')) {
            throw new \RuntimeException(
                'اصلاح کدینگ حساب‌ها به‌خاطر عملیات مسدود متوقف شد. لاگ را ببینید: Chart of accounts standardization'
            );
        }

        $summary = collect($result['applied'])->countBy('status')->all();
        Log::info('Chart of accounts standardization migration finished.', [
            'database' => DB::connection()->getDatabaseName(),
            'backup_table' => $result['backup'],
            'statuses' => $summary,
            'line_count' => $result['validation']['line_count'],
            'debit_total' => $result['validation']['debit_total'],
            'credit_total' => $result['validation']['credit_total'],
        ]);

        foreach ($result['applied'] as $operation) {
            if (in_array($operation['status'], ['blocked', 'skipped'], true)) {
                Log::warning('Chart of accounts standardization operation not applied.', [
                    'action' => $operation['action'],
                    'code' => $operation['code'],
                    'status' => $operation['status'],
                    'note' => $operation['note'] ?? null,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible by design: restore from the chart_accounts_backup_* table created in up() if needed.
    }
};
