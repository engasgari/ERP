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
use App\Models\PaymentVoucher;
use App\Models\PayrollCalculation;
use App\Models\ReceiptVoucher;
use App\Models\OrganizationUnit;
use App\Models\Party;
use App\Models\Project;
use App\Models\TreasuryTransaction;
use App\Repositories\FinancialReportRepository;
use App\Support\FinancialReportContext;
use App\Support\FinancialReportRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialReportService
{
    /** @var list<string> */
    private const GENERIC_BANK_LINE_DESCRIPTIONS = [
        'پرداخت خزانه',
        'دریافت خزانه',
        'طرف حساب پرداخت',
        'طرف حساب دریافت',
        'انتقال ورودی خزانه',
        'انتقال خروجی خزانه',
        'پرداخت بانکی',
        'دریافت بانکی',
        'واریز شریک',
        'برداشت شریک',
    ];

    /** @var list<string> */
    private const GENERIC_INVOICE_LINE_DESCRIPTIONS = [
        'حساب پرداختنی فروشنده',
        'حساب دریافتنی مشتری',
        'خرید کالا',
        'هزینه خدمات',
        'درآمد فروش',
    ];

    public function __construct(
        private FinancialReportRepository $reports,
        private FinancialReportRegistry $registry
    ) {
    }

    public function catalog(): array
    {
        return $this->registry->catalog();
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
            'cash-statement' => $this->cashStatementReport($filters, $context),
            'bank-book' => $this->bankBookReport($filters, $context),
            'bank-statement' => $this->bankBookReport($filters, $context),
            'bank-reconciliation' => $this->bankReconciliationReport($filters, $context),
            'cash-flow-by-period' => $this->cashFlowByPeriodReport($filters, $context),
            'sales-tax' => $this->salesTaxReport($filters, $context),
            'purchase-tax' => $this->purchaseTaxReport($filters, $context),
            'vat-summary' => $this->vatSummaryReport($filters, $context),
            'tax-transactions' => $this->taxTransactionsReport($filters, $context),
            'tax-electronic-books' => $this->taxElectronicBooksReport($filters, $context),
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
        $filters = $this->normalizeFilters($filters);
        $filters['account_id'] = $account->id;

        $running = 0.0;
        $lines = $this->postedLines($filters)->map(function ($line) use (&$running) {
            $running += (float) $line->debit - (float) $line->credit;
            $row = $this->lineRow($line);
            $row['document'] = $line->document;
            $row['party'] = $line->party;
            $row['project'] = $line->project;
            $row['account'] = $line->account;
            $row['running_balance'] = $running;

            return $row;
        });

        $balance = (float) $lines->sum(fn (array $row) => $row['debit'] - $row['credit']);

        return [
            'account' => $account,
            'lines' => $lines,
            'debit' => (float) $lines->sum('debit'),
            'credit' => (float) $lines->sum('credit'),
            'balance' => $balance,
            'balance_type' => $this->balanceType($balance),
        ];
    }

    public function partyStatementSummaries(?int $accountId = null, ?int $partyId = null, array $filters = [], ?string $side = null): Collection
    {
        unset($filters['side'], $side);

        $report = $this->partyStatementByAccount($accountId, $partyId, $filters);

        return $this->filterPartyStatementSummaries(collect($report['sections'][0]['rows'] ?? []), $filters);
    }

    public function employeeStatementSummaries(?int $partyId = null, array $filters = []): Collection
    {
        return $this->partyStatementSummaries(null, $partyId, $filters);
    }

    private function normalizeStatementSide(?string $side): ?string
    {
        if ($side === null || $side === '') {
            return null;
        }

        $side = strtolower(trim($side));

        return $side === 'employee' ? 'personnel' : $side;
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

    /**
     * مانده مطالبات مشتریان از دفتر (حساب دریافتنی)، هم‌راستا با گزارش سن مطالبات.
     *
     * @return array{
     *     total_receivables: float,
     *     current: float,
     *     overdue: float,
     *     over_90: float,
     *     party_count: int,
     *     invoice_count: int,
     *     buckets: array<string, float>
     * }
     */
    public function customerReceivableSummary(array $filters = []): array
    {
        $report = $this->report('accounts-receivable-aging', $filters);
        $summary = $this->agingBalanceSummary(
            collect($report['sections'][0]['rows'] ?? []),
            'total_receivables',
        );

        return array_merge($summary, [
            'current' => (float) ($summary['buckets']['current'] ?? 0),
        ]);
    }

    /**
     * مانده بدهی تأمین‌کنندگان از دفتر (حساب پرداختنی)، هم‌راستا با گزارش سن بدهی‌ها.
     *
     * @return array{
     *     total_payables: float,
     *     overdue: float,
     *     over_90: float,
     *     party_count: int,
     *     invoice_count: int,
     *     buckets: array<string, float>
     * }
     */
    public function supplierPayableSummary(array $filters = []): array
    {
        $report = $this->report('accounts-payable-aging', $filters);
        $rows = collect($report['sections'][0]['rows'] ?? []);
        $summary = $this->agingBalanceSummary($rows, 'total_payables');

        $parties = $rows
            ->map(fn (array $row) => [
                'party_id' => (int) ($row['party_id'] ?? 0),
                'party_name' => (string) ($row['name'] ?? '—'),
                'outstanding_amount' => abs((float) ($row['balance'] ?? 0)),
                'days' => (int) abs((float) ($row['days'] ?? 0)),
                'bucket' => (string) ($row['bucket'] ?? ''),
            ])
            ->filter(fn (array $row) => (float) $row['outstanding_amount'] > 0.00001)
            ->sortByDesc('outstanding_amount')
            ->values()
            ->all();

        return array_merge($summary, [
            'current' => (float) ($summary['buckets']['current'] ?? 0),
            'parties' => $parties,
        ]);
    }

    /**
     * مانده بدهی یک حساب بدهی (طبیعت بستانکار) از دفتر کل — مثلاً 2102 مالیات ارزش افزوده فروش.
     */
    public function liabilityAccountBalance(array $filters, string $accountCode): float
    {
        if (! ChartAccount::query()->where('code', $accountCode)->exists()) {
            return 0.0;
        }

        $balances = $this->accountBalances($this->postedLines($filters), $this->openingLines($filters));
        $row = $balances->first(fn ($item) => (string) $item['code'] === $accountCode);

        if (! $row) {
            return 0.0;
        }

        return max((float) $row['closing_credit'] - (float) $row['closing_debit'], 0);
    }

    public function bankTransactions(BankAccount $bankAccount, array $filters = []): array
    {
        return $this->treasuryStatementTransactions(
            treasuryFilterKey: 'bank_account_id',
            treasuryId: $bankAccount->id,
            openingBalance: (float) $bankAccount->opening_balance,
            subtitle: $bankAccount->code . ' - ' . $bankAccount->bank_name,
            payloadKey: 'bank-transactions',
            title: 'تراکنش‌های بانکی',
            sectionTitle: 'گردش حساب بانکی',
            filters: $filters,
        );
    }

    public function cashTransactions(Cashbox $cashbox, array $filters = []): array
    {
        return $this->treasuryStatementTransactions(
            treasuryFilterKey: 'cashbox_id',
            treasuryId: $cashbox->id,
            openingBalance: (float) $cashbox->opening_balance,
            subtitle: $cashbox->code . ' - ' . $cashbox->name,
            payloadKey: 'cash-transactions',
            title: 'تراکنش‌های صندوق',
            sectionTitle: 'گردش صندوق',
            filters: $filters,
        );
    }

    public function reportDefinitions(string $key): ?array
    {
        return $this->registry->reportDefinitions($key);
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
        return $this->simpleProfitAndLossReport($filters, $context);
    }

    private function simpleProfitAndLossReport(array $filters, FinancialReportContext $context): array
    {
        $range = $this->profitAndLossPeriodRange($filters);
        $cacheKey = $this->profitAndLossCacheKey($filters, $range, 'simple');

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($filters, $context) {
            $revenue = $this->pnlGroupRows($filters, ['41'], 'credit', 'درآمدها');
            $costOfSalesRows = $this->pnlGroupRows($filters, ['51'], 'debit', 'بهای تمام‌شده فروش');
            $expenseRows = $this->pnlGroupRows($filters, ['52'], 'debit', 'هزینه‌های عملیاتی');
            $incomeTaxRows = $this->pnlGroupRows($filters, ['53'], 'debit', 'مالیات بر درآمد');

            $costOfSales = $costOfSalesRows['total'];
            $grossProfit = $revenue['total'] - $costOfSales;
            $operatingExpenses = $expenseRows['total'];
            $operatingProfit = $grossProfit - $operatingExpenses;
            $incomeTaxExpense = $incomeTaxRows['total'];
            $netProfit = $operatingProfit - $incomeTaxExpense;

            return $this->reportPayload(
                key: $context->reportKey,
                title: 'صورت سود و زیان',
                subtitle: 'محاسبه صرفاً بر اساس سندهای حسابداری ثبت‌شده',
                filters: $filters,
                summary: [
                    'revenue' => $revenue['total'],
                    'cost_of_sales' => $costOfSales,
                    'gross_profit' => $grossProfit,
                    'operating_expenses' => $operatingExpenses,
                    'operating_profit' => $operatingProfit,
                    'income_tax_expense' => $incomeTaxExpense,
                    'net_profit' => $netProfit,
                ],
                sections: [
                    [
                        'title' => 'درآمدها',
                        'headers' => ['کد', 'عنوان', 'مبلغ'],
                        'rows' => $revenue['rows'],
                    ],
                    [
                        'title' => 'بهای تمام‌شده فروش',
                        'headers' => ['کد', 'عنوان', 'مبلغ'],
                        'rows' => $costOfSalesRows['rows'],
                    ],
                    [
                        'title' => 'سود ناخالص',
                        'headers' => ['عنوان', 'مبلغ'],
                        'rows' => [
                            ['title' => 'سود ناخالص', 'amount' => $grossProfit],
                        ],
                    ],
                    [
                        'title' => 'هزینه‌های عملیاتی',
                        'headers' => ['کد', 'عنوان', 'مبلغ'],
                        'rows' => $expenseRows['rows'],
                    ],
                    [
                        'title' => 'سود عملیاتی',
                        'headers' => ['عنوان', 'مبلغ'],
                        'rows' => [
                            ['title' => 'سود عملیاتی', 'amount' => $operatingProfit],
                        ],
                    ],
                    [
                        'title' => 'مالیات بر درآمد',
                        'headers' => ['کد', 'عنوان', 'مبلغ'],
                        'rows' => $incomeTaxRows['rows'],
                    ],
                    [
                        'title' => 'سود خالص',
                        'headers' => ['عنوان', 'مبلغ'],
                        'rows' => [
                            ['title' => 'سود خالص', 'amount' => $netProfit],
                        ],
                    ],
                ],
            );
        });
    }

    private function pnlGroupRows(array $filters, array $prefixes, string $nature, string $title, array $excludePrefixes = []): array
    {
        $accounts = ChartAccount::query()
            ->select(['id', 'code', 'title'])
            ->where(function (Builder $query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('code', 'like', $prefix . '%');
                }
            })
            ->when($excludePrefixes, function (Builder $query) use ($excludePrefixes) {
                foreach ($excludePrefixes as $prefix) {
                    $query->where('code', 'not like', $prefix . '%');
                }
            })
            ->whereNotIn('code', ['1102', '2102'])
            ->orderBy('code')
            ->get();

        $balances = $this->postedLineQuery($filters)
            ->select('chart_account_id')
            ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')
            ->whereIn('chart_account_id', $accounts->pluck('id'))
            ->groupBy('chart_account_id')
            ->get()
            ->keyBy('chart_account_id');

        $rows = $accounts->map(function (ChartAccount $account) use ($balances, $nature, $filters) {
            $balance = $balances->get($account->id);
            $debit = (float) ($balance->debit ?? 0);
            $credit = (float) ($balance->credit ?? 0);
            $amount = $nature === 'credit' ? max($credit - $debit, 0) : max($debit - $credit, 0);

            return [
                'code' => $account->code,
                'title' => $account->title,
                'amount' => $amount,
                'detail_url' => route('financial-reports.account-statement', array_merge(['account' => $account->id], $this->filtersForUrl($filters))),
            ];
        })->filter(fn (array $row) => $row['amount'] != 0.0)->values();

        return [
            'title' => $title,
            'total' => (float) $rows->sum('amount'),
            'rows' => $rows,
        ];
    }

    private function cashFlowStatementReport(array $filters, FinancialReportContext $context): array
    {
        $cashAccounts = $this->cashAccountIds();
        $rows = $this->postedLines($filters)->filter(fn ($line) => in_array((int) $line->chart_account_id, $cashAccounts, true))->values();
        $opening = $this->openingLines($filters)->filter(fn ($line) => in_array((int) $line->chart_account_id, $cashAccounts, true))->values();
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
        if ($side === 'supplier') {
            return $this->supplierUnifiedAgingReport($filters, $context);
        }

        $accountId = $this->accountIdLike('1101');
        $rows = $this->partyBalances($filters, $accountId)->map(function ($row) {
            $days = $row['last_date'] ? now()->startOfDay()->diffInDays($row['last_date']) : 0;
            // مانده دفتر ممکن است منفی ثبت شود؛ برای سن مطالبات قدر مطلق نمایش داده می‌شود.
            $balance = abs((float) $row['balance_debit'] - (float) $row['balance_credit']);

            return [
                'party_id' => $row['party']->id,
                'party' => $row['party'],
                'code' => $row['party']->code,
                'name' => $row['party']->name,
                'balance' => $balance,
                'days' => $days,
                'bucket' => $this->agingBucket($days),
            ];
        })->filter(fn ($row) => (float) $row['balance'] > 0.00001)
            ->sortBy('name')
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'سن مطالبات',
            subtitle: 'طبقه‌بندی مانده طرف حساب بر اساس تاریخ آخرین گردش',
            filters: $filters,
            summary: [
                'parties' => $rows->count(),
                'balance' => (float) $rows->sum('balance'),
            ],
            sections: [
                [
                    'title' => 'مشتریان',
                    'headers' => ['کد', 'عنوان', 'مانده', 'روزهای گذشته', 'بازه سنی'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function supplierUnifiedAgingReport(array $filters, FinancialReportContext $context): array
    {
        $rows = $this->unifiedPartyStatementBalances($filters)
            ->filter(fn (array $row) => $this->partyQualifiesForSupplierScope($row['party'], $row['lines']))
            ->map(function (array $row) {
                $credit = (float) $row['credit'];
                $debit = (float) $row['debit'];
                $days = $row['last_date']
                    ? (int) abs(now()->startOfDay()->diffInDays($row['last_date']))
                    : 0;

                return [
                    'party_id' => $row['party']->id,
                    'party' => $row['party'],
                    'code' => $row['party']->code,
                    'name' => $row['party']->name,
                    'balance' => max($credit - $debit, 0.0),
                    'statement_balance' => (float) $row['balance'],
                    'balance_type' => $this->balanceType((float) $row['balance']),
                    'days' => $days,
                    'bucket' => $this->agingBucket($days),
                ];
            })
            ->filter(fn (array $row) => (float) ($row['balance'] ?? 0) > 0)
            ->sortBy('name')
            ->values();

        return $this->reportPayload(
            key: $context->reportKey,
            title: 'سن بدهی‌ها',
            subtitle: 'مانده net تأمین‌کننده/همکار مطابق صورتحساب اشخاص (پرداختنی + حقوق و سایر حساب‌های طرف)',
            filters: $filters,
            summary: [
                'parties' => $rows->count(),
                'balance' => (float) $rows->sum('balance'),
            ],
            sections: [
                [
                    'title' => 'تأمین‌کنندگان',
                    'headers' => ['کد', 'عنوان', 'مانده', 'روزهای گذشته', 'بازه سنی'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function partyStatementReport(string $side, array $filters, FinancialReportContext $context): array
    {
        $accountId = $side === 'supplier'
            ? null
            : $this->accountIdLike('1101');

        return $this->partyStatementByAccount(
            $accountId,
            $filters['party_id'] ?? null,
            $filters,
            $context,
            $side,
        );
    }

    private function partyStatementByAccount(?int $accountId, ?int $partyId, array $filters, ?FinancialReportContext $context = null, ?string $side = null): array
    {
        $side = $this->normalizeStatementSide($side ?? ($filters['side'] ?? null));

        $lines = $this->reports->partyStatementRows($accountId, $partyId, $filters);

        $parties = $lines->groupBy('party_id')->map(function (Collection $group) {
            $running = 0;
            $party = $group->first()?->party;
            $rows = $group->map(function ($line) use (&$running) {
                $running += (float) $line->debit - (float) $line->credit;

                return [
                    'date' => gregorianToJalaliDate($line->document?->document_date),
                    'transaction_type' => $this->partyStatementTransactionType($line),
                    'description' => $this->partyStatementLineDescription($line),
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
        })
            ->when($side === 'supplier' && ! $partyId, function (Collection $collection) {
                return $collection->filter(function (array $row) {
                    $party = Party::query()
                        ->with(['types:id,name', 'employees:id,party_id'])
                        ->find($row['party_id']);

                    return $party && $this->partyQualifiesForSupplierScope($party, collect());
                });
            })
            ->sortBy('party_name')
            ->values();

        $payload = [
            'title' => match (true) {
                $side === 'personnel' && $partyId => ($parties->first()['party_name'] ?? 'صورتحساب پرسنل'),
                $side === 'personnel' => 'صورتحساب پرسنل',
                (bool) $partyId => ($parties->first()['party_name'] ?? 'صورتحساب'),
                default => 'صورتحساب اشخاص',
            },
            'subtitle' => match ($side) {
                'personnel' => 'فیلتر لیست: پرسنل — ریز گردش شامل همه حساب‌ها',
                'customer' => 'فیلتر لیست: مشتری — ریز گردش شامل همه حساب‌ها',
                'supplier' => 'فیلتر لیست: تأمین‌کننده — مانده و گردش مطابق صورتحساب اشخاص (2101، حقوق و …)',
                'vendor' => 'فیلتر لیست: فروشنده — ریز گردش شامل همه حساب‌ها',
                'colleague' => 'فیلتر لیست: همکار — ریز گردش شامل همه حساب‌ها',
                default => 'گردش بدهکار و بستانکار طرف حساب‌ها',
            },
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

    private function statementScopeAccountId(string $scope): ?int
    {
        return $scope === 'supplier' ? $this->accountIdLike('2101') : $this->accountIdLike('1101');
    }

    private function partyStatementTransactionType($line): string
    {
        $type = (string) ($line->document?->type ?? '');

        return match ($type) {
            AccountingDocument::TYPE_SALE_INVOICE => 'فاکتور فروش',
            AccountingDocument::TYPE_PURCHASE_INVOICE => 'فاکتور خرید',
            AccountingDocument::TYPE_PAYMENT => 'پرداخت',
            AccountingDocument::TYPE_RECEIPT => 'دریافت',
            AccountingDocument::TYPE_INVENTORY => 'انبار',
            AccountingDocument::TYPE_OPENING => 'افتتاحیه',
            AccountingDocument::TYPE_CLOSING => 'اختتامیه',
            AccountingDocument::TYPE_PARTNER_CURRENT => 'جاری شریک',
            AccountingDocument::TYPE_PAYROLL => 'ثبت حقوق',
            AccountingDocument::TYPE_MANUAL => 'دستی',
            default => $type !== '' ? $type : '-',
        };
    }

    private function partyStatementLineDescription($line): string
    {
        $document = $line->document;
        $documentType = (string) ($document?->type ?? '');
        $lineDescription = trim((string) ($line->description ?? ''));

        if (in_array($documentType, [AccountingDocument::TYPE_SALE_INVOICE, AccountingDocument::TYPE_PURCHASE_INVOICE], true)) {
            $invoiceDescription = $this->partyStatementInvoiceDescription($document, $documentType);

            if ($invoiceDescription !== null) {
                if ($lineDescription === '' || in_array($lineDescription, self::GENERIC_INVOICE_LINE_DESCRIPTIONS, true)) {
                    return $invoiceDescription;
                }

                return $invoiceDescription.' — '.$lineDescription;
            }
        }

        if ($lineDescription !== '') {
            return $lineDescription;
        }

        $documentDescription = trim((string) ($document?->description ?? ''));

        return $documentDescription !== '' ? $documentDescription : '-';
    }

    private function partyStatementInvoiceDescription(?AccountingDocument $document, string $documentType): ?string
    {
        if (! $document) {
            return null;
        }

        $invoice = $document->source instanceof Invoice ? $document->source : null;

        if (! $invoice && $document->source_type === Invoice::class && $document->source_id) {
            $invoice = Invoice::query()->find($document->source_id);
        }

        $prefix = $documentType === AccountingDocument::TYPE_SALE_INVOICE ? 'فاکتور فروش' : 'فاکتور خرید';
        $number = trim((string) ($invoice?->number ?? ''));

        if ($number === '') {
            return null;
        }

        return $prefix.' شماره '.$number;
    }

    private function filterPartyStatementSummaries(Collection $parties, array $filters): Collection
    {
        $balanceNature = (string) ($filters['balance_nature'] ?? '');

        if ($balanceNature === '') {
            return $parties->values();
        }

        return $parties->filter(function (array $row) use ($balanceNature) {
            $balance = (float) ($row['balance'] ?? 0);

            return match ($balanceNature) {
                'debit' => $balance > 0,
                'credit' => $balance < 0,
                'settled' => abs($balance) < 0.00001,
                default => true,
            };
        })->values();
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

    private function cashStatementReport(array $filters, FinancialReportContext $context): array
    {
        if (! empty($filters['cashbox_id'])) {
            $cashbox = Cashbox::query()->findOrFail((int) $filters['cashbox_id']);
            $detail = $this->cashTransactions($cashbox, $filters);

            return $this->reportPayload(
                key: $context->reportKey,
                title: 'صورتحساب صندوق',
                subtitle: $cashbox->code . ' - ' . $cashbox->name,
                filters: $filters,
                summary: $detail['summary'],
                sections: $detail['sections'],
            );
        }

        return $this->cashboxesSummaryReport('صورتحساب صندوق', 'موجودی و گردش صندوق‌های نقدی', $filters, $context);
    }

    private function bankBookReport(array $filters, FinancialReportContext $context): array
    {
        if (! empty($filters['bank_account_id'])) {
            $bankAccount = BankAccount::query()->findOrFail((int) $filters['bank_account_id']);
            $detail = $this->bankTransactions($bankAccount, $filters);

            return $this->reportPayload(
                key: $context->reportKey,
                title: 'صورتحساب بانک',
                subtitle: $bankAccount->code . ' - ' . $bankAccount->bank_name,
                filters: $filters,
                summary: $detail['summary'],
                sections: $detail['sections'],
            );
        }

        return $this->cashOrBankReport('bank_account_id', 'دفتر بانک', 'گردش حساب‌های بانکی', $filters, $context);
    }

    private function bankReconciliationReport(array $filters, FinancialReportContext $context): array
    {
        $rows = BankAccount::query()
            ->with('account')
            ->when(! empty($filters['bank_account_id']), fn ($query) => $query->whereKey($filters['bank_account_id']))
            ->get()
            ->map(function (BankAccount $bankAccount) use ($filters) {
            $lineFilters = $filters + ['bank_account_id' => $bankAccount->id];
            $query = $this->postedLines($lineFilters, excludeReversalDocuments: true);
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

    private function taxElectronicBooksReport(array $filters, FinancialReportContext $context): array
    {
        $workbook = app(TaxElectronicBooksWorkbookService::class)->build($filters);
        $analysis = $workbook['analysis'];

        $payload = $this->reportPayload(
            key: $context->reportKey,
            title: 'دفاتر الکترونیک مالیاتی',
            subtitle: 'گزارش مالیاتی دوره ' . TaxElectronicBooksWorkbookService::PERIOD_FROM . ' تا ' . TaxElectronicBooksWorkbookService::PERIOD_TO,
            filters: $workbook['filters'],
            summary: [
                'lines' => $analysis['line_count'],
                'debit' => $analysis['total_debit'],
                'credit' => $analysis['total_credit'],
                'balance_difference' => $analysis['balance_difference'],
                'documents' => $analysis['document_count'],
            ],
            sections: $workbook['sections'],
        );

        $payload['analysis'] = $analysis;
        $payload['tax_export_rows'] = $workbook['tax_export_rows'];
        $payload['summary']['export_status'] = $analysis['export_status'] ?? null;
        $payload['summary']['export_ready'] = $analysis['export_ready'] ?? false;

        return $payload;
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
                $operating = $group->filter(fn ($line) => $line->account && (
                    str_starts_with((string) $line->account->code, '4')
                    || str_starts_with((string) $line->account->code, '5')
                    || str_starts_with((string) $line->account->code, '6')
                ));
                $revenue = $this->operatingRevenueAmount($operating);
                $cost = $this->operatingCostAmount($operating);

                return [
                    'project' => $project?->name ?: '-',
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $revenue - $cost,
                ];
            })->filter(fn (array $row) => $row['revenue'] !== 0.0 || $row['cost'] !== 0.0)->values();

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
                $operating = $group->filter(fn ($line) => $line->account && (
                    str_starts_with((string) $line->account->code, '4')
                    || str_starts_with((string) $line->account->code, '5')
                    || str_starts_with((string) $line->account->code, '6')
                ));
                $revenue = $this->operatingRevenueAmount($operating);
                $cost = $this->operatingCostAmount($operating);

                return [
                    'party' => $party?->name ?: '-',
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $revenue - $cost,
                ];
            })->filter(fn (array $row) => $row['revenue'] !== 0.0 || $row['cost'] !== 0.0)->values();

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
        $amountResolver = fn (array $row) => $this->analysisBalanceAmount($row, $prefix);

        return $this->reportPayload(
            key: $context->reportKey,
            title: $title,
            subtitle: $subtitle,
            filters: $filters,
            summary: [
                'accounts' => $balances->count(),
                'amount' => (float) $balances->sum($amountResolver),
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
                        'balance' => $amountResolver($row),
                    ])->values(),
                ],
            ],
        );
    }

    private function cashOrBankReport(string $field, string $title, string $subtitle, array $filters, FinancialReportContext $context): array
    {
        $filters = $this->normalizeFilters($filters);

        // Bank/cash list must use the same balance engine as detail views
        // (opening + posted lines via applyBankAccountFilter / applyCashboxFilter).
        if ($field === 'bank_account_id') {
            return $this->bankAccountsSummaryReport($title, $subtitle, $filters, $context);
        }

        if ($field === 'cashbox_id' && $context->reportKey === 'cash-statement') {
            return $this->cashboxesSummaryReport($title, $subtitle, $filters, $context);
        }

        $rows = $this->postedLines($filters, excludeReversalDocuments: true)->filter(fn ($line) => filled(data_get($line, $field)))->groupBy($field)->map(function (Collection $group, $key) use ($field, $filters) {
            $entity = Cashbox::find($key);
            $detailUrl = route('financial-reports.show', array_merge([
                'report' => 'cash-book',
                'cashbox_id' => (int) $key,
            ], $filters));

            return [
                'id' => (int) $key,
                'code' => $entity?->code ?: (string) $key,
                'name' => $entity?->name ?? '-',
                'opening' => (float) ($entity?->opening_balance ?? 0),
                'debit' => (float) $group->sum('debit'),
                'credit' => (float) $group->sum('credit'),
                'closing' => (float) ($entity?->opening_balance ?? 0) + (float) $group->sum('debit') - (float) $group->sum('credit'),
                'bank_account_id' => null,
                'cashbox_id' => (int) $key,
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

    private function bankAccountsSummaryReport(string $title, string $subtitle, array $filters, FinancialReportContext $context): array
    {
        $banks = BankAccount::query()
            ->when(! empty($filters['bank_account_id']), fn ($query) => $query->whereKey((int) $filters['bank_account_id']))
            ->orderBy('code')
            ->get();

        $rows = $banks->map(function (BankAccount $bankAccount) use ($filters) {
            $summary = $this->bankTransactions($bankAccount, $filters)['summary'];
            $detailUrl = route('financial-reports.show', array_merge([
                'report' => 'bank-statement',
                'bank_account_id' => $bankAccount->id,
            ], $filters));

            return [
                'id' => $bankAccount->id,
                'code' => $bankAccount->code,
                'name' => $bankAccount->bank_name,
                'opening' => (float) ($summary['opening_balance'] ?? 0),
                'debit' => (float) ($summary['period_debit'] ?? 0),
                'credit' => (float) ($summary['period_credit'] ?? 0),
                'closing' => (float) ($summary['closing_balance'] ?? 0),
                'bank_account_id' => $bankAccount->id,
                'cashbox_id' => null,
                'detail_url' => $detailUrl,
            ];
        })->filter(function (array $row) {
            return abs($row['opening']) > 0.00001
                || abs($row['debit']) > 0.00001
                || abs($row['credit']) > 0.00001
                || abs($row['closing']) > 0.00001;
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

    private function cashboxesSummaryReport(string $title, string $subtitle, array $filters, FinancialReportContext $context): array
    {
        $cashboxes = Cashbox::query()
            ->when(! empty($filters['cashbox_id']), fn ($query) => $query->whereKey((int) $filters['cashbox_id']))
            ->orderBy('code')
            ->get();

        $rows = $cashboxes->map(function (Cashbox $cashbox) use ($filters) {
            $summary = $this->cashTransactions($cashbox, $filters)['summary'];
            $detailUrl = route('financial-reports.show', array_merge([
                'report' => 'cash-statement',
                'cashbox_id' => $cashbox->id,
            ], $filters));

            return [
                'id' => $cashbox->id,
                'code' => $cashbox->code,
                'name' => $cashbox->name,
                'opening' => (float) ($summary['opening_balance'] ?? 0),
                'debit' => (float) ($summary['period_debit'] ?? 0),
                'credit' => (float) ($summary['period_credit'] ?? 0),
                'closing' => (float) ($summary['closing_balance'] ?? 0),
                'bank_account_id' => null,
                'cashbox_id' => $cashbox->id,
                'detail_url' => $detailUrl,
            ];
        })->filter(function (array $row) {
            return abs($row['opening']) > 0.00001
                || abs($row['debit']) > 0.00001
                || abs($row['credit']) > 0.00001
                || abs($row['closing']) > 0.00001;
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
            'cash-book', 'bank-book', 'bank-statement', 'cash-statement' => ['code', 'name', 'opening', 'debit', 'credit', 'closing'],
            'bank-transactions', 'cash-transactions' => ['date', 'document_number', 'account', 'detail_account', 'party', 'project', 'description', 'debit', 'credit', 'running_balance'],
            'bank-reconciliation' => ['code', 'bank_name', 'account_number', 'opening_balance', 'journal_debit', 'journal_credit', 'statement_balance', 'variance'],
            'cash-flow-by-period' => ['period', 'debit', 'credit', 'net_cash'],
            'sales-tax', 'purchase-tax', 'vat-summary' => ['number', 'date', 'party', 'taxable_amount', 'tax_amount', 'total_amount'],
            'tax-transactions' => ['date', 'document_number', 'account', 'description', 'debit', 'credit'],
            'tax-electronic-books' => ['row_number', 'date', 'ledger_code', 'ledger_title', 'subsidiary_code', 'subsidiary_title', 'description', 'debit', 'credit'],
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

        foreach (['fiscal_year_id', 'branch_id', 'project_id', 'party_id', 'account_id', 'bank_account_id', 'cashbox_id', 'per_page', 'fiscal_period_id'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $filters[$key] = (int) $filters[$key];
            }
        }

        $filters['direction'] = $filters['direction'] ?? 'desc';

        return $filters;
    }

    private function postedLines(array $filters, bool $excludeReversalDocuments = false): Collection
    {
        return $this->postedLineQuery($filters, $excludeReversalDocuments)
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id')
            ->get();
    }

    private function openingLines(array $filters, bool $excludeReversalDocuments = false): Collection
    {
        if (empty($filters['date_from'])) {
            return collect();
        }

        $openingFilters = $filters;
        $openingFilters['date_to'] = \Carbon\Carbon::parse($filters['date_from'])->subDay()->toDateString();

        return $this->postedLineQuery($openingFilters, $excludeReversalDocuments)
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id')
            ->get();
    }

    private function postedLineQuery(array $filters, bool $excludeReversalDocuments = false): Builder
    {
        $query = $this->reports->postedLineQuery($filters, $excludeReversalDocuments);

        if (! empty($filters['fiscal_period_id'])) {
            $query->where('accounting_documents.fiscal_period_id', $filters['fiscal_period_id']);
        }

        return $query;
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

    private function analysisBalanceAmount(array $row, string $prefix): float
    {
        $debit = (float) ($row['closing_debit'] ?? 0);
        $credit = (float) ($row['closing_credit'] ?? 0);

        return match (true) {
            str_starts_with($prefix, '5'), str_starts_with($prefix, '6') => $debit - $credit,
            default => $credit - $debit,
        };
    }

    private function operatingRevenueAmount(Collection $lines): float
    {
        return (float) $lines
            ->filter(fn ($line) => $line->account && str_starts_with((string) $line->account->code, '4'))
            ->sum(fn ($line) => max((float) $line->credit - (float) $line->debit, 0));
    }

    private function operatingCostAmount(Collection $lines): float
    {
        return (float) $lines
            ->filter(fn ($line) => $line->account && (
                str_starts_with((string) $line->account->code, '5')
                || str_starts_with((string) $line->account->code, '6')
            ))
            ->sum(fn ($line) => max((float) $line->debit - (float) $line->credit, 0));
    }

    private function filtersForUrl(array $filters): array
    {
        return collect($filters)
            ->only(['date_from', 'date_to', 'fiscal_year_id', 'branch_id', 'company_id', 'project_id', 'party_id', 'cost_center', 'comparison_scope'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function profitAndLossPeriodRange(array $filters): array
    {
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return [
                'start' => Carbon::parse($filters['date_from'])->startOfDay(),
                'end' => Carbon::parse($filters['date_to'])->endOfDay(),
            ];
        }

        if (! empty($filters['fiscal_year_id'])) {
            $fiscalYear = FiscalYear::find($filters['fiscal_year_id']);

            if ($fiscalYear) {
                return [
                    'start' => Carbon::parse($fiscalYear->start_date)->startOfDay(),
                    'end' => Carbon::parse($fiscalYear->end_date)->endOfDay(),
                ];
            }
        }

        return [
            'start' => now()->startOfYear(),
            'end' => now()->endOfYear(),
        ];
    }

    private function profitAndLossCacheKey(array $filters, array $range, string $comparisonScope): string
    {
        $snapshot = sprintf(
            '%s|%s|%s',
            (string) (AccountingDocument::query()->where('status', 'posted')->max('updated_at') ?? '0'),
            (string) (ChartAccount::query()->max('updated_at') ?? '0'),
            $comparisonScope
        );

        return 'financial-report:profit-loss:' . md5(json_encode([
            'filters' => $filters,
            'range' => [$range['start']->toDateString(), $range['end']->toDateString()],
            'snapshot' => $snapshot,
        ]));
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
            'detail_account_code' => $line->detailAccount?->code ?: '-',
            'detail_account_title' => $line->detailAccount?->title ?: '-',
            'detail_account' => trim(($line->detailAccount?->code ?: '') . ' - ' . ($line->detailAccount?->title ?: '')),
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

    private function bankStatementLineRow($line, Collection $linesByDocument, Collection $documentSources): array
    {
        $row = $this->lineRow($line);
        $documentId = (int) ($line->accounting_document_id ?? 0);
        $siblings = $linesByDocument->get($documentId, collect());
        $document = $documentSources->get($documentId) ?? $line->document;

        $party = $this->resolveBankStatementParty($line, $siblings, $document);
        if ($party) {
            $row['party'] = $party['name'];
            $row['party_id'] = $party['id'];
        } else {
            $row['party_id'] = $line->party_id ? (int) $line->party_id : null;
        }

        $project = $this->resolveBankStatementProject($line, $siblings, $document);
        if ($project) {
            $row['project'] = $project['name'];
            $row['project_id'] = $project['id'];
        } else {
            $row['project_id'] = $line->project_id ? (int) $line->project_id : null;
        }

        $row['description_parts'] = $this->resolveBankStatementDescriptionParts($line, $siblings, $document, $party, $project);
        $row['description'] = $row['description_parts']['full'];
        $row['description_primary'] = $row['description_parts']['primary'];
        $row['description_secondary'] = $row['description_parts']['secondary'];

        return $row;
    }

    private function treasuryStatementTransactions(
        string $treasuryFilterKey,
        int $treasuryId,
        float $openingBalance,
        string $subtitle,
        string $payloadKey,
        string $title,
        string $sectionTitle,
        array $filters,
    ): array {
        $filters = $this->normalizeFilters($filters);
        $filters[$treasuryFilterKey] = $treasuryId;

        $partyFilterId = ! empty($filters['party_id']) ? (int) $filters['party_id'] : null;
        $projectFilterId = ! empty($filters['project_id']) ? (int) $filters['project_id'] : null;
        $scopedByCounterparty = $partyFilterId || $projectFilterId;

        $lineFilters = $filters;
        if ($scopedByCounterparty) {
            unset($lineFilters['party_id'], $lineFilters['project_id']);
        }

        $baseQuery = $this->postedLineQuery($lineFilters, excludeReversalDocuments: true)
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id');

        $openingLines = collect();
        if (! empty($filters['date_from'])) {
            $openingFilters = $lineFilters;
            $openingFilters['date_to'] = Carbon::parse($openingFilters['date_from'])->subDay()->toDateString();
            unset($openingFilters['date_from']);

            $openingLines = $this->postedLineQuery($openingFilters, excludeReversalDocuments: true)
                ->orderBy('accounting_documents.document_date')
                ->orderBy('accounting_document_lines.id')
                ->get();
        }

        $periodLines = $baseQuery->get();
        [$linesByDocument, $documentSources] = $this->bankStatementLineContext(
            $openingLines->concat($periodLines)->unique('id'),
        );

        $openingRows = $this->mapBankStatementRows($openingLines, $linesByDocument, $documentSources);
        if ($scopedByCounterparty) {
            $openingRows = $this->filterBankStatementRowsByCounterparty($openingRows, $partyFilterId, $projectFilterId);
        }

        $treasuryOpening = $scopedByCounterparty ? 0.0 : $openingBalance;
        $startingBalance = $treasuryOpening + (float) $openingRows->sum(
            fn (array $row) => (float) $row['debit'] - (float) $row['credit'],
        );
        $computedOpeningBalance = $startingBalance;

        $periodRows = $this->mapBankStatementRows($periodLines, $linesByDocument, $documentSources);
        if ($scopedByCounterparty) {
            $periodRows = $this->filterBankStatementRowsByCounterparty($periodRows, $partyFilterId, $projectFilterId);
        }

        $runningBalance = $startingBalance;
        $rows = $periodRows->map(function (array $row) use (&$runningBalance) {
            $runningBalance += (float) $row['debit'] - (float) $row['credit'];
            $row['running_balance'] = $runningBalance;

            return $row;
        })->values();

        return $this->reportPayload(
            key: $payloadKey,
            title: $title,
            subtitle: $subtitle,
            filters: $filters,
            summary: [
                'opening_balance' => $computedOpeningBalance,
                'period_debit' => (float) $periodRows->sum('debit'),
                'period_credit' => (float) $periodRows->sum('credit'),
                'closing_balance' => $runningBalance,
                'line_count' => $rows->count(),
            ],
            sections: [
                [
                    'title' => $sectionTitle,
                    'headers' => ['تاریخ', 'شماره سند', 'طرف حساب', 'پروژه', 'شرح', 'بدهکار', 'بستانکار', 'مانده جاری'],
                    'rows' => $rows,
                    'columns' => ['date', 'document_number', 'party', 'project', 'description', 'debit', 'credit', 'running_balance'],
                ],
            ],
        );
    }

    private function bankStatementLineContext(Collection $lines): array
    {
        $documentIds = $lines->pluck('accounting_document_id')->unique()->filter()->all();

        $linesByDocument = AccountingDocumentLine::query()
            ->whereIn('accounting_document_id', $documentIds)
            ->with(['party', 'project'])
            ->get()
            ->groupBy('accounting_document_id');

        $documentSources = AccountingDocument::query()
            ->whereIn('id', $documentIds)
            ->with(['source'])
            ->get()
            ->keyBy('id');

        return [$linesByDocument, $documentSources];
    }

    private function mapBankStatementRows(Collection $lines, Collection $linesByDocument, Collection $documentSources): Collection
    {
        return $lines
            ->map(fn ($line) => $this->bankStatementLineRow($line, $linesByDocument, $documentSources))
            ->values();
    }

    private function filterBankStatementRowsByCounterparty(Collection $rows, ?int $partyFilterId, ?int $projectFilterId): Collection
    {
        return $rows->filter(function (array $row) use ($partyFilterId, $projectFilterId) {
            if ($partyFilterId && (int) ($row['party_id'] ?? 0) !== $partyFilterId) {
                return false;
            }

            if ($projectFilterId && (int) ($row['project_id'] ?? 0) !== $projectFilterId) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @param  array{id:int,name:string}|null  $party
     * @param  array{id:int,name:string}|null  $project
     * @return array{primary: string, secondary: ?string, full: string}
     */
    private function resolveBankStatementDescriptionParts($line, Collection $siblings, ?AccountingDocument $document, ?array $party, ?array $project): array
    {
        $lineDescription = trim((string) ($line->description ?? ''));
        $documentDescription = trim((string) ($document?->description ?? ''));
        $isGeneric = $lineDescription === '' || in_array($lineDescription, self::GENERIC_BANK_LINE_DESCRIPTIONS, true);
        $source = $document?->source;
        $sourceNote = $this->bankStatementSourceNote($source);
        $categoryDetail = $this->bankStatementCategoryDetail($source, $siblings, $line, $documentDescription);

        if ($isGeneric) {
            $primaryParts = array_values(array_filter([
                $this->bankStatementActionLabel($lineDescription, $line),
                $party['name'] ?? null,
                $categoryDetail,
            ], fn ($part) => is_string($part) && trim($part) !== ''));

            $primary = $primaryParts !== [] ? implode(' — ', $primaryParts) : ($documentDescription ?: '-');
        } else {
            $primary = $lineDescription;
        }

        if (($project['name'] ?? null) && ! str_contains($primary, $project['name'])) {
            $primary .= ' — پروژه: ' . $project['name'];
        }

        $secondary = $this->bankStatementSecondaryDescription(
            $primary,
            $sourceNote,
            $documentDescription,
            $categoryDetail,
        );

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'full' => $secondary ? $primary . ' — ' . $secondary : $primary,
        ];
    }

    private function bankStatementSourceNote(mixed $source): ?string
    {
        if ($source instanceof TreasuryTransaction || $source instanceof PaymentVoucher || $source instanceof ReceiptVoucher || $source instanceof FinancialTransaction) {
            $note = trim((string) ($source->description ?? ''));

            return $note !== '' ? $note : null;
        }

        if ($source instanceof Invoice) {
            $note = trim((string) ($source->description ?? ''));

            return $note !== '' ? $note : null;
        }

        return null;
    }

    private function bankStatementCategoryDetail(mixed $source, Collection $siblings, $line, string $documentDescription): ?string
    {
        if ($source instanceof FinancialTransaction) {
            $source->loadMissing(['detailAccount', 'chartAccount']);
            $category = trim((string) ($source->detailAccount?->title ?: $source->category ?: $source->chartAccount?->title ?: ''));
            if ($category !== '') {
                $kind = $source->type === 'income' ? 'سند درآمد مالی' : 'سند هزینه مالی';

                return $kind . ' - ' . $category;
            }
        }

        if ($source instanceof Invoice) {
            return 'فاکتور ' . $source->number;
        }

        $sibling = $this->bankStatementMeaningfulSibling($siblings, $line);
        if ($sibling) {
            return $sibling;
        }

        return $documentDescription !== '' ? $documentDescription : null;
    }

    private function bankStatementMeaningfulSibling(Collection $siblings, $line): ?string
    {
        $sibling = $siblings->first(function ($sibling) use ($line) {
            if ((int) $sibling->id === (int) $line->id) {
                return false;
            }

            $description = trim((string) ($sibling->description ?? ''));

            return $description !== '' && ! in_array($description, self::GENERIC_BANK_LINE_DESCRIPTIONS, true);
        });

        return $sibling ? trim((string) $sibling->description) : null;
    }

    private function bankStatementSecondaryDescription(string $primary, ?string $sourceNote, string $documentDescription, ?string $categoryDetail): ?string
    {
        $candidates = array_values(array_filter([
            $sourceNote,
            $documentDescription !== '' && $documentDescription !== $categoryDetail ? $documentDescription : null,
        ]));

        foreach ($candidates as $candidate) {
            if ($this->isDistinctBankStatementDescription($candidate, $primary)) {
                return trim($candidate);
            }
        }

        return null;
    }

    private function isDistinctBankStatementDescription(string $candidate, string ...$existing): bool
    {
        $normalizedCandidate = $this->normalizeBankStatementDescription($candidate);

        if ($normalizedCandidate === '') {
            return false;
        }

        foreach ($existing as $item) {
            $normalizedExisting = $this->normalizeBankStatementDescription($item);

            if ($normalizedExisting === '') {
                continue;
            }

            if ($normalizedCandidate === $normalizedExisting) {
                return false;
            }

            if (str_contains($normalizedExisting, $normalizedCandidate) || str_contains($normalizedCandidate, $normalizedExisting)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeBankStatementDescription(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
    }

    private function bankStatementActionLabel(string $lineDescription, $line): string
    {
        return match ($lineDescription) {
            'پرداخت خزانه', 'انتقال خروجی خزانه', 'پرداخت بانکی', 'برداشت شریک' => 'پرداخت',
            'دریافت خزانه', 'انتقال ورودی خزانه', 'دریافت بانکی', 'واریز شریک' => 'دریافت',
            default => (float) $line->debit > 0
                ? 'دریافت'
                : ((float) $line->credit > 0 ? 'پرداخت' : 'تراکنش'),
        };
    }

    private function resolveBankStatementParty($line, Collection $siblings, ?AccountingDocument $document): ?array
    {
        if ($line->party_id && $line->party?->name) {
            return ['id' => (int) $line->party_id, 'name' => $line->party->name];
        }

        $source = $document?->source;

        if ($source instanceof TreasuryTransaction) {
            $source->loadMissing('party');

            if ($source->party_id && $source->party?->name) {
                return ['id' => (int) $source->party_id, 'name' => $source->party->name];
            }
        }

        if ($source instanceof Invoice) {
            $source->loadMissing('party');

            if ($source->party_id && $source->party?->name) {
                return ['id' => (int) $source->party_id, 'name' => $source->party->name];
            }
        }

        $sibling = $siblings
            ->first(fn ($sibling) => (int) $sibling->id !== (int) $line->id && $sibling->party_id);

        if ($sibling?->party_id && $sibling->party?->name) {
            return ['id' => (int) $sibling->party_id, 'name' => $sibling->party->name];
        }

        return null;
    }

    private function resolveBankStatementProject($line, Collection $siblings, ?AccountingDocument $document): ?array
    {
        if ($line->project_id && $line->project?->name) {
            return ['id' => (int) $line->project_id, 'name' => $line->project->name];
        }

        $source = $document?->source;

        if ($source instanceof TreasuryTransaction && $source->project_id) {
            $source->loadMissing('project');
            $project = $source->project ?: Project::query()->find($source->project_id);

            if ($project?->name) {
                return ['id' => (int) $project->id, 'name' => $project->name];
            }
        }

        $sibling = $siblings
            ->first(fn ($sibling) => (int) $sibling->id !== (int) $line->id && $sibling->project_id);

        if ($sibling?->project_id && $sibling->project?->name) {
            return ['id' => (int) $sibling->project_id, 'name' => $sibling->project->name];
        }

        return null;
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

    /**
     * @return Collection<int, array{party: Party, debit: float, credit: float, balance: float, last_date: ?Carbon, lines: Collection}>
     */
    private function unifiedPartyStatementBalances(array $filters): Collection
    {
        $lines = $this->reports->partyStatementRows(null, null, $filters);

        return $lines->groupBy('party_id')->map(function (Collection $group) {
            $party = $group->first()?->party;
            if (! $party) {
                return null;
            }

            $debit = (float) $group->sum('debit');
            $credit = (float) $group->sum('credit');
            $lastDate = $group->max(fn ($line) => $line->document?->document_date);

            return [
                'party' => $party,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $debit - $credit,
                'last_date' => $lastDate ? Carbon::parse($lastDate) : null,
                'lines' => $group,
            ];
        })->filter()->values();
    }

    private function partyQualifiesForSupplierScope(?Party $party, Collection $lines): bool
    {
        if (! $party) {
            return false;
        }

        $party->loadMissing(['types:id,name', 'employees:id,party_id']);

        if ($party->types->whereIn('name', ['vendor', 'supplier', 'colleague'])->isNotEmpty()) {
            return true;
        }

        if ($party->employees->isNotEmpty()) {
            return true;
        }

        if ($lines->contains(function ($line) {
            $code = (string) ($line->account?->code ?? '');

            return $code !== '' && str_starts_with($code, '2101');
        })) {
            return true;
        }

        return AccountingDocumentLine::query()
            ->where('party_id', $party->id)
            ->whereHas('account', fn (Builder $query) => $query->where('code', 'like', '2101%'))
            ->exists();
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

    /**
     * @param  Collection<int, array{balance?: float|int, days?: int}>  $rows
     * @return array{
     *     total_payables: float,
     *     overdue: float,
     *     over_90: float,
     *     party_count: int,
     *     invoice_count: int,
     *     buckets: array<string, float>
     * }
     */
    private function agingBalanceSummary(Collection $rows, string $totalKey): array
    {
        $buckets = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'over_90' => 0.0,
        ];

        $partyCount = 0;

        foreach ($rows as $row) {
            $amount = abs((float) ($row['balance'] ?? 0));
            if ($amount < 0.00001) {
                continue;
            }

            $partyCount++;
            $days = (int) ($row['days'] ?? 0);

            if ($days <= 0) {
                $buckets['current'] += $amount;
            } elseif ($days <= 30) {
                $buckets['days_1_30'] += $amount;
            } elseif ($days <= 60) {
                $buckets['days_31_60'] += $amount;
            } elseif ($days <= 90) {
                $buckets['days_61_90'] += $amount;
            } else {
                $buckets['over_90'] += $amount;
            }
        }

        $total = array_sum($buckets);

        return [
            $totalKey => $total,
            'overdue' => $total - $buckets['current'],
            'over_90' => $buckets['over_90'],
            'party_count' => $partyCount,
            'invoice_count' => $partyCount,
            'buckets' => $buckets,
        ];
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
