<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('پیش‌نمایش محاسبات حقوق') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-bold mb-6 text-center">
                        پیش‌نمایش محاسبات حقوق
                        <span class="text-blue-600">{{getPersianMonthName($month)}}{{ $year }}</span>
                    </h2>


                    <form method="POST" action="{{ route('salaries.store') }}">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $month }}">

                        <!-- جمع کل -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <h3 class="font-semibold mb-3 text-green-800">خلاصه محاسبات:</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-green-600">تعداد پرسنل:</span>
                                    <span class="font-semibold">{{ count($calculatedSalaries) }} نفر</span>
                                </div>
                                <div>
                                    <span class="text-green-600">مجموع ساعت کار:</span>
                                    <span class="font-semibold">{{ number_format(collect($calculatedSalaries)->sum('total_hours'), 1) }} ساعت</span>
                                </div>
                                <div>
                                    <span class="text-green-600">مجموع اضافه کاری:</span>
                                    <span class="font-semibold">{{ number_format(collect($calculatedSalaries)->sum('overtime_hours'), 1) }} ساعت</span>
                                </div>
                                <div>
                                    <span class="text-green-600">مجموع حقوق:</span>
                                    <span class="font-semibold">{{ number_format(collect($calculatedSalaries)->sum('net_salary')) }} ریال</span>
                                </div>
                            </div>
                        </div>

                        <!-- جدول محاسبات -->
                        <div class="overflow-x-auto mb-6">
                            <table class="min-w-full bg-white border border-gray-200">
                                <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">پرسنل</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">ساعت کار</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">نرخ ساعتی (ریال)</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">حقوق پایه (ریال)</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">اضافه کاری</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">پاداش</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">کسورات</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">تنخواه</th>
                                    <th class="py-3 px-4 border-b text-right font-medium text-gray-700">حقوق نهایی (ریال)</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($calculatedSalaries as $index => $calc)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <div class="font-semibold">{{ $calc['employee']->full_name }}</div>
                                            <input type="hidden" name="salaries[{{ $index }}][employee_id]" value="{{ $calc['employee']->id }}">
                                            <input type="hidden" name="salaries[{{ $index }}][total_hours]" value="{{ $calc['total_hours'] }}">
                                            <input type="hidden" name="salaries[{{ $index }}][hourly_rate]" value="{{ $calc['hourly_rate'] }}">
                                            <input type="hidden" name="salaries[{{ $index }}][base_salary]" value="{{ $calc['base_salary'] }}">
                                            <input type="hidden" name="salaries[{{ $index }}][overtime_hours]" value="{{ $calc['overtime_hours'] }}">
                                            <input type="hidden" name="salaries[{{ $index }}][overtime_rate]" value="{{ $calc['overtime_rate'] }}">
                                            <input type="hidden" name="salaries[{{ $index }}][overtime_salary]" value="{{ $calc['overtime_salary'] }}">
                                            <input type="hidden" name="salaries[{{ $index }}][net_salary]" value="{{ $calc['net_salary'] }}">
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="text-center">
                                                <div class="font-semibold">{{ number_format($calc['total_hours'], 1) }}</div>
                                                @if($calc['overtime_hours'] > 0)
                                                    <div class="text-xs text-orange-600">+{{ number_format($calc['overtime_hours'], 1) }} اضافه</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            {{ number_format($calc['hourly_rate']) }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            {{ number_format($calc['base_salary']) }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            @if($calc['overtime_salary'] > 0)
                                                {{ number_format($calc['overtime_salary']) }}
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <input type="number"
                                                   name="salaries[{{ $index }}][bonus]"
                                                   value="0"
                                                   min="0"
                                                   class="w-24 border border-gray-300 rounded px-2 py-1 text-center"
                                                   placeholder="0">
                                        </td>
                                        <td class="py-3 px-4">
                                            <input type="number"
                                                   name="salaries[{{ $index }}][deduction]"
                                                   value="0"
                                                   min="0"
                                                   class="w-24 border border-gray-300 rounded px-2 py-1 text-center"
                                                   placeholder="0">
                                        </td>
                                        <td class="py-3 px-4">
                                            <input type="number"
                                                   name="salaries[{{ $index }}][advance_payment]"
                                                   value="0"
                                                   min="0"
                                                   class="w-24 border border-gray-300 rounded px-2 py-1 text-center"
                                                   placeholder="0">
                                        </td>
                                        <td class="py-3 px-4 text-center font-semibold">
                                            {{ number_format($calc['net_salary']) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex gap-4 justify-center">
                            <a href="{{ route('salaries.create') }}"
                               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">
                                بازگشت
                            </a>
                            <button type="submit"
                                    class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded transition duration-200">
                                ذخیره حقوق‌ها
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
