<x-erp.ui.page-shell title="گزارش احکام کارگزینی">
    <x-erp.ui.panel>
        <x-erp.ui.page-header
            title="احکام کارگزینی"
            description="فهرست احکام برای کنترل، ارائه و چاپ فرم استاندارد وزارت کار"
            :actions="[[
                'label' => 'چاپ گزارش',
                'url' => route('management-reports.hr-employment-orders.print', request()->query()),
                'class' => 'erp-action-detail',
            ]]"
        />

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

        <x-erp.ui.data-table :headers="['شماره', 'پرسنل', 'نوع', 'تاریخ اجرا', 'گروه/رتبه/پایه', 'حقوق پایه', 'مشمول بیمه', 'وضعیت', 'عملیات']" colspan="9">
            @forelse($rows as $order)
                <tr>
                    <td>{{ $order->number }}</td>
                    <td>{{ $order->employee?->full_name ?: '-' }}</td>
                    <td>{{ $order->orderTypeLabel() }}</td>
                    <td>{{ formatJalaliDateSafe($order->effective_date) }}</td>
                    <td dir="ltr">{{ ($order->job_group ?: '-') . ' / ' . ($order->job_rank ?: '-') . ' / ' . ($order->job_base ?: '-') }}</td>
                    <td>{{ formatMoney((float) $order->base_salary) }}</td>
                    <td>{{ formatMoney($order->totalInsurableWage()) }}</td>
                    <td>{{ $order->status === 'approved' ? 'تایید شده' : 'پیش‌نویس' }}</td>
                    <td>
                        <a class="erp-action-btn erp-action-detail" target="_blank"
                           href="{{ route('management-reports.hr-employment-orders.print-form', $order) }}">
                            چاپ فرم حکم
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-500">رکوردی یافت نشد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>

        {{ $rows->links() }}
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
