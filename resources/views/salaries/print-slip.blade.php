<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فیش حقوقی - {{ $salary->employee->full_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 14px;
            }
            .print-break {
                page-break-after: always;
            }
        }
        @font-face {
            font-family: 'Vazir';
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.woff2') format('woff2');
        }
        body {
            font-family: 'Vazir', 'Tanha', 'Segoe UI', Tahoma, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
<div class="max-w-4xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
    <!-- هدر قابل چاپ -->
    <div class="no-print flex justify-between items-center p-4 bg-gray-800 text-white">
        <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded">
            🖨️ چاپ فیش
        </button>
        <a href="{{ route('salaries.show', $salary->id) }}" class="bg-gray-500 hover:bg-gray-600 px-4 py-2 rounded">
            بازگشت
        </a>
    </div>

    <!-- محتوای فیش حقوقی -->
    <div class="p-6">
        <!-- هدر فیش -->
        <div class="text-center border-b-2 border-gray-300 pb-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-800">فیش حقوقی</h1>
            <div class="grid grid-cols-3 gap-4 mt-4 text-sm">
                <div class="text-left">
                    <strong>کد فیش:</strong> SL{{ $salary->id }}{{ $salary->year }}{{ $salary->month }}
                </div>
                <div class="text-center">
                    <strong>تاریخ چاپ:</strong> {{ verta()->format('Y/m/d') }}
                </div>
                <div class="text-right">
                    <strong>دوره:</strong> {{ getPersianMonthName($salary->month) }} {{ $salary->year }}
                </div>
            </div>
        </div>

        <!-- اطلاعات پرسنل -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-bold text-blue-800 mb-3">اطلاعات پرسنل</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>نام و نام خانوادگی:</span>
                        <span class="font-semibold">{{ $salary->employee->full_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>کد ملی:</span>
                        <span>{{ $salary->employee->national_code ?? '---' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>سمت:</span>
                        <span>{{ $salary->employee->position }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>نرخ ساعتی (ریال):</span>
                        <span>{{ number_format($salary->hourly_rate) }} ریال</span>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 class="font-bold text-green-800 mb-3">خلاصه حقوق</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>وضعیت:</span>
                        <span class="{{ $salary->status == 'paid' ? 'text-green-600' : ($salary->status == 'partial' ? 'text-yellow-600' : 'text-red-600') }} font-semibold">
                                @if($salary->status == 'draft') پیش‌نویس
                            @elseif($salary->status == 'calculated') محاسبه شده
                            @elseif($salary->status == 'partial') پرداخت جزئی
                            @elseif($salary->status == 'paid') پرداخت شده
                            @endif
                            </span>
                    </div>
                    <div class="flex justify-between">
                        <span>جمع حقوق (ریال):</span>
                        <span class="font-semibold">{{ number_format($salary->final_salary) }} ریال</span>
                    </div>
                    <div class="flex justify-between">
                        <span>پرداختی:</span>
                        <span class="text-green-600">{{ number_format($totalPayments) }} ریال</span>
                    </div>
                    <div class="flex justify-between">
                        <span>مانده:</span>
                        <span class="{{ $remaining > 0 ? 'text-red-600' : 'text-green-600' }} font-semibold">
                                {{ number_format(abs($remaining)) }} ریال
                            </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- جزئیات محاسبات -->
        <div class="mb-6">
            <h3 class="font-bold text-gray-800 mb-3 border-b pb-2">جزئیات محاسبات</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>ساعات کار عادی:</span>
                        <span>{{ number_format($salary->total_hours - $salary->overtime_hours, 1) }} ساعت</span>
                    </div>
                    <div class="flex justify-between">
                        <span>ساعات اضافه کاری:</span>
                        <span class="text-orange-600">{{ number_format($salary->overtime_hours, 1) }} ساعت</span>
                    </div>
                    <div class="flex justify-between">
                        <span>جمع ساعات کار:</span>
                        <span class="font-semibold">{{ number_format($salary->total_hours, 1) }} ساعت</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>حقوق پایه (ریال):</span>
                        <span>{{ number_format($salary->base_salary) }} ریال</span>
                    </div>
                    <div class="flex justify-between">
                        <span>اضافه کاری:</span>
                        <span class="text-orange-600">{{ number_format($salary->overtime_salary) }} ریال</span>
                    </div>
                    <div class="flex justify-between">
                        <span>حقوق خالص (ریال):</span>
                        <span class="font-semibold">{{ number_format($salary->net_salary) }} ریال</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- اضافات و کسورات -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 class="font-bold text-green-800 mb-3">اضافات</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>پاداش:</span>
                        <span class="text-green-600">+ {{ number_format($salary->bonus) }} ریال</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 mt-2">
                        <span class="font-semibold">جمع اضافات:</span>
                        <span class="font-semibold text-green-600">{{ number_format($salary->bonus) }} ریال</span>
                    </div>
                </div>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="font-bold text-red-800 mb-3">کسورات</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>کسورات:</span>
                        <span class="text-red-600">- {{ number_format($salary->deduction) }} ریال</span>
                    </div>
                    <div class="flex justify-between">
                        <span>پیش پرداخت:</span>
                        <span class="text-red-600">- {{ number_format($salary->advance_payment) }} ریال</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 mt-2">
                        <span class="font-semibold">جمع کسورات:</span>
                        <span class="font-semibold text-red-600">{{ number_format($salary->deduction + $salary->advance_payment) }} ریال</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- پرداخت‌ها -->
        @if($salary->payments->count() > 0)
            <div class="mb-6">
                <h3 class="font-bold text-gray-800 mb-3 border-b pb-2">سوابق پرداخت</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-3 border text-right">تاریخ پرداخت</th>
                            <th class="py-2 px-3 border text-right">روش پرداخت</th>
                            <th class="py-2 px-3 border text-right">شماره پیگیری</th>
                            <th class="py-2 px-3 border text-right">مبلغ (ریال)</th>
                            <th class="py-2 px-3 border text-right">توضیحات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($salary->payments as $payment)
                            <tr class="border-b">
                                <td class="py-2 px-3 border">{{ verta($payment->payment_date)->format('Y/m/d') }}</td>
                                <td class="py-2 px-3 border">
                                    @if($payment->payment_method == 'cash') نقدی
                                    @elseif($payment->payment_method == 'bank') حساب بانکی
                                    @elseif($payment->payment_method == 'card') کارت
                                    @endif
                                </td>
                                <td class="py-2 px-3 border">{{ $payment->reference_number ?? '---' }}</td>
                                <td class="py-2 px-3 border text-left">{{ number_format($payment->amount) }}</td>
                                <td class="py-2 px-3 border">{{ $payment->description ?? '---' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="3" class="py-2 px-3 border text-right">جمع پرداختی‌ها:</td>
                            <td colspan="2" class="py-2 px-3 border text-left text-green-600">
                                {{ number_format($totalPayments) }} ریال
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- خلاصه نهایی -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="font-bold text-yellow-800 mb-3">خلاصه نهایی</h3>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center">
                    <div class="text-gray-600">حقوق خالص (ریال)</div>
                    <div class="text-lg font-bold text-blue-600">{{ number_format($salary->net_salary) }}</div>
                </div>
                <div class="text-center">
                    <div class="text-gray-600">اضافات و کسورات</div>
                    <div class="text-lg font-bold {{ ($salary->bonus - $salary->deduction - $salary->advance_payment) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($salary->bonus - $salary->deduction - $salary->advance_payment) }}
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-gray-600">حقوق نهایی (ریال)</div>
                    <div class="text-2xl font-bold text-green-600">{{ number_format($salary->final_salary) }}</div>
                </div>
            </div>
        </div>

        <!-- امضاها -->
        <div class="mt-8 grid grid-cols-2 gap-8 text-center">
            <div>
                <div class="border-t border-gray-300 mt-12 pt-2">
                    امضا پرسنل
                </div>
            </div>
            <div>
                <div class="border-t border-gray-300 mt-12 pt-2">
                    امضا مسئول مالی
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // چاپ خودکار هنگام لود صفحه (اختیاری)
    @if(request()->has('auto-print'))
        window.onload = function() {
        window.print();
    }
    @endif
</script>
</body>
</html>
