@php
    $printMode = $printMode ?? false;
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
                        <a href="{{ $printUrl }}" target="_blank" data-no-spa class="erp-action-btn erp-action-detail">چاپ</a>
                        <a href="{{ $pdfUrl }}" data-no-spa class="erp-action-btn erp-action-detail">PDF</a>
                        <a href="{{ $excelUrl }}" data-no-spa class="erp-action-btn erp-action-detail">Excel</a>
                    </div>
                @endif
            </div>

            @if(! $printMode)
                <x-erp.ui.filter-bar method="GET" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm font-bold text-slate-700">از تاریخ
                        <input type="text" name="date_from" value="{{ request('date_from') ? jalaliDateInputValue(request('date_from')) : '' }}" class="mt-1 w-full rounded-lg border-slate-300">
                    </label>
                    <label class="text-sm font-bold text-slate-700">تا تاریخ
                        <input type="text" name="date_to" value="{{ request('date_to') ? jalaliDateInputValue(request('date_to')) : '' }}" class="mt-1 w-full rounded-lg border-slate-300">
                    </label>
                    <label class="text-sm font-bold text-slate-700">سال مالی
                        <select name="fiscal_year_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">همه</option>
                            @foreach($fiscalYears ?? [] as $year)
                                <option value="{{ $year->id }}" @selected(request('fiscal_year_id') == $year->id)>{{ $year->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">شعبه
                        <input type="number" name="branch_id" value="{{ request('branch_id') }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="شناسه شعبه">
                    </label>
                    <label class="text-sm font-bold text-slate-700">شرکت
                        <input type="number" name="company_id" value="{{ request('company_id') }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="شناسه شرکت">
                    </label>
                    <label class="text-sm font-bold text-slate-700">پروژه
                        <select name="project_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">همه</option>
                            @foreach($projects ?? [] as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">حساب
                        <select name="account_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">همه</option>
                            @foreach($accounts ?? [] as $account)
                                <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->code }} - {{ $account->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">طرف حساب
                        <select name="party_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">همه</option>
                            @foreach($parties ?? [] as $party)
                                <option value="{{ $party->id }}" @selected(request('party_id') == $party->id)>{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">مرکز هزینه
                        <select name="cost_center" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">همه</option>
                            @foreach($costCenters ?? [] as $costCenter)
                                <option value="{{ $costCenter->code }}" @selected(request('cost_center') == $costCenter->code)>{{ $costCenter->code }} - {{ $costCenter->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">جستجو
                        <input type="text" name="search" value="{{ request('search') }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="شماره سند، نام، شرح...">
                    </label>
                    <label class="text-sm font-bold text-slate-700">تعداد در صفحه
                        <input type="number" name="per_page" value="{{ request('per_page', 25) }}" class="mt-1 w-full rounded-lg border-slate-300" min="10" max="200">
                    </label>
                    <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-4">
                        <button class="erp-action-btn erp-action-detail" type="submit">اعمال فیلتر</button>
                        <a href="{{ route('financial-reports.show', ['report' => $reportKey]) }}" class="erp-action-btn erp-action-detail">پاک کردن</a>
                    </div>
                </x-erp.ui.filter-bar>
            @endif
        </section>

        @if(! empty($summary))
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @php
                    $summaryLabels = [
                        'opening_debit' => 'مانده افتتاحیه بدهکار',
                        'opening_credit' => 'مانده افتتاحیه بستانکار',
                        'period_debit' => 'گردش بدهکار دوره',
                        'period_credit' => 'گردش بستانکار دوره',
                        'closing_debit' => 'مانده نهایی بدهکار',
                        'closing_credit' => 'مانده نهایی بستانکار',
                        'opening_cash' => 'نقد افتتاحیه',
                        'period_cash_in' => 'ورودی نقد دوره',
                        'period_cash_out' => 'خروجی نقد دوره',
                        'closing_cash' => 'نقد پایان دوره',
                        'net_profit' => 'سود خالص',
                        'revenue' => 'درآمد',
                        'expenses' => 'هزینه',
                        'cost_of_sales' => 'بهای تمام‌شده',
                        'balance' => 'مانده',
                    ];
                @endphp
                @foreach($summary as $label => $value)
                    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $summaryLabels[$label] ?? str_replace('_', ' ', $label) }}</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ is_numeric($value) ? number_format((float) $value) : $value }}</div>
                    </div>
                @endforeach
            </section>
        @endif

        @foreach($sections as $section)
            @php
                $rows = $section['rows'] ?? [];
                $paginator = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows : null;
                $items = $paginator ? $paginator->getCollection() : collect($rows);
                $hasDrilldown = $items->contains(fn ($row) => is_array($row) && ! empty($row['lines'] ?? []));
                $resolveColumns = function (string $reportKey, array $section) {
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
                        'cash-book', 'bank-book' => ['code', 'name', 'opening', 'debit', 'credit', 'closing'],
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
                        default => is_array($items->first()) ? array_keys($items->first()) : [],
                    };
                };
                $columns = $section['columns'] ?? $resolveColumns($reportKey, $section);
            @endphp

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-lg font-black text-slate-900">{{ $section['title'] ?? 'بخش گزارش' }}</h3>
                        @if(! empty($section['totals']))
                            <p class="mt-1 text-sm text-slate-500">جمع‌ها و زیرجمع‌های این بخش در انتهای جدول محاسبه شده‌اند.</p>
                        @endif
                    </div>
                </div>

                <x-erp.ui.data-table :headers="$section['headers'] ?? []" :colspan="count($section['headers'] ?? []) + ($hasDrilldown ? 1 : 0)" :empty-message="'رکوردی برای نمایش وجود ندارد.'">
                    @forelse($items as $row)
                        <tr>
                            @foreach($columns as $key)
                                <td>
                                    @php $cell = data_get($row, $key); @endphp
                                    @if(is_numeric($cell))
                                        {{ number_format((float) $cell) }}
                                    @elseif($cell instanceof \Carbon\CarbonInterface)
                                        {{ gregorianToJalaliDate($cell) }}
                                    @elseif(is_array($cell))
                                        {{ implode('، ', $cell) }}
                                    @elseif(is_object($cell))
                                        {{ data_get($cell, 'code') ? trim(data_get($cell, 'code') . ' - ' . (data_get($cell, 'title') ?: data_get($cell, 'name') ?: data_get($cell, 'bank_name') ?: '')) : (data_get($cell, 'title') ?: data_get($cell, 'name') ?: data_get($cell, 'bank_name') ?: '-') }}
                                    @else
                                        {{ $cell ?: '-' }}
                                    @endif
                                </td>
                            @endforeach
                            @if($hasDrilldown)
                                <td>
                                    @if(! empty($row['lines'] ?? []))
                                        <details class="group">
                                            <summary class="cursor-pointer text-sm font-bold text-blue-700">نمایش ریز</summary>
                                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                <x-erp.ui.data-table :headers="['تاریخ', 'شماره سند', 'حساب', 'شرح', 'بدهکار', 'بستانکار', 'مانده جاری']" :colspan="7">
                                                    @foreach($row['lines'] as $line)
                                                        <tr>
                                                            <td>{{ $line['date'] ?? '-' }}</td>
                                                            <td>{{ $line['document_number'] ?? '-' }}</td>
                                                            <td>{{ $line['account'] ?? '-' }}</td>
                                                            <td>{{ $line['description'] ?? '-' }}</td>
                                                            <td>{{ isset($line['debit']) ? number_format((float) $line['debit']) : '-' }}</td>
                                                            <td>{{ isset($line['credit']) ? number_format((float) $line['credit']) : '-' }}</td>
                                                            <td>{{ isset($line['running_balance']) ? number_format((float) $line['running_balance']) : '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </x-erp.ui.data-table>
                                            </div>
                                        </details>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['headers'] ?? []) + ($hasDrilldown ? 1 : 0) }}" class="py-8 text-center text-slate-500">رکوردی برای نمایش وجود ندارد.</td>
                        </tr>
                    @endforelse
                </x-erp.ui.data-table>

                @if($paginator)
                    <div class="mt-4">
                        {{ $paginator->links() }}
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
