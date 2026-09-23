<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\BankAccount;
use App\Models\ChartAccount;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PartnerCurrentAccountService
{
    public function __construct(private AccountingPostingService $posting)
    {
    }

    public function depositToPartnerAccount(
        Party $party,
        BankAccount $bankAccount,
        float $amount,
        string $transactionDate,
        ?string $description = null,
        ?int $userId = null
    ): AccountingDocument {
        return $this->transfer($party, $bankAccount, 'deposit', $amount, $transactionDate, $description, $userId);
    }

    public function withdrawFromPartnerAccount(
        Party $party,
        BankAccount $bankAccount,
        float $amount,
        string $transactionDate,
        ?string $description = null,
        ?int $userId = null
    ): AccountingDocument {
        return $this->transfer($party, $bankAccount, 'withdraw', $amount, $transactionDate, $description, $userId);
    }

    public function partnerLedgerBalance(Party $party, ?ChartAccount $partnerAccount = null): float
    {
        $partnerAccount ??= $this->resolvePartnerCurrentAccount($party);

        return (float) DB::table('accounting_document_lines as l')
            ->join('accounting_documents as d', 'd.id', '=', 'l.accounting_document_id')
            ->whereNull('d.deleted_at')
            ->where('d.status', 'posted')
            ->where('l.chart_account_id', $partnerAccount->id)
            ->where('l.party_id', $party->id)
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as balance')
            ->value('balance');
    }

    public function resolvePartnerCurrentAccount(Party $party): ChartAccount
    {
        if (! $party->is_active) {
            throw new RuntimeException('شریک/سهامدار غیرفعال است.');
        }

        if ($party->detail_code) {
            $byDetailCode = ChartAccount::query()
                ->where('code', $party->detail_code)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('code', 'like', '32%')
                        ->orWhereHas('parent', fn ($parent) => $parent->where('code', '32'))
                        ->orWhereHas('parent.parent', fn ($parent) => $parent->where('code', '32'));
                })
                ->first();

            if ($byDetailCode) {
                return $byDetailCode;
            }
        }

        $byName = ChartAccount::query()
            ->where('is_active', true)
            ->where('title', 'like', '%' . $party->name . '%')
            ->where(function ($query) {
                $query->where('code', 'like', '32%')
                    ->orWhereHas('parent', fn ($parent) => $parent->where('code', '32'));
            })
            ->orderByRaw("CASE WHEN level = 'subsidiary' THEN 0 ELSE 1 END")
            ->first();

        if ($byName) {
            return $byName;
        }

        throw new RuntimeException('حساب جاری شریک برای «' . $party->name . '» در کدینگ (گروه ۳۲) تعریف نشده است.');
    }

    private function transfer(
        Party $party,
        BankAccount $bankAccount,
        string $direction,
        float $amount,
        string $transactionDate,
        ?string $description,
        ?int $userId
    ): AccountingDocument {
        $this->assertTransferIsValid($party, $bankAccount, $amount);
        $partnerAccount = $this->resolvePartnerCurrentAccount($party);

        return $this->posting->fromPartnerCurrentAccountTransfer(
            party: $party,
            bankAccount: $bankAccount,
            direction: $direction,
            amount: $amount,
            date: $transactionDate,
            partnerAccount: $partnerAccount,
            description: $description,
            userId: $userId
        );
    }

    private function assertTransferIsValid(Party $party, BankAccount $bankAccount, float $amount): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('مبلغ انتقال باید بزرگ‌تر از صفر باشد.');
        }

        if (! $party->is_active) {
            throw new RuntimeException('شریک/سهامدار انتخاب‌شده غیرفعال است.');
        }

        if (! $bankAccount->is_active) {
            throw new RuntimeException('حساب بانکی انتخاب‌شده غیرفعال است.');
        }

        $bankAccount->loadMissing('detailAccount');

        if (! $bankAccount->detail_account_id || ! $bankAccount->detailAccount?->is_active) {
            throw new RuntimeException('تفصیل حساب بانکی برای ثبت سند حسابداری فعال نیست.');
        }
    }
}
