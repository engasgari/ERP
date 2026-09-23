<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ExecutiveDashboardRepository;
use App\Repositories\SalesReportRepository;
use App\Services\Crm\CrmDashboardService;
use App\Support\ExecutiveDashboardPeriod;
use App\Support\SalesReportFilters;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Facades\Route;

class ExecutiveDashboardService
{
    public function __construct(
        private readonly SalesReportRepository $salesReports,
        private readonly FinancialReportService $financialReports,
        private readonly ExecutiveDashboardRepository $executive,
        private readonly ProjectCostingService $projectCosting,
        private readonly CrmDashboardService $crmDashboard,
        private readonly InsuranceLiabilityService $insuranceLiabilities,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, string $preset, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $period = ExecutiveDashboardPeriod::resolve($preset, $dateFrom, $dateTo);
        $filters = SalesReportFilters::normalize([
            'date_from' => $period['date_from'],
            'date_to' => $period['date_to'],
        ]);
        $previousFilters = SalesReportFilters::normalize(
            ExecutiveDashboardPeriod::previous($period),
        );

        $kpis = [];
        $charts = [];
        $fiscalChartFilters = null;
        $financial = null;
        $sales = null;
        $receivables = null;
        $payables = null;
        $statutoryLiabilities = null;
        $projects = null;
        $purchase = null;
        $crm = null;
        $alerts = [];

        if ($user->hasPermission('reports.sales.view') || $user->hasPermission('commerce.view')) {
            $currentSales = $this->salesReports->invoiceTotals($filters);
            $previousSales = $this->salesReports->invoiceTotals($previousFilters);
            $receivables = $this->salesReports->receivableSummary($filters);

            $salesGrowth = ExecutiveDashboardPeriod::growthPercent(
                (float) $currentSales['total_amount'],
                (float) $previousSales['total_amount'],
            );

            $collectionGrowth = ExecutiveDashboardPeriod::growthPercent(
                (float) $currentSales['paid_amount'],
                (float) $previousSales['paid_amount'],
            );

            $avgInvoice = $currentSales['invoice_count'] > 0
                ? (float) $currentSales['total_amount'] / $currentSales['invoice_count']
                : 0.0;

            $sales = [
                'totals' => $currentSales,
                'previous_totals' => $previousSales,
                'avg_invoice' => $avgInvoice,
                'new_customers' => $this->executive->newCustomersInPeriod($filters),
                'active_customers' => $this->executive->activeCustomerCount(),
                'top_customers' => $this->salesReports->topCustomers($filters)->take(5)->map(fn ($row) => [
                    'label' => $row->party_name,
                    'value' => (float) $row->total_amount,
                ])->values()->all(),
                'top_products' => $this->salesReports->aggregateByProduct($filters)->take(5)->map(fn ($row) => [
                    'label' => $row->item_name,
                    'value' => (float) $row->net_amount,
                ])->values()->all(),
                'unsettled_count' => (int) Invoice::query()
                    ->where('direction', 'sale')
                    ->where('status', 'confirmed')
                    ->whereNull('settled_at')
                    ->count(),
            ];

            $fiscalChartFilters = SalesReportFilters::normalize(
                SalesReportFilters::currentFiscalYearFilters() ?: SalesReportFilters::resolvePreset('this_year'),
            );
            $charts['sales_trend'] = $this->monthlySalesChartPoints($fiscalChartFilters);
            $charts['sales_by_category'] = $this->salesByCategoryChartPoints($fiscalChartFilters);
            $charts['sales_by_customer'] = $this->salesByCustomerChartPoints($fiscalChartFilters);
            $charts['sales_fiscal_label'] = SalesReportFilters::currentFiscalYear()?->title;

            $kpis[] = $this->kpi(
                'sales',
                'فروش',
                (float) $currentSales['total_amount'],
                'money',
                $salesGrowth,
                route('sales-reports.show', array_merge(['report' => 'sales'], $filters)),
            );

            $kpis[] = $this->kpi(
                'collections',
                'وصولی',
                (float) $currentSales['paid_amount'],
                'money',
                $collectionGrowth,
                route('sales-reports.show', array_merge(['report' => 'sales'], $filters)),
            );

            $kpis[] = $this->kpi(
                'receivables',
                'مطالبات',
                (float) $receivables['total_receivables'],
                'money',
                null,
                route('sales-reports.show', ['report' => 'receivables']),
            );

            $kpis[] = $this->kpi(
                'active_customers',
                'مشتریان فعال',
                (float) $this->executive->activeCustomerCount(),
                'number',
                null,
                route('parties.index', ['type' => 'customer']),
            );

            if ($receivables['overdue'] > 0) {
                $alerts[] = $this->alert(
                    'warning',
                    'مطالبات معوق',
                    formatMoney((float) $receivables['overdue']).' ریال سررسید گذشته',
                    route('sales-reports.show', ['report' => 'receivables']),
                );
            }

            if ($receivables['over_90'] > 0) {
                $alerts[] = $this->alert(
                    'critical',
                    'مطالبات ۹۰+ روز',
                    formatMoney((float) $receivables['over_90']).' ریال',
                    route('financial-reports.show', ['report' => 'accounts-receivable-aging']),
                );
            }
        }

        if ($user->hasPermission('financial.reports.view') || $user->hasPermission('financial.view')) {
            $liquidity = $this->liquiditySnapshot();
            $payables = $this->financialReports->supplierPayableSummary([]);
            $statutoryLiabilities = $this->statutoryLiabilitiesSnapshot($user);

            $financial = [
                'cash_total' => $liquidity['cash_total'],
                'bank_total' => $liquidity['bank_total'],
                'liquidity_total' => $liquidity['total'],
                'cash_accounts' => $liquidity['cash_rows'],
                'bank_accounts' => $liquidity['bank_rows'],
            ];

            $kpis[] = $this->kpi(
                'liquidity',
                'نقد و بانک',
                $liquidity['total'],
                'money',
                null,
                route('financial-reports.index'),
            );

            $kpis[] = $this->kpi(
                'payables',
                'بدهی تأمین‌کنندگان',
                (float) $payables['total_payables'],
                'money',
                null,
                route('financial-reports.show', ['report' => 'accounts-payable-aging']),
            );

            if ($payables['overdue'] > 0) {
                $alerts[] = $this->alert(
                    'warning',
                    'بدهی سررسید گذشته',
                    formatMoney((float) $payables['overdue']).' ریال',
                    route('financial-reports.show', ['report' => 'accounts-payable-aging']),
                );
            }

            if ($user->hasPermission('financial.reports.view')) {
                $profitReport = $this->financialReports->report('income-statement', $filters);
                $netProfit = (float) data_get($profitReport, 'summary.net_profit', data_get($profitReport, 'summary.profit', 0));
                if ($netProfit != 0.0 || ! empty($profitReport['sections'])) {
                    $kpis[] = $this->kpi(
                        'net_profit',
                        'سود / زیان دوره',
                        $netProfit,
                        'money',
                        null,
                        route('financial-reports.profit-loss', $filters),
                    );
                }

                $chartFilters = $fiscalChartFilters ?? SalesReportFilters::normalize(
                    SalesReportFilters::currentFiscalYearFilters() ?: SalesReportFilters::resolvePreset('this_year'),
                );
                $charts['profit_monthly'] = $this->monthlyProfitChartPayload($chartFilters);
                $charts['sales_fiscal_label'] = $charts['sales_fiscal_label']
                    ?? SalesReportFilters::currentFiscalYear()?->title;
            }
        }

        if ($user->hasPermission('commerce.view')) {
            $purchaseCurrent = $this->executive->purchaseTotals($filters);
            $purchasePrevious = $this->executive->purchaseTotals($previousFilters);
            $purchaseGrowth = ExecutiveDashboardPeriod::growthPercent(
                (float) $purchaseCurrent['total_amount'],
                (float) $purchasePrevious['total_amount'],
            );

            $purchase = [
                'totals' => $purchaseCurrent,
                'previous_totals' => $purchasePrevious,
                'open_orders' => $this->executive->openPurchaseOrdersCount(),
            ];

            if (! collect($kpis)->contains(fn ($k) => $k['key'] === 'payables')) {
                $payables = $payables ?? $this->financialReports->supplierPayableSummary([]);
            }
        }

        if ($user->hasPermission('projects.view')) {
            $counts = $this->executive->projectCounts();
            $projectRows = $this->executive->importantProjects();

            foreach ($projectRows as &$row) {
                $row['revenue'] = 0.0;
                $row['profit'] = 0.0;
                $project = Project::query()->find($row['id']);
                if ($project) {
                    $summary = $this->projectCosting->summary($project);
                    $row['revenue'] = (float) ($summary['revenue'] ?? 0);
                    $row['profit'] = (float) ($summary['profit_net'] ?? 0);
                }
            }
            unset($row);

            $projects = [
                'counts' => $counts,
                'items' => $projectRows,
            ];

            $kpis[] = $this->kpi(
                'active_projects',
                'پروژه‌های فعال',
                (float) $counts['active'],
                'number',
                null,
                route('projects.index', ['status' => 'active']),
            );

            if ($counts['overdue'] > 0) {
                $alerts[] = $this->alert(
                    'warning',
                    'پروژه‌های عقب‌افتاده',
                    $counts['overdue'].' پروژه از موعد عبور کرده‌اند',
                    route('projects.index'),
                );
            }

            $charts['project_status'] = collect(Project::STATUSES)
                ->map(function ($label, $status) {
                    $count = Project::query()->where('status', $status)->count();

                    return ['label' => $label, 'value' => (float) $count];
                })
                ->filter(fn ($row) => $row['value'] > 0)
                ->values()
                ->all();
        }

        if ($user->hasPermission('crm.dashboard.view') && Route::has('crm.dashboard')) {
            $crmMetrics = $this->crmDashboard->metrics($user);
            $crmCharts = $this->crmDashboard->charts($user);
            $crm = [
                'metrics' => $crmMetrics,
                'pipeline_chart' => $crmCharts['opportunities_by_stage']->map(fn ($row) => [
                    'label' => $row->stage?->title ?? '—',
                    'value' => (float) ($row->amount ?? 0),
                ])->values()->all(),
            ];

            if (($crmMetrics['overdue_tasks'] ?? 0) > 0) {
                $alerts[] = $this->alert(
                    'warning',
                    'وظایف CRM سررسید گذشته',
                    (int) $crmMetrics['overdue_tasks'].' مورد',
                    route('crm.tasks.index'),
                );
            }
        }

        $draftInvoices = $this->executive->draftInvoicesCount();
        if ($draftInvoices > 0 && $user->hasPermission('commerce.view')) {
            $alerts[] = $this->alert(
                'info',
                'فاکتور پیش‌نویس',
                $draftInvoices.' فاکتور در انتظار تأیید',
                route('invoices.index', ['status' => 'draft']),
            );
        }

        if ($receivables) {
            $charts['receivable_aging'] = [
                ['label' => 'جاری', 'value' => (float) $receivables['buckets']['current']],
                ['label' => '۱–۳۰', 'value' => (float) $receivables['buckets']['days_1_30']],
                ['label' => '۳۱–۶۰', 'value' => (float) $receivables['buckets']['days_31_60']],
                ['label' => '۶۱–۹۰', 'value' => (float) $receivables['buckets']['days_61_90']],
                ['label' => '۹۰+', 'value' => (float) $receivables['buckets']['over_90']],
            ];
        }

        $debtors = ($user->hasPermission('reports.sales.view') || $user->hasPermission('commerce.view'))
            ? $this->executive->topDebtorCustomers()
            : [];

        if ($statutoryLiabilities === null && (
            $user->hasPermission('financial.reports.view')
            || $user->hasPermission('financial.view')
            || $user->hasPermission('insurance.view')
        )) {
            $statutoryLiabilities = $this->statutoryLiabilitiesSnapshot($user);
        }

        return [
            'generated_at' => now(),
            'period' => $period,
            'filters' => $filters,
            'kpis' => $kpis,
            'charts' => $charts,
            'financial' => $financial,
            'sales' => $sales,
            'receivables' => $receivables,
            'payables' => $payables,
            'statutory_liabilities' => $statutoryLiabilities,
            'purchase' => $purchase,
            'projects' => $projects,
            'crm' => $crm,
            'alerts' => $alerts,
            'debtors' => $debtors,
        ];
    }

    /**
     * @return array{cash_total: float, bank_total: float, total: float, cash_rows: list<array<string, mixed>>, bank_rows: list<array<string, mixed>>}
     */
    private function liquiditySnapshot(): array
    {
        $filters = ['date_to' => Carbon::today()->toDateString()];

        $bankReport = $this->financialReports->report('bank-statement', $filters);
        $cashReport = $this->financialReports->report('cash-statement', $filters);

        $bankRows = collect($bankReport['sections'][0]['rows'] ?? [])->values()->all();
        $cashRows = collect($cashReport['sections'][0]['rows'] ?? [])->values()->all();

        $bankTotal = (float) collect($bankRows)->sum('closing');
        $cashTotal = (float) collect($cashRows)->sum('closing');

        return [
            'bank_rows' => $bankRows,
            'cash_rows' => $cashRows,
            'bank_total' => $bankTotal,
            'cash_total' => $cashTotal,
            'total' => $bankTotal + $cashTotal,
        ];
    }

    /**
     * @return array{
     *     vat_payable: float,
     *     insurance_payable: float,
     *     total: float,
     *     vat_report_url: string|null,
     *     insurance_url: string|null
     * }|null
     */
    private function statutoryLiabilitiesSnapshot(User $user): ?array
    {
        $canVat = $user->hasPermission('financial.reports.view') || $user->hasPermission('financial.view');
        $canInsurance = $user->hasPermission('insurance.view');

        if (! $canVat && ! $canInsurance) {
            return null;
        }

        $vatPayable = $canVat
            ? $this->financialReports->liabilityAccountBalance([], '2102')
            : 0.0;
        $insurancePayable = $canInsurance
            ? (float) $this->insuranceLiabilities->summary()['balance']
            : 0.0;

        return [
            'vat_payable' => round($vatPayable, 2),
            'insurance_payable' => round($insurancePayable, 2),
            'total' => round($vatPayable + $insurancePayable, 2),
            'vat_report_url' => $canVat && Route::has('financial-reports.show')
                ? route('financial-reports.show', ['report' => 'balance-sheet'])
                : null,
            'insurance_url' => $canInsurance && Route::has('insurance.payments.index')
                ? route('insurance.payments.index')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(
        string $key,
        string $title,
        float $value,
        string $format,
        ?float $deltaPercent,
        ?string $url,
    ): array {
        $direction = 'flat';
        if ($deltaPercent !== null) {
            if ($deltaPercent > 0) {
                $direction = 'up';
            } elseif ($deltaPercent < 0) {
                $direction = 'down';
            }
        }

        return [
            'key' => $key,
            'title' => $title,
            'value' => $value,
            'format' => $format,
            'delta_percent' => $deltaPercent,
            'delta_direction' => $direction,
            'url' => $url,
        ];
    }

    /**
     * @return array{tone: string, title: string, message: string, url: string|null}
     */
    private function alert(string $tone, string $title, string $message, ?string $url): array
    {
        return compact('tone', 'title', 'message', 'url');
    }

    /**
     * @return list<array{label: string, value: float, title: string}>
     */
    private function monthlySalesChartPoints(array $filters): array
    {
        $byMonth = $this->salesReports->aggregateSalesByJalaliMonth($filters)->keyBy('period');

        $points = [];

        foreach ($this->jalaliMonthPeriods() as $period) {
            $amount = (float) ($byMonth->get($period['key'])?->total_amount ?? 0);

            $points[] = [
                'label' => $period['label'],
                'value' => $amount,
                'title' => $period['label'].' — '.formatMoney($amount).' ریال',
            ];
        }

        return $points;
    }

    /**
     * @return array{series: list<array{key: string, label: string}>, points: list<array{label: string, period: string, values: array<string, float>}>}
     */
    private function monthlyProfitChartPayload(array $filters): array
    {
        $salesByMonth = $this->salesReports->aggregateSalesByJalaliMonth($filters)->keyBy('period');
        $purchaseByMonth = $this->salesReports->aggregatePurchasesByJalaliMonthSplit($filters)->keyBy('period');
        $expenses = $this->executive->aggregateOperatingExpensesByJalaliMonth($filters);

        $series = [
            ['key' => 'sales', 'label' => 'فروش'],
            ['key' => 'purchase_product', 'label' => 'خرید کالا'],
            ['key' => 'purchase_service', 'label' => 'خرید خدمات'],
            ['key' => 'payroll', 'label' => 'حقوق و دستمزد'],
            ['key' => 'other_expense', 'label' => 'سایر هزینه'],
            ['key' => 'profit', 'label' => 'سود'],
        ];

        $points = [];

        foreach ($this->jalaliMonthPeriods() as $period) {
            $key = $period['key'];
            $sales = (float) ($salesByMonth->get($key)?->total_amount ?? 0);
            $purchaseProduct = (float) ($purchaseByMonth->get($key)?->product_amount ?? 0);
            $purchaseService = (float) ($purchaseByMonth->get($key)?->service_amount ?? 0);
            $payroll = (float) ($expenses['payroll'][$key] ?? 0);
            $otherExpense = (float) ($expenses['other'][$key] ?? 0);
            $costs = $purchaseProduct + $purchaseService + $payroll + $otherExpense;
            $profit = $sales - $costs;

            $points[] = [
                'label' => $period['label'],
                'period' => $key,
                'values' => [
                    'sales' => $sales,
                    'purchase_product' => $purchaseProduct,
                    'purchase_service' => $purchaseService,
                    'payroll' => $payroll,
                    'other_expense' => $otherExpense,
                    'profit' => $profit,
                ],
            ];
        }

        return [
            'series' => $series,
            'points' => $points,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function jalaliMonthPeriods(): array
    {
        $fiscalYear = SalesReportFilters::currentFiscalYear();
        $today = Carbon::today();

        if ($fiscalYear) {
            $cursor = Verta::instance($fiscalYear->start_date)->startMonth();
            $end = Verta::instance(min($today, $fiscalYear->end_date));
        } else {
            $cursor = Verta::now()->startYear();
            $end = Verta::now();
        }

        $periods = [];

        while ($this->jalaliMonthCompare($cursor, $end) <= 0) {
            $periods[] = [
                'key' => $cursor->format('Y-m'),
                'label' => getPersianMonthName((int) $cursor->month),
            ];
            $cursor = $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function salesByCategoryChartPoints(array $filters): array
    {
        return $this->salesReports->salesByCategory($filters)
            ->take(10)
            ->map(fn ($row) => [
                'label' => (string) $row->category,
                'value' => (float) $row->total_amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function salesByCustomerChartPoints(array $filters): array
    {
        return $this->salesReports->topCustomers($filters, 4)
            ->map(fn ($row) => [
                'label' => (string) $row->party_name,
                'value' => (float) $row->total_amount,
            ])
            ->values()
            ->all();
    }

    private function jalaliMonthCompare(Verta $left, Verta $right): int
    {
        $leftKey = ((int) $left->year * 12) + (int) $left->month;
        $rightKey = ((int) $right->year * 12) + (int) $right->month;

        return $leftKey <=> $rightKey;
    }
}
