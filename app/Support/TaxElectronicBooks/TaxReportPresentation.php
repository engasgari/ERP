<?php

namespace App\Support\TaxElectronicBooks;

use App\Models\AccountingDocumentLine;
use App\Models\ChartAccount;
use Illuminate\Support\Collection;

class TaxReportPresentation
{
    /**
     * @return array{
     *     ledger_code: string,
     *     ledger_title: string,
     *     subsidiary_code: string,
     *     subsidiary_title: string,
     *     report_group: string,
     *     statement_section: string,
     *     erp_code: string,
     *     erp_title: string
     * }
     */
    public function mapAccount(AccountingDocumentLine $line, Collection $accounts): array
    {
        $account = $accounts->get($line->chart_account_id);
        $detailAccount = $line->detail_account_id ? $accounts->get($line->detail_account_id) : null;
        $leaf = $detailAccount ?: $account;

        $erpCode = (string) ($leaf?->code ?: '');
        $erpTitle = (string) ($leaf?->title ?: '');

        // Always resolve کل/معین from the posted leaf account hierarchy in ERP coding.
        $ancestors = $this->ancestorChain($leaf ?: $account, $accounts);
        $ledger = collect($ancestors)->first(fn ($item) => $item?->level === 'ledger');
        $subsidiary = collect($ancestors)->first(fn ($item) => $item?->level === 'subsidiary');

        // If posting is on detail under a subsidiary, keep subsidiary as معین
        // unless it is a bank/cash detail — then show the concrete bank/cash account.
        $reportSubsidiary = $this->resolveReportSubsidiary($leaf, $subsidiary, $ledger);

        $ledgerCode = (string) ($ledger?->code ?: $this->fallbackLedgerCode($erpCode));
        $ledgerTitle = (string) ($ledger?->title ?: $this->fallbackLedgerTitle($ledgerCode));
        $subsidiaryCode = (string) ($reportSubsidiary?->code ?: $erpCode);
        $subsidiaryTitle = (string) ($reportSubsidiary?->title ?: $erpTitle ?: 'سایر');

        // Expense detail breakdown under 5201: show detail title as معین for tax clarity.
        if ($leaf && $leaf->level === 'detail' && str_starts_with((string) $leaf->code, '5201')) {
            $subsidiaryCode = (string) $leaf->code;
            $subsidiaryTitle = $this->expenseDetailTitle((string) $leaf->code, (string) $leaf->title);
        }

        // Contractor payables presentation label (report-only).
        if (str_starts_with($subsidiaryCode, '2101') && $this->isVendorParty($line)) {
            $subsidiaryTitle = 'پیمانکاران';
        }

        return [
            'ledger_code' => $ledgerCode,
            'ledger_title' => $ledgerTitle,
            'subsidiary_code' => $subsidiaryCode,
            'subsidiary_title' => $subsidiaryTitle,
            'report_group' => $subsidiaryTitle,
            'statement_section' => $this->statementSectionForCode($ledgerCode ?: $erpCode),
            'erp_code' => $erpCode,
            'erp_title' => $erpTitle,
        ];
    }

    /**
     * @param  Collection<int, AccountingDocumentLine>  $lines
     */
    public function classifyDocumentPattern(Collection $lines, Collection $accounts): string
    {
        $activeLines = $lines->filter(fn (AccountingDocumentLine $line) => (float) $line->debit > 0 || (float) $line->credit > 0);

        if ($activeLines->isEmpty()) {
            return 'general';
        }

        $codes = $activeLines->map(fn (AccountingDocumentLine $line) => $this->lineErpCode($line, $accounts));
        $bankCashLines = $codes->filter(fn (string $code) => $this->isBankOrCashCode($code));

        if ($bankCashLines->count() >= 2 && $bankCashLines->count() === $codes->count()) {
            return 'internal_transfer';
        }

        $hasBankDebit = $activeLines->contains(
            fn (AccountingDocumentLine $line) => (float) $line->debit > 0 && $this->isBankOrCashCode($this->lineErpCode($line, $accounts))
        );
        $hasReceivableCredit = $activeLines->contains(
            fn (AccountingDocumentLine $line) => (float) $line->credit > 0 && str_starts_with($this->lineErpCode($line, $accounts), '1101')
        );

        if ($hasBankDebit && $hasReceivableCredit) {
            return 'customer_receipt';
        }

        $hasExpenseDebit = $activeLines->contains(
            fn (AccountingDocumentLine $line) => (float) $line->debit > 0 && $this->isExpenseCode($this->lineErpCode($line, $accounts))
        );
        $hasBankCredit = $activeLines->contains(
            fn (AccountingDocumentLine $line) => (float) $line->credit > 0 && $this->isBankOrCashCode($this->lineErpCode($line, $accounts))
        );

        if ($hasExpenseDebit && $hasBankCredit) {
            return 'expense_payment';
        }

        return 'general';
    }

    public function standardizeDescription(AccountingDocumentLine $line, array $taxAccount, string $documentPattern = 'general'): string
    {
        $original = trim((string) ($line->description ?: $line->document?->description ?: ''));
        $party = trim((string) ($line->party?->name ?: ''));
        $project = trim((string) ($line->project?->name ?: ''));
        $documentNumber = trim((string) ($line->document?->number ?: ''));
        $erpCode = (string) ($taxAccount['erp_code'] ?? $line->account?->code ?? '');
        $accountTitle = (string) ($taxAccount['subsidiary_title'] ?: $taxAccount['ledger_title'] ?: '');

        if ($documentPattern === 'internal_transfer' && $this->isBankOrCashCode($erpCode)) {
            return $this->join([
                'انتقال وجه بین حساب‌های نقد و بانک شرکت',
                $accountTitle,
                $documentNumber !== '' ? 'سند ' . $documentNumber : null,
            ]);
        }

        if ($documentPattern === 'customer_receipt') {
            if ($this->isBankOrCashCode($erpCode) && (float) $line->debit > 0) {
                return $this->join([
                    'دریافت از مشتری و واریز به حساب بانکی',
                    $party !== '' ? 'طرف حساب: ' . $party : null,
                    $project !== '' ? 'پروژه: ' . $project : null,
                    $documentNumber !== '' ? 'سند ' . $documentNumber : null,
                ]);
            }

            if (str_starts_with($erpCode, '1101') && (float) $line->credit > 0) {
                return $this->join([
                    'تسویه حساب دریافتنی مشتری',
                    $party !== '' ? 'طرف حساب: ' . $party : null,
                    $documentNumber !== '' ? 'سند ' . $documentNumber : null,
                ]);
            }
        }

        if ($documentPattern === 'expense_payment') {
            if ($this->isExpenseCode($erpCode) && (float) $line->debit > 0) {
                return $this->join([
                    'ثبت هزینه مرتبط با فعالیت شرکت',
                    $accountTitle,
                    $party !== '' ? 'طرف حساب: ' . $party : null,
                    $project !== '' ? 'پروژه: ' . $project : null,
                    $documentNumber !== '' ? 'سند ' . $documentNumber : null,
                ]);
            }

            if ($this->isBankOrCashCode($erpCode) && (float) $line->credit > 0) {
                return $this->join([
                    'پرداخت هزینه از حساب نقد و بانک',
                    $accountTitle,
                    $party !== '' ? 'طرف حساب: ' . $party : null,
                    $documentNumber !== '' ? 'سند ' . $documentNumber : null,
                ]);
            }
        }

        $normalized = $this->normalizeKnownDescription($original, $line, $taxAccount, $party, $project, $documentNumber);
        if ($normalized !== null) {
            return $normalized;
        }

        if ($original === '' || mb_strlen($original) < 10) {
            return $this->contextualFallback($line, $taxAccount, $party, $project, $documentNumber, $erpCode);
        }

        if (mb_strlen($original) < 25) {
            return $this->join([$original, $party !== '' ? 'طرف حساب: ' . $party : null, $project !== '' ? 'پروژه: ' . $project : null]);
        }

        return $original;
    }

    private function resolveReportSubsidiary(?ChartAccount $leaf, ?ChartAccount $subsidiary, ?ChartAccount $ledger): ?ChartAccount
    {
        if ($leaf && $leaf->level === 'detail' && $this->isBankOrCashCode((string) $leaf->code)) {
            // Keep bank/cash detail under ledger 12, but معین = parent subsidiary (بانک/صندوق)
            return $subsidiary ?: $leaf;
        }

        if ($leaf && $leaf->level === 'subsidiary') {
            return $leaf;
        }

        return $subsidiary ?: $leaf;
    }

    private function expenseDetailTitle(string $code, string $title): string
    {
        return match (true) {
            str_starts_with($code, '520101') => 'اجاره دفتر',
            str_starts_with($code, '520107') => 'حمل و نقل',
            str_starts_with($code, '520112') => 'هزینه نرم‌افزار و سایت',
            str_starts_with($code, '520114') => 'اینترنت و تلفن',
            str_starts_with($code, '520113') => 'هزینه ثبت و امور شرکتی',
            str_starts_with($code, '520109') => 'هزینه‌های بانکی',
            str_starts_with($code, '520111') => 'هزینه بنگاه معاملاتی',
            str_starts_with($code, '520103') => 'تنخواه گردان',
            default => $title !== '' ? $title : 'سایر هزینه‌های عملیاتی',
        };
    }

    private function normalizeKnownDescription(
        string $original,
        AccountingDocumentLine $line,
        array $taxAccount,
        string $party,
        string $project,
        string $documentNumber
    ): ?string {
        $accountTitle = (string) ($taxAccount['subsidiary_title'] ?: $taxAccount['ledger_title'] ?: '');
        $erpCode = (string) ($taxAccount['erp_code'] ?? '');

        $map = [
            'هزینه' => 'ثبت هزینه اجرای پروژه خدمات فنی و مهندسی',
            'پرداخت' => 'پرداخت هزینه مرتبط با فعالیت شرکت',
            'پرداخت هزینه' => 'پرداخت هزینه مرتبط با فعالیت شرکت',
            'برداشت' => 'برداشت از حساب نقد و بانک شرکت',
            'واریز' => 'واریز به حساب نقد و بانک شرکت',
            'دریافت' => 'دریافت وجه مرتبط با فعالیت شرکت',
            'دریافت وجه' => 'دریافت وجه مرتبط با فعالیت شرکت',
            'پرداخت خزانه' => 'پرداخت از طریق خزانه و حساب بانکی شرکت',
            'طرف حساب پرداخت' => 'پرداخت به طرف حساب و تسویه بدهی',
            'درآمد فروش' => 'ثبت درآمد فروش کالا و خدمات',
        ];

        if ($original === 'درآمد خزانه' || $original === 'دریافت خزانه') {
            $base = (float) $line->debit > 0
                ? 'دریافت درآمد و واریز به حساب نقد و بانک'
                : 'ثبت گردش مرتبط با درآمد در حساب نقد و بانک';

            return $this->join([
                $base,
                $accountTitle,
                $party !== '' ? 'طرف حساب: ' . $party : null,
                $project !== '' ? 'پروژه: ' . $project : null,
                $documentNumber !== '' ? 'سند ' . $documentNumber : null,
            ]);
        }

        if (isset($map[$original])) {
            // Bank side of treasury payment should not say "پرداخت خزانه" as revenue-like text.
            if ($original === 'پرداخت خزانه' && $this->isBankOrCashCode($erpCode)) {
                $base = (float) $line->credit > 0
                    ? 'پرداخت از حساب نقد و بانک شرکت'
                    : 'واریز به حساب نقد و بانک شرکت';

                return $this->join([
                    $base,
                    $accountTitle,
                    $party !== '' ? 'طرف حساب: ' . $party : null,
                    $documentNumber !== '' ? 'سند ' . $documentNumber : null,
                ]);
            }

            return $this->join([
                $map[$original],
                $accountTitle,
                $party !== '' ? 'طرف حساب: ' . $party : null,
                $project !== '' ? 'پروژه: ' . $project : null,
                $documentNumber !== '' ? 'سند ' . $documentNumber : null,
            ]);
        }

        if (str_starts_with($original, 'سند هزینه مالی')) {
            return $this->join([
                'ثبت هزینه مرتبط با فعالیت شرکت',
                $accountTitle,
                $party !== '' ? 'طرف حساب: ' . $party : null,
                $documentNumber !== '' ? 'سند ' . $documentNumber : null,
            ]);
        }

        if ($original === 'هزینه' || (str_starts_with($original, 'هزینه') && mb_strlen($original) <= 8)) {
            return $this->join([
                'ثبت هزینه اجرای پروژه خدمات فنی و مهندسی',
                $accountTitle,
                $party !== '' ? 'طرف حساب: ' . $party : null,
                $project !== '' ? 'پروژه: ' . $project : null,
                $documentNumber !== '' ? 'سند ' . $documentNumber : null,
            ]);
        }

        if ($this->isBankOrCashCode($erpCode) && in_array($original, ['پرداخت خزانه', 'دریافت خزانه', 'درآمد خزانه'], true)) {
            return $this->join([
                (float) $line->debit > 0 ? 'واریز به حساب نقد و بانک شرکت' : 'پرداخت از حساب نقد و بانک شرکت',
                $accountTitle,
                $party !== '' ? 'طرف حساب: ' . $party : null,
                $documentNumber !== '' ? 'سند ' . $documentNumber : null,
            ]);
        }

        return null;
    }

    private function contextualFallback(
        AccountingDocumentLine $line,
        array $taxAccount,
        string $party,
        string $project,
        string $documentNumber,
        string $erpCode
    ): string {
        $taxTitle = $taxAccount['subsidiary_title'] ?: $taxAccount['ledger_title'];
        $action = match (true) {
            (float) $line->debit > 0 && str_starts_with($erpCode, '4') => 'ثبت درآمد عملیاتی',
            (float) $line->credit > 0 && str_starts_with($erpCode, '4') => 'ثبت درآمد عملیاتی',
            (float) $line->debit > 0 && str_starts_with($erpCode, '5') => 'ثبت هزینه عملیاتی',
            (float) $line->credit > 0 && $this->isBankOrCashCode($erpCode) => 'پرداخت از حساب نقد و بانک',
            (float) $line->debit > 0 && $this->isBankOrCashCode($erpCode) => 'واریز به حساب نقد و بانک',
            (float) $line->debit > 0 && str_starts_with($erpCode, '2101') => 'پرداخت به طرف حساب',
            (float) $line->credit > 0 && str_starts_with($erpCode, '2101') => 'ایجاد بدهی به طرف حساب',
            (float) $line->debit > 0 && str_starts_with($erpCode, '1101') => 'افزایش مطالبات مشتری',
            (float) $line->credit > 0 && str_starts_with($erpCode, '1101') => 'تسویه مطالبات مشتری',
            default => 'ثبت مالی',
        };

        return $this->join([
            $action,
            $taxTitle,
            $party !== '' ? 'طرف حساب: ' . $party : null,
            $project !== '' ? 'پروژه: ' . $project : null,
            $documentNumber !== '' ? 'سند ' . $documentNumber : null,
        ]);
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function join(array $parts): string
    {
        return collect($parts)
            ->filter(fn (?string $part) => $part !== null && trim($part) !== '')
            ->implode(' - ');
    }

    private function lineErpCode(AccountingDocumentLine $line, Collection $accounts): string
    {
        $account = $accounts->get($line->chart_account_id);
        $detailAccount = $line->detail_account_id ? $accounts->get($line->detail_account_id) : null;

        return (string) ($detailAccount?->code ?: $account?->code ?: '');
    }

    private function isBankOrCashCode(string $code): bool
    {
        return str_starts_with($code, '12');
    }

    private function isExpenseCode(string $code): bool
    {
        return str_starts_with($code, '5');
    }

    private function fallbackLedgerCode(string $code): string
    {
        return match (true) {
            str_starts_with($code, '12') => '12',
            str_starts_with($code, '11') => '11',
            str_starts_with($code, '21') || str_starts_with($code, '20') => '21',
            str_starts_with($code, '31') => '31',
            str_starts_with($code, '32') => '32',
            str_starts_with($code, '41') => '41',
            str_starts_with($code, '51') => '51',
            str_starts_with($code, '52') => '52',
            default => substr($code, 0, 2),
        };
    }

    private function fallbackLedgerTitle(string $ledgerCode): string
    {
        return match ($ledgerCode) {
            '12' => 'نقد و بانک',
            '11' => 'دارایی‌های جاری',
            '21' => 'بدهی‌های جاری',
            '31' => 'سرمایه و سود انباشته',
            '32' => 'حساب‌های جاری شرکا',
            '41' => 'درآمد عملیاتی',
            '51' => 'خرید و بهای تمام شده',
            '52' => 'هزینه‌های عملیاتی',
            default => 'سایر حساب‌ها',
        };
    }

    /**
     * @return list<ChartAccount|null>
     */
    private function ancestorChain(?ChartAccount $account, Collection $accounts): array
    {
        $chain = [];
        $current = $account;

        while ($current) {
            $chain[] = $current;
            $current = $current->parent_id ? $accounts->get($current->parent_id) : null;
        }

        return array_reverse($chain);
    }

    private function statementSectionForCode(string $code): string
    {
        return match (true) {
            str_starts_with($code, '1') => 'asset',
            str_starts_with($code, '2') => 'liability',
            str_starts_with($code, '3') => 'equity',
            str_starts_with($code, '4') => 'revenue',
            str_starts_with($code, '5'), str_starts_with($code, '6') => 'expense',
            default => 'other',
        };
    }

    private function isVendorParty(AccountingDocumentLine $line): bool
    {
        if (! $line->relationLoaded('party')) {
            $line->load('party.types');
        }

        return $line->party?->types?->contains(fn ($type) => in_array($type->name, ['vendor', 'contractor'], true)) ?? false;
    }
}
