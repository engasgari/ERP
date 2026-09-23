<?php

namespace App\Services;

use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        private NumberingService $numbering,
        private AccountingPostingService $posting,
        private FiscalPeriodService $periods
    ) {
    }

    public function createAndPost(array $data, ?int $userId = null): TreasuryTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $this->periods->ensureDateIsAllowed($data['transaction_date']);
            $fiscalYear = $this->periods->fiscalYearForDate($data['transaction_date']);

            $transaction = TreasuryTransaction::create($data + [
                'fiscal_year_id' => $fiscalYear?->id,
                'number' => $data['number'] ?? $this->numbering->next('treasury_transaction', 'TR-', $fiscalYear?->id),
                'created_by' => $userId,
                'status' => 'draft',
            ]);

            $document = $this->posting->fromTreasuryTransaction($transaction, $userId);
            $transaction->update([
                'status' => 'posted',
                'accounting_document_id' => $document->id,
                'posted_at' => now(),
            ]);

            return $transaction;
        });
    }

    public function updateAndPost(TreasuryTransaction $transaction, array $data, ?int $userId = null): TreasuryTransaction
    {
        return DB::transaction(function () use ($transaction, $data, $userId) {
            $this->periods->ensureDateIsAllowed($data['transaction_date'] ?? $transaction->transaction_date);
            $this->deleteAccountingDocument($transaction, $userId);

            $fiscalYear = $this->periods->fiscalYearForDate($data['transaction_date'] ?? $transaction->transaction_date);

            $transaction->update($data + [
                'fiscal_year_id' => $fiscalYear?->id,
                'status' => 'draft',
                'accounting_document_id' => null,
                'posted_at' => null,
            ]);

            $document = $this->posting->fromTreasuryTransaction($transaction->refresh(), $userId);
            $transaction->update([
                'status' => 'posted',
                'accounting_document_id' => $document->id,
                'posted_at' => now(),
            ]);

            return $transaction->refresh();
        });
    }

    public function deleteWithAccounting(TreasuryTransaction $transaction, ?int $userId = null): void
    {
        DB::transaction(function () use ($transaction, $userId) {
            $this->periods->ensureDateIsAllowed($transaction->transaction_date);
            $this->deleteAccountingDocument($transaction, $userId);
            $transaction->delete();
        });
    }

    private function deleteAccountingDocument(TreasuryTransaction $transaction, ?int $userId = null): void
    {
        $this->posting->deleteTreasuryTransactionDocument($transaction, $userId);
    }
}
