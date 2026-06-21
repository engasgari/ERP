<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ویرایش تراکنش مالی') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">ویرایش تراکنش</h1>
                    <p class="mt-1 text-sm text-slate-500">اطلاعات و کدینگ مالی این سند را به‌روز کنید.</p>
                </div>
                <a href="{{ route('financial-transactions.index') }}" class="rounded-md bg-gray-500 px-4 py-2 text-white transition duration-200 hover:bg-gray-600">بازگشت</a>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @include('financial-transactions._form', [
                    'isEdit' => true,
                    'financialTransaction' => $financialTransaction,
                    'action' => route('financial-transactions.update', $financialTransaction),
                    'buttonLabel' => 'ذخیره تغییرات',
                    'backRoute' => route('financial-transactions.index'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
