<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\FinancialTransaction;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\OrganizationUnit;
use App\Models\Party;
use App\Models\Project;
use App\Repositories\FinancialReportRepository;
use App\Support\FinancialReportContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FinancialReportService
{
    public function __construct(
        private FinancialReportRepository $reports
    ) {
    }

    public function catalog(): array
    {
        return [
            [
                'title' => 'صورت‌های مالی',
                'description' => 'تراز آزمایشی، دفاتر، ترازنامه، سود و زیان و صورت جریان وجوه نقد',
                'reports' => [
                    ['key' => 'trial-balance', 'title' => 'تراز آزمایشی', 'description' => 'مانده بدهکار/بستانکار و گردش دوره'],
                    ['key' => 'detailed-trial-balance', 'title' => 'تراز آزمایشی تفصیلی', 'description' => 'تراز در سطح حساب و طرف حساب'],
                    ['key' => 'general-ledger', 'title' => 'دفتر کل', 'description' => 'گردش حساب‌ها با مانده جاری'],
                    ['key' => 'detailed-ledger', 'title' => 'دفتر معین', 'description' => 'ریز اسناد و سطرهای حسابداری'],
                    ['key' => 'balance-sheet', 'title' => 'ترازنامه', 'description' => 'دارایی، بدهی و حقوق صاحبان سرمایه'],
                    ['key' => 'income-statement', 'title' => 'صورت سود و زیان', 'description' => 'درآمد، بهای تمام‌شده و هزینه‌ها'],
                    ['key' => 'cash-flow-statement', 'title' => 'صورت جریان وجوه نقد', 'description' => 'جریان نقد عملیاتی، سرمایه‌گذاری و تأمین مالی'],
                    ['key' => 'changes-in-equity', 'title' => 'صورت تغییرات حقوق صاحبان سهام', 'description' => 'مانده اول دوره، سود و زیان و تغییرات سرمایه'],
                ],
            ],
            [
                'title' => 'دریافتنی و پرداختنی',
                'description' => 'مانده مشتریان و تأمین‌کنندگان، صورتحساب و فهرست اسناد باز',
                'reports' => [
                    ['key' => 'accounts-receivable-aging', 'title' => 'سن مطالبات', 'description' => 'تحلیل مانده مشتریان بر اساس سررسید'],
                    ['key' => 'accounts-payable-aging', 'title' => 'سن بدهی‌ها', 'description' => 'تحلیل مانده تأمین‌کنندگان بر اساس سررسید'],
                    ['key' => 'customer-statement', 'title' => 'صورتحساب مشتری', 'description' => 'ریز گردش طرف حساب مشتری'],
                    ['key' => 'supplier-statement', 'title' => 'صورتحساب تأمین‌کننده', 'description' => 'ریز گردش طرف حساب تأمین‌کننده'],
                    ['key' => 'outstanding-invoices', 'title' => 'فاکتورهای باز', 'description' => 'فاکتورهای تأییدشده و تسویه‌نشده'],
                    ['key' => 'overdue-invoices', 'title' => 'فاکتورهای سررسید گذشته', 'description' => 'فاکتورهای باز با سررسید فرضی ۳۰ روزه'],
                ],
            ],
            [
                'title' => 'نقد و بانک',
                'description' => 'دفتر نقدی، بانک، مغایرت‌گیری و جریان نقد دوره‌ای',
                'reports' => [
                    ['key' => 'cash-book', 'title' => 'دفتر صندوق', 'description' => 'گردش صندوق‌های نقدی'],
                    ['key' => 'bank-statement', 'title' => 'صورتحساب بانک', 'description' => 'موجودی و گردش حساب‌های بانکی'],
                    ['key' => 'bank-reconciliation', 'title' => 'گزارش مغایرت بانکی', 'description' => 'تطبیق دفتر بانک با تراکنش‌های ثبت‌شده'],
                    ['key' => 'cash-flow-by-period', 'title' => 'جریان نقد بر اساس دوره', 'description' => 'جریان نقد به‌تفکیک ماه/دوره'],
                ],
            ],
            [
                'title' => 'مالیات',
                'description' => 'مالیات فروش، خرید، VAT و سطرهای مالیاتی',
                'reports' => [
                    ['key' => 'sales-tax', 'title' => 'گزارش مالیات فروش', 'description' => 'مالیات فروش فاکتورهای فروش'],
                    ['key' => 'purchase-tax', 'title' => 'گزارش مالیات خرید', 'description' => 'مالیات خرید فاکتورهای خرید'],
                    ['key' => 'vat-summary', 'title' => 'خلاصه VAT', 'description' => 'جمع فروش، خرید، مالیات و مانده قابل پرداخت'],
                    ['key' => 'tax-transactions', 'title' => 'گردش مالیاتی', 'description' => 'سطرهای سند مرتبط با حساب‌های مالیاتی'],
                ],
            ],
            [
                'title' => 'تحلیل مدیریت',
                'description' => 'تحلیل هزینه، درآمد، پروژه، مشتری، مرکز هزینه و واحد سازمانی',
                'reports' => [
                    ['key' => 'expense-analysis-by-account', 'title' => 'تحلیل هزینه به تفکیک حساب', 'description' => 'هزینه‌ها بر اساس حساب‌های هزینه'],
                    ['key' => 'revenue-analysis-by-account', 'title' => 'تحلیل درآمد به تفکیک حساب', 'description' => 'درآمدها بر اساس حساب‌های درآمد'],
                    ['key' => 'profitability-by-project', 'title' => 'سودآوری پروژه', 'description' => 'سود ناخالص هر پروژه'],
                    ['key' => 'profitability-by-customer', 'title' => 'سودآوری مشتری', 'description' => 'سود ناخالص بر اساس طرف حساب مشتری'],
                    ['key' => 'cost-center-report', 'title' => 'گزارش مرکز هزینه', 'description' => 'گردش و مانده مراکز هزینه'],
                    ['key' => 'department-financial-performance', 'title' => 'عملکرد مالی واحدها', 'description' => 'تحلیل مالی واحدهای سازمانی'],
                ],
            ],
            [
                'title' => 'سند و حسابرسی',
                'description' => 'ثبت سند، گزارش‌های بازه‌ای و لاگ حسابرسی',
                'reports' => [
                    ['key' => 'journal-entries', 'title' => 'گزارش اسناد حسابداری', 'description' => 'فهرست اسناد و جمع سطرها'],
                    ['key' => 'journal-entries-by-date', 'title' => 'اسناد به تفکیک تاریخ', 'description' => 'گروه‌بندی اسناد بر اساس تاریخ'],
                    ['key' => 'journal-entries-by-account', 'title' => 'اسناد به تفکیک حساب', 'description' => 'گردش سندی هر حساب'],
                    ['key' => 'audit-trail', 'title' => 'ردیاب حسابرسی', 'description' => 'لاگ رخدادهای حسابرسی'],
                    ['key' => 'deleted-modified-transactions', 'title' => 'اسناد حذف/ویرایش‌شده', 'description' => 'اسناد و تراکنش‌های تغییر یافته یا حذف شده'],
                ],
            ],
        ];
    }

    public function report(string $key, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $context = new FinancialReportContext($key);

        return match ($key) {
            'trial-balance' => $this->trialBalanceReport($filters, $context),
            'detailed-trial-balance' => $this->detailedTrialBalanceReport($filters, $context),
            'general-ledger' => $this->generalLedgerReport($filters, $context),
            'detailed-ledger' => $this->detailedLedgerReport($filters, $context),
            'balance-sheet' => $this->balanceSheetReport($filters, $context),
            'income-statement' => $this->incomeStatementReport($filters, $context),
            'cash-flow-statement' => $this->cashFlowStatementReport($filters, $context),
            'changes-in-equity' => $this->changesInEquityReport($filters, $context),
            'accounts-receivable-aging' => $this->agingReport('customer', $filters, $context),
            'accounts-payable-aging' => $this->agingReport('supplier', $filters, $context),
            'customer-statement' => $this->partyStatementReport('customer', $filters, $context),
            'supplier-statement' => $this->partyStatementReport('supplier', $filters, $context),
            'outstanding-invoices' => $this->outstandingInvoicesReport($filters, $context),
            'overdue-invoices' => $this->overdueInvoicesReport($filters, $context),
            'cash-book' => $this->cashBookReport($filters, $context),
            'bank-book' => $this->bankBookReport($filters, $context),
            'bank-statement' => $this->bankBookReport($filters, $context),
            'bank-reconciliation' => $this->bankReconciliationReport($filters, $context),
            'cash-flow-by-period' => $this->cashFlowByPeriodReport($filters, $context),
            'sales-tax' => $this->salesTaxReport($filters, $context),
            'purchase-tax' => $this->purchaseTaxReport($filters, $context),
            'vat-summary' => $this->vatSummaryReport($filters, $context),
            'tax-transactions' => $this->taxTransactionsReport($filters, $context),
            'expense-analysis-by-account' => $this->expenseAnalysisReport($filters, $context),
            'revenue-analysis-by-account' => $this->revenueAnalysisReport($filters, $context),
            'profitability-by-project' => $this->profitabilityByProjectReport($filters, $context),
            'profitability-by-customer' => $this->profitabilityByCustomerReport($filters, $context),
            'cost-center-report' => $this->costCenterReport($filters, $context),
            'department-financial-performance' => $this->departmentFinancialPerformanceReport($filters, $context),
            'journal-entries' => $this->journalEntriesReport($filters, $context),
            'journal-entries-by-date' => $this->journalEntriesByDateReport($filters, $context),
            'journal-entries-by-account' => $this->journalEntriesByAccountReport($filters, $context),
            'audit-trail' => $this->auditTrailReport($filters, $context),
            'deleted-modified-transactions' => $this->deletedModifiedTransactionsReport($filters, $context),
            default => $this->trialBalanceReport($filters, $context),
        };
    }

    public function generalLedger(array $filters = []): Collection
    {
        return collect($this->report('general-ledger', $filters)['sections'][0]['rows'] ?? []);
    }

    public function trialBalance(array $filters = []): Collection
    {
        return collect($this->report('trial-balance', $filters)['sections'][0]['rows'] ?? []);
    }

    public function accountStatementSummary(ChartAccount $account, array $filters = []): array
    {
        $statement = $this->partyStatementByAccount($account->id, null, $filters);

        return [
            'account' => $account,
            'lines' => collect($statement['sections'][0]['rows'] ?? []),
            'debit' => $statement['summary']['debit'] ?? 0,
            'credit' => $statement['summary']['credit'] ?? 0,
            'balance' => $statement['summary']['balance'] ?? 0,
            'balance_type' => $this->balanceType(($statement['summary']['balance'] ?? 0)),
        ];
    }

    public function partyStatementSummaries(?int $accountId = null, ?int $partyId = null, array $filters = []): Collection
    {
        $report = $this->partyStatementByAccount($accountId, $partyId, $filters);

        return collect($report['sections'][0]['rows'] ?? []);
    }

    public function balanceSheet(array $filters = []): array
    {
        return $this->report('balance-sheet', $filters)['summary'];
    }

    public function profitAndLoss(array $filters = []): array
    {
        return $this->report('income-statement', $filters)['summary'];
    }

    public function aging(string $side = 'customer'): Collection
    {
        $report = $this->report($side === 'supplier' ? 'accounts-payable-aging' : 'accounts-receivable-aging');

        return collect($report['sections'][0]['rows'] ?? []);
    }

    public function bankTransactions(BankAccount $bankAccount, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $baseQuery = $this->postedLineQuery($filters)
            ->where('bank_account_id', $bankAccount->id)
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id');

        if (! empty($filters['date_from'])) {
            $openingFilters = $filters;
            $openingFilters['date_to'] = \Carbon\Carbon::parse($openingFilters['date_from'])->subDay()->toDateString();
            unset($openingFilters['date_from']);

            $openingLines = $this->postedLineQuery($openingFilters)
                ->where('bank_account_id', $bankAccount->id)
                ->orderBy('accounting_documents.document_date')
                ->orderBy('accounting_document_lines.id')
                ->get();
        } else {
            $openingLines = collect();
        }

        $startingBalance = (float) $bankAccount->opening_balance + (float) $openingLines->sum(fn ($line) => (float) $line->debit - (float) $line->credit);
        $periodDebit = (float) $baseQuery->sum('debit');
        $periodCredit = (float) $baseQuery->sum('credit');
        $rows = $baseQuery->get()->map(function ($line) use (&$startingBalance) {
            $startingBalance += (float) $line->debit - (float) $line->credit;
            $row = $this->lineRow($line);
            $row['running_balance'] = $startingBalance;

            return $row;
        })->values();

        return $this->reportPayload(
            key: 'bank-transactions',
            title: 'تراکنش‌های بانکی',
            subtitle: $bankAccount->code . ' - ' . $bankAccount->bank_name,
            filters: $filters,
            summary: [
                'opening_balance' => (float) $bankAccount->opening_balance + (float) $openingLines->sum(fn ($line) => (float) $line->debit - (float) $line->credit),
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_balance' => $startingBalance,
                'line_count' => $rows->count(),
            ],
            sections: [
                [
                    'title' => 'گردش حساب بانکی',
                    'headers' => ['تاریخ', 'شماره سند', 'حساب', 'طرف حساب', 'پروژه', 'شرح', 'بدهکار', 'بستانکار', 'مانده جاری'],
                    'rows' => $rows,
                    'columns' => ['date', 'document_number', 'account', 'party', 'project', 'description', 'debit', 'credit', 'running_balance'],
                ],
            ],
        );
    }

    public function reportDefinitions(string $key): ?array
    {
        foreach ($this->catalog() as $category) {
            foreach ($category['reports'] as $report) {
                if ($report['key'] === $key) {
                    return $report;
                }
            }
        }

        return null;
    }

    private function trialBalanceReport(array $filters, FinancialReportContext $context): array
    {
        $lines = $this->postedLines($filters);
        $openingLines = $this->openingLines($filters);
        $grouped = $this->accountBalances($lines, $openingLines);

        $rows = $grouped->sortBy(fn ($row) => $row['code'])->values();
        $summary = [
            'opening_debit' => (float) $rows->sum('opening_debit'),
            'opening_credit' => (float) $rows->sum('opening_credit'),
            'period_debit' => (float) $rows->sum('period_debit'),
            'period_credit' => (float) $rows->sum('period_credit'),
            'closing_debit' => (float) $rows->sum('closing_debit'),
            'closing_credit' => (float) $rows->sum('closing_credit'),
        ];

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'تراز آزمایشی',
            subtitle: 'مانده بدهکار/بستانکار و گردش دوره',
            filters: $filters,
            summary: $summary,
            sections: [
                [
                    'title' => 'تراز حساب‌ها',
                    'headers' => ['کد', 'عنوان', 'مانده افتتاحیه بدهکار', 'مانده افتتاحیه بستانکار', 'گردش بدهکار', 'گردش بستانکار', 'مانده نهایی بدهکار', 'مانده نهایی بستانکار'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function detailedTrialBalanceReport(array $filters, FinancialReportContext $context): array
    {
        $base = $this->trialBalanceReport($filters, $context);
        $detailRows = $this->postedLines($filters)
            ->sortBy(fn ($line) => [$line->account?->code ?? '', $line->document?->document_date?->format('Y-m-d') ?? '', $line->id])
            ->map(fn ($line) => $this->lineRow($line))
            ->values();

        $base['sections'][] = [
            'title' => 'ریز اسناد',
            'headers' => ['تاریخ', 'شماره سند', 'حساب', 'طرف حساب', 'پروژه', 'مرکز هزینه', 'شرح', 'بدهکار', 'بستانکار', 'مانده جاری'],
            'rows' => $detailRows,
        ];

        return $base;
    }

    private function generalLedgerReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->ledgerRows($filters);
        $summary = [
            'opening' => (float) $rows->sum('opening_balance'),
            'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'),
            'closing' => (float) $rows->sum('closing_balance'),
        ];

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'دفتر کل',
            subtitle: 'گردش حساب‌ها با مانده افتتاحیه و مانده جاری',
            filters: $filters,
            summary: $summary,
            sections: [
                [
                    'title' => 'گردش حساب‌ها',
                    'headers' => ['تاریخ', 'شماره سند', 'حساب', 'شرح', 'بدهکار', 'بستانکار', 'مانده جاری'],
                    'rows' => $this->paginateCollection($rows, (int) ($filters['per_page'] ?? 25)),
                ],
            ],
        );
    }

    private function detailedLedgerReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->ledgerRows($filters, true);
        $grouped = $rows->groupBy('account_code')->map(function (Collection $items, string $accountCode) {
            $first = $items->first();
            return [
                'title' => $first['account_title'] ?? $accountCode,
                'rows' => $items->values(),
                'debit' => (float) $items->sum('debit'),
                'credit' => (float) $items->sum('credit'),
                'balance' => (float) $items->last()['closing_balance'],
            ];
        })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'دفتر معین',
            subtitle: 'ریز اسناد به تفکیک حساب',
            filters: $filters,
            summary: [
                'accounts_count' => $grouped->count(),
                'debit' => (float) $rows->sum('debit'),
                'credit' => (float) $rows->sum('credit'),
            ],
            sections: $grouped->map(fn ($group) => [
                'title' => $group['title'],
                'headers' => ['تاریخ', 'شماره سند', 'شرح', 'بدهکار', 'بستانکار', 'مانده جاری'],
                'rows' => $group['rows'],
                'totals' => ['debit' => $group['debit'], 'credit' => $group['credit'], 'balance' => $group['balance']],
            ])->all(),
        );
    }

    private function balanceSheetReport(array $filters, FinancialReportContext $context): array
    {
        $balances = $this->accountBalances($this->postedLines($filters), $this->openingLines($filters));
        $assets = $balances->filter(fn ($row) => str_starts_with((string) $row['code'], '1'));
        $liabilities = $balances->filter(fn ($row) => str_starts_with((string) $row['code'], '2'));
        $equity = $balances->filter(fn ($row) => str_starts_with((string) $row['code'], '3'));

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'ترازنامه',
            subtitle: 'دارایی، بدهی و حقوق صاحبان سهام',
            filters: $filters,
            summary: [
                'assets' => (float) $assets->sum('closing_debit'),
                'liabilities' => (float) $liabilities->sum('closing_credit'),
                'equity' => (float) $equity->sum('closing_credit'),
            ],
            sections: [
                [
                    'title' => 'دارایی‌ها',
                    'headers' => ['کد', 'عنوان', 'مانده'],
                    'rows' => $assets->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'amount' => max($row['closing_debit'] - $row['closing_credit'], 0),
                    ])->values(),
                ],
                [
                    'title' => 'بدهی‌ها',
                    'headers' => ['کد', 'عنوان', 'مانده'],
                    'rows' => $liabilities->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'amount' => max($row['closing_credit'] - $row['closing_debit'], 0),
                    ])->values(),
                ],
                [
                    'title' => 'حقوق صاحبان سهام',
                    'headers' => ['کد', 'عنوان', 'مانده'],
                    'rows' => $equity->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'amount' => max($row['closing_credit'] - $row['closing_debit'], 0),
                    ])->values(),
                ],
            ],
        );
    }

    private function incomeStatementReport(array $filters, FinancialReportContext $context): array
    {
        $balances = $this->accountBalances($this->postedLines($filters), $this->openingLines($filters));
        $revenue = $balances->filter(fn ($row) => str_starts_with((string) $row['code'], '4'));
        $cogs = $balances->filter(fn ($row) => str_starts_with((string) $row['code'], '5'));
        $expenses = $balances->filter(fn ($row) => str_starts_with((string) $row['code'], '6'));

        $income = (float) $revenue->sum(fn ($row) => $row['closing_credit'] - $row['closing_debit']);
        $costOfSales = (float) $cogs->sum(fn ($row) => $row['closing_debit'] - $row['closing_credit']);
        $operatingExpenses = (float) $expenses->sum(fn ($row) => $row['closing_debit'] - $row['closing_credit']);

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'صورت سود و زیان',
            subtitle: 'درآمد، بهای تمام‌شده و هزینه‌ها',
            filters: $filters,
            summary: [
                'revenue' => $income,
                'cost_of_sales' => $costOfSales,
                'expenses' => $operatingExpenses,
                'net_profit' => $income - $costOfSales - $operatingExpenses,
            ],
            sections: [
                [
                    'title' => 'درآمدها',
                    'headers' => ['کد', 'عنوان', 'مبلغ'],
                    'rows' => $revenue->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'amount' => max($row['closing_credit'] - $row['closing_debit'], 0),
                    ])->values(),
                ],
                [
                    'title' => 'بهای تمام‌شده و هزینه‌ها',
                    'headers' => ['کد', 'عنوان', 'مبلغ'],
                    'rows' => $cogs->merge($expenses)->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'amount' => max($row['closing_debit'] - $row['closing_credit'], 0),
                    ])->values(),
                ],
            ],
        );
    }

    private function cashFlowStatementReport(array $filters, FinancialReportContext $context): array
    {
        $cashAccounts = $this->cashAccountIds();
        $rows = $this->postedLines($filters)->whereIn('chart_account_id', $cashAccounts)->get();
        $opening = $this->openingLines($filters)->whereIn('chart_account_id', $cashAccounts)->get();
        $movement = $this->accountBalances($rows, $opening);

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'صورت جریان وجوه نقد',
            subtitle: 'بر اساس حساب‌های نقد و بانک',
            filters: $filters,
            summary: [
                'opening_cash' => (float) $movement->sum('opening_net'),
                'period_cash_in' => (float) $movement->sum('period_debit'),
                'period_cash_out' => (float) $movement->sum('period_credit'),
                'closing_cash' => (float) $movement->sum('closing_net'),
            ],
            sections: [
                [
                    'title' => 'حساب‌های نقدی',
                    'headers' => ['کد', 'عنوان', 'افتتاحیه', 'بدهکار', 'بستانکار', 'پایان دوره'],
                    'rows' => $movement->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'opening' => $row['opening_net'],
                        'debit' => $row['period_debit'],
                        'credit' => $row['period_credit'],
                        'closing' => $row['closing_net'],
                    ])->values(),
                ],
            ],
        );
    }

    private function changesInEquityReport(array $filters, FinancialReportContext $context): array
    {
        $balances = $this->accountBalances($this->postedLines($filters), $this->openingLines($filters))
            ->filter(fn ($row) => str_starts_with((string) $row['code'], '3'));

        $opening = (float) $balances->sum('opening_credit') - (float) $balances->sum('opening_debit');
        $movement = (float) $balances->sum('period_credit') - (float) $balances->sum('period_debit');
        $closing = (float) $balances->sum('closing_credit') - (float) $balances->sum('closing_debit');

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'صورت تغییرات حقوق صاحبان سهام',
            subtitle: 'مانده افتتاحیه، سود و زیان و مانده پایان دوره',
            filters: $filters,
            summary: [
                'opening_equity' => $opening,
                'net_change' => $movement,
                'closing_equity' => $closing,
            ],
            sections: [
                [
                    'title' => 'حساب‌های سرمایه و سود انباشته',
                    'headers' => ['کد', 'عنوان', 'افتتاحیه', 'گردش دوره', 'پایان دوره'],
                    'rows' => $balances->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'opening' => $row['opening_credit'] - $row['opening_debit'],
                        'movement' => $row['period_credit'] - $row['period_debit'],
                        'closing' => $row['closing_credit'] - $row['closing_debit'],
                    ])->values(),
                ],
            ],
        );
    }

    private function agingReport(string $side, array $filters, FinancialReportContext $context): array
    {
        $accountId = $side === 'supplier' ? $this->accountIdLike('2101') : $this->accountIdLike('1101');
        $rows = $this->partyBalances($filters, $accountId)->map(function ($row) use ($side) {
            $days = $row['last_date'] ? now()->startOfDay()->diffInDays($row['last_date']) : 0;
            return [
                'party_id' => $row['party']->id,
                'party' => $row['party'],
                'code' => $row['party']->code,
                'name' => $row['party']->name,
                'balance' => max($side === 'supplier' ? $row['balance_credit'] - $row['balance_debit'] : $row['balance_debit'] - $row['balance_credit'], 0),
                'days' => $days,
                'bucket' => $this->agingBucket($days),
            ];
        })->sortBy('name')->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: $side === 'supplier' ? 'سن بدهی‌ها' : 'سن مطالبات',
            subtitle: 'طبقه‌بندی مانده طرف حساب بر اساس تاریخ آخرین گردش',
            filters: $filters,
            summary: [
                'parties' => $rows->count(),
                'balance' => (float) $rows->sum('balance'),
            ],
            sections: [
                [
                    'title' => $side === 'supplier' ? 'تأمین‌کنندگان' : 'مشتریان',
                    'headers' => ['کد', 'عنوان', 'مانده', 'روزهای گذشته', 'بازه سنی'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function partyStatementReport(string $side, array $filters, FinancialReportContext $context): array
    {
        return $this->partyStatementByAccount($side === 'supplier' ? $this->accountIdLike('2101') : $this->accountIdLike('1101'), $filters['party_id'] ?? null, $filters, $context);
    }

    private function partyStatementByAccount(?int $accountId, ?int $partyId, array $filters, ?FinancialReportContext $context = null): array
    {
        $accountIds = $accountId ? $this->accountAndDescendantIds($accountId) : null;
        $lines = $this->postedLines($filters)
            ->when($accountIds, fn (Collection $lines) => $lines->whereIn('chart_account_id', $accountIds))
            ->when($partyId, fn (Collection $lines) => $lines->where('party_id', $partyId))
            ->sortBy(fn ($line) => $line->document?->document_date . '|' . $line->id);

        $parties = $lines->groupBy('party_id')->map(function (Collection $group) {
            $running = 0;
            $party = $group->first()?->party;
            $rows = $group->map(function ($line) use (&$running) {
                $running += (float) $line->debit - (float) $line->credit;

                return [
                    'date' => gregorianToJalaliDate($line->document?->document_date),
                    'document_number' => $line->document?->number,
                    'account' => trim(($line->account?->code ?: '') . ' - ' . ($line->account?->title ?: '')),
                    'description' => $line->description ?: $line->document?->description ?: '-',
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'running_balance' => $running,
                ];
            })->values();

            $debit = (float) $group->sum('debit');
            $credit = (float) $group->sum('credit');
            $balance = $debit - $credit;

            return [
                'party_id' => $party?->id,
                'party_name' => $party?->name ?: '-',
                'party_code' => $party?->code ?: '-',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'balance_type' => $this->balanceType($balance),
                'lines' => $rows,
            ];
        })->sortBy('party_name')->values();

        $payload = [
            'title' => $partyId ? ($parties->first()['party_name'] ?? 'صورتحساب') : 'صورتحساب اشخاص و شرکت‌ها',
            'subtitle' => 'گردش بدهکار و بستانکار طرف حساب‌ها',
            'filters' => $filters,
            'summary' => [
                'debit' => (float) $parties->sum('debit'),
                'credit' => (float) $parties->sum('credit'),
                'balance' => (float) $parties->sum('balance'),
            ],
            'sections' => [
                [
                    'title' => 'طرف حساب‌ها',
                    'headers' => ['کد', 'نام', 'جمع بدهکار', 'جمع بستانکار', 'مانده', 'ماهیت'],
                    'rows' => $parties,
                ],
            ],
        ];

        return $context ? $this->reportPayload(
            key: $context->reportKey,
            title: $payload['title'],
            subtitle: $payload['subtitle'],
            filters: $filters,
            summary: $payload['summary'],
            sections: $payload['sections'],
        ) : $payload;
    }

    private function outstandingInvoicesReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->invoiceRows($filters)
            ->where('status', 'confirmed')
            ->whereNull('settled_at')
            ->map(fn ($invoice) => $this->invoiceRow($invoice))
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'فاکتورهای باز',
            subtitle: 'فاکتورهای تأییدشده و تسویه‌نشده',
            filters: $filters,
            summary: [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('outstanding_balance'),
            ],
            sections: [
                [
                    'title' => 'فاکتورهای باز',
                    'headers' => ['شماره', 'تاریخ', 'طرف حساب', 'نوع', 'مبلغ', 'وضعیت', 'مانده باز'],
                    'rows' => $this->paginateCollection($rows, (int) ($filters['per_page'] ?? 25)),
                ],
            ],
        );
    }

    private function overdueInvoicesReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->invoiceRows($filters)
            ->where('status', 'confirmed')
            ->whereNull('settled_at')
            ->filter(fn ($invoice) => $invoice->invoice_date && now()->greaterThan($invoice->invoice_date->copy()->addDays(30)))
            ->map(fn ($invoice) => $this->invoiceRow($invoice))
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'فاکتورهای سررسید گذشته',
            subtitle: 'فاکتورهای باز با فرض سررسید ۳۰ روزه',
            filters: $filters,
            summary: [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('outstanding_balance'),
            ],
            sections: [
                [
                    'title' => 'فاکتورهای معوق',
                    'headers' => ['شماره', 'تاریخ', 'طرف حساب', 'نوع', 'مبلغ', 'روزهای تأخیر', 'مانده باز'],
                    'rows' => $rows->map(function ($row) {
                        $row['days_overdue'] = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($row['invoice_date']));
                        return $row;
                    }),
                ],
            ],
        );
    }

    private function cashBookReport(array $filters, FinancialReportContext $context): array
    {
        return $this->cashOrBankReport('cashbox_id', 'دفتر صندوق', 'گردش صندوق‌های نقدی', $filters, $context);
    }

    private function bankBookReport(array $filters, FinancialReportContext $context): array
    {
        return $this->cashOrBankReport('bank_account_id', 'دفتر بانک', 'گردش حساب‌های بانکی', $filters, $context);
    }

    private function bankReconciliationReport(array $filters, FinancialReportContext $context): array
    {
        $rows = BankAccount::query()
            ->with('account')
            ->when(! empty($filters['bank_account_id']), fn ($query) => $query->whereKey($filters['bank_account_id']))
            ->get()
            ->map(function (BankAccount $bankAccount) use ($filters) {
            $query = $this->postedLines($filters)->where('bank_account_id', $bankAccount->id);
            $journalDebit = (float) $query->sum('debit');
            $journalCredit = (float) $query->sum('credit');
            $statementBalance = (float) $bankAccount->opening_balance + $journalDebit - $journalCredit;

            return [
                'code' => $bankAccount->code,
                'bank_name' => $bankAccount->bank_name,
                'account_number' => $bankAccount->account_number,
                'opening_balance' => (float) $bankAccount->opening_balance,
                'journal_debit' => $journalDebit,
                'journal_credit' => $journalCredit,
                'statement_balance' => $statementBalance,
                'variance' => null,
            ];
            })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'گزارش مغایرت بانکی',
            subtitle: 'تطبیق مانده دفتری حساب بانک با گردش ثبت‌شده',
            filters: $filters,
            summary: [
                'accounts' => $rows->count(),
                'variance' => (float) $rows->sum('variance'),
            ],
            sections: [
                [
                    'title' => 'حساب‌های بانکی',
                    'headers' => ['کد', 'بانک', 'شماره حساب', 'افتتاحیه', 'بدهکار', 'بستانکار', 'مانده دفتری', 'مغایرت'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function cashFlowByPeriodReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)
            ->map(function ($line) {
                $period = $line->document?->document_date?->format('Y-m') ?: '-';

                return [
                    'period' => $period,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ];
            })
            ->groupBy('period')
            ->map(function (Collection $group, string $period) {
                return [
                    'period' => $period,
                    'debit' => (float) $group->sum('debit'),
                    'credit' => (float) $group->sum('credit'),
                    'net_cash' => (float) $group->sum('debit') - (float) $group->sum('credit'),
                ];
            })
            ->sortBy('period')
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'جریان نقد بر اساس دوره',
            subtitle: 'گردش نقدی به تفکیک بازه زمانی',
            filters: $filters,
            summary: [
                'periods' => $rows->count(),
                'net_cash' => (float) $rows->sum('net_cash'),
            ],
            sections: [
                [
                    'title' => 'دوره‌ها',
                    'headers' => ['دوره', 'بدهکار', 'بستانکار', 'جریان خالص'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function salesTaxReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->invoiceRows($filters)->where('direction', 'sale')->map(fn ($invoice) => [
            'number' => $invoice->number,
            'date' => gregorianToJalaliDate($invoice->invoice_date),
            'party' => $invoice->party?->name ?: '-',
            'taxable_amount' => (float) $invoice->subtotal - (float) $invoice->discount_amount,
            'tax_amount' => (float) $invoice->tax_amount,
            'total_amount' => (float) $invoice->total_amount,
        ])->values();

        return $this->taxReportPayload($context, 'گزارش مالیات فروش', 'مالیات فروش فاکتورهای فروش', $filters, $rows);
    }

    private function purchaseTaxReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->invoiceRows($filters)->where('direction', 'purchase')->map(fn ($invoice) => [
            'number' => $invoice->number,
            'date' => gregorianToJalaliDate($invoice->invoice_date),
            'party' => $invoice->party?->name ?: '-',
            'taxable_amount' => (float) $invoice->subtotal - (float) $invoice->discount_amount,
            'tax_amount' => (float) $invoice->tax_amount,
            'total_amount' => (float) $invoice->total_amount,
        ])->values();

        return $this->taxReportPayload($context, 'گزارش مالیات خرید', 'مالیات خرید فاکتورهای خرید', $filters, $rows);
    }

    private function vatSummaryReport(array $filters, FinancialReportContext $context): array
    {
        $sales = $this->invoiceRows($filters)->where('direction', 'sale');
        $purchases = $this->invoiceRows($filters)->where('direction', 'purchase');

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'خلاصه VAT',
            subtitle: 'جمع فروش، خرید، مالیات و مانده قابل پرداخت',
            filters: $filters,
            summary: [
                'sales_tax' => (float) $sales->sum('tax_amount'),
                'purchase_tax' => (float) $purchases->sum('tax_amount'),
                'net_vat' => (float) $sales->sum('tax_amount') - (float) $purchases->sum('tax_amount'),
            ],
            sections: [
                [
                    'title' => 'فروش',
                    'headers' => ['شماره', 'مبلغ مشمول', 'مالیات'],
                    'rows' => $sales->map(fn ($invoice) => [
                        'number' => $invoice->number,
                        'taxable_amount' => (float) $invoice->subtotal - (float) $invoice->discount_amount,
                        'tax_amount' => (float) $invoice->tax_amount,
                    ])->values(),
                ],
                [
                    'title' => 'خرید',
                    'headers' => ['شماره', 'مبلغ مشمول', 'مالیات'],
                    'rows' => $purchases->map(fn ($invoice) => [
                        'number' => $invoice->number,
                        'taxable_amount' => (float) $invoice->subtotal - (float) $invoice->discount_amount,
                        'tax_amount' => (float) $invoice->tax_amount,
                    ])->values(),
                ],
            ],
        );
    }

    private function taxTransactionsReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)
            ->filter(fn ($line) => $line->account && (str_starts_with($line->account->code, '21') || str_starts_with($line->account->code, '11')))
            ->map(fn ($line) => $this->lineRow($line))
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'گردش مالیاتی',
            subtitle: 'سطرهای سند مرتبط با حساب‌های مالیاتی',
            filters: $filters,
            summary: [
                'rows' => $rows->count(),
            ],
            sections: [
                [
                    'title' => 'سطرهای مالیاتی',
                    'headers' => ['تاریخ', 'سند', 'حساب', 'شرح', 'بدهکار', 'بستانکار'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function expenseAnalysisReport(array $filters, FinancialReportContext $context): array
    {
        return $this->analysisByAccountReport('5', 'تحلیل هزینه به تفکیک حساب', 'هزینه‌ها بر اساس حساب', $filters, $context);
    }

    private function revenueAnalysisReport(array $filters, FinancialReportContext $context): array
    {
        return $this->analysisByAccountReport('4', 'تحلیل درآمد به تفکیک حساب', 'درآمدها بر اساس حساب', $filters, $context);
    }

    private function profitabilityByProjectReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)
            ->filter(fn ($line) => ! is_null($line->project_id))
            ->groupBy('project_id')
            ->map(function (Collection $group) {
                $project = $group->first()?->project;
                $debit = (float) $group->sum('debit');
                $credit = (float) $group->sum('credit');

                return [
                    'project' => $project?->name ?: '-',
                    'revenue' => max($credit - $debit, 0),
                    'cost' => max($debit - $credit, 0),
                    'profit' => $credit - $debit,
                ];
            })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'سودآوری پروژه',
            subtitle: 'تحلیل سود ناخالص پروژه‌ها',
            filters: $filters,
            summary: ['projects' => $rows->count(), 'profit' => (float) $rows->sum('profit')],
            sections: [
                [
                    'title' => 'پروژه‌ها',
                    'headers' => ['پروژه', 'درآمد', 'هزینه', 'سود'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function profitabilityByCustomerReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)
            ->filter(fn ($line) => ! is_null($line->party_id))
            ->groupBy('party_id')
            ->map(function (Collection $group) {
                $party = $group->first()?->party;
                $debit = (float) $group->sum('debit');
                $credit = (float) $group->sum('credit');

                return [
                    'party' => $party?->name ?: '-',
                    'revenue' => max($credit - $debit, 0),
                    'cost' => max($debit - $credit, 0),
                    'profit' => $credit - $debit,
                ];
            })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'سودآوری مشتری',
            subtitle: 'تحلیل سود ناخالص طرف حساب مشتری',
            filters: $filters,
            summary: ['customers' => $rows->count(), 'profit' => (float) $rows->sum('profit')],
            sections: [
                [
                    'title' => 'مشتریان',
                    'headers' => ['مشتری', 'درآمد', 'هزینه', 'سود'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function costCenterReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)
            ->filter(fn ($line) => filled($line->cost_center))
            ->groupBy('cost_center')
            ->map(function (Collection $group, string $costCenter) {
                return [
                    'cost_center' => $costCenter,
                    'debit' => (float) $group->sum('debit'),
                    'credit' => (float) $group->sum('credit'),
                    'balance' => (float) $group->sum('debit') - (float) $group->sum('credit'),
                ];
            })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'گزارش مرکز هزینه',
            subtitle: 'گردش و مانده مراکز هزینه',
            filters: $filters,
            summary: ['centers' => $rows->count()],
            sections: [
                [
                    'title' => 'مراکز هزینه',
                    'headers' => ['مرکز هزینه', 'بدهکار', 'بستانکار', 'مانده'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function departmentFinancialPerformanceReport(array $filters, FinancialReportContext $context): array
    {
        $departments = OrganizationUnit::query()->where('type', 'department')->orderBy('title')->get();
        $lines = $this->postedLines($filters);
        $rows = $departments->map(function (OrganizationUnit $unit) use ($lines) {
            $code = $unit->cost_center_code ?: $unit->code;
            $departmentLines = $lines->filter(function ($line) use ($code) {
                return filled($line->cost_center) && str_contains((string) $line->cost_center, (string) $code);
            });

            return [
                'department' => $unit->title,
                'cost_center_code' => $code,
                'debit' => (float) $departmentLines->sum('debit'),
                'credit' => (float) $departmentLines->sum('credit'),
                'balance' => (float) $departmentLines->sum('debit') - (float) $departmentLines->sum('credit'),
            ];
        });

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'عملکرد مالی واحدها',
            subtitle: 'تحلیل مالی واحدهای سازمانی',
            filters: $filters,
            summary: ['departments' => $rows->count()],
            sections: [
                [
                    'title' => 'واحدها',
                    'headers' => ['واحد سازمانی', 'کد مرکز هزینه', 'بدهکار', 'بستانکار', 'مانده'],
                    'rows' => $rows->values(),
                ],
            ],
        );
    }

    private function journalEntriesReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->documentRows($filters)->paginate((int) ($filters['per_page'] ?? 25));

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'گزارش اسناد حسابداری',
            subtitle: 'فهرست اسناد و جمع سطرها',
            filters: $filters,
            summary: ['documents' => $rows->total()],
            sections: [
                [
                    'title' => 'اسناد',
                    'headers' => ['شماره', 'تاریخ', 'نوع', 'وضعیت', 'جمع بدهکار', 'جمع بستانکار', 'شرح'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function journalEntriesByDateReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->documentRows($filters)->orderBy('accounting_documents.document_date')->get()->groupBy(fn ($row) => gregorianToJalaliDate($row->document_date))->map(function (Collection $group, string $date) {
            return [
                'date' => $date,
                'documents' => $group->count(),
                'debit' => (float) $group->sum('debit_total'),
                'credit' => (float) $group->sum('credit_total'),
            ];
        })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'اسناد به تفکیک تاریخ',
            subtitle: 'گروه‌بندی اسناد بر اساس تاریخ سند',
            filters: $filters,
            summary: ['days' => $rows->count()],
            sections: [
                [
                    'title' => 'تاریخ‌ها',
                    'headers' => ['تاریخ', 'تعداد سند', 'بدهکار', 'بستانکار'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function journalEntriesByAccountReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)->groupBy('chart_account_id')->map(function (Collection $group) {
            $account = $group->first()?->account;
            return [
                'account' => trim(($account?->code ?: '') . ' - ' . ($account?->title ?: '')),
                'debit' => (float) $group->sum('debit'),
                'credit' => (float) $group->sum('credit'),
                'balance' => (float) $group->sum('debit') - (float) $group->sum('credit'),
            ];
        })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'اسناد به تفکیک حساب',
            subtitle: 'گردش سندی هر حساب',
            filters: $filters,
            summary: ['accounts' => $rows->count()],
            sections: [
                [
                    'title' => 'حساب‌ها',
                    'headers' => ['حساب', 'بدهکار', 'بستانکار', 'مانده'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function auditTrailReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->reports->auditQuery($filters)->latest('created_at')->paginate((int) ($filters['per_page'] ?? 25));
        $rows->setCollection($rows->getCollection()->map(fn ($row) => [
            'date' => formatJalaliDateSafe($row->created_at),
            'event' => $row->event,
            'type' => class_basename($row->auditable_type),
            'user_id' => $row->user_id ?: '-',
            'auditable_id' => $row->auditable_id,
        ]));

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'ردیاب حسابرسی',
            subtitle: 'لاگ رخدادهای حسابرسی',
            filters: $filters,
            summary: ['entries' => $rows->total()],
            sections: [
                [
                    'title' => 'رویدادها',
                    'headers' => ['تاریخ', 'رویداد', 'نوع رکورد', 'کاربر', 'شناسه'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function deletedModifiedTransactionsReport(array $filters, FinancialReportContext $context): array
    {
        $documents = AccountingDocument::withTrashed()->with('lines.account')
            ->whereNotNull('deleted_at')
            ->latest('deleted_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
        $documents->setCollection($documents->getCollection()->map(fn ($document) => [
            'number' => $document->number,
            'date' => gregorianToJalaliDate($document->document_date),
            'status' => $document->status,
            'description' => $document->description ?: '-',
            'deleted_at' => formatJalaliDateSafe($document->deleted_at),
        ]));

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'اسناد حذف/ویرایش‌شده',
            subtitle: 'اسناد و تراکنش‌های تغییر یافته یا حذف شده',
            filters: $filters,
            summary: ['documents' => $documents->total()],
            sections: [
                [
                    'title' => 'اسناد حذف‌شده',
                    'headers' => ['شماره', 'تاریخ', 'وضعیت', 'شرح', 'تاریخ حذف'],
                    'rows' => $documents,
                ],
                [
                    'title' => 'تراکنش‌های حسابرسی‌شده',
                    'headers' => ['سند', 'رخداد', 'شناسه کاربر'],
                    'rows' => $this->reports->auditQuery($filters)->latest('created_at')->limit(25)->get()->map(fn ($audit) => [
                        'document' => class_basename($audit->auditable_type),
                        'event' => $audit->event,
                        'user_id' => $audit->user_id ?: '-',
                    ]),
                ],
            ],
        );
    }

    private function analysisByAccountReport(string $prefix, string $title, string $subtitle, array $filters, FinancialReportContext $context): array
    {
        $balances = $this->accountBalances($this->postedLines($filters), $this->openingLines($filters))
            ->filter(fn ($row) => str_starts_with((string) $row['code'], $prefix))
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: $title,
            subtitle: $subtitle,
            filters: $filters,
            summary: [
                'accounts' => $balances->count(),
                'amount' => (float) $balances->sum(fn ($row) => $row['closing_credit'] - $row['closing_debit']),
            ],
            sections: [
                [
                    'title' => $title,
                    'headers' => ['کد', 'عنوان', 'بدهکار', 'بستانکار', 'مانده'],
                    'rows' => $balances->map(fn ($row) => [
                        'code' => $row['code'],
                        'title' => $row['title'],
                        'debit' => $row['closing_debit'],
                        'credit' => $row['closing_credit'],
                        'balance' => $row['closing_credit'] - $row['closing_debit'],
                    ])->values(),
                ],
            ],
        );
    }

    private function cashOrBankReport(string $field, string $title, string $subtitle, array $filters, FinancialReportContext $context): array
    {
        $rows = $this->postedLines($filters)->filter(fn ($line) => filled(data_get($line, $field)))->groupBy($field)->map(function (Collection $group, $key) use ($field, $filters) {
            $entity = $field === 'cashbox_id' ? Cashbox::find($key) : BankAccount::find($key);
            $reportKey = $field === 'bank_account_id' ? 'bank-statement' : 'cash-book';
            $detailUrl = route('financial-reports.show', array_merge([
                'report' => $reportKey,
                'bank_account_id' => $field === 'bank_account_id' ? (int) $key : null,
                'cashbox_id' => $field === 'cashbox_id' ? (int) $key : null,
            ], $filters));

            return [
                'id' => (int) $key,
                'code' => $entity?->code ?: (string) $key,
                'name' => $entity?->name ?? $entity?->bank_name ?? '-',
                'opening' => (float) ($entity?->opening_balance ?? 0),
                'debit' => (float) $group->sum('debit'),
                'credit' => (float) $group->sum('credit'),
                'closing' => (float) ($entity?->opening_balance ?? 0) + (float) $group->sum('debit') - (float) $group->sum('credit'),
                'bank_account_id' => $field === 'bank_account_id' ? (int) $key : null,
                'cashbox_id' => $field === 'cashbox_id' ? (int) $key : null,
                'detail_url' => $detailUrl,
            ];
        })->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: $title,
            subtitle: $subtitle,
            filters: $filters,
            summary: ['accounts' => $rows->count()],
            sections: [
                [
                    'title' => $title,
                    'headers' => ['کد', 'عنوان', 'افتتاحیه', 'بدهکار', 'بستانکار', 'پایان دوره'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function taxReportPayload(FinancialReportContext $context, string $title, string $subtitle, array $filters, Collection $rows): array
    {
        return $this->reportPayload(
            key: $context->reportKey,
            title: $title,
            subtitle: $subtitle,
            filters: $filters,
            summary: [
                'rows' => $rows->count(),
                'tax_amount' => (float) $rows->sum('tax_amount'),
            ],
            sections: [
                [
                    'title' => $title,
                    'headers' => ['شماره', 'تاریخ', 'طرف حساب', 'مبلغ مشمول', 'مالیات', 'جمع کل'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function reportPayload(string $key, string $title, string $subtitle, array $filters, array $summary, array $sections): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'filters' => $filters,
            'summary' => $summary,
            'sections' => $sections,
            'export_rows' => $this->flattenSections($key, $sections),
        ];
    }

    private function flattenSections(string $reportKey, array $sections): Collection
    {
        return collect($sections)->flatMap(function (array $section) use ($reportKey) {
            $rows = $section['rows'] ?? [];

            if ($rows instanceof Paginator) {
                $rows = $rows->getCollection();
            }

            return collect($rows)->map(function ($row) use ($section, $reportKey) {
                $columns = $section['columns'] ?? $this->sectionColumns($reportKey, $section);

                if (is_array($row)) {
                    $payload = ['section' => $section['title'] ?? ''];

                    foreach ($columns as $column) {
                        $payload[$column] = data_get($row, $column, '');
                    }

                    return $payload;
                }

                $payload = ['section' => $section['title'] ?? ''];

                foreach ($columns as $column) {
                    $payload[$column] = data_get($row, $column, '');
                }

                return $payload;
            });
        })->values();
    }

    private function sectionColumns(string $reportKey, array $section): array
    {
        $title = (string) ($section['title'] ?? '');

        return match ($reportKey) {
            'trial-balance' => ['code', 'title', 'opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'],
            'detailed-trial-balance' => str_contains($title, 'ریز') ? ['date', 'document_number', 'account', 'party', 'project', 'cost_center', 'description', 'debit', 'credit', 'running_balance'] : ['code', 'title', 'opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'],
            'general-ledger', 'detailed-ledger' => ['date', 'document_number', 'account', 'description', 'debit', 'credit', 'running_balance'],
            'balance-sheet', 'income-statement' => ['code', 'title', 'amount'],
            'expense-analysis-by-account', 'revenue-analysis-by-account' => ['code', 'title', 'debit', 'credit', 'balance'],
            'changes-in-equity' => ['code', 'title', 'opening', 'movement', 'closing'],
            'cash-flow-statement' => ['code', 'title', 'opening', 'debit', 'credit', 'closing'],
            'accounts-receivable-aging', 'accounts-payable-aging' => ['code', 'name', 'balance', 'days', 'bucket'],
            'customer-statement', 'supplier-statement' => ['code', 'name', 'debit', 'credit', 'balance', 'balance_type'],
            'outstanding-invoices' => ['number', 'date', 'party', 'direction', 'total_amount', 'status', 'outstanding_balance'],
            'overdue-invoices' => ['number', 'date', 'party', 'direction', 'total_amount', 'days_overdue', 'outstanding_balance'],
            'cash-book', 'bank-book', 'bank-statement' => ['code', 'name', 'opening', 'debit', 'credit', 'closing'],
            'bank-reconciliation' => ['code', 'bank_name', 'account_number', 'opening_balance', 'journal_debit', 'journal_credit', 'statement_balance', 'variance'],
            'cash-flow-by-period' => ['period', 'debit', 'credit', 'net_cash'],
            'sales-tax', 'purchase-tax', 'vat-summary' => ['number', 'date', 'party', 'taxable_amount', 'tax_amount', 'total_amount'],
            'tax-transactions' => ['date', 'document_number', 'account', 'description', 'debit', 'credit'],
            'profitability-by-project' => ['project', 'revenue', 'cost', 'profit'],
            'profitability-by-customer' => ['party', 'revenue', 'cost', 'profit'],
            'cost-center-report' => ['cost_center', 'debit', 'credit', 'balance'],
            'department-financial-performance' => ['department', 'cost_center_code', 'debit', 'credit', 'balance'],
            'journal-entries' => ['number', 'date', 'type', 'status', 'debit_total', 'credit_total', 'description'],
            'journal-entries-by-date' => ['date', 'documents', 'debit', 'credit'],
            'journal-entries-by-account' => ['account', 'debit', 'credit', 'balance'],
            'audit-trail' => ['date', 'event', 'type', 'user_id', 'auditable_id'],
            'deleted-modified-transactions' => str_contains($title, 'حذف') ? ['number', 'date', 'status', 'description', 'deleted_at'] : ['document', 'event', 'user_id'],
            default => [],
        };
    }

    private function normalizeFilters(array $filters): array
    {
        foreach (['date_from', 'date_to'] as $key) {
            if (! empty($filters[$key])) {
                $filters[$key] = jalaliToGregorianDate($filters[$key]) ?: $filters[$key];
            }
        }

        foreach (['fiscal_year_id', 'branch_id', 'project_id', 'party_id', 'account_id', 'bank_account_id', 'per_page'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $filters[$key] = (int) $filters[$key];
            }
        }

        $filters['direction'] = $filters['direction'] ?? 'desc';

        return $filters;
    }

    private function postedLines(array $filters): Collection
    {
        return $this->postedLineQuery($filters)
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id')
            ->get();
    }

    private function openingLines(array $filters): Collection
    {
        if (empty($filters['date_from'])) {
            return collect();
        }

        $openingFilters = $filters;
        $openingFilters['date_to'] = \Carbon\Carbon::parse($filters['date_from'])->subDay()->toDateString();

        return $this->postedLineQuery($openingFilters)
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id')
            ->get();
    }

    private function postedLineQuery(array $filters): Builder
    {
        return $this->reports->postedLineQuery($filters);
    }

    private function accountBalances(Collection $currentLines, Collection $openingLines): Collection
    {
        $current = $currentLines->groupBy('chart_account_id');
        $opening = $openingLines->groupBy('chart_account_id');

        $accountIds = $current->keys()->merge($opening->keys())->unique()->filter()->values();
        $accounts = ChartAccount::whereIn('id', $accountIds)->get()->keyBy('id');

        return $accountIds->map(function ($accountId) use ($current, $opening, $accounts) {
            $openingGroup = $opening->get($accountId, collect());
            $currentGroup = $current->get($accountId, collect());

            $openingDebit = (float) $openingGroup->sum('debit');
            $openingCredit = (float) $openingGroup->sum('credit');
            $periodDebit = (float) $currentGroup->sum('debit');
            $periodCredit = (float) $currentGroup->sum('credit');

            return [
                'account' => $accounts->get($accountId),
                'code' => $accounts->get($accountId)?->code ?: '-',
                'title' => $accounts->get($accountId)?->title ?: '-',
                'opening_debit' => $openingDebit,
                'opening_credit' => $openingCredit,
                'opening_net' => $openingDebit - $openingCredit,
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_debit' => max(($openingDebit + $periodDebit) - ($openingCredit + $periodCredit), 0),
                'closing_credit' => max(($openingCredit + $periodCredit) - ($openingDebit + $periodDebit), 0),
                'closing_net' => ($openingDebit + $periodDebit) - ($openingCredit + $periodCredit),
            ];
        });
    }

    private function ledgerRows(array $filters, bool $includeRunningBalance = true): Collection
    {
        $rows = $this->postedLines($filters)->map(function ($line) {
            return $this->lineRow($line);
        });

        if (! $includeRunningBalance) {
            return $rows;
        }

        $runningByAccount = [];

        return $rows->map(function (array $row) use (&$runningByAccount) {
            $key = $row['account_code'];
            $runningByAccount[$key] = ($runningByAccount[$key] ?? 0) + $row['debit'] - $row['credit'];
            $row['closing_balance'] = $runningByAccount[$key];
            $row['opening_balance'] = $row['closing_balance'] - $row['debit'] + $row['credit'];

            return $row;
        });
    }

    private function lineRow($line): array
    {
        $running = (float) $line->debit - (float) $line->credit;

        return [
            'date' => gregorianToJalaliDate($line->document?->document_date),
            'document_number' => $line->document?->number,
            'account_code' => $line->account?->code ?: '-',
            'account_title' => $line->account?->title ?: '-',
            'account' => trim(($line->account?->code ?: '') . ' - ' . ($line->account?->title ?: '')),
            'party' => $line->party?->name ?: '-',
            'project' => $line->project?->name ?: '-',
            'cost_center' => $line->cost_center ?: '-',
            'description' => $line->description ?: $line->document?->description ?: '-',
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'running_balance' => $running,
            'bank_account_id' => $line->bank_account_id,
            'cashbox_id' => $line->cashbox_id,
        ];
    }

    private function invoiceRows(array $filters): Collection
    {
        return $this->reports->invoiceQuery($filters)->orderBy('invoice_date')->get();
    }

    private function invoiceRow(Invoice $invoice): array
    {
        $outstanding = $invoice->settled_at ? 0 : (float) $invoice->total_amount;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'date' => gregorianToJalaliDate($invoice->invoice_date),
            'party' => $invoice->party?->name ?: '-',
            'direction' => $invoice->direction === 'sale' ? 'فروش' : 'خرید',
            'total_amount' => (float) $invoice->total_amount,
            'tax_amount' => (float) $invoice->tax_amount,
            'outstanding_balance' => $outstanding,
            'status' => $invoice->settled_at ? 'تسویه شده' : ($invoice->status === 'confirmed' ? 'باز' : ($invoice->status === 'draft' ? 'موقت' : $invoice->status)),
        ];
    }

    private function documentRows(array $filters): Builder
    {
        return $this->reports->documentQuery($filters)
            ->withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->withCount('lines as line_count')
            ->orderByDesc('accounting_documents.document_date')
            ->orderByDesc('accounting_documents.id');
    }

    private function partyBalances(array $filters, ?int $accountId): Collection
    {
        $accountIds = $accountId ? $this->accountAndDescendantIds($accountId) : null;

        return $this->postedLines($filters)
            ->when($accountIds, fn (Collection $collection) => $collection->whereIn('chart_account_id', $accountIds))
            ->filter(fn ($line) => $line->party_id)
            ->groupBy('party_id')
            ->map(function (Collection $group) {
                $party = $group->first()?->party;
                $lastDate = $group->max(fn ($line) => $line->document?->document_date);

                return [
                    'party' => $party,
                    'balance_debit' => (float) $group->sum('debit'),
                    'balance_credit' => (float) $group->sum('credit'),
                    'last_date' => $lastDate ? \Carbon\Carbon::parse($lastDate) : null,
                ];
            })
            ->values();
    }

    private function cashAccountIds(): array
    {
        return Cashbox::query()->whereNotNull('chart_account_id')->pluck('chart_account_id')->merge(
            BankAccount::query()->whereNotNull('chart_account_id')->pluck('chart_account_id')
        )->filter()->unique()->values()->all();
    }

    private function accountIdLike(string $prefix): ?int
    {
        return ChartAccount::where('code', 'like', $prefix . '%')->orderBy('code')->value('id');
    }

    private function accountAndDescendantIds(int $accountId): array
    {
        $ids = [$accountId];
        $frontier = [$accountId];

        while ($frontier) {
            $children = ChartAccount::whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));

            if (! $children) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    private function balanceType(float $balance): string
    {
        return $balance > 0 ? 'بدهکار' : ($balance < 0 ? 'بستانکار' : 'تسویه');
    }

    private function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 30 => '0-30 روز',
            $days <= 60 => '31-60 روز',
            $days <= 90 => '61-90 روز',
            default => 'بیش از 90 روز',
        };
    }

    private function paginateCollection(Collection $rows, int $perPage): Paginator
    {
        $perPage = max(10, $perPage);
        $page = Paginator::resolveCurrentPage();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
