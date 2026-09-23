<x-erp.ui.page-shell :title="$title">
    <x-erp.ui.panel>
        <x-erp.ui.page-header :title="$title" />

        <x-erp.ui.filter-bar method="GET" action="{{ route('management-reports.payroll-summary') }}" data-erp-auto-filter>
            <div class="erp-filter-row payroll-summary-filter-row">
                <label class="erp-filter-field">جستجو
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="نام یا کد پرسنلی">
                </label>
                <label class="erp-filter-field">دوره حقوق
                    <select name="payroll_period_id">
                        <option value="">همه</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}" @selected((string) ($filters['payroll_period_id'] ?? '') === (string) $period->id)>
                                {{ $period->persian_title }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select name="status">
                        <option value="">همه</option>
                        <option value="calculated" @selected(($filters['status'] ?? '') === 'calculated')>محاسبه‌شده</option>
                        <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>پرداخت‌شده</option>
                        <option value="partial" @selected(($filters['status'] ?? '') === 'partial')>پرداخت جزئی</option>
                        <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>ناموفق</option>
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <a href="{{ route('management-reports.payroll-summary') }}" wire:navigate class="erp-action-btn">حذف فیلترها</a>
                </div>
            </div>
        </x-erp.ui.filter-bar>

        <x-erp.ui.data-table :headers="$headers" :colspan="count($headers)" empty-message="داده‌ای برای نمایش وجود ندارد.">
            @forelse($rows as $row)
                <tr>
                    @foreach($mapper($row) as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" class="px-3 py-8 text-center text-slate-500">داده‌ای برای نمایش وجود ندارد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>

        <div class="mt-4">{{ $rows->links() }}</div>
    </x-erp.ui.panel>

    <style>
        .erp-shell .payroll-summary-filter-row {
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.95fr) minmax(0, 0.8fr) auto;
        }

        .erp-shell .payroll-summary-filter-row .erp-filter-actions {
            flex-wrap: nowrap;
        }
    </style>
</x-erp.ui.page-shell>
