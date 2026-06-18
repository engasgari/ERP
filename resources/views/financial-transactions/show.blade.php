<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">جزئیات تراکنش مالی</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-3xl mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">{{ $financialTransaction->type_label }}</h2>
                <div class="flex gap-2">
                    <a href="{{ route('financial-transactions.edit', $financialTransaction) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">ویرایش</a>
                    <a href="{{ route('financial-transactions.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">بازگشت</a>
                </div>
            </div>

            <table class="w-full border-collapse border border-gray-300">
                <tbody>
                    <tr><th class="border border-gray-300 p-3">پروژه</th><td class="border border-gray-300 p-3">{{ $financialTransaction->project?->name ?: '-' }}</td></tr>
                    <tr><th class="border border-gray-300 p-3">دسته بندی</th><td class="border border-gray-300 p-3">{{ $financialTransaction->category }}</td></tr>
                    <tr><th class="border border-gray-300 p-3">مبلغ (ریال)</th><td class="border border-gray-300 p-3">{{ $financialTransaction->signed_amount }}</td></tr>
                    <tr><th class="border border-gray-300 p-3">تاریخ</th><td class="border border-gray-300 p-3">{{ verta($financialTransaction->transaction_date)->format('Y/m/d') }}</td></tr>
                    <tr><th class="border border-gray-300 p-3">شماره مرجع</th><td class="border border-gray-300 p-3">{{ $financialTransaction->reference_number ?: '-' }}</td></tr>
                    <tr><th class="border border-gray-300 p-3">شرح</th><td class="border border-gray-300 p-3">{{ $financialTransaction->description ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
