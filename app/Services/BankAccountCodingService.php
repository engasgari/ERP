<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\BankAccount;
use App\Models\ChartAccount;
use App\Models\FinancialTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

class BankAccountCodingService
{
    public function syncDetailAccount(BankAccount $bankAccount): BankAccount
    {
        return DB::transaction(function () use ($bankAccount) {
            $bankAccount->loadMissing('account', 'detailAccount');

            $parent = $bankAccount->account
                ?: ChartAccount::where('code', '1202')->first();

            if (! $parent) {
                return $bankAccount;
            }

            $code = $this->detailCode($bankAccount, $parent->code);
            $title = trim($bankAccount->bank_name . ' - ' . $bankAccount->code);

            $detail = $bankAccount->detailAccount;

            if ($detail) {
                $detail->update([
                    'parent_id' => $parent->id,
                    'level' => 'detail',
                    'code' => $code,
                    'title' => $title,
                    'nature' => 'debit',
                    'is_active' => true,
                    'is_system' => true,
                ]);
            } else {
                $detail = ChartAccount::updateOrCreate(
                    ['code' => $code],
                    [
                        'parent_id' => $parent->id,
                        'level' => 'detail',
                        'title' => $title,
                        'nature' => 'debit',
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }

            $bankAccount->update([
                'chart_account_id' => $bankAccount->chart_account_id ?: $parent->id,
                'detail_account_id' => $detail->id,
            ]);

            return $bankAccount->refresh()->load('account', 'detailAccount');
        });
    }

    public function syncAllBankDetailAccounts(): int
    {
        $count = 0;

        BankAccount::query()
            ->with(['account', 'detailAccount'])
            ->orderBy('id')
            ->chunkById(100, function ($banks) use (&$count): void {
                foreach ($banks as $bank) {
                    $this->syncDetailAccount($bank);
                    $count++;
                }
            });

        return $count;
    }

    public function backfillHistoricalBankDetails(): array
    {
        $accountingLines = 0;
        $financialTransactions = 0;
        $documentSourceLines = 0;

        AccountingDocumentLine::query()
            ->with('bankAccount.detailAccount')
            ->whereNotNull('bank_account_id')
            ->orderBy('id')
            ->chunkById(500, function ($lines) use (&$accountingLines): void {
                foreach ($lines as $line) {
                    $bank = $line->bankAccount;

                    if (! $bank || ! $bank->detail_account_id) {
                        continue;
                    }

                    if ((int) $line->detail_account_id === (int) $bank->detail_account_id) {
                        continue;
                    }

                    $line->update(['detail_account_id' => $bank->detail_account_id]);
                    $accountingLines++;
                }
            });

        FinancialTransaction::query()
            ->with('bankAccount.detailAccount')
            ->whereNotNull('bank_account_id')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use (&$financialTransactions): void {
                foreach ($transactions as $transaction) {
                    $bank = $transaction->bankAccount;

                    if (! $bank || ! $bank->detail_account_id) {
                        continue;
                    }

                    if ((int) $transaction->detail_account_id === (int) $bank->detail_account_id) {
                        continue;
                    }

                    $transaction->update(['detail_account_id' => $bank->detail_account_id]);
                    $financialTransactions++;
                }
            });

        AccountingDocument::query()
            ->with(['lines.account', 'source'])
            ->whereIn('source_type', [
                FinancialTransaction::class,
                TreasuryTransaction::class,
                ReceiptVoucher::class,
                PaymentVoucher::class,
            ])
            ->where('status', 'posted')
            ->orderBy('id')
            ->chunkById(200, function ($documents) use (&$documentSourceLines): void {
                foreach ($documents as $document) {
                    $source = $document->source;

                    if (! $source) {
                        continue;
                    }

                    foreach ($document->lines as $line) {
                        $bankAccountId = $this->resolveSourceBankAccountId($source, $line);

                        if (! $bankAccountId) {
                            continue;
                        }

                        $bank = BankAccount::with('detailAccount')->find($bankAccountId);

                        if (! $bank || ! $bank->detail_account_id) {
                            continue;
                        }

                        if ((int) $line->bank_account_id === (int) $bank->id && (int) $line->detail_account_id === (int) $bank->detail_account_id) {
                            continue;
                        }

                        $line->update([
                            'bank_account_id' => $bank->id,
                            'detail_account_id' => $bank->detail_account_id,
                        ]);
                        $documentSourceLines++;
                    }
                }
            });

        return [
            'accounting_document_lines' => $accountingLines,
            'financial_transactions' => $financialTransactions,
            'document_source_lines' => $documentSourceLines,
        ];
    }

    private function resolveSourceBankAccountId(object $source, AccountingDocumentLine $line): ?int
    {
        if ($source instanceof FinancialTransaction) {
            return $this->isTreasuryLine($line) ? ($source->bank_account_id ?: null) : null;
        }

        if ($source instanceof ReceiptVoucher) {
            return $this->isTreasuryLine($line) && $source->treasury_type === \App\Models\BankAccount::class
                ? (int) $source->treasury_id
                : null;
        }

        if ($source instanceof PaymentVoucher) {
            return $this->isTreasuryLine($line) && $source->treasury_type === \App\Models\BankAccount::class
                ? (int) $source->treasury_id
                : null;
        }

        if ($source instanceof TreasuryTransaction) {
            if (! $this->isTreasuryLine($line)) {
                return null;
            }

            if ($source->type === 'transfer') {
                if ((float) $line->debit > 0 && $source->to_treasury_type === \App\Models\BankAccount::class) {
                    return (int) $source->to_treasury_id;
                }

                if ((float) $line->credit > 0 && $source->from_treasury_type === \App\Models\BankAccount::class) {
                    return (int) $source->from_treasury_id;
                }

                return null;
            }

            if (in_array($source->type, ['deposit', 'cash_receipt', 'bank_receipt'], true)) {
                return $source->to_treasury_type === \App\Models\BankAccount::class
                    ? (int) $source->to_treasury_id
                    : null;
            }

            if (in_array($source->type, ['withdrawal', 'cash_payment', 'bank_payment'], true)) {
                return $source->from_treasury_type === \App\Models\BankAccount::class
                    ? (int) $source->from_treasury_id
                    : null;
            }
        }

        return null;
    }

    private function isTreasuryLine(AccountingDocumentLine $line): bool
    {
        return in_array((int) $line->chart_account_id, $this->treasuryAccountIds(), true);
    }

    private function treasuryAccountIds(): array
    {
        return ChartAccount::query()
            ->whereIn('code', ['1201', '1202'])
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function detailCode(BankAccount $bankAccount, string $parentCode): string
    {
        return $parentCode . '-' . $bankAccount->code;
    }
}
