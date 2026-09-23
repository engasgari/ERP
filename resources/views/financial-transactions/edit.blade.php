<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">ویرایش تراکنش مالی</h2>
    </x-slot>

    <x-erp.ui.form-page
        title="ویرایش تراکنش"
        description="اطلاعات و کدینگ مالی این سند را به‌روز کنید."
        route="financial-transactions.edit"
        :back-url="route('financial-transactions.index')"
    >
        @include('financial-transactions._form', [
            'isEdit' => true,
            'financialTransaction' => $financialTransaction,
            'action' => route('financial-transactions.update', $financialTransaction),
            'buttonLabel' => 'ذخیره تغییرات',
            'backRoute' => route('financial-transactions.index'),
        ])
    </x-erp.ui.form-page>
</x-app-layout>
