@php
    $typeLabels = [
        'manual' => 'دستی',
        'sale_invoice' => 'فاکتور فروش',
        'purchase_invoice' => 'فاکتور خرید',
        'payment' => 'پرداخت',
        'receipt' => 'دریافت',
        'inventory' => 'انبار',
        'opening' => 'افتتاحیه',
        'closing' => 'اختتامیه',
    ];
    $statusLabels = ['draft' => 'پیش‌نویس', 'posted' => 'ثبت قطعی', 'void' => 'باطل'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">سند حسابداری {{ $document->number }}</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex justify-between gap-4">
            <div>
                <div>{{ gregorianToJalaliDate($document->document_date) }} | {{ $typeLabels[$document->type] ?? $document->type }} | {{ $statusLabels[$document->status] ?? $document->status }}</div>
                <div class="text-slate-600">{{ $document->description }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($document->is_automatic)
                    <span class="erp-action-btn erp-action-detail">سند سیستمی - غیرقابل ویرایش</span>
                @else
                    <a href="{{ route('accounting-documents.edit', $document) }}" class="erp-action-btn erp-action-edit">ویرایش</a>
                    <form method="post" action="{{ route('accounting-documents.destroy', $document) }}" onsubmit="return confirm('آیا از حذف این سند حسابداری مطمئن هستید؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="erp-action-btn erp-action-delete">حذف</button>
                    </form>
                @endif
                <a href="{{ route('accounting-documents.print', $document) }}" target="_blank" class="erp-action-btn erp-action-edit">چاپ</a>
                <a href="{{ route('accounting-documents.pdf', $document) }}" class="erp-action-btn erp-action-detail" data-no-spa>PDF</a>
                @if($document->status !== 'posted')
                    <form method="post" action="{{ route('accounting-documents.post', $document) }}">
                        @csrf
                        <button class="bg-green-600 text-white px-3 py-2 rounded">ثبت قطعی</button>
                    </form>
                @elseif(! $document->is_automatic)
                    <form method="post" action="{{ route('accounting-documents.unpost', $document) }}">
                        @csrf
                        <button class="bg-gray-600 text-white px-3 py-2 rounded">برگشت به پیش‌نویس</button>
                    </form>
                @endif
            </div>
        </div>

        <table class="erp-ui-data-table">
            <thead>
                <tr>
                    <th>حساب</th>
                    <th>تفصیل</th>
                    <th>شخص/شرکت</th>
                    <th>پروژه</th>
                    <th>شرح</th>
                    <th>بدهکار (ریال)</th>
                    <th>بستانکار (ریال)</th>
                </tr>
            </thead>
            <tbody>
            @foreach($document->lines as $line)
                <tr>
                    <td>
                        <div>{{ chartAccountDisplayLabel($line->account) }}</div>
                        @if($line->bankAccount)
                            <div class="mt-1 text-xs text-slate-500">بانک: {{ $line->bankAccount->bank_name }} - {{ $line->bankAccount->code }}</div>
                        @endif
                    </td>
                    <td>{{ chartAccountDisplayLabel($line->detailAccount) }}</td>
                    <td>{{ $line->party?->name ?: '-' }}</td>
                    <td>{{ $line->project?->name ?: '-' }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ number_format($line->debit) }}</td>
                    <td>{{ number_format($line->credit) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
