@php
    $moneyColumns = $moneyColumns ?? [
        'gross_amount', 'discount_amount', 'net_amount', 'tax_amount', 'total_amount',
        'paid_amount', 'outstanding_amount', 'cogs_amount', 'profit', 'sales_today',
        'sales_this_month', 'sales_fiscal_year', 'return_amount', 'current_value',
        'previous_value', 'total_receivables', 'current', 'overdue', 'over_90',
        'net_sales', 'value', 'debit', 'credit', 'balance', 'amount', 'opening_debit',
        'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit',
        'opening', 'closing', 'movement', 'taxable_amount', 'tax_amount', 'total_amount',
        'debit_total', 'credit_total', 'running_balance', 'outstanding_balance',
        'net_cash', 'revenue', 'cost', 'margin_percent', 'growth_percent',
    ];

    $salesSummaryLabels = [
        'invoice_count' => 'تعداد صورتحساب',
        'fiscal_year_invoice_count' => 'تعداد صورتحساب سال مالی',
        'sales_fiscal_year' => 'کل فروش سال مالی',
        'gross_amount' => 'مبلغ ناخالص',
        'discount_amount' => 'تخفیف',
        'net_amount' => 'مبلغ خالص',
        'tax_amount' => 'مالیات',
        'total_amount' => 'مبلغ نهایی',
        'paid_amount' => 'وصول‌شده',
        'outstanding_amount' => 'مانده مطالبات',
        'customer_count' => 'تعداد مشتری',
        'product_count' => 'تعداد محصول',
        'quantity_sold' => 'تعداد فروش',
        'salesperson_count' => 'تعداد فروشنده',
        'total_receivables' => 'کل مطالبات',
        'current' => 'مطالبات جاری',
        'overdue' => 'مطالبات سررسید گذشته',
        'over_90' => 'بالای ۹۰ روز',
        'return_count' => 'تعداد برگشت',
        'return_amount' => 'مبلغ برگشتی',
        'sales_today' => 'فروش امروز',
        'sales_this_month' => 'فروش این ماه',
        'sales_this_year' => 'فروش سال جاری',
        'gross_profit' => 'سود ناخالص',
        'margin_percent' => 'حاشیه سود %',
        'net_sales' => 'فروش خالص',
        'cogs_amount' => 'بهای تمام‌شده',
        'profit' => 'سود ناخالص',
    ];

    $financialSummaryLabels = [
        'opening_debit' => 'مانده افتتاحیه بدهکار',
        'opening_credit' => 'مانده افتتاحیه بستانکار',
        'period_debit' => 'جمع بدهکار دوره',
        'period_credit' => 'جمع بستانکار دوره',
        'closing_debit' => 'مانده نهایی بدهکار',
        'closing_credit' => 'مانده نهایی بستانکار',
        'opening_cash' => 'نقد افتتاحیه',
        'period_cash_in' => 'ورودی نقد دوره',
        'period_cash_out' => 'خروجی نقد دوره',
        'closing_cash' => 'نقد پایان دوره',
        'opening_balance' => 'مانده افتتاحیه',
        'closing_balance' => 'مانده نهایی',
        'line_count' => 'تعداد ردیف',
        'net_profit' => 'سود خالص',
        'revenue' => 'درآمد',
        'cost_of_sales' => 'بهای تمام‌شده فروش',
        'gross_profit' => 'سود ناخالص',
        'operating_expenses' => 'هزینه‌های عملیاتی',
        'operating_profit' => 'سود عملیاتی',
        'income_tax_expense' => 'مالیات بر درآمد',
    ];

    $summaryLabels = array_merge($salesSummaryLabels, $financialSummaryLabels, $summary_labels ?? []);

    $summaryItems = $summary ?? [];
    if (($reportKey ?? '') === 'income-statement' && ! empty($summaryItems)) {
        $summaryOrder = [
            'net_profit', 'operating_profit', 'gross_profit', 'revenue',
            'cost_of_sales', 'operating_expenses', 'income_tax_expense',
        ];
        $summaryItems = collect($summaryItems)
            ->sortBy(fn ($value, $key) => array_search($key, $summaryOrder, true) === false ? 999 : array_search($key, $summaryOrder, true))
            ->all();
    }

    $resolveFinancialColumns = function (string $reportKey, array $section) {
        $title = (string) ($section['title'] ?? '');

        return match ($reportKey) {
            'trial-balance' => ['code', 'title', 'opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'],
            'detailed-trial-balance' => str_contains($title, 'ریز')
                ? ['date', 'document_number', 'account', 'detail_account', 'party', 'project', 'cost_center', 'description', 'debit', 'credit', 'running_balance']
                : ['code', 'title', 'opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'],
            'general-ledger', 'detailed-ledger' => ['date', 'document_number', 'account', 'detail_account', 'description', 'debit', 'credit', 'running_balance'],
            'balance-sheet', 'income-statement' => ['code', 'title', 'amount'],
            'expense-analysis-by-account', 'revenue-analysis-by-account' => ['code', 'title', 'debit', 'credit', 'balance'],
            'changes-in-equity' => ['code', 'title', 'opening', 'movement', 'closing'],
            'cash-flow-statement' => ['code', 'title', 'opening', 'debit', 'credit', 'closing'],
            'accounts-receivable-aging', 'accounts-payable-aging' => ['code', 'name', 'balance', 'days', 'bucket'],
            'customer-statement', 'supplier-statement' => ['code', 'name', 'debit', 'credit', 'balance', 'balance_type'],
            'outstanding-invoices', 'overdue-invoices' => ['number', 'date', 'party', 'direction', 'total_amount', 'status', 'outstanding_balance'],
            'cash-book', 'bank-book', 'bank-statement', 'cash-statement' => ['code', 'name', 'opening', 'debit', 'credit', 'closing'],
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
            'deleted-modified-transactions' => str_contains($title, 'حذف')
                ? ['number', 'date', 'status', 'description', 'deleted_at']
                : ['document', 'event', 'user_id'],
            'tax-electronic-books' => ['row_number', 'date', 'ledger_code', 'ledger_title', 'subsidiary_code', 'subsidiary_title', 'description', 'debit', 'credit'],
            default => is_array(($section['rows'] ?? [])[0] ?? null) ? array_keys(($section['rows'] ?? [])[0]) : [],
        };
    };

    $formatCell = function ($cell, string $column = '') use ($moneyColumns) {
        if (is_numeric($cell) && (
            in_array($column, $moneyColumns, true)
            || str_contains($column, 'amount')
            || in_array($column, ['debit', 'credit', 'balance', 'profit', 'value', 'quantity_sold', 'quantity'], true)
        )) {
            return formatMoney((float) $cell);
        }

        if ($cell instanceof \Carbon\CarbonInterface) {
            return gregorianToJalaliDate($cell);
        }

        if (is_array($cell)) {
            return implode('، ', $cell);
        }

        if (is_object($cell)) {
            return data_get($cell, 'code')
                ? trim(data_get($cell, 'code') . ' - ' . (data_get($cell, 'title') ?: data_get($cell, 'name') ?: data_get($cell, 'bank_name') ?: ''))
                : (data_get($cell, 'title') ?: data_get($cell, 'name') ?: data_get($cell, 'bank_name') ?: '-');
        }

        return $cell ?: '-';
    };

    $isNumericColumn = function (string $column) use ($moneyColumns): bool {
        return in_array($column, $moneyColumns, true)
            || str_contains($column, 'amount')
            || in_array($column, ['debit', 'credit', 'balance', 'profit', 'value', 'quantity_sold', 'quantity', 'margin_percent', 'discount_percent'], true);
    };
@endphp

@if(! empty($reportSubtitle))
    <p class="pdf-subtitle">{{ $reportSubtitle }}</p>
@endif

@if(! empty($summaryItems) && ($reportKey ?? '') !== 'dashboard')
    <table class="pdf-summary-table">
        <tbody>
            @foreach($summaryItems as $label => $value)
                <tr>
                    <td class="pdf-summary-label">{{ $summaryLabels[$label] ?? str_replace('_', ' ', (string) $label) }}</td>
                    <td class="pdf-summary-value pdf-num">
                        @if(is_numeric($value) && (
                            str_contains((string) $label, 'amount')
                            || str_contains((string) $label, 'profit')
                            || str_contains((string) $label, 'receivables')
                            || str_contains((string) $label, 'sales')
                            || str_contains((string) $label, 'debit')
                            || str_contains((string) $label, 'credit')
                            || str_contains((string) $label, 'balance')
                            || str_contains((string) $label, 'cash')
                            || in_array($label, ['current', 'overdue', 'over_90', 'paid_amount', 'outstanding_amount'], true)
                        ))
                            {{ formatMoney((float) $value) }}
                        @elseif(str_contains((string) $label, 'percent') || $label === 'margin_percent')
                            {{ is_numeric($value) ? number_format((float) $value, 1) . '%' : $value }}
                        @else
                            {{ is_numeric($value) ? number_format((float) $value) : $value }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if(! empty($comparison))
    <table class="pdf-summary-table">
        <tbody>
            <tr>
                <td class="pdf-summary-label">{{ $comparison['current_label'] ?? 'دوره جاری' }}</td>
                <td class="pdf-summary-value pdf-num">{{ formatMoney((float) ($comparison['current_value'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="pdf-summary-label">{{ $comparison['previous_label'] ?? 'دوره قبل' }}</td>
                <td class="pdf-summary-value pdf-num">{{ formatMoney((float) ($comparison['previous_value'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="pdf-summary-label">رشد</td>
                <td class="pdf-summary-value pdf-num">
                    {{ ($comparison['growth_percent'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($comparison['growth_percent'] ?? 0), 1) }}%
                </td>
            </tr>
        </tbody>
    </table>
@endif

@foreach($sections ?? [] as $section)
    @php
        $rows = $section['rows'] ?? [];
        $paginator = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows : null;
        $items = $paginator ? $paginator->getCollection() : collect($rows);
        $headers = $section['headers'] ?? [];
        $columns = $section['columns'] ?? [];
        if ($columns === [] && ($reportKey ?? '') !== '') {
            $columns = $resolveFinancialColumns($reportKey, $section);
        }
        if ($columns === [] && $items->isNotEmpty()) {
            $first = $items->first();
            $columns = is_array($first) ? array_keys($first) : [];
        }
    @endphp

    @if(! empty($section['title']))
        <h3 class="pdf-section-title">{{ $section['title'] }}</h3>
    @endif

    <table class="pdf-data-table">
        @if($headers !== [])
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            @forelse($items as $index => $row)
                @php $row = is_array($row) ? $row : (array) $row; @endphp
                <tr>
                    @foreach($columns as $column)
                        @php
                            if ($column === 'row') {
                                $cell = $paginator ? ($paginator->firstItem() + $index) : ($index + 1);
                            } elseif ($column === 'margin_percent' || $column === 'discount_percent') {
                                $cell = isset($row[$column]) ? number_format((float) $row[$column], 1) . '%' : '—';
                            } elseif ($column === 'quantity_sold' || $column === 'quantity') {
                                $cell = number_format((float) ($row[$column] ?? 0), 3);
                            } else {
                                $cell = $formatCell($row[$column] ?? null, $column);
                            }
                        @endphp
                        <td @class(['pdf-num' => $isNumericColumn($column) || is_numeric($cell)])>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(1, count($headers ?: $columns)) }}">رکوردی برای نمایش وجود ندارد.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if(($reportKey ?? '') === 'sales' && ! empty($totals))
        <div class="pdf-note">
            <strong>جمع کل (مطابق فیلتر):</strong>
            تعداد {{ number_format($totals['invoice_count'] ?? 0) }} |
            ناخالص {{ formatMoney((float) ($totals['gross_amount'] ?? 0)) }} |
            تخفیف {{ formatMoney((float) ($totals['discount_amount'] ?? 0)) }} |
            خالص {{ formatMoney((float) ($totals['net_amount'] ?? 0)) }} |
            مالیات {{ formatMoney((float) ($totals['tax_amount'] ?? 0)) }} |
            نهایی {{ formatMoney((float) ($totals['total_amount'] ?? 0)) }} |
            پرداخت {{ formatMoney((float) ($totals['paid_amount'] ?? 0)) }} |
            مانده {{ formatMoney((float) ($totals['outstanding_amount'] ?? 0)) }}
        </div>
    @endif
@endforeach
