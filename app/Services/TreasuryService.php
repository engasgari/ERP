<?php

namespace App\Services;

use App\Models\TreasuryTransaction;
use App\Models\AccountingDocument;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            $transaction = TreasuryTransaction::create($data + [
                'number' => $data['number'] ?? $this->nextNumber(),
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

    private function nextNumber(): string
    {
        $prefix = 'TR-';
        $padding = 5;

        $maxNumber = TreasuryTransaction::withTrashed()
            ->where('number', 'like', $prefix . '%')
            ->pluck('number')
            ->map(function (string $number) use ($prefix): int {
                return (int) preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $number);
            })
            ->max() ?: 0;

        if ($maxNumber < 0) {
            throw new RuntimeException('شماره‌گذاری خزانه معتبر نیست.');
        }

        return $prefix . str_pad((string) ($maxNumber + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function deleteWithAccounting(TreasuryTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->periods->ensureDateIsAllowed($transaction->transaction_date);
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
