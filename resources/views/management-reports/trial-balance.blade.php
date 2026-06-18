<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">گزارش‌های مدیریتی</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-5">
                <div>
                    <h2 class="text-xl font-bold">تراز آزمایشی مالی</h2>
                    <p class="mt-1 text-sm text-gray-500">کل پروژه‌ها، درآمدها، هزینه‌ها، حقوق و دستمزد و انبار</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('management-reports.index') }}" wire:navigate
                       class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-200">
                        فهرست گزارش‌ها
                    </a>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        {{ todayJalaliDate() }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
                <div class="bg-green-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500">درآمد ثبت شده</div>
                    <div class="mt-1 font-bold text-green-700">{{ number_format($summary['income']) }} تومان</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500">هزینه‌های مالی</div>
                    <div class="mt-1 font-bold text-red-700">{{ number_format($summary['financial_expense']) }} تومان</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500">حقوق و دستمزد</div>
                    <div class="mt-1 font-bold text-gray-700">{{ number_format($summary['salary_expense']) }} تومان</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500">سود/زیان مدیریتی</div>
                    <div class="mt-1 font-bold {{ $summary['net_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ number_format($summary['net_profit']) }} تومان
                    </div>
                </div>
            </div>

            <div class="mb-3 rounded-lg px-4 py-3 text-sm {{ $summary['is_balanced'] ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                وضعیت تراز:
                <strong>{{ $summary['is_balanced'] ? 'متعادل' : 'نامتعادل' }}</strong>
            </div>

            <div class="overflow-x-auto">
                <table class="erp-compact-table w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-2">کد حساب</th>
                        <th class="border border-gray-300 p-2">شرح حساب</th>
                        <th class="border border-gray-300 p-2">بدهکار</th>
                        <th class="border border-gray-300 p-2">بستانکار</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="border border-gray-300 p-2 text-gray-500">{{ $row['code'] }}</td>
                            <td class="border border-gray-300 p-2 font-medium">{{ $row['title'] }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">
                                {{ $row['debit'] ? number_format($row['debit']) : '-' }}
                            </td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">
                                {{ $row['credit'] ? number_format($row['credit']) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="border border-gray-300 p-4 text-center text-gray-500">
                                هنوز داده مالی برای گزارش وجود ندارد.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr class="font-bold">
                        <td class="border border-gray-300 p-2" colspan="2">جمع کل</td>
                        <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ number_format($totals['debit']) }}</td>
                        <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ number_format($totals['credit']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 text-xs leading-6 text-gray-500">
                این تراز آزمایشی از ثبت‌های عملیاتی فعلی ساخته شده است: درآمدها و هزینه‌های مالی، حقوق محاسبه شده، پرداخت‌های حقوق، ورود و خروج انبار.
            </div>
        </div>
    </div>
</x-app-layout>
