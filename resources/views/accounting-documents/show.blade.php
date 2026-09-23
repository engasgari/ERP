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
    $statusTone = match ($document->status) {
        'posted' => 'success',
        'void' => 'danger',
        default => 'warning',
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">سند حسابداری {{ $document->number }}</h2>
    </x-slot>

    <x-erp.ui.detail-page
        :title="'سند ' . $document->number"
        :description="($typeLabels[$document->type] ?? $document->type) . ' — ' . gregorianToJalaliDate($document->document_date)"
        route="accounting-documents.show"
        :actions="[['label' => 'بازگشت', 'url' => route('accounting-documents.index'), 'class' => 'erp-action-detail']]"
    >
        <x-slot name="toolbar">
            @if($document->is_automatic)
                <span class="erp-action-btn erp-action-detail">سند سیستمی — غیرقابل ویرایش</span>
            @elseif($document->status === 'draft')
                <a href="{{ route('accounting-documents.edit', $document) }}" class="erp-action-btn erp-action-edit text-center">ویرایش</a>
                <form method="post" action="{{ route('accounting-documents.destroy', $document) }}" onsubmit="return confirm('آیا از حذف این سند حسابداری مطمئن هستید؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="erp-action-btn erp-action-delete">حذف</button>
                </form>
            @endif
            <a href="{{ route('accounting-documents.print', $document) }}" target="_blank" class="erp-action-btn erp-action-edit text-center">چاپ</a>
            <a href="{{ route('accounting-documents.pdf', $document) }}" class="erp-action-btn erp-action-detail text-center" data-no-spa>PDF</a>
            @if($document->status === 'draft' && ! $document->is_automatic)
                <form method="post" action="{{ route('accounting-documents.post', $document) }}">
                    @csrf
                    <button type="submit" class="erp-action-btn erp-action-edit">ثبت قطعی</button>
                </form>
            @elseif($document->status === 'posted' && ! $document->is_automatic && ! $document->voided_at)
                <form method="post" action="{{ route('accounting-documents.unpost', $document) }}" onsubmit="return confirm('سند به پیش‌نویس برگردد و قابل ویرایش شود؟')">
                    @csrf
                    <button type="submit" class="erp-action-btn erp-action-detail">برگشت به پیش‌نویس</button>
                </form>
            @endif
        </x-slot>

        <div class="erp-modal-grid mb-4">
            <div class="erp-modal-field">شماره سند<div class="erp-modal-value">{{ $document->number }}</div></div>
            <div class="erp-modal-field">تاریخ<div class="erp-modal-value">{{ gregorianToJalaliDate($document->document_date) }}</div></div>
            <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $typeLabels[$document->type] ?? $document->type }}</div></div>
            <div class="erp-modal-field">وضعیت
                <div class="erp-modal-value">
                    <x-erp.ui.status-badge :label="$statusLabels[$document->status] ?? $document->status" :tone="$statusTone" />
                </div>
            </div>
            <div class="erp-modal-field md:col-span-2">شرح<div class="erp-modal-value">{{ $document->description ?: '-' }}</div></div>
            @if($document->notes)
                <div class="erp-modal-field md:col-span-2">یادداشت<div class="erp-modal-value">{{ $document->notes }}</div></div>
            @endif
        </div>

        <x-erp.ui.data-table
            :headers="['حساب', 'تفصیل', 'شخص/شرکت', 'پروژه', 'شرح', 'بدهکار (ریال)', 'بستانکار (ریال)']"
            empty-message="ردیفی ثبت نشده است."
            :colspan="7"
        >
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
                    <td>{{ $line->description ?: '-' }}</td>
                    <td class="text-nowrap">{{ formatMoney((float) $line->debit) }}</td>
                    <td class="text-nowrap">{{ formatMoney((float) $line->credit) }}</td>
                </tr>
            @endforeach
        </x-erp.ui.data-table>
    </x-erp.ui.detail-page>
</x-app-layout>
