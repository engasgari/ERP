<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('تراکنش‌های مالی') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">ثبت هزینه یا درآمد غیرفاکتوری</h1>
                    <p class="mt-1 text-sm text-slate-500">ثبت سریع اجاره، ناهار پرسنل، تنخواه و درآمدهای خاص با سند حسابداری خودکار.</p>
                </div>
                <a href="{{ route('financial-transactions.index') }}" class="rounded-md bg-gray-500 px-4 py-2 text-white transition duration-200 hover:bg-gray-600">بازگشت</a>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @include('financial-transactions._form', [
                    'isEdit' => false,
                    'action' => route('financial-transactions.store'),
                    'buttonLabel' => 'ثبت تراکنش',
                    'backRoute' => route('financial-transactions.index'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
