<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Repositories\ExecutiveDashboardRepository;
use App\Repositories\SalesReportRepository;
use App\Services\Crm\CrmDashboardService;
use App\Support\SalesReportFilters;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Sales Intelligence Dashboard payload builder.
 * All money figures come from SalesReportRepository / SalesProfitCalculationService — no Blade math.
 */
class SalesDashboardService
{
    public function __construct(
        private readonly SalesReportRepository $salesReports,
        private readonly SalesProfitCalculationService $profit,
        private readonly InvoiceCalculationService $invoices,
        private readonly CrmDashboardService $crmDashboard,
        private readonly ExecutiveDashboardRepository $executive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters = []): array
    {
        $filters = SalesReportFilters::normalize($filters);
        $today = Carbon::today();
        $thisMonthStart = $today->copy()->startOfMonth()->toDateString();
        $lastMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $fiscalYear = ! empty($filters['fiscal_year_id'])
            ? \App\Models\FiscalYear::query()->find($filters['fiscal_year_id'])
            : SalesReportFilters::currentFiscalYear();

        $fiscalFilters = $filters;
        if ($fiscalYear) {
            $fiscalFilters['fiscal_year_id'] = $fiscalYear->id;
        }

        $todayTotals = $this->salesReports->invoiceTotals(array_merge($filters, [
            'date_from' => $today->toDateString(),
            'date_to' => $today->toDateString(),
        ]));
        $thisMonthTotals = $this->salesReports->invoiceTotals(array_merge($filters, [
            'date_from' => $thisMonthStart,
            'date_to' => $today->toDateString(),
        ]));
        $lastMonthTotals = $this->salesReports->invoiceTotals([
            'date_from' => $lastMonthStart,
            'date_to' => $lastMonthEnd,
        ]);
        $yearTotals = $this->salesReports->invoiceTotals($fiscalFilters);
        $receivables = $this->salesReports->receivableSummary($filters);

        $monthProfit = $this->profitSummaryForFilters(array_merge($filters, [
            'date_from' => $thisMonthStart,
            'date_to' => $today->toDateString(),
        ]));
        $yearProfit = $this->profitSummaryForFilters($fiscalFilters);

        $growth = $this->growthPercent(
            (float) $thisMonthTotals['total_amount'],
            (float) $lastMonthTotals['total_amount'],
        );

        $avgInvoice = ((int) $thisMonthTotals['invoice_count'] > 0)
            ? $this->invoices->round(((float) $thisMonthTotals['total_amount']) / (int) $thisMonthTotals['invoice_count'])
            : 0.0;

        $crm = null;
        if ($user->hasPermission('crm.dashboard.view') && Route::has('crm.dashboard')) {
            $crm = $this->crmDashboard->metrics($user);
        }

        $newCustomers = $this->executive->newCustomersInPeriod([
            'date_from' => $thisMonthStart,
            'date_to' => $today->toDateString(),
        ]);

        $kpis = [
            $this->kpi('sales_today', 'فروش امروز', (float) $todayTotals['total_amount'], 'money', null, route('sales-reports.show', ['report' => 'sales', 'date_from' => $today->toDateString(), 'date_to' => $today->toDateString()])),
            $this->kpi('sales_month', 'فروش این ماه', (float) $thisMonthTotals['total_amount'], 'money', $growth, route('sales-reports.show', ['report' => 'sales', 'date_from' => $thisMonthStart, 'date_to' => $today->toDateString()])),
            $this->kpi('sales_last_month', 'فروش ماه قبل', (float) $lastMonthTotals['total_amount'], 'money', null, route('sales-reports.show', ['report' => 'sales', 'date_from' => $lastMonthStart, 'date_to' => $lastMonthEnd])),
            $this->kpi('sales_year', 'فروش امسال (سال مالی)', (float) $yearTotals['total_amount'], 'money', null, route('sales-reports.show', array_filter(['report' => 'sales', 'fiscal_year_id' => $fiscalYear?->id]))),
            $this->kpi('growth', 'رشد فروش ماهانه', $growth ?? 0.0, 'percent', $growth, null),
            $this->kpi('invoice_count', 'تعداد فاکتور (این ماه)', (float) $thisMonthTotals['invoice_count'], 'number', null, route('sales-reports.show', ['report' => 'sales'])),
            $this->kpi('avg_invoice', 'میانگین مبلغ فاکتور', $avgInvoice, 'money', null, null),
            $this->kpi('gross_profit', 'سود ناخالص (این ماه)', (float) $monthProfit['gross_profit'], 'money', null, route('sales-reports.show', ['report' => 'profitability'])),
            $this->kpi('margin', 'حاشیه سود (این ماه)', (float) $monthProfit['margin_percent'], 'percent', null, route('sales-reports.show', ['report' => 'profitability'])),
            $this->kpi('collected', 'وصولی (این ماه)', (float) $thisMonthTotals['paid_amount'], 'money', null, route('sales-reports.show', ['report' => 'receivables'])),
            $this->kpi('receivables', 'مطالبات', (float) $receivables['total_receivables'], 'money', null, route('sales-reports.show', ['report' => 'receivables'])),
            $this->kpi('overdue', 'مطالبات سررسیدشده', (float) ($receivables['overdue'] ?? 0), 'money', null, route('sales-reports.show', ['report' => 'receivables'])),
            $this->kpi('pipeline', 'ارزش Pipeline', (float) ($crm['pipeline_value'] ?? 0), 'money', null, Route::has('crm.pipeline') ? route('crm.pipeline') : null),
            $this->kpi('new_customers', 'مشتریان جدید', (float) $newCustomers, 'number', null, route('parties.index', ['type' => 'customer'])),
        ];

        $alerts = $this->buildAlerts($growth, $monthProfit, $receivables, $thisMonthTotals, $crm);

        return [
            'generated_at' => now(),
            'fiscal_year' => $fiscalYear ? ['id' => $fiscalYear->id, 'title' => $fiscalYear->title] : null,
            'filters' => $filters,
            'kpis' => $kpis,
            'profit_analysis' => [
                'period_label' => 'این ماه',
                'gross_sales' => (float) $thisMonthTotals['total_amount'],
                'net_sales' => (float) $thisMonthTotals['net_amount'],
                'discount' => (float) $thisMonthTotals['discount_amount'],
                'vat' => (float) $thisMonthTotals['tax_amount'],
                'cogs' => (float) $monthProfit['cogs_amount'],
                'gross_profit' => (float) $monthProfit['gross_profit'],
                'margin_percent' => (float) $monthProfit['margin_percent'],
                'collected' => (float) $thisMonthTotals['paid_amount'],
                'outstanding' => (float) $thisMonthTotals['outstanding_amount'],
                'year_gross_profit' => (float) $yearProfit['gross_profit'],
                'year_margin_percent' => (float) $yearProfit['margin_percent'],
            ],
            'charts' => [
                'monthly_trend' => $this->salesReports->aggregateSalesByJalaliMonth($fiscalFilters)->map(function ($row) {
                    $month = (int) substr((string) $row->period, -2);

                    return [
                        'label' => function_exists('getPersianMonthName') ? getPersianMonthName($month) : (string) $row->period,
                        'value' => (float) $row->total_amount,
                        'title' => (string) $row->period.' — '.formatMoney((float) $row->total_amount).' ریال',
                    ];
                })->values()->all(),
                'by_category' => $this->salesReports->salesByCategory($fiscalFilters)->take(8)->map(fn ($row) => [
                    'label' => (string) $row->category,
                    'value' => (float) $row->total_amount,
                ])->values()->all(),
                'top_customers' => $this->salesReports->topCustomers($fiscalFilters, 5)->map(fn ($row) => [
                    'label' => (string) $row->party_name,
                    'value' => (float) $row->total_amount,
                ])->values()->all(),
                'receivable_aging' => [
                    ['label' => 'جاری', 'value' => (float) ($receivables['buckets']['current'] ?? 0)],
                    ['label' => '۱–۳۰', 'value' => (float) ($receivables['buckets']['days_1_30'] ?? 0)],
                    ['label' => '۳۱–۶۰', 'value' => (float) ($receivables['buckets']['days_31_60'] ?? 0)],
                    ['label' => '۶۱–۹۰', 'value' => (float) ($receivables['buckets']['days_61_90'] ?? 0)],
                    ['label' => '۹۰+', 'value' => (float) ($receivables['buckets']['over_90'] ?? 0)],
                ],
            ],
            'crm' => $crm,
            'alerts' => $alerts,
            'ai_brief' => app(AISalesAnalyticsService::class)->dailyBrief([
                'this_month_sales' => (float) $thisMonthTotals['total_amount'],
                'last_month_sales' => (float) $lastMonthTotals['total_amount'],
                'growth_percent' => $growth,
                'margin_percent' => (float) $monthProfit['margin_percent'],
                'gross_profit' => (float) $monthProfit['gross_profit'],
                'receivables' => (float) $receivables['total_receivables'],
                'overdue' => (float) ($receivables['overdue'] ?? 0),
                'pipeline' => (float) ($crm['pipeline_value'] ?? 0),
                'overdue_tasks' => (int) ($crm['overdue_tasks'] ?? 0),
            ]),
            'links' => [
                'sales' => route('sales-reports.show', ['report' => 'sales']),
                'profitability' => route('sales-reports.show', ['report' => 'profitability']),
                'receivables' => route('sales-reports.show', ['report' => 'receivables']),
                'by_customer' => route('sales-reports.show', ['report' => 'by-customer']),
                'by_product' => route('sales-reports.show', ['report' => 'by-product']),
            ],
        ];
    }

    /**
     * @return array{net_sales: float, cogs_amount: float, gross_profit: float, margin_percent: float, invoice_count: int}
     */
    private function profitSummaryForFilters(array $filters): array
    {
        if (! $this->salesReports->hasValidCogs()) {
            return [
                'net_sales' => 0.0,
                'cogs_amount' => 0.0,
                'gross_profit' => 0.0,
                'margin_percent' => 0.0,
                'invoice_count' => 0,
            ];
        }

        $rows = $this->salesReports->profitabilityBreakdown($filters, 'invoice');
        $net = $this->invoices->round((float) $rows->sum('net_sales'));
        $cogs = $this->invoices->round((float) $rows->sum('cogs_amount'));
        $profit = $this->invoices->round($net - $cogs);

        return [
            'net_sales' => $net,
            'cogs_amount' => $cogs,
            'gross_profit' => $profit,
            'margin_percent' => $this->profit->marginPercent($profit, $net),
            'invoice_count' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $monthProfit
     * @param  array<string, mixed>  $receivables
     * @param  array<string, mixed>  $thisMonthTotals
     * @param  array<string, mixed>|null  $crm
     * @return list<array{tone: string, title: string, message: string, url: ?string}>
     */
    private function buildAlerts(?float $growth, array $monthProfit, array $receivables, array $thisMonthTotals, ?array $crm): array
    {
        $alerts = [];

        if ($growth !== null && $growth < -10) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => 'افت فروش',
                'message' => 'فروش این ماه '.number_format(abs($growth), 1).'% کمتر از ماه قبل است.',
                'url' => route('sales-reports.show', ['report' => 'sales']),
            ];
        }

        if (($monthProfit['margin_percent'] ?? 0) < 5 && ($monthProfit['net_sales'] ?? 0) > 0) {
            $alerts[] = [
                'tone' => 'critical',
                'title' => 'حاشیه سود پایین',
                'message' => 'حاشیه سود این ماه '.$monthProfit['margin_percent'].'% است.',
                'url' => route('sales-reports.show', ['report' => 'profitability']),
            ];
        }

        if (($receivables['overdue'] ?? 0) > 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => 'مطالبات سررسیدشده',
                'message' => formatMoney((float) $receivables['overdue']).' ریال',
                'url' => route('sales-reports.show', ['report' => 'receivables']),
            ];
        }

        if (($thisMonthTotals['outstanding_amount'] ?? 0) > 0 && ($thisMonthTotals['invoice_count'] ?? 0) > 0) {
            $unsettled = Invoice::query()
                ->where('direction', 'sale')
                ->where('document_type', 'invoice')
                ->where('status', 'confirmed')
                ->whereNull('settled_at')
                ->count();
            if ($unsettled > 0) {
                $alerts[] = [
                    'tone' => 'info',
                    'title' => 'فاکتور بدون وصول',
                    'message' => $unsettled.' فاکتور تأییدشده تسویه نشده است.',
                    'url' => route('sales-reports.show', ['report' => 'receivables']),
                ];
            }
        }

        if (($crm['overdue_tasks'] ?? 0) > 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => 'پیگیری CRM عقب‌افتاده',
                'message' => (int) $crm['overdue_tasks'].' وظیفه سررسید گذشته',
                'url' => Route::has('crm.tasks.index') ? route('crm.tasks.index') : null,
            ];
        }

        return $alerts;
    }

    private function growthPercent(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.009) {
            return $current > 0.009 ? 100.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * @return array{key: string, title: string, value: float, format: string, delta_percent: ?float, url: ?string}
     */
    private function kpi(string $key, string $title, float $value, string $format, ?float $deltaPercent, ?string $url): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'value' => $value,
            'format' => $format,
            'delta_percent' => $deltaPercent,
            'url' => $url,
        ];
    }
}
