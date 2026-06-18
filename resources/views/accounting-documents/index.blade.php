@php
    $typeLabels = [
        'manual' => 'دستی',
        'sale_invoice' => 'فاکتور فروش',
        'purchase_invoice' => 'فاکتور خرید',
        'payment' => 'پرداخت',
        'receipt' => 'دریافت',
        'inventory' => 'انبار',
        'closing' => 'اختتامیه',
    ];

    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'posted' => 'ثبت قطعی',
        'void' => 'باطل',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">اسناد حسابداری</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">فهرست اسناد حسابداری</h2>
            <a href="{{ route('accounting-documents.create') }}" class="erp-action-btn erp-action-edit">ثبت سند حسابداری</a>
        </div>

        <form method="GET" action="{{ route('accounting-documents.index') }}" class="erp-ui-filter-bar">
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input name="search" value="{{ request('search') }}" placeholder="شماره سند، شرح یا سرفصل">
                </label>
                <label class="erp-filter-field">نوع
                    <select name="type">
                        <option value="">همه</option>
                        @foreach($typeLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select name="status">
                        <option value="">همه</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">از تاریخ
                    <input type="text" name="date_from" data-jalali-datepicker inputmode="numeric" dir="ltr" placeholder="1405/01/01" value="{{ request('date_from') ? jalaliDateInputValue(request('date_from')) : '' }}">
                </label>
                <label class="erp-filter-field">تا تاریخ
                    <input type="text" name="date_to" data-jalali-datepicker inputmode="numeric" dir="ltr" placeholder="1405/12/29" value="{{ request('date_to') ? jalaliDateInputValue(request('date_to')) : '' }}">
                </label>
                <x-filter-actions :reset-route="route('accounting-documents.index')" />
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table">
                <thead>
                    <tr>
                        <th>شماره سند</th>
                        <th>تاریخ</th>
                        <th>نوع</th>
                        <th>وضعیت</th>
                        <th>بدهکار (ریال)</th>
                        <th>بستانکار (ریال)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td>{{ $document->number }}</td>
                            <td>{{ gregorianToJalaliDate($document->document_date) }}</td>
                            <td>{{ $typeLabels[$document->type] ?? $document->type }}</td>
                            <td>{{ $statusLabels[$document->status] ?? $document->status }}</td>
                            <td>{{ number_format($document->lines->sum('debit')) }}</td>
                            <td>{{ number_format($document->lines->sum('credit')) }}</td>
                            <td>
                                <a class="erp-action-btn erp-action-detail" href="{{ route('accounting-documents.show', $document) }}">مشاهده</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-slate-500 py-6">سندی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $documents->links() }}</div>
    </div>
</x-app-layout>
