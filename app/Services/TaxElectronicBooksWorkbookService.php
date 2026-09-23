<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Repositories\FinancialReportRepository;
use App\Support\TaxElectronicBooks\TaxReportPresentation;
use Illuminate\Support\Collection;

class TaxElectronicBooksWorkbookService
{
    public const PERIOD_FROM = '1404/07/01';

    public const PERIOD_TO = '1404/12/29';

    public function __construct(
        private FinancialReportRepository $repository,
        private TaxReportPresentation $presentation,
    ) {
    }

    public function build(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        $lines = $this->repository->postedLineQuery($filters)
            ->with(['document.fiscalYear', 'document.fiscalPeriod', 'account', 'detailAccount', 'party.types', 'project'])
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_documents.number')
            ->orderBy('accounting_document_lines.id')
            ->get();

        $accounts = ChartAccount::query()->get(['id', 'parent_id', 'level', 'code', 'title', 'nature'])->keyBy('id');
        $documentPatterns = $lines
            ->groupBy('accounting_document_id')
            ->map(fn (Collection $group) => $this->presentation->classifyDocumentPattern($group, $accounts));

        $rows = $lines->map(function ($line) use ($accounts, $documentPatterns) {
            $taxAccount = $this->presentation->mapAccount($line, $accounts);
            $originalDescription = trim((string) ($line->description ?: $line->document?->description ?: ''));
            $documentPattern = (string) ($documentPatterns[$line->accounting_document_id] ?? 'general');

            return [
                'line' => $line,
                'document_id' => $line->accounting_document_id,
                'document_number' => $line->document?->number ?: '-',
                'document_pattern' => $documentPattern,
                'date' => gregorianToJalaliDate($line->document?->document_date),
                'party_code' => $line->party?->code ?: '',
                'party_name' => $line->party?->name ?: '',
                'erp_code' => $taxAccount['erp_code'],
                'erp_title' => $taxAccount['erp_title'],
                'ledger_code' => $taxAccount['ledger_code'],
                'ledger_title' => $taxAccount['ledger_title'],
                'subsidiary_code' => $taxAccount['subsidiary_code'],
                'subsidiary_title' => $taxAccount['subsidiary_title'],
                'report_group' => $taxAccount['report_group'],
                'statement_section' => $taxAccount['statement_section'],
                'original_description' => $originalDescription,
                'description' => $this->presentation->standardizeDescription($line, $taxAccount, $documentPattern),
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ];
        })->values();

        $analysis = $this->analyze($rows, $filters, $documentPatterns);

        return [
            'filters' => $filters,
            'analysis' => $analysis,
            'sections' => $this->uiSections($rows, $analysis),
            'tax_export_rows' => $this->exportRows($rows),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function exportRows(Collection $rows): array
    {
        return $rows->values()->map(function (array $row, int $index) {
            return [
                'row_number' => $index + 1,
                'date' => $row['date'],
                'ledger_code' => $row['ledger_code'],
                'ledger_title' => $row['ledger_title'],
                'subsidiary_code' => $row['subsidiary_code'],
                'subsidiary_title' => $row['subsidiary_title'],
                'description' => $row['description'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
            ];
        })->all();
    }

    private function normalizeFilters(array $filters): array
    {
        foreach (['date_from', 'date_to'] as $key) {
            if (! empty($filters[$key])) {
                $filters[$key] = jalaliToGregorianDate($filters[$key]) ?: $filters[$key];
            }
        }

        if (empty($filters['date_from'])) {
            $filters['date_from'] = jalaliToGregorianDate(self::PERIOD_FROM);
        }

        if (empty($filters['date_to'])) {
            $filters['date_to'] = jalaliToGregorianDate(self::PERIOD_TO);
        }

        foreach (['fiscal_year_id', 'branch_id', 'project_id', 'party_id', 'account_id', 'bank_account_id', 'per_page', 'fiscal_period_id'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $filters[$key] = (int) $filters[$key];
            }
        }

        return $filters;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int|string, string>  $documentPatterns
     * @return array<string, mixed>
     */
    private function analyze(Collection $rows, array $filters, Collection $documentPatterns): array
    {
        $totalDebit = (float) $rows->sum('debit');
        $totalCredit = (float) $rows->sum('credit');
        $documentIds = $rows->pluck('document_id')->unique();

        $unbalancedDocuments = $rows
            ->groupBy('document_id')
            ->map(fn (Collection $group) => (float) $group->sum('debit') - (float) $group->sum('credit'))
            ->filter(fn (float $diff) => abs($diff) > 0.01)
            ->count();

        $genericDescriptions = $rows->filter(function (array $row) {
            $text = (string) ($row['original_description'] ?? '');

            return in_array($text, ['هزینه', 'دریافت', 'پرداخت', 'واریز', 'برداشت', 'پرداخت هزینه', 'پرداخت خزانه', 'طرف حساب پرداخت', 'درآمد فروش', 'درآمد خزانه'], true)
                || mb_strlen($text) < 10;
        })->count();

        $generalLedgerDebit = (float) $this->generalLedgerRows($rows)->sum('debit');
        $generalLedgerCredit = (float) $this->generalLedgerRows($rows)->sum('credit');

        $bankInternalTransfers = $documentPatterns->filter(fn (string $pattern) => $pattern === 'internal_transfer')->count();
        $revenueOnBankTransfers = $rows
            ->filter(fn (array $row) => ($row['document_pattern'] ?? '') === 'internal_transfer')
            ->where('statement_section', 'revenue')
            ->count();

        $activityStart = jalaliToGregorianDate(self::PERIOD_FROM);
        $documentsBeforeActivity = $rows
            ->filter(function (array $row) use ($activityStart) {
                $documentDate = $row['line']?->document?->document_date;

                return $activityStart && $documentDate && $documentDate < $activityStart;
            })
            ->pluck('document_id')
            ->unique()
            ->count();

        $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
        $journalGeneralMatch = abs($totalDebit - $generalLedgerDebit) < 0.01 && abs($totalCredit - $generalLedgerCredit) < 0.01;
        $exportReady = $isBalanced
            && $unbalancedDocuments === 0
            && $revenueOnBankTransfers === 0
            && $documentsBeforeActivity === 0;

        $exportStatusMessage = match (true) {
            ! $isBalanced => 'جمع بدهکار و بستانکار برابر نیست.',
            $unbalancedDocuments > 0 => 'اسناد نامتوازن در دوره گزارش وجود دارد.',
            $revenueOnBankTransfers > 0 => 'انتقال بانکی به‌اشتباه به عنوان درآمد طبقه‌بندی شده است.',
            $documentsBeforeActivity > 0 => 'اسناد قبل از تاریخ شروع فعالیت در خروجی وجود دارد.',
            default => 'خروجی برای ارسال به سامانه مالیاتی آماده است.',
        };

        return [
            'period_from' => self::PERIOD_FROM,
            'period_to' => self::PERIOD_TO,
            'fiscal_period' => self::PERIOD_FROM . ' تا ' . self::PERIOD_TO,
            'applied_date_from' => gregorianToJalaliDate($filters['date_from'] ?? null),
            'applied_date_to' => gregorianToJalaliDate($filters['date_to'] ?? null),
            'only_posted_documents' => true,
            'line_count' => $rows->count(),
            'document_count' => $documentIds->count(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance_difference' => $totalDebit - $totalCredit,
            'is_balanced' => $isBalanced,
            'unbalanced_documents' => $unbalancedDocuments,
            'generic_descriptions' => $genericDescriptions,
            'journal_general_match' => $journalGeneralMatch,
            'mapped_account_count' => $rows->pluck('erp_code')->unique()->filter()->count(),
            'bank_internal_transfers' => $bankInternalTransfers,
            'revenue_on_bank_transfers' => $revenueOnBankTransfers,
            'documents_before_activity' => $documentsBeforeActivity,
            'export_ready' => $exportReady,
            'export_status' => $exportReady ? 'آماده خروجی' : 'نیاز به بررسی',
            'export_status_message' => $exportStatusMessage,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function uiSections(Collection $rows, array $analysis): array
    {
        $journalHeaders = [
            'ردیف', 'تاریخ', 'شماره سند', 'کد حساب گزارش', 'عنوان حساب گزارش', 'شرح گزارش', 'شرح اصلی', 'بدهکار', 'بستانکار',
        ];
        $journalColumns = [
            'row_number', 'date', 'document_number', 'subsidiary_code', 'subsidiary_title', 'description', 'original_description', 'debit', 'credit',
        ];

        $journalRows = $rows->values()->map(function (array $row, int $index) {
            return [
                'row_number' => $index + 1,
                'date' => $row['date'],
                'document_number' => $row['document_number'],
                'subsidiary_code' => $row['subsidiary_code'],
                'subsidiary_title' => $row['subsidiary_title'],
                'description' => $row['description'],
                'original_description' => $row['original_description'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
            ];
        });

        return [
            [
                'title' => 'کنترل پیش از خروجی مالیاتی',
                'headers' => ['شاخص', 'مقدار'],
                'columns' => ['metric', 'value'],
                'rows' => [
                    ['metric' => 'دوره فعالیت', 'value' => $analysis['fiscal_period']],
                    ['metric' => 'بازه اعمال‌شده', 'value' => ($analysis['applied_date_from'] ?: '-') . ' تا ' . ($analysis['applied_date_to'] ?: '-')],
                    ['metric' => 'تعداد اسناد', 'value' => $analysis['document_count']],
                    ['metric' => 'تعداد سطرها', 'value' => $analysis['line_count']],
                    ['metric' => 'جمع بدهکار', 'value' => $analysis['total_debit']],
                    ['metric' => 'جمع بستانکار', 'value' => $analysis['total_credit']],
                    ['metric' => 'اختلاف تراز', 'value' => $analysis['balance_difference']],
                    ['metric' => 'وضعیت تراز', 'value' => $analysis['is_balanced'] ? 'متوازن' : 'نامتوازن'],
                    ['metric' => 'تطبیق روزنامه و کل', 'value' => $analysis['journal_general_match'] ? 'بله' : 'خیر'],
                    ['metric' => 'انتقال بانکی داخلی', 'value' => $analysis['bank_internal_transfers']],
                    ['metric' => 'درآمد اشتباه در انتقال بانکی', 'value' => $analysis['revenue_on_bank_transfers']],
                    ['metric' => 'شرح‌های استانداردسازی‌شده', 'value' => $analysis['generic_descriptions']],
                    ['metric' => 'وضعیت خروجی', 'value' => $analysis['export_status']],
                    ['metric' => 'پیام کنترل', 'value' => $analysis['export_status_message']],
                ],
            ],
            [
                'title' => 'دفتر روزنامه الکترونیکی (نمای گزارش)',
                'headers' => $journalHeaders,
                'columns' => $journalColumns,
                'rows' => $journalRows,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function generalLedgerRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['ledger_code'] . '|' . $row['subsidiary_code'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'ledger_code' => $first['ledger_code'],
                    'ledger_title' => $first['ledger_title'],
                    'subsidiary_code' => $first['subsidiary_code'],
                    'subsidiary_title' => $first['subsidiary_title'],
                    'debit' => (float) $group->sum('debit'),
                    'credit' => (float) $group->sum('credit'),
                ];
            })
            ->values();
    }
}
