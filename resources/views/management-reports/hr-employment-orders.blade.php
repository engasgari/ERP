<x-erp.ui.page-shell title="گزارش احکام کارگزینی">
    <x-erp.ui.panel>
        <x-erp.ui.page-header title="احکام کارگزینی" />

        <x-erp.ui.filter-bar method="GET">
            <div class="row g-2">
                <label class="erp-filter-field col-12 col-md-6">جستجو
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="شماره حکم یا نام پرسنل">
                </label>
                <label class="erp-filter-field col-12 col-md-3">وضعیت
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>پیش‌نویس</option>
                        <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>تایید شده</option>
                    </select>
                </label>
                <div class="col-12 col-md-3 d-grid"><button class="erp-action-btn">اعمال فیلتر</button></div>
            </div>
        </x-erp.ui.filter-bar>

        <x-erp.ui.data-table :headers="['شماره', 'پرسنل', 'نوع', 'تاریخ اثر', 'پست', 'حقوق پایه', 'وضعیت']" colspan="7">
            @forelse($rows as $order)
                <tr>
                    <td>{{ $order->number }}</td>
                    <td>{{ $order->employee?->full_name ?: '-' }}</td>
                    <td>{{ $order->order_type }}</td>
                    <td>{{ formatJalaliDateSafe($order->effective_date) }}</td>
                    <td>{{ $order->employee?->position ?: '-' }}</td>
                    <td>{{ number_format((float) $order->base_salary) }}</td>
                    <td>{{ $order->status === 'approved' ? 'تایید شده' : 'پیش‌نویس' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">رکوردی یافت نشد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>

        {{ $rows->links() }}
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
