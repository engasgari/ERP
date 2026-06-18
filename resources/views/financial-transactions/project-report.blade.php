<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('گزارش مالی ') }}
        </h2>
    </x-slot>
    <div class="py-12">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold">گزارش مالی پروژه</h2>
                <h3 class="text-xl text-gray-700 mt-2">{{ $project->name }}</h3>
                @if($project->description)
                    <p class="text-gray-600 mt-1">{{ $project->description }}</p>
                @endif
            </div>
            <div class="flex gap-4">
                <a href="{{ route('financial-transactions.create') }}"
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition duration-200">
                    تراکنش جدید
                </a>
                <a href="{{ route('projects.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
                    بازگشت به پروژه‌ها
                </a>
            </div>
        </div>

        <!-- خلاصه مالی پروژه -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="text-green-600 text-sm">کل درآمدها</div>
                <div class="text-2xl font-bold text-green-700">{{ number_format($totalIncome) }} ریال</div>
                <div class="text-xs text-green-600 mt-1">
                    {{ $project->financialTransactions()->where('type', 'income')->count() }} تراکنش
                </div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="text-red-600 text-sm">کل هزینه‌ها</div>
                <div class="text-2xl font-bold text-red-700">{{ number_format($totalExpense) }} ریال</div>
                <div class="text-xs text-red-600 mt-1">
                    {{ $project->financialTransactions()->where('type', 'expense')->count() }} تراکنش
                </div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="text-blue-600 text-sm">هزینه حقوق</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($totalLaborCost) }} ریال</div>
                <div class="text-xs text-blue-600 mt-1">
                    {{ $project->workLogs()->count() }} کارکرد
                </div>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="text-orange-600 text-sm">هزینه قطعات</div>
                <div class="text-2xl font-bold text-orange-600">
                    {{ number_format($WareHouseOutTotalAmount) }} ریال
                </div>
                <div class="text-xs text-orange-600 mt-1">
                    قبل از کسر حقوق
                </div>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div class="text-purple-600 text-sm">سود خالص</div>
                <div class="text-2xl font-bold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    {{ number_format($netProfit) }} ریال
                </div>
                <div class="text-xs {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                    {{ number_format($profitPercentage, 1) }}%
                </div>
            </div>
        </div>

        <!-- نمودار دایره‌ای ساده -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- توزیع درآمد بر اساس دسته‌بندی -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold mb-4">توزیع درآمد بر اساس دسته‌بندی</h3>
                @if($incomeByCategory->count() > 0)
                    <div class="space-y-3">
                        @foreach($incomeByCategory as $category)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">{{ $category->category }}</span>
                                    <span class="font-semibold">{{ number_format($category->total) }} ریال</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    @php
                                        $percentage = $totalIncome > 0 ? ($category->total / $totalIncome) * 100 : 0;
                                    @endphp
                                    <div class="bg-green-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 text-left mt-1">{{ number_format($percentage, 1) }}%</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">هیچ درآمدی ثبت نشده است</p>
                @endif
            </div>

            <!-- توزیع هزینه بر اساس دسته‌بندی -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold mb-4">توزیع هزینه بر اساس دسته‌بندی</h3>
                @if($expenseByCategory->count() > 0)
                    <div class="space-y-3">
                        @foreach($expenseByCategory as $category)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">{{ $category->category }}</span>
                                    <span class="font-semibold">{{ number_format($category->total) }} ریال</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    @php
                                        $percentage = $totalExpense > 0 ? ($category->total / $totalExpense) * 100 : 0;
                                    @endphp
                                    <div class="bg-red-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 text-left mt-1">{{ number_format($percentage, 1) }}%</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">هیچ هزینه‌ای ثبت نشده است</p>
                @endif
            </div>
        </div>

        <!-- خلاصه هزینه حقوق پرسنل -->
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold mb-4">هزینه حقوق پرسنل در این پروژه</h3>
            @if($project->workLogs->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 p-3">پرسنل</th>
                            <th class="border border-gray-300 p-3">کل ساعت کار</th>
                            <th class="border border-gray-300 p-3">نرخ ساعتی متوسط (ریال)</th>
                            <th class="border border-gray-300 p-3">کل حقوق (ریال)</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($project->active_employees as $employee)
                            @php
                                $employeeWorkLogs = $project->workLogs->where('employee_id', $employee->id);
                                $totalHours = $employeeWorkLogs->sum('hours');
                                $totalAmount = $employeeWorkLogs->sum('total_amount');
                                $avgHourlyRate = $totalHours > 0 ? $totalAmount / $totalHours : 0;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 p-3">
                                    <a href="{{ route('work-logs.employee-report', $employee->id) }}"
                                       class="text-blue-600 hover:underline">
                                        {{ $employee->full_name }}
                                    </a>
                                </td>
                                <td class="border border-gray-300 p-3">{{ number_format($totalHours, 1) }} ساعت</td>
                                <td class="border border-gray-300 p-3">{{ number_format($avgHourlyRate) }} ریال</td>
                                <td class="border border-gray-300 p-3 font-semibold text-green-600">
                                    {{ number_format($totalAmount) }} ریال
                                </td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-semibold">
                            <td class="border border-gray-300 p-3">جمع کل</td>
                            <td class="border border-gray-300 p-3">{{ number_format($project->total_work_hours, 1) }} ساعت</td>
                            <td class="border border-gray-300 p-3">-</td>
                            <td class="border border-gray-300 p-3 text-green-600">
                                {{ number_format($totalLaborCost) }} ریال
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">هیچ کارکردی برای این پروژه ثبت نشده است</p>
            @endif
        </div>

        <!-- لیست تراکنش‌ها -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="font-semibold mb-4">لیست تراکنش‌های مالی</h3>

            @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 p-3">تاریخ</th>
                            <th class="border border-gray-300 p-3">نوع</th>
                            <th class="border border-gray-300 p-3">دسته‌بندی</th>
                            <th class="border border-gray-300 p-3">مبلغ (ریال)</th>
                            <th class="border border-gray-300 p-3">شرح</th>
                            <th class="border border-gray-300 p-3">شماره مرجع</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 p-3">
                                    {{ verta($transaction->transaction_date)->format('Y/m/d') }}
                                </td>
                                <td class="border border-gray-300 p-3">
                                <span class="{{ $transaction->type_color }} font-semibold">
                                    {{ $transaction->type_icon }} {{ $transaction->type_label }}
                                </span>
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->category }}
                                </td>
                                <td class="border border-gray-300 p-3 font-semibold {{ $transaction->type_color }}">
                                    {{ $transaction->signed_amount }}
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->description ?: '-' }}
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->reference_number ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">هیچ تراکنش مالی برای این پروژه ثبت نشده است</p>
            @endif
        </div>

        <!-- لیست تراکنش‌ها انبار-->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="font-semibold mb-4">لیست تراکنش‌های انبار</h3>

            @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 p-3">تاریخ</th>
                            <th class="border border-gray-300 p-3">نوع</th>
                            <th class="border border-gray-300 p-3">نام کالا</th>
                            <th class="border border-gray-300 p-3">تعداد</th>
                            <th class="border border-gray-300 p-3">فی (ریال)</th>
                            <th class="border border-gray-300 p-3">جمع</th>
                            <th class="border border-gray-300 p-3">شرح</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactionsWarehouse as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 p-3">
                                    {{ verta($transaction->transaction_date)->format('Y/m/d') }}
                                </td>
                                <td class="border border-gray-300 p-3">
                                <span class="{{ $transaction->type_color }} font-semibold">
                                    {{ $transaction->type_icon }} {{ $transaction->type_label }}
                                </span>
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->item_name }}
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->quantity }}
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->unit_price }} ریال
                                </td>
                                <td class="border border-gray-300 p-3 font-semibold {{ $transaction->type_color }}">
                                    {{ $transaction->total_amount }} ریال
                                </td>
                                <td class="border border-gray-300 p-3">
                                    {{ $transaction->description ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">هیچ تراکنش مالی برای این پروژه ثبت نشده است</p>
            @endif
        </div>



        <!-- خلاصه نهایی -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mt-6">
            <h3 class="font-semibold text-lg mb-4">خلاصه نهایی مالی پروژه</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">کل درآمدها:</span>
                        <span class="font-semibold text-green-600">{{ number_format($totalIncome) }} ریال</span>
                    </div>
                    <div class="flex justify-between  border-t border-gray-300 pt-2 mt-2">
                        <span class="text-gray-600">کل هزینه‌ها (بدون حقوق):</span>
                        <span class="font-semibold text-red-600">{{ number_format($totalExpense) }} ریال</span>
                    </div>

                    <div class="flex justify-between  border-t border-gray-300 pt-2 mt-2">
                        <span class="text-gray-600">هزینه حقوق پرسنل:</span>
                        <span class="font-semibold text-blue-600">{{ number_format($totalLaborCost) }} ریال</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-300 pt-2 mt-2">
                        <span class="text-gray-600">هزینه خرید قطعات:</span>
                        <span class="font-semibold text-blue-600">{{ number_format($WareHouseOutTotalAmount) }} ریال</span>
                    </div>

                    <div class="border-t border-gray-300 pt-2 mt-2">
                        <div class="flex justify-between text-lg font-bold">
                            <span>سود خالص:</span>
                            <span class="{{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($netProfit) }} ریال
                            <span class="text-sm font-normal">({{ number_format($profitPercentage, 1) }}%)</span>
                        </span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">مدت زمان پروژه:</span>
                        <span class="font-semibold">{{ $project->duration }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">وضعیت پروژه:</span>
                        <span class="font-semibold {{ $project->date_status_color }} px-2 py-1 rounded">
                        {{ $project->date_status }}
                    </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تعداد پرسنل فعال:</span>
                        <span class="font-semibold">{{ $project->active_employees->count() }} نفر</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">کل ساعت کار:</span>
                        <span class="font-semibold">{{ number_format($project->total_work_hours, 1) }} ساعت</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>
