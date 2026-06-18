<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('جزئیات حقوق') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- هدر -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold">جزئیات حقوق</h2>
                        <div class="flex gap-2">
                            <a href="{{ route('salaries.print-slip', $salary->id) }}"
                               target="_blank"
                               class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                🖨️ چاپ فیش
                            </a>
                            <a href="{{ route('salaries.index') }}"
                               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                                بازگشت
                            </a>
                        </div>
                    </div>

                    <!-- نمایش پیام‌ها -->
                    @if (session('success'))
                        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-green-800 font-semibold">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-red-800 font-semibold">{{ session('error') }}</span>
                            </div>
                        </div>
                    @endif

                    <!-- اطلاعات پرسنل و دوره -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h3 class="font-semibold text-blue-800 mb-2">اطلاعات پرسنل</h3>
                                <p class="text-lg font-bold">{{ $salary->employee->full_name }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ $salary->employee->position }} -
                                    نرخ ساعتی: {{ number_format($salary->hourly_rate) }} ریال
                                </p>
                            </div>
                            <div>
                                <h3 class="font-semibold text-blue-800 mb-2">دوره حقوق</h3>
                                <p class="text-lg font-bold">{{ getPersianMonthName($salary->month) }} {{ $salary->year }}</p>
                                <p class="text-sm text-gray-600">وضعیت:
                                    @php
                                        $remaining = $salary->final_salary - $salary->payments->sum('amount');
                                        $isFullyPaid = $remaining <= 0;
                                    @endphp
                                    <span class="font-semibold {{ $isFullyPaid ? 'text-green-600' : 'text-orange-600' }}">
                                        {{ $isFullyPaid ? 'تسویه شده' : 'مانده دارد' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- جزئیات محاسبات -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- درآمدها -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h3 class="font-semibold text-green-800 mb-3">درآمدها</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>حقوق پایه ({{ number_format($salary->total_hours, 1) }} ساعت):</span>
                                    <span class="font-semibold">{{ number_format($salary->base_salary) }} ریال</span>
                                </div>
                                @if($salary->overtime_salary > 0)
                                    <div class="flex justify-between">
                                        <span>اضافه کاری ({{ number_format($salary->overtime_hours, 1) }} ساعت):</span>
                                        <span class="font-semibold">{{ number_format($salary->overtime_salary) }} ریال</span>
                                    </div>
                                @endif
                                @if($salary->bonus > 0)
                                    <div class="flex justify-between">
                                        <span>پاداش:</span>
                                        <span class="font-semibold text-green-600">+{{ number_format($salary->bonus) }} ریال</span>
                                    </div>
                                @endif
                                <div class="border-t border-green-200 pt-2 mt-2">
                                    <div class="flex justify-between font-bold">
                                        <span>جمع درآمدها:</span>
                                        <span>{{ number_format($salary->base_salary + $salary->overtime_salary + $salary->bonus) }} ریال</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- کسورات -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 class="font-semibold text-red-800 mb-3">کسورات</h3>
                            <div class="space-y-2">
                                @if($salary->deduction > 0)
                                    <div class="flex justify-between">
                                        <span>کسورات:</span>
                                        <span class="font-semibold text-red-600">-{{ number_format($salary->deduction) }} ریال</span>
                                    </div>
                                @endif
                                @if($salary->advance_payment > 0)
                                    <div class="flex justify-between">
                                        <span>پیش پرداخت:</span>
                                        <span class="font-semibold text-red-600">-{{ number_format($salary->advance_payment) }} ریال</span>
                                    </div>
                                @endif
                                <div class="border-t border-red-200 pt-2 mt-2">
                                    <div class="flex justify-between font-bold">
                                        <span>جمع کسورات:</span>
                                        <span class="text-red-600">-{{ number_format($salary->deduction + $salary->advance_payment) }} ریال</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- خلاصه نهایی -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="font-semibold text-purple-800">حقوق نهایی (ریال)</h3>
                                <p class="text-sm text-purple-600">پس از اعمال تمام محاسبات</p>
                            </div>
                            <div class="text-2xl font-bold text-purple-700">
                                {{ number_format($salary->final_salary) }} ریال
                            </div>
                        </div>
                    </div>

                    <!-- بخش پرداخت‌ها -->
                    <div id="payment" class="mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold">تاریخچه پرداخت‌ها</h3>
                            @php
                                $remaining = $salary->final_salary - $salary->payments->sum('amount');
                            @endphp
                            @can('salaries.manage')
                            @if($remaining > 0)
                                <button onclick="togglePaymentForm()"
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    ثبت پرداخت جدید
                                </button>
                            @endif
                            @endcan
                        </div>

                        <!-- فرم پرداخت جدید -->
                        @can('salaries.manage')
                        <div id="paymentForm" class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4 hidden">
                            <h4 class="font-semibold mb-3">ثبت پرداخت جدید</h4>
                            <form method="POST" action="{{ route('salaries.payments.store', $salary->id) }}">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ پرداختی (ریال) *</label>
                                        <input type="number" name="amount" required min="1"
                                               max="{{ $remaining }}"
                                               class="w-full border border-gray-300 rounded-md px-3 py-2"
                                               placeholder="حداکثر {{ number_format($remaining) }} ریال">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ پرداخت *</label>
                                        <input type="text" name="payment_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ old('payment_date') ? jalaliDateInputValue(old('payment_date')) : todayJalaliDate() }}" required
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">روش پرداخت *</label>
                                        <select name="payment_method" required
                                                class="w-full border border-gray-300 rounded-md px-3 py-2">
                                            <option value="cash">نقدی</option>
                                            <option value="bank">حساب بانکی</option>
                                            <option value="card">کارت به کارت</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">شماره پیگیری</label>
                                        <input type="text" name="reference_number"
                                               class="w-full border border-gray-300 rounded-md px-3 py-2"
                                               placeholder="اختیاری">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">توضیحات</label>
                                    <textarea name="description" rows="2"
                                              class="w-full border border-gray-300 rounded-md px-3 py-2"
                                              placeholder="توضیحات پرداخت"></textarea>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="togglePaymentForm()"
                                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                                        انصراف
                                    </button>
                                    <button type="submit"
                                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                                        ثبت پرداخت
                                    </button>
                                </div>
                            </form>
                        </div>
                        @endcan

                        <!-- لیست پرداخت‌ها -->
                        @if($salary->payments->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200">
                                    <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-2 px-4 border-b text-right font-medium text-gray-700">تاریخ</th>
                                        <th class="py-2 px-4 border-b text-right font-medium text-gray-700">مبلغ (ریال)</th>
                                        <th class="py-2 px-4 border-b text-right font-medium text-gray-700">روش</th>
                                        <th class="py-2 px-4 border-b text-right font-medium text-gray-700">شماره پیگیری</th>
                                        <th class="py-2 px-4 border-b text-right font-medium text-gray-700">توضیحات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($salary->payments as $payment)
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-2 px-4">{{ verta($payment->payment_date)->format('Y/m/d') }}</td>
                                            <td class="py-2 px-4 font-semibold">{{ number_format($payment->amount) }} ریال</td>
                                            <td class="py-2 px-4">
                                                @if($payment->payment_method == 'cash') نقدی
                                                @elseif($payment->payment_method == 'bank') حساب بانکی
                                                @elseif($payment->payment_method == 'card') کارت به کارت
                                                @endif
                                            </td>
                                            <td class="py-2 px-4">{{ $payment->reference_number ?? '-' }}</td>
                                            <td class="py-2 px-4">{{ $payment->description ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                                <p class="text-yellow-700">هنوز پرداختی برای این حقوق ثبت نشده است.</p>
                            </div>
                        @endif

                        <!-- خلاصه پرداخت‌ها -->
                        @if($salary->payments->count() > 0)
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <span class="text-blue-600">مجموع پرداختی‌ها:</span>
                                        <span class="font-semibold">{{ number_format($salary->payments->sum('amount')) }} ریال</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-600">مانده پرداختی:</span>
                                        <span class="font-semibold {{ $remaining > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            {{ number_format($remaining) }} ریال
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-blue-600">وضعیت:</span>
                                        <span class="font-semibold {{ $remaining <= 0 ? 'text-green-600' : 'text-orange-600' }}">
                                            {{ $remaining <= 0 ? 'تسویه کامل' : 'مانده دارد' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePaymentForm() {
            const form = document.getElementById('paymentForm');
            form.classList.toggle('hidden');
        }

        // اگر در URL هش payment وجود دارد، فرم را نمایش بده
        @can('salaries.manage')
        if (window.location.hash === '#payment') {
            togglePaymentForm();
        }
        @endcan
    </script>
</x-app-layout>
