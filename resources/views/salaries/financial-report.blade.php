<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('گزارش مالی پرسنل') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- فیلتر تاریخ -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <form method="GET" class="flex gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">از تاریخ</label>
                                <input type="text" name="start_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ request('start_date') ? jalaliDateInputValue(request('start_date')) : '' }}" class="border border-gray-300 rounded px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">تا تاریخ</label>
                                <input type="text" name="end_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ request('end_date') ? jalaliDateInputValue(request('end_date')) : '' }}" class="border border-gray-300 rounded px-3 py-2">
                            </div>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                فیلتر
                            </button>
                            <a href="{{ route('salaries.financial-report') }}"
                               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                                نمایش همه
                            </a>
                        </form>
                    </div>

                    <!-- جدول گزارش -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 border-b text-right">پرسنل</th>
                                <th class="py-3 px-4 border-b text-right">بدهکاری</th>
                                <th class="py-3 px-4 border-b text-right">بستانکاری</th>
                                <th class="py-3 px-4 border-b text-right">مانده</th>
                                <th class="py-3 px-4 border-b text-right">عملیات</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($report as $item)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold">{{ $item['employee']->full_name }}</div>
                                        <div class="text-sm text-gray-500">{{ $item['employee']->position }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-red-600 font-semibold">
                                        {{ number_format($item['debit']) }} ریال
                                    </td>
                                    <td class="py-3 px-4 text-green-600 font-semibold">
                                        {{ number_format($item['credit']) }} ریال
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($item['balance'] > 0)
                                            <span class="text-green-600 font-semibold">
                                                {{ number_format($item['balance']) }} ریال
                                            </span>
                                        @elseif($item['balance'] < 0)
                                            <span class="text-red-600 font-semibold">
                                                {{ number_format(abs($item['balance'])) }} ریال
                                            </span>
                                        @else
                                            <span class="text-gray-500">صفر</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <a href="{{ route('salaries.employee-statement', $item['employee']->id) }}"
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                            صورتحساب
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
