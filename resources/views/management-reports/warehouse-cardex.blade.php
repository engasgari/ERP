<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">گزارش‌های انبار</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-bold">کاردکس انبار</h2>
                </div>
                <a href="{{ route('management-reports.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded">
                    فهرست گزارش‌ها
                </a>
            </div>

            @include('management-reports.partials.warehouse-report-filters', [
                'action' => route('management-reports.warehouse-cardex'),
                'reportKey' => 'cardex',
            ])

            @if($isLimited)
                <div class="mb-3 rounded-lg bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                    {{ number_format($totalMatches) }} رکورد پیدا شد؛ فقط 500 رکورد اول نمایش داده می‌شود. فیلتر را دقیق‌تر کنید.
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-green-50 rounded-lg p-3"><div class="text-xs text-gray-500">ورود</div><div class="font-bold text-green-700">{{ number_format($summary['quantity_in']) }}</div></div>
                <div class="bg-red-50 rounded-lg p-3"><div class="text-xs text-gray-500">خروج</div><div class="font-bold text-red-700">{{ number_format($summary['quantity_out']) }}</div></div>
                <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">مانده</div><div class="font-bold text-gray-700">{{ number_format($summary['balance_quantity']) }}</div></div>
                <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">مانده ریالی</div><div class="font-bold text-gray-700">{{ number_format($summary['balance_value']) }}</div></div>
            </div>

            <div class="overflow-x-auto">
                <table class="erp-compact-table w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-2">تاریخ</th>
                        <th class="border border-gray-300 p-2">انبار</th>
                        <th class="border border-gray-300 p-2">کالا</th>
                        <th class="border border-gray-300 p-2">دسته</th>
                        <th class="border border-gray-300 p-2">پروژه</th>
                        <th class="border border-gray-300 p-2">ورود</th>
                        <th class="border border-gray-300 p-2">خروج</th>
                        <th class="border border-gray-300 p-2">مانده</th>
                        <th class="border border-gray-300 p-2">مانده ریالی</th>
                        <th class="border border-gray-300 p-2">مرجع</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php($transaction = $row['transaction'])
                        <tr>
                            <td class="border border-gray-300 p-2 whitespace-nowrap">{{ verta($transaction->transaction_date)->format('Y/m/d') }}</td>
                            <td class="border border-gray-300 p-2">{{ $transaction->warehouse?->name }}</td>
                            <td class="border border-gray-300 p-2 font-medium">{{ $transaction->item_name }}</td>
                            <td class="border border-gray-300 p-2">{{ $transaction->category }}</td>
                            <td class="border border-gray-300 p-2">{{ $transaction->project?->name ?: '-' }}</td>
                            <td class="border border-gray-300 p-2 text-green-700">{{ $row['quantity_in'] ? number_format($row['quantity_in']) : '-' }}</td>
                            <td class="border border-gray-300 p-2 text-red-700">{{ $row['quantity_out'] ? number_format($row['quantity_out']) : '-' }}</td>
                            <td class="border border-gray-300 p-2 font-bold">{{ number_format($row['balance_quantity']) }}</td>
                            <td class="border border-gray-300 p-2 font-bold">{{ number_format($row['balance_value']) }}</td>
                            <td class="border border-gray-300 p-2">{{ $transaction->reference_number ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="border border-gray-300 p-4 text-center text-gray-500">رکوردی یافت نشد.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
