<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">جزئیات تراکنش مالی</h2>
    </x-slot>

    <x-erp.ui.detail-page
        :title="$financialTransaction->type_label"
        :description="($financialTransaction->category ?: 'بدون دسته') . ' — ' . gregorianToJalaliDate($financialTransaction->transaction_date)"
        route="financial-transactions.show"
        :actions="[['label' => 'بازگشت', 'url' => route('financial-transactions.index'), 'class' => 'erp-action-detail']]"
    >
        <x-slot name="toolbar">
            <a href="{{ route('financial-transactions.edit', $financialTransaction) }}" class="erp-action-btn erp-action-edit text-center">ویرایش</a>
            <form method="POST" action="{{ route('financial-transactions.destroy', $financialTransaction) }}" onsubmit="return confirm('آیا از حذف این تراکنش مطمئن هستید؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="erp-action-btn erp-action-delete">حذف</button>
            </form>
        </x-slot>

        <div class="erp-modal-grid">
            <div class="erp-modal-field">نوع
                <div class="erp-modal-value">
                    <x-erp.ui.status-badge
                        :label="$financialTransaction->type_label"
                        :tone="$financialTransaction->type === 'income' ? 'success' : 'danger'"
                    />
                </div>
            </div>
            <div class="erp-modal-field">مبلغ (ریال)
                <div class="erp-modal-value font-semibold {{ $financialTransaction->type === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $financialTransaction->signed_amount }}
                </div>
            </div>
            <div class="erp-modal-field">تاریخ<div class="erp-modal-value">{{ gregorianToJalaliDate($financialTransaction->transaction_date) }}</div></div>
            <div class="erp-modal-field">دسته‌بندی<div class="erp-modal-value">{{ $financialTransaction->category ?: '-' }}</div></div>
            <div class="erp-modal-field">پروژه<div class="erp-modal-value">{{ $financialTransaction->project?->name ?: '-' }}</div></div>
            <div class="erp-modal-field">بانک<div class="erp-modal-value">{{ $financialTransaction->bankAccount ? $financialTransaction->bankAccount->bank_name . ' - ' . $financialTransaction->bankAccount->code : '-' }}</div></div>
            <div class="erp-modal-field">صندوق<div class="erp-modal-value">{{ $financialTransaction->cashbox ? $financialTransaction->cashbox->name . ' - ' . $financialTransaction->cashbox->code : '-' }}</div></div>
            <div class="erp-modal-field">کدینگ<div class="erp-modal-value">{{ $financialTransaction->coding_label }}</div></div>
            <div class="erp-modal-field">سند حسابداری
                <div class="erp-modal-value">
                    @if($financialTransaction->accountingDocument)
                        <a href="{{ route('accounting-documents.show', $financialTransaction->accountingDocument) }}" class="text-blue-700 font-semibold">
                            {{ $financialTransaction->accountingDocument->number }}
                        </a>
                    @else
                        -
                    @endif
                </div>
            </div>
            <div class="erp-modal-field">شماره مرجع<div class="erp-modal-value">{{ $financialTransaction->reference_number ?: '-' }}</div></div>
            <div class="erp-modal-field md:col-span-2">شرح<div class="erp-modal-value">{{ $financialTransaction->description ?: '-' }}</div></div>
        </div>
    </x-erp.ui.detail-page>
</x-app-layout>
