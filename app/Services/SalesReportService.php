<?php

namespace App\Services;

use App\Models\Invoice;
use App\Repositories\SalesReportRepository;
use App\Support\SalesReportFilters;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalesReportService
{
    public function __construct(
        private readonly SalesReportRepository $repository,
    ) {}

    public function hasValidCogs(): bool
    {
        return $this->repository->hasValidCogs();
    }

    public function report(string $key, array $filters): array
    {
        $filters = SalesReportFilters::normalize($filters);
        $perPage = (int) ($filters['per_page'] ?? 25);

        return match ($key) {
            'sales' => $this->mainSalesReport($filters, $perPage),
            'by-customer' => $this->byCustomerReport($filters, $perPage),
            'by-product' => $this->byProductReport($filters, $perPage),
            'receivables' => $this->receivablesReport($filters, $perPage),
            'salesperson' => $this->salespersonReport($filters, $perPage),
            'profitability' => $this->profitabilityReport($filters, $perPage),
            'returns-discounts' => $this->returnsDiscountsReport($filters, $perPage),
            'dashboard' => $this->dashboardReport($filters),
            default => abort(404),
        };
    }

    public function filterOptions(): array
    {
        return $this->repository->filterOptions();
    }

    private function mainSalesReport(array $filters, int $perPage): array
    {
        $totals = $this->repository->invoiceTotals($filters);
        $paginator = $this->repository->paginateInvoices($filters, $perPage);

        $rows = $paginator->through(fn (Invoice $invoice) => $this->invoiceRow($invoice));

        return [
            'title' => 'گزارش اصلی فروش',
            'subtitle' => 'فهرست صورتحساب‌های فروش با جزئیات مالی',
            'summary' => $this->invoiceSummaryCards($totals),
            'totals' => $totals,
            'sections' => [
                [
                    'title' => 'صورتحساب‌های فروش',
                    'headers' => $this->mainSalesHeaders(),
                    'columns' => $this->mainSalesColumns(),
                    'rows' => $rows,
                ],
            ],
            'export_rows' => $this->exportMainSalesRows($paginator),
        ];
    }

    private function byCustomerReport(array $filters, int $perPage): array
    {
        $rows = $this->repository->aggregateByCustomer($filters);
        $hasCogs = $this->hasValidCogs();

        $mapped = $rows->map(function ($row) use ($hasCogs, $filters) {
            $netSales = (float) $row->gross_amount - (float) $row->discount_amount;
            $profit = $hasCogs ? $netSales - (float) ($row->cogs_amount ?? 0) : null;

            return [
                'party_id' => (int) $row->party_id,
                'party_name' => $row->party_name,
                'invoice_count' => (int) $row->invoice_count,
                'gross_amount' => (float) $row->gross_amount,
                'discount_amount' => (float) $row->discount_amount,
                'tax_amount' => (float) $row->tax_amount,
                'total_amount' => (float) $row->total_amount,
                'paid_amount' => (float) $row->paid_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
                'profit' => $profit,
                'drilldown_url' => route('sales-reports.show', array_merge(['report' => 'sales'], $filters, ['party_id' => $row->party_id])),
            ];
        });

        $paginated = $this->paginateCollection($mapped, $perPage);

        return [
            'title' => 'فروش به تفکیک مشتری',
            'subtitle' => 'خلاصه فروش و مطالبات هر مشتری',
            'summary' => [
                'customer_count' => $rows->count(),
                'total_amount' => (float) $rows->sum('total_amount'),
                'outstanding_amount' => (float) $rows->sum('outstanding_amount'),
            ],
            'sections' => [
                [
                    'title' => 'مشتریان',
                    'headers' => $this->byCustomerHeaders($hasCogs),
                    'columns' => $this->byCustomerColumns($hasCogs),
                    'rows' => $paginated,
                ],
            ],
            'export_rows' => $mapped->values()->all(),
        ];
    }

    private function byProductReport(array $filters, int $perPage): array
    {
        $rows = $this->repository->aggregateByProduct($filters);
        $hasCogs = $this->hasValidCogs();

        $mapped = $rows->map(function ($row) use ($hasCogs, $filters) {
            $cogs = $hasCogs ? (float) ($row->cogs_amount ?? 0) : null;
            $profit = $cogs !== null ? (float) $row->net_amount - $cogs : null;
            $margin = ($profit !== null && (float) $row->net_amount > 0)
                ? round(($profit / (float) $row->net_amount) * 100, 1)
                : null;

            return [
                'item_id' => (int) $row->item_id,
                'item_name' => $row->item_name,
                'item_category' => $row->item_category ?: '—',
                'quantity_sold' => (float) $row->quantity_sold,
                'gross_amount' => (float) $row->gross_amount,
                'discount_amount' => (float) $row->discount_amount,
                'net_amount' => (float) $row->net_amount,
                'cogs_amount' => $cogs,
                'profit' => $profit,
                'margin_percent' => $margin,
                'drilldown_url' => route('sales-reports.show', array_merge(['report' => 'sales'], $filters, ['item_id' => $row->item_id])),
            ];
        });

        $paginated = $this->paginateCollection($mapped, $perPage);

        return [
            'title' => 'فروش به تفکیک محصول',
            'subtitle' => 'عملکرد فروش هر کالا/خدمت',
            'summary' => [
                'product_count' => $rows->count(),
                'net_amount' => (float) $rows->sum('net_amount'),
                'quantity_sold' => (float) $rows->sum('quantity_sold'),
            ],
            'sections' => [
                [
                    'title' => 'محصولات',
                    'headers' => $this->byProductHeaders($hasCogs),
                    'columns' => $this->byProductColumns($hasCogs),
                    'rows' => $paginated,
                ],
            ],
            'export_rows' => $mapped->values()->all(),
        ];
    }

    private function receivablesReport(array $filters, int $perPage): array
    {
        $summary = $this->repository->receivableSummary($filters);
        $paginator = $this->repository->receivableInvoices($filters, $perPage);

        $rows = $paginator->through(function (Invoice $invoice) use ($filters) {
            $days = $invoice->invoice_date
                ? Carbon::today()->diffInDays($invoice->invoice_date->copy()->startOfDay())
                : 0;

            return [
                'party_name' => $invoice->party?->name ?? '—',
                'party_id' => $invoice->party_id,
                'number' => $invoice->number,
                'invoice_date' => gregorianToJalaliDate($invoice->invoice_date),
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => 0.0,
                'outstanding_amount' => (float) $invoice->total_amount,
                'status' => $invoice->settlement_status_label,
                'days_open' => $days,
                'aging_bucket' => $this->agingLabel($days),
                'invoice_url' => route('invoices.show', $invoice),
                'drilldown_url' => route('sales-reports.show', array_merge(['report' => 'receivables'], $filters, ['party_id' => $invoice->party_id])),
            ];
        });

        return [
            'title' => 'مطالبات و فروش‌های تسویه‌نشده',
            'subtitle' => 'صورتحساب‌های باز — سررسید بر اساس تاریخ صورتحساب (سیستم تاریخ سررسید ندارد)',
            'summary' => [
                'total_receivables' => $summary['total_receivables'],
                'current' => $summary['current'],
                'overdue' => $summary['overdue'],
                'over_90' => $summary['over_90'],
            ],
            'sections' => [
                [
                    'title' => 'صورتحساب‌های باز',
                    'headers' => ['مشتری', 'شماره', 'تاریخ', 'مبلغ', 'پرداخت‌شده', 'مانده', 'روز باز', 'باکت', 'وضعیت'],
                    'columns' => ['party_name', 'number', 'invoice_date', 'total_amount', 'paid_amount', 'outstanding_amount', 'days_open', 'aging_bucket', 'status'],
                    'rows' => $rows,
                ],
            ],
            'export_rows' => $this->exportReceivableRows($paginator),
        ];
    }

    private function salespersonReport(array $filters, int $perPage): array
    {
        $rows = $this->repository->aggregateBySalesperson($filters);
        $hasCogs = $this->hasValidCogs();

        $mapped = $rows->map(function ($row) use ($hasCogs, $filters) {
            $netSales = (float) ($row->net_sales ?? $row->total_amount);
            $cogs = $hasCogs ? (float) ($row->cogs_amount ?? 0) : null;
            $profit = $cogs !== null ? $netSales - $cogs : null;
            $margin = ($profit !== null && $netSales > 0) ? round(($profit / $netSales) * 100, 1) : null;

            return [
                'created_by' => (int) $row->created_by,
                'salesperson_name' => $row->salesperson_name,
                'invoice_count' => (int) $row->invoice_count,
                'customer_count' => (int) $row->customer_count,
                'total_amount' => $netSales,
                'paid_amount' => (float) $row->paid_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
                'profit' => $profit,
                'margin_percent' => $margin,
                'drilldown_url' => route('sales-reports.show', array_merge(['report' => 'sales'], $filters, ['created_by' => $row->created_by])),
            ];
        });

        $paginated = $this->paginateCollection($mapped, $perPage);

        return [
            'title' => 'عملکرد فروشندگان',
            'subtitle' => 'بر اساس کاربر ثبت‌کننده صورتحساب',
            'summary' => [
                'salesperson_count' => $rows->count(),
                'total_amount' => (float) $rows->sum('total_amount'),
                'outstanding_amount' => (float) $rows->sum('outstanding_amount'),
            ],
            'sections' => [
                [
                    'title' => 'فروشندگان',
                    'headers' => $this->salespersonHeaders($hasCogs),
                    'columns' => $this->salespersonColumns($hasCogs),
                    'rows' => $paginated,
                ],
            ],
            'export_rows' => $mapped->values()->all(),
        ];
    }

    private function profitabilityReport(array $filters, int $perPage): array
    {
        if (! $this->hasValidCogs()) {
            abort(404);
        }

        $groupBy = $filters['group_by'] ?? 'invoice';
        $rows = $this->repository->profitabilityBreakdown($filters, $groupBy);

        $mapped = $rows->map(function ($row) {
            $netSales = (float) $row->net_sales;
            $cogs = (float) $row->cogs_amount;
            $profit = $netSales - $cogs;
            $margin = $netSales > 0 ? round(($profit / $netSales) * 100, 1) : 0;

            return [
                'label' => $row->group_name ?? ($row->number ?? null) . ($row->party_name ? ' — ' . $row->party_name : '') ?: '—',
                'net_sales' => $netSales,
                'cogs_amount' => $cogs,
                'profit' => $profit,
                'margin_percent' => $margin,
                'invoice_date' => isset($row->invoice_date) ? gregorianToJalaliDate($row->invoice_date) : null,
            ];
        });

        $totals = [
            'net_sales' => (float) $mapped->sum('net_sales'),
            'cogs_amount' => (float) $mapped->sum('cogs_amount'),
            'profit' => (float) $mapped->sum('profit'),
        ];
        $totals['margin_percent'] = $totals['net_sales'] > 0
            ? round(($totals['profit'] / $totals['net_sales']) * 100, 1)
            : 0;

        $paginated = $this->paginateCollection($mapped, $perPage);

        return [
            'title' => 'سود و حاشیه سود فروش',
            'subtitle' => 'فروش خالص بدون VAT − بهای تمام‌شده (میانگین خرید واقعی پروژه/کالا، در غیر این صورت قیمت خرید مستر)',
            'summary' => $totals,
            'group_by' => $groupBy,
            'sections' => [
                [
                    'title' => 'تحلیل سود',
                    'headers' => ['عنوان', 'فروش خالص', 'بهای تمام‌شده', 'سود ناخالص', 'حاشیه %'],
                    'columns' => ['label', 'net_sales', 'cogs_amount', 'profit', 'margin_percent'],
                    'rows' => $paginated,
                ],
            ],
            'export_rows' => $mapped->values()->all(),
        ];
    }

    private function returnsDiscountsReport(array $filters, int $perPage): array
    {
        $tab = $filters['tab'] ?? 'discounts';

        if ($tab === 'returns') {
            $totals = $this->repository->cancelledSaleTotals($filters);
            $paginator = $this->repository->cancelledSaleRows($filters, $perPage);

            $rows = $paginator->through(function (Invoice $invoice) {
                $firstLine = $invoice->lines->first();

                return [
                    'invoice_date' => gregorianToJalaliDate($invoice->invoice_date),
                    'party_name' => $invoice->party?->name ?? '—',
                    'number' => $invoice->number,
                    'item_name' => $firstLine?->item?->name ?? '—',
                    'quantity' => (float) ($firstLine?->quantity ?? 0),
                    'total_amount' => (float) $invoice->total_amount,
                    'return_reason' => '—',
                    'created_by' => $invoice->createdBy?->name ?? '—',
                    'invoice_url' => route('invoices.show', $invoice),
                ];
            });

            return [
                'title' => 'برگشت از فروش و تخفیفات',
                'subtitle' => 'برگشت: فاکتورهای لغوشده — علت برگشت در سیستم ثبت نمی‌شود',
                'tab' => 'returns',
                'summary' => [
                    'return_count' => $totals['invoice_count'],
                    'return_amount' => $totals['total_amount'],
                ],
                'sections' => [
                    [
                        'title' => 'برگشت از فروش',
                        'headers' => ['تاریخ', 'مشتری', 'شماره', 'محصول', 'تعداد', 'مبلغ', 'علت', 'ثبت‌کننده'],
                        'columns' => ['invoice_date', 'party_name', 'number', 'item_name', 'quantity', 'total_amount', 'return_reason', 'created_by'],
                        'rows' => $rows,
                    ],
                ],
                'export_rows' => $this->exportReturnRows($paginator),
            ];
        }

        $totals = $this->repository->discountTotals($filters);
        $paginator = $this->repository->discountRows($filters, $perPage);

        $rows = $paginator->through(function (Invoice $invoice) {
            $gross = (float) $invoice->subtotal;
            $discount = (float) $invoice->discount_amount;
            $percent = $gross > 0 ? round(($discount / $gross) * 100, 1) : 0;

            return [
                'invoice_date' => gregorianToJalaliDate($invoice->invoice_date),
                'party_name' => $invoice->party?->name ?? '—',
                'salesperson_name' => $invoice->createdBy?->name ?? '—',
                'number' => $invoice->number,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'discount_percent' => $percent,
                'invoice_url' => route('invoices.show', $invoice),
            ];
        });

        return [
            'title' => 'برگشت از فروش و تخفیفات',
            'subtitle' => 'تخفیفات اعمال‌شده روی صورتحساب‌های فروش',
            'tab' => 'discounts',
            'summary' => [
                'invoice_count' => $totals['invoice_count'],
                'discount_amount' => $totals['discount_amount'],
            ],
            'sections' => [
                [
                    'title' => 'تخفیفات',
                    'headers' => ['تاریخ', 'مشتری', 'فروشنده', 'شماره', 'مبلغ فروش', 'تخفیف', 'درصد'],
                    'columns' => ['invoice_date', 'party_name', 'salesperson_name', 'number', 'gross_amount', 'discount_amount', 'discount_percent'],
                    'rows' => $rows,
                ],
            ],
            'export_rows' => $this->exportDiscountRows($paginator),
        ];
    }

    private function dashboardReport(array $filters): array
    {
        $today = Carbon::today();
        $thisMonthStart = $today->copy()->startOfMonth()->toDateString();
        $lastMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $fiscalYear = ! empty($filters['fiscal_year_id'])
            ? \App\Models\FiscalYear::query()->find($filters['fiscal_year_id'])
            : SalesReportFilters::currentFiscalYear();

        $fiscalYearFilters = $filters;
        if ($fiscalYear) {
            $fiscalYearFilters['fiscal_year_id'] = $fiscalYear->id;
        }

        $todayTotals = $this->repository->invoiceTotals(array_merge($filters, [
            'date_from' => $today->toDateString(),
            'date_to' => $today->toDateString(),
        ]));

        $thisMonthTotals = $this->repository->invoiceTotals(array_merge($filters, [
            'date_from' => $thisMonthStart,
            'date_to' => $today->toDateString(),
        ]));

        $lastMonthTotals = $this->repository->invoiceTotals([
            'date_from' => $lastMonthStart,
            'date_to' => $lastMonthEnd,
        ]);

        $yearTotals = $this->repository->invoiceTotals($fiscalYearFilters);

        $receivables = $this->repository->receivableSummary($filters);
        $hasCogs = $this->hasValidCogs();
        $profit = null;
        $margin = null;

        if ($hasCogs) {
            $yearRows = $this->repository->profitabilityBreakdown($fiscalYearFilters, 'invoice');
            $netSales = (float) $yearRows->sum('net_sales');
            $cogs = (float) $yearRows->sum('cogs_amount');
            $profit = $netSales - $cogs;
            $margin = $netSales > 0 ? round(($profit / $netSales) * 100, 1) : 0;
        }

        $growth = $this->comparisonGrowth(
            (float) $thisMonthTotals['total_amount'],
            (float) $lastMonthTotals['total_amount'],
        );

        $summary = [
            'sales_today' => $todayTotals['total_amount'],
            'sales_this_month' => $thisMonthTotals['total_amount'],
            'sales_fiscal_year' => $yearTotals['total_amount'],
            'fiscal_year_invoice_count' => $yearTotals['invoice_count'],
            'invoice_count' => $thisMonthTotals['invoice_count'],
            'paid_amount' => $thisMonthTotals['paid_amount'],
            'outstanding_amount' => $receivables['total_receivables'],
        ];

        if ($hasCogs) {
            $summary['gross_profit'] = $profit;
            $summary['margin_percent'] = $margin;
        }

        return [
            'title' => 'داشبورد فروش',
            'subtitle' => $fiscalYear
                ? 'نمای کلی عملکرد فروش — سال مالی ' . $fiscalYear->title
                : 'نمای کلی عملکرد فروش',
            'fiscal_year' => $fiscalYear ? [
                'id' => $fiscalYear->id,
                'title' => $fiscalYear->title,
            ] : null,
            'summary' => $summary,
            'summary_labels' => [
                'sales_fiscal_year' => $fiscalYear
                    ? 'کل فروش سال مالی ' . $fiscalYear->title
                    : 'کل فروش سال مالی جاری',
            ],
            'comparison' => [
                'current_label' => 'فروش این ماه',
                'current_value' => $thisMonthTotals['total_amount'],
                'previous_label' => 'ماه قبل',
                'previous_value' => $lastMonthTotals['total_amount'],
                'growth_percent' => $growth,
            ],
            'charts' => [
                'monthly_trend' => $this->repository->monthlySalesTrend($fiscalYearFilters)->map(fn ($row) => [
                    'label' => $row->period,
                    'value' => (float) $row->total_amount,
                ])->all(),
                'by_category' => $this->repository->salesByCategory($fiscalYearFilters)->map(fn ($row) => [
                    'label' => $row->category,
                    'value' => (float) $row->total_amount,
                ])->all(),
                'top_customers' => $this->repository->topCustomers($fiscalYearFilters)->map(fn ($row) => [
                    'label' => $row->party_name,
                    'value' => (float) $row->total_amount,
                ])->all(),
                'salespeople' => $this->repository->salespersonChart($fiscalYearFilters)->map(fn ($row) => [
                    'label' => $row->salesperson_name,
                    'value' => (float) $row->total_amount,
                ])->all(),
            ],
            'sections' => [],
        ];
    }

    private function invoiceRow(Invoice $invoice): array
    {
        $net = (float) $invoice->subtotal - (float) $invoice->discount_amount;
        $paid = $invoice->settled_at ? (float) $invoice->total_amount : 0.0;
        $outstanding = $invoice->settled_at ? 0.0 : (float) $invoice->total_amount;

        return [
            'invoice_id' => $invoice->id,
            'invoice_date' => gregorianToJalaliDate($invoice->invoice_date),
            'number' => $invoice->number,
            'party_name' => $invoice->party?->name ?? '—',
            'salesperson_name' => $invoice->createdBy?->name ?? '—',
            'project_name' => $invoice->project?->name ?? '—',
            'gross_amount' => (float) $invoice->subtotal,
            'discount_amount' => (float) $invoice->discount_amount,
            'net_amount' => $net,
            'tax_amount' => (float) $invoice->tax_amount,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => $paid,
            'outstanding_amount' => $outstanding,
            'status' => $invoice->settlement_status_label,
            'invoice_url' => route('invoices.show', $invoice),
        ];
    }

    private function invoiceSummaryCards(array $totals): array
    {
        return [
            'invoice_count' => $totals['invoice_count'],
            'gross_amount' => $totals['gross_amount'],
            'discount_amount' => $totals['discount_amount'],
            'net_amount' => $totals['net_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
            'paid_amount' => $totals['paid_amount'],
            'outstanding_amount' => $totals['outstanding_amount'],
        ];
    }

    private function mainSalesHeaders(): array
    {
        return ['ردیف', 'تاریخ', 'شماره', 'مشتری', 'فروشنده', 'پروژه', 'ناخالص', 'تخفیف', 'خالص', 'مالیات', 'نهایی', 'پرداخت', 'مانده', 'وضعیت'];
    }

    private function mainSalesColumns(): array
    {
        return ['row', 'invoice_date', 'number', 'party_name', 'salesperson_name', 'project_name', 'gross_amount', 'discount_amount', 'net_amount', 'tax_amount', 'total_amount', 'paid_amount', 'outstanding_amount', 'status'];
    }

    private function byCustomerHeaders(bool $hasCogs): array
    {
        $headers = ['مشتری', 'تعداد', 'فروش', 'تخفیف', 'مالیات', 'نهایی', 'وصول', 'مانده'];
        if ($hasCogs) {
            $headers[] = 'سود';
        }

        return $headers;
    }

    private function byCustomerColumns(bool $hasCogs): array
    {
        $columns = ['party_name', 'invoice_count', 'gross_amount', 'discount_amount', 'tax_amount', 'total_amount', 'paid_amount', 'outstanding_amount'];
        if ($hasCogs) {
            $columns[] = 'profit';
        }

        return $columns;
    }

    private function byProductHeaders(bool $hasCogs): array
    {
        $headers = ['محصول', 'گروه', 'تعداد', 'فروش', 'تخفیف', 'خالص'];
        if ($hasCogs) {
            $headers[] = 'بهای تمام‌شده';
            $headers[] = 'سود';
            $headers[] = 'حاشیه %';
        }

        return $headers;
    }

    private function byProductColumns(bool $hasCogs): array
    {
        $columns = ['item_name', 'item_category', 'quantity_sold', 'gross_amount', 'discount_amount', 'net_amount'];
        if ($hasCogs) {
            $columns[] = 'cogs_amount';
            $columns[] = 'profit';
            $columns[] = 'margin_percent';
        }

        return $columns;
    }

    private function salespersonHeaders(bool $hasCogs): array
    {
        $headers = ['فروشنده', 'صورتحساب', 'مشتری', 'فروش', 'وصول', 'مانده'];
        if ($hasCogs) {
            $headers[] = 'سود';
            $headers[] = 'حاشیه %';
        }

        return $headers;
    }

    private function salespersonColumns(bool $hasCogs): array
    {
        $columns = ['salesperson_name', 'invoice_count', 'customer_count', 'total_amount', 'paid_amount', 'outstanding_amount'];
        if ($hasCogs) {
            $columns[] = 'profit';
            $columns[] = 'margin_percent';
        }

        return $columns;
    }

    private function agingLabel(int $days): string
    {
        if ($days <= 0) {
            return 'جاری';
        }

        if ($days <= 30) {
            return '۱–۳۰ روز';
        }

        if ($days <= 60) {
            return '۳۱–۶۰ روز';
        }

        if ($days <= 90) {
            return '۶۱–۹۰ روز';
        }

        return 'بیش از ۹۰ روز';
    }

    private function comparisonGrowth(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function paginateCollection(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request('page', 1));
        $items = $rows->values();
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function exportMainSalesRows(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())->map(fn ($row) => is_array($row) ? $row : $this->invoiceRow($row))->all();
    }

    private function exportReceivableRows(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())->map(function ($row) {
            return is_array($row) ? $row : [];
        })->all();
    }

    private function exportReturnRows(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())->map(fn ($row) => is_array($row) ? $row : [])->all();
    }

    private function exportDiscountRows(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())->map(fn ($row) => is_array($row) ? $row : [])->all();
    }
}
