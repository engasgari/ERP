<?php

namespace App\Services;

use App\Models\TreasuryTransaction;
use App\Models\AccountingDocument;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        private NumberingService $numbering,
        private AccountingPostingService $posting
    ) {
    }

    public function createAndPost(array $data, ?int $userId = null): TreasuryTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $transaction = TreasuryTransaction::create($data + [
                'number' => $data['number'] ?? $this->numbering->next('treasury_transaction', 'TR-'),
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
            $this->deleteAccountingDocument($transaction);

            $transaction->update($data + [
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

    public function deleteWithAccounting(TreasuryTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->deleteAccountingDocument($transaction);
            $transaction->delete();
        });
    }

    private function deleteAccountingDocument(TreasuryTransaction $transaction): void
    {
        $ids = collect([$transaction->accounting_document_id])
            ->merge(AccountingDocument::withTrashed()
                ->where('source_type', TreasuryTransaction::class)
                ->where('source_id', $transaction->id)
                ->pluck('id'))
            ->filter()
            ->unique();

        foreach ($ids as $id) {
            $document = AccountingDocument::withTrashed()->find($id);
            if (!$document) {
                continue;
            }

            $document->lines()->delete();
            $document->forceDelete();
        }
    }
}
