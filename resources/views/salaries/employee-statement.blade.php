<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('صورتحساب پرسنل - ' . $employee->full_name) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- اطلاعات پرسنل -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <strong>نام:</strong> {{ $employee->full_name }}
                            </div>
                            <div>
                                <strong>کد ملی:</strong> {{ $employee->national_code }}
                            </div>
                            <div>
                                <strong>سمت:</strong> {{ $employee->position }}
                            </div>
                        </div>
                    </div>

                    <!-- خلاصه مالی -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                            <div class="text-red-600 font-semibold">مجموع بدهکاری (ریال)</div>
                            <div class="text-2xl font-bold text-red-700">{{ number_format($debitTotal) }}</div>
                            <div class="text-sm text-red-600">ریال</div>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                            <div class="text-green-600 font-semibold">مجموع بستانکاری (ریال)</div>
                            <div class="text-2xl font-bold text-green-700">{{ number_format($creditTotal) }}</div>
                            <div class="text-sm text-green-600">ریال</div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <div class="text-blue-600 font-semibold">مانده حساب</div>
                            <div class="text-2xl font-bold {{ $balance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ number_format(abs($balance)) }}
                            </div>
                            <div class="text-sm {{ $balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $balance >= 0 ? 'بستانکار' : 'بدهکار' }}
                            </div>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                            <div class="text-gray-600 font-semibold">تعداد تراکنش‌ها</div>
                            <div class="text-2xl font-bold text-gray-700">{{ $transactions->count() }}</div>
                            <div class="text-sm text-gray-600">تراکنش</div>
                        </div>
                    </div>

                    <!-- لیست تراکنش‌ها -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 border-b text-right">تاریخ</th>
                                <th class="py-3 px-4 border-b text-right">نوع</th>
                                <th class="py-3 px-4 border-b text-right">مبلغ (ریال)</th>
                                <th class="py-3 px-4 border-b text-right">شرح</th>
                                <th class="py-3 px-4 border-b text-right">مرجع</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transactions as $transaction)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        {{ verta($transaction->transaction_date)->format('Y/m/d') }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="{{ $transaction->type_color }} font-semibold">
                                            {{ $transaction->type_text }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-semibold {{ $transaction->type_color }}">
                                        {{ number_format($transaction->amount) }} ریال
                                    </td>
                                    <td class="py-3 px-4">
                                        {{ $transaction->description }}
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-500">
                                        {{ $transaction->reference_type }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 px-4 text-center text-gray-500">
                                        هیچ تراکنشی یافت نشد
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <a href="{{ route('salaries.financial-report') }}"
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            بازگشت
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
