<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">تراکنش‌های مالی</h2>
    </x-slot>

    <x-erp.ui.form-page
        title="ثبت هزینه یا درآمد"
        description="انتخاب دسته‌بندی معین و تفصیل، سپس ثبت سریع با سند حسابداری خودکار."
        route="financial-transactions.create"
        :back-url="route('financial-transactions.index')"
    >
        @include('financial-transactions._form', [
            'isEdit' => false,
            'action' => route('financial-transactions.store'),
            'buttonLabel' => 'ثبت تراکنش',
            'backRoute' => route('financial-transactions.index'),
        ])
    </x-erp.ui.form-page>
</x-app-layout>
