<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">حساب جاری شرکا</h2>
    </x-slot>

    <x-erp.ui.form-page
        title="ثبت انتقال حساب جاری شریک"
        description="واریز یا برداشت بین حساب بانکی شرکت و حساب جاری شریک در گروه ۳۲ با سند حسابداری خودکار."
        route="partner-current-accounts.create"
        :back-url="route('partner-current-accounts.index')"
    >
        @include('partner-current-accounts._form')
    </x-erp.ui.form-page>
</x-app-layout>
