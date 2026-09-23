@php
    $printMode = $printMode ?? false;
    $moneyColumns = ['gross_amount', 'discount_amount', 'net_amount', 'tax_amount', 'total_amount', 'paid_amount', 'outstanding_amount', 'cogs_amount', 'profit', 'sales_today', 'sales_this_month', 'sales_fiscal_year', 'return_amount', 'current_value', 'previous_value', 'total_receivables', 'current', 'overdue', 'over_90', 'net_sales', 'value'];
    $summaryLabels = [
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
@endphp

<div class="{{ $printMode ? '' : 'py-8' }}">
    <div class="{{ $printMode ? '' : 'mx-auto max-w-7xl px-3 sm:px-5 lg:px-6 space-y-6' }}">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">{{ $reportTitle }}</h1>
                    @if($reportSubtitle)
                        <p class="mt-1 text-sm text-slate-500">{{ $reportSubtitle }}</p>
                    @endif
                </div>
                @if(! $printMode)
                    <div class="flex flex-wrap gap-2">
                        @if($reportKey !== 'dashboard')
                            <a href="{{ $printUrl }}" target="_blank" data-no-spa class="erp-action-btn erp-action-detail">چاپ</a>
                            <a href="{{ $pdfUrl }}" data-no-spa class="erp-action-btn erp-action-detail">PDF</a>
                            <a href="{{ $excelUrl }}" data-no-spa class="erp-action-btn erp-action-detail">Excel</a>
                        @endif
                    </div>
                @endif
            </div>

            @if(! $printMode)
                <nav class="mt-4 flex flex-wrap gap-2 border-b border-slate-100 pb-3">
                    @foreach($reportsNav as $nav)
                        <a href="{{ erp_report_url('sales-reports.show', ['report' => $nav['key']], request()->query('return_to'), request()->query('from')) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-bold {{ $reportKey === $nav['key'] ? 'bg-primary-100 text-primary-800' : 'text-slate-600 hover:bg-slate-100' }}">
                            {{ $nav['label'] }}
                        </a>
                    @endforeach
                </nav>

                @if($reportKey !== 'dashboard')
                    @include('sales-reports.partials.filters')
                @endif
            @endif
        </section>

        @if($reportKey === 'returns-discounts' && ! $printMode)
            <div class="flex gap-2">
                <a href="{{ route('sales-reports.show', array_merge(['report' => 'returns-discounts', 'tab' => 'returns'], request()->except('tab'))) }}"
                   class="rounded-lg px-4 py-2 text-sm font-bold {{ ($tab ?? 'discounts') === 'returns' ? 'bg-primary-600 text-white' : 'bg-white ring-1 ring-slate-200 text-slate-700' }}">
                    برگشت از فروش
                </a>
                <a href="{{ route('sales-reports.show', array_merge(['report' => 'returns-discounts', 'tab' => 'discounts'], request()->except('tab'))) }}"
                   class="rounded-lg px-4 py-2 text-sm font-bold {{ ($tab ?? 'discounts') === 'discounts' ? 'bg-primary-600 text-white' : 'bg-white ring-1 ring-slate-200 text-slate-700' }}">
                    تخفیفات
                </a>
            </div>
        @endif

        @if($reportKey === 'profitability' && ! $printMode)
            <x-erp.ui.filter-bar method="GET" action="{{ route('sales-reports.show', ['report' => 'profitability']) }}" class="grid gap-3 md:grid-cols-4">
                @foreach(request()->except('group_by', 'page') as $key => $value)
                    @if(is_scalar($value) && $value !== '')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="text-sm font-bold text-slate-700 md:col-span-2">نمایش بر اساس
                    <select name="group_by" class="mt-1 w-full rounded-lg border-slate-300">
                        @foreach(['invoice' => 'صورتحساب', 'customer' => 'مشتری', 'product' => 'محصول', 'category' => 'گروه محصول', 'salesperson' => 'فروشنده', 'project' => 'پروژه'] as $value => $label)
                            <option value="{{ $value }}" @selected(($groupBy ?? 'invoice') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="erp-action-btn erp-action-detail">اعمال</button>
                </div>
            </x-erp.ui.filter-bar>
        @endif

        @if($reportKey === 'dashboard')
            @include('sales-reports.partials.dashboard')
        @endif

        @if(! empty($summary) && $reportKey !== 'dashboard')
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach($summary as $label => $value)
                    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $summaryLabels[$label] ?? str_replace('_', ' ', $label) }}</div>
                        <div class="mt-2 text-lg font-black text-slate-900">
                            @if(is_numeric($value) && (str_contains($label, 'amount') || str_contains($label, 'profit') || str_contains($label, 'receivables') || str_contains($label, 'sales') || in_array($label, ['current', 'overdue', 'over_90', 'paid_amount', 'outstanding_amount'], true)))
                                {{ formatMoney((float) $value) }}
                            @elseif(str_contains($label, 'percent') || $label === 'margin_percent')
                                {{ is_numeric($value) ? number_format((float) $value, 1) . '%' : $value }}
                            @else
                                {{ is_numeric($value) ? number_format((float) $value) : $value }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if(! empty($comparison))
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <div class="text-xs font-bold text-slate-500">{{ $comparison['current_label'] ?? 'دوره جاری' }}</div>
                        <div class="mt-1 text-xl font-black">{{ formatMoney((float) ($comparison['current_value'] ?? 0)) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500">{{ $comparison['previous_label'] ?? 'دوره قبل' }}</div>
                        <div class="mt-1 text-xl font-black">{{ formatMoney((float) ($comparison['previous_value'] ?? 0)) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500">رشد</div>
                        <div class="mt-1 text-xl font-black {{ ($comparison['growth_percent'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ ($comparison['growth_percent'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($comparison['growth_percent'] ?? 0), 1) }}%
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @foreach($sections as $section)
            @php
                $rows = $section['rows'] ?? [];
                $paginator = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows : null;
                $items = $paginator ? $paginator->getCollection() : collect($rows);
                $columns = $section['columns'] ?? [];
                $headers = $section['headers'] ?? [];
            @endphp

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                @if(! empty($section['title']))
                    <h2 class="mb-4 text-lg font-black text-slate-900">{{ $section['title'] }}</h2>
                @endif

                <x-erp.ui.data-table :headers="$headers">
                    @forelse($items as $index => $row)
                        @php $row = is_array($row) ? $row : (array) $row; @endphp
                        <tr>
                            @foreach($columns as $column)
                                <td class="{{ in_array($column, $moneyColumns, true) || str_contains($column, 'amount') ? 'text-left font-mono' : '' }}">
                                    @if($column === 'row')
                                        {{ $paginator ? ($paginator->firstItem() + $index) : ($index + 1) }}
                                    @elseif($column === 'number' && ! empty($row['invoice_url']))
                                        <a href="{{ $row['invoice_url'] }}" class="text-primary-700 hover:underline">{{ $row[$column] ?? '—' }}</a>
                                    @elseif(in_array($column, ['party_name', 'item_name', 'salesperson_name'], true) && ! empty($row['drilldown_url']))
                                        <a href="{{ $row['drilldown_url'] }}" class="text-primary-700 hover:underline">{{ $row[$column] ?? '—' }}</a>
                                    @elseif(in_array($column, $moneyColumns, true) || str_contains($column, 'amount'))
                                        {{ formatMoney((float) ($row[$column] ?? 0)) }}
                                    @elseif($column === 'margin_percent' || $column === 'discount_percent')
                                        {{ isset($row[$column]) ? number_format((float) $row[$column], 1) . '%' : '—' }}
                                    @elseif($column === 'quantity_sold' || $column === 'quantity')
                                        {{ number_format((float) ($row[$column] ?? 0), 3) }}
                                    @else
                                        {{ $row[$column] ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                    @endforelse
                </x-erp.ui.data-table>

                @if($reportKey === 'sales' && ! empty($totals))
                    <div class="mt-4 overflow-x-auto rounded-xl bg-slate-50 p-4 text-sm">
                        <div class="font-bold text-slate-700 mb-2">جمع کل (مطابق فیلتر)</div>
                        <div class="grid gap-2 md:grid-cols-4 xl:grid-cols-8">
                            <div>تعداد: <strong>{{ number_format($totals['invoice_count'] ?? 0) }}</strong></div>
                            <div>ناخالص: <strong>{{ formatMoney((float) ($totals['gross_amount'] ?? 0)) }}</strong></div>
                            <div>تخفیف: <strong>{{ formatMoney((float) ($totals['discount_amount'] ?? 0)) }}</strong></div>
                            <div>خالص: <strong>{{ formatMoney((float) ($totals['net_amount'] ?? 0)) }}</strong></div>
                            <div>مالیات: <strong>{{ formatMoney((float) ($totals['tax_amount'] ?? 0)) }}</strong></div>
                            <div>نهایی: <strong>{{ formatMoney((float) ($totals['total_amount'] ?? 0)) }}</strong></div>
                            <div>پرداخت: <strong>{{ formatMoney((float) ($totals['paid_amount'] ?? 0)) }}</strong></div>
                            <div>مانده: <strong>{{ formatMoney((float) ($totals['outstanding_amount'] ?? 0)) }}</strong></div>
                        </div>
                    </div>
                @endif

                @if($paginator && ! $printMode)
                    <div class="mt-4">{{ $paginator->links() }}</div>
                @endif
            </section>
        @endforeach
    </div>
</div>
