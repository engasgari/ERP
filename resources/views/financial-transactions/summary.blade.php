<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('گزارش تراکنش های مالی عمومی') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold">گزارش جمع‌بندی هزینه و درآمدهای غیر فاکتوری</h2>
                    <p class="text-sm text-gray-500 mt-1">برای اجاره، ناهار پرسنل، تنخواه، هزینه‌های روزمره و درآمدهای خاص.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('financial-transactions.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">بازگشت</a>
                    <a href="{{ route('financial-transactions.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">ثبت تراکنش</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-green-600 text-sm">کل درآمد</div>
                    <div class="text-2xl font-bold text-green-700">{{ number_format($summary['total_income']) }} ریال</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="text-red-600 text-sm">کل هزینه</div>
                    <div class="text-2xl font-bold text-red-700">{{ number_format($summary['total_expense']) }} ریال</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-blue-600 text-sm">خالص</div>
                    <div class="text-2xl font-bold {{ $summary['profit_loss'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($summary['profit_loss']) }} ریال</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold mb-4">خلاصه بر اساس نوع</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span>تعداد تراکنش‌ها</span>
                            <span class="font-semibold">{{ $transactions->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>تعداد درآمدها</span>
                            <span class="font-semibold text-green-700">{{ $transactions->where('type', 'income')->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>تعداد هزینه‌ها</span>
                            <span class="font-semibold text-red-700">{{ $transactions->where('type', 'expense')->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>مجموع درآمد</span>
                            <span class="font-semibold text-green-700">{{ number_format($byType['income'] ?? 0) }} ریال</span>
                        </div>
                        <div class="flex justify-between">
                            <span>مجموع هزینه</span>
                            <span class="font-semibold text-red-700">{{ number_format($byType['expense'] ?? 0) }} ریال</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold mb-4">خلاصه بر اساس منبع</h3>
                    <div class="space-y-3 text-sm max-h-80 overflow-auto">
                        @foreach($bySource as $row)
                            <div class="border border-gray-100 rounded p-3">
                                <div class="flex justify-between gap-4">
                                    <span class="font-semibold">{{ str_replace(['bank:', 'cashbox:'], ['بانک: ', 'صندوق: '], $row['label']) }}</span>
                                    <span>{{ $row['count'] }} سند</span>
                                </div>
                                <div class="flex justify-between mt-2">
                                    <span class="text-green-700">درآمد: {{ number_format($row['income']) }}</span>
                                    <span class="text-red-700">هزینه: {{ number_format($row['expense']) }}</span>
                                    <span class="{{ $row['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">خالص: {{ number_format($row['net']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold mb-4">تحلیل بر اساس دسته‌بندی</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 p-3">دسته</th>
                            <th class="border border-gray-300 p-3">تعداد</th>
                            <th class="border border-gray-300 p-3">درآمد</th>
                            <th class="border border-gray-300 p-3">هزینه</th>
                            <th class="border border-gray-300 p-3">خالص</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($byCategory as $row)
                            <tr>
                                <td class="border border-gray-300 p-3">{{ $row['category'] }}</td>
                                <td class="border border-gray-300 p-3">{{ $row['count'] }}</td>
                                <td class="border border-gray-300 p-3 text-green-700">{{ number_format($row['income']) }}</td>
                                <td class="border border-gray-300 p-3 text-red-700">{{ number_format($row['expense']) }}</td>
                                <td class="border border-gray-300 p-3 font-semibold {{ $row['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($row['net']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold mb-4">آخرین تراکنش‌ها</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 p-3">تاریخ</th>
                            <th class="border border-gray-300 p-3">نوع</th>
                            <th class="border border-gray-300 p-3">منبع</th>
                            <th class="border border-gray-300 p-3">دسته‌بندی</th>
                            <th class="border border-gray-300 p-3">مبلغ</th>
                            <th class="border border-gray-300 p-3">سند حسابداری</th>
                            <th class="border border-gray-300 p-3">شرح</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $transaction)
                            <tr>
                                <td class="border border-gray-300 p-3">{{ verta($transaction->transaction_date)->format('Y/m/d') }}</td>
                                <td class="border border-gray-300 p-3">
                                    <span class="{{ $transaction->type_color }} font-semibold">{{ $transaction->type_label }}</span>
                                </td>
                                <td class="border border-gray-300 p-3">{{ $transaction->source_label }}</td>
                                <td class="border border-gray-300 p-3">{{ $transaction->category }}</td>
                                <td class="border border-gray-300 p-3 font-semibold {{ $transaction->type_color }}">{{ $transaction->signed_amount }}</td>
                                <td class="border border-gray-300 p-3">
                                    @if($transaction->accountingDocument)
                                        <a href="{{ route('accounting-documents.show', $transaction->accountingDocument) }}" class="text-blue-600 hover:underline">{{ $transaction->accountingDocument->number }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="border border-gray-300 p-3">{{ $transaction->description ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
