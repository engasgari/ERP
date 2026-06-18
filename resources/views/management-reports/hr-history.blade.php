<x-erp.ui.page-shell title="گزارش سوابق پرسنلی">
    <x-erp.ui.panel>
        <x-erp.ui.page-header title="سوابق پرسنلی" />

        <x-erp.ui.filter-bar method="GET">
            <div class="row g-2">
                <label class="erp-filter-field col-12 col-md-9">جستجو
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="نام پرسنل، عنوان یا رویداد">
                </label>
                <div class="col-12 col-md-3 d-grid"><button class="erp-action-btn">اعمال فیلتر</button></div>
            </div>
        </x-erp.ui.filter-bar>

        <x-erp.ui.data-table :headers="['پرسنل', 'رویداد', 'عنوان', 'تاریخ اثر', 'ثبت']" colspan="5">
            @forelse($rows as $history)
                <tr>
                    <td>{{ $history->employee?->full_name ?: '-' }}</td>
                    <td>{{ $history->event }}</td>
                    <td>{{ $history->title }}</td>
                    <td>{{ formatJalaliDateSafe($history->effective_date, '-') }}</td>
                    <td>{{ formatJalaliDateSafe($history->created_at, '-') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">رکوردی یافت نشد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>

        {{ $rows->links() }}
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
