<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">پرونده پرسنلی</h2>
    </x-slot>

    @php($employee->loadMissing(['party', 'positionRecord', 'organizationUnit', 'employmentOrders.position', 'contracts', 'documents', 'history']))

    <div class="py-4 py-md-5">
        <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-5">
            @if(session('success'))<div class="rounded-md bg-green-50 p-3 text-green-700">{{ session('success') }}</div>@endif

            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold mb-1">{{ $employee->full_name }}</h2>
                    <div class="text-sm text-slate-500">کد پرسنلی: {{ $employee->personnel_code ?: $employee->employee_code ?: $employee->id }}</div>
                </div>
                <div class="d-grid d-sm-flex gap-2">
                    <a href="{{ route('employees.edit', $employee) }}" class="erp-action-btn erp-action-edit text-center">ویرایش پرونده</a>
                    <a href="{{ route('employment-orders.index') }}" class="erp-action-btn erp-action-detail text-center">ثبت حکم</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="erp-modal-field">کد ملی<div class="erp-modal-value">{{ $employee->national_code ?: '-' }}</div></div>
                <div class="erp-modal-field">موبایل<div class="erp-modal-value">{{ $employee->phone ?: '-' }}</div></div>
                <div class="erp-modal-field">واحد<div class="erp-modal-value">{{ $employee->organizationUnit?->title ?: '-' }}</div></div>
                <div class="erp-modal-field">پست<div class="erp-modal-value">{{ $employee->positionRecord?->title ?: $employee->position }}</div></div>
                <div class="erp-modal-field">تاریخ استخدام<div class="erp-modal-value">{{ formatJalaliDateSafe($employee->hire_date ?: $employee->start_date) }}</div></div>
                <div class="erp-modal-field">تاریخ پایان<div class="erp-modal-value">{{ formatJalaliDateSafe($employee->termination_date ?: $employee->end_date, '-') }}</div></div>
                <div class="erp-modal-field">نوع استخدام<div class="erp-modal-value">{{ $employee->employment_type }}</div></div>
                <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $employee->employment_status }}</div></div>
            </div>

            <section>
                <h3 class="font-bold text-slate-800 mb-2">احکام کارگزینی</h3>
                <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>شماره</th><th>نوع</th><th>تاریخ اثر</th><th>پست</th><th>حقوق پایه</th><th>وضعیت</th></tr></thead><tbody>@forelse($employee->employmentOrders as $order)<tr><td>{{ $order->number }}</td><td>{{ $order->order_type }}</td><td>{{ formatJalaliDateSafe($order->effective_date) }}</td><td>{{ $order->position?->title ?: '-' }}</td><td>{{ number_format((float) $order->base_salary) }}</td><td>{{ $order->status === 'approved' ? 'تایید شده' : 'پیش‌نویس' }}</td></tr>@empty<tr><td colspan="6" class="text-center py-4 text-slate-500">حکمی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
            </section>

            <section>
                <h3 class="font-bold text-slate-800 mb-2">قراردادها و مدارک</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>شماره قرارداد</th><th>شروع</th><th>پایان</th><th>وضعیت</th></tr></thead><tbody>@forelse($employee->contracts as $contract)<tr><td>{{ $contract->number }}</td><td>{{ formatJalaliDateSafe($contract->start_date) }}</td><td>{{ formatJalaliDateSafe($contract->end_date, '-') }}</td><td>{{ $contract->status }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-slate-500">قراردادی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
                    <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>نوع</th><th>عنوان</th><th>انقضا</th></tr></thead><tbody>@forelse($employee->documents as $document)<tr><td>{{ $document->document_type }}</td><td>{{ $document->title }}</td><td>{{ formatJalaliDateSafe($document->expires_at, '-') }}</td></tr>@empty<tr><td colspan="3" class="text-center py-4 text-slate-500">مدرکی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
                </div>
            </section>

            <section>
                <h3 class="font-bold text-slate-800 mb-2">سوابق پرسنلی</h3>
                <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>رویداد</th><th>عنوان</th><th>تاریخ اثر</th><th>ثبت</th></tr></thead><tbody>@forelse($employee->history as $history)<tr><td>{{ $history->event }}</td><td>{{ $history->title }}</td><td>{{ formatJalaliDateSafe($history->effective_date, '-') }}</td><td>{{ formatJalaliDateSafe($history->created_at, '-') }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-slate-500">سابقه‌ای ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
