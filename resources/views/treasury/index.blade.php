@php
    $typeLabels = [
        'deposit' => 'واریز',
        'withdrawal' => 'برداشت',
        'transfer' => 'انتقال بین حساب‌ها',
        'cash_receipt' => 'دریافت نقدی',
        'cash_payment' => 'پرداخت نقدی',
        'bank_receipt' => 'دریافت بانکی',
        'bank_payment' => 'پرداخت بانکی',
    ];

    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'posted' => 'ثبت قطعی',
        'void' => 'باطل',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">خزانه‌داری</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">تراکنش‌های خزانه</h2>
            <a href="{{ route('treasury.create') }}" class="erp-action-btn erp-action-edit">ثبت تراکنش خزانه</a>
        </div>

        <form method="GET" action="{{ route('treasury.index') }}" class="erp-ui-filter-bar">
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input name="search" value="{{ request('search') }}" placeholder="شماره، شرح، شخص یا سند حسابداری">
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
                <x-filter-actions :reset-route="route('treasury.index')" />
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>تاریخ</th>
                        <th>نوع</th>
                        <th>مبلغ (ریال)</th>
                        <th>شخص/شرکت</th>
                        <th>وضعیت</th>
                        <th>سند حسابداری</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->number }}</td>
                            <td>{{ gregorianToJalaliDate($transaction->transaction_date) }}</td>
                            <td>{{ $typeLabels[$transaction->type] ?? $transaction->type }}</td>
                            <td>{{ number_format($transaction->amount) }}</td>
                            <td>{{ $transaction->party?->name ?: '-' }}</td>
                            <td>{{ $statusLabels[$transaction->status] ?? $transaction->status }}</td>
                            <td>{{ $transaction->accountingDocument?->number ?: '-' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <a href="{{ route('treasury.edit', $transaction) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                                    <form method="POST" action="{{ route('treasury.destroy', $transaction) }}" onsubmit="return confirm('تراکنش خزانه و سند حسابداری وابسته حذف شوند؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="erp-action-btn erp-action-delete">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-slate-500 py-6">تراکنشی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
