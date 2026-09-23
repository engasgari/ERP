<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            جزئیات پرداخت بیمه {{ $payment->number }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8" dir="rtl">
            <div class="rounded-lg bg-white p-6 shadow-md">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <div class="text-xs text-slate-500">شماره</div>
                        <div class="font-bold">{{ $payment->number }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">تاریخ</div>
                        <div class="font-bold">{{ formatJalaliDateSafe($payment->payment_date) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">مبلغ کل</div>
                        <div class="font-bold">{{ formatMoney((float) $payment->total_amount) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">روش</div>
                        <div class="font-bold">{{ $payment->method === 'cash' ? 'نقدی' : 'بانکی' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">حساب/صندوق</div>
                        <div class="font-bold">
                            @if($payment->method === 'bank' && $payment->bankAccount)
                                {{ $payment->bankAccount->bank_name }} - {{ $payment->bankAccount->account_number }}
                            @elseif($payment->cashbox)
                                {{ $payment->cashbox->name }}
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">ثبت‌کننده</div>
                        <div class="font-bold">{{ $payment->creator?->name ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">سند حسابداری</div>
                        <div class="font-bold">
                            @if($payment->accountingDocument)
                                <a href="{{ route('accounting-documents.show', $payment->accountingDocument) }}" class="text-blue-600 hover:underline">{{ $payment->accountingDocument->number }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">توضیحات</div>
                        <div class="font-bold">{{ $payment->description ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-md">
                <h3 class="mb-4 text-lg font-bold">ردیف‌های پرداخت</h3>
                <div class="overflow-x-auto">
                    <table class="erp-ui-data-table min-w-full text-sm">
                        <thead>
                        <tr>
                            <th>دوره</th>
                            <th>اصل</th>
                            <th>جریمه</th>
                            <th>سایر</th>
                            <th>جمع</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($payment->lines as $line)
                            <tr>
                                <td>{{ $line->period->persian_title }}</td>
                                <td>{{ formatMoney((float) $line->principal_amount) }}</td>
                                <td>{{ formatMoney((float) $line->penalty_amount) }}</td>
                                <td>{{ formatMoney((float) $line->other_amount) }}</td>
                                <td>{{ formatMoney((float) $line->total_amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('insurance.payments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">بازگشت</a>
            </div>
        </div>
    </div>
</x-app-layout>
