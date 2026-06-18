<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">گزارش‌های انبار</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-bold">موجودی انبار</h2>
                </div>
                <a href="{{ route('management-reports.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded">
                    فهرست گزارش‌ها
                </a>
            </div>

            @include('management-reports.partials.warehouse-report-filters', [
                'action' => route('management-reports.warehouse-inventory'),
                'reportKey' => 'inventory',
                'showOnlyAvailable' => true,
            ])

            @if($isLimited)
                <div class="mb-3 rounded-lg bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                    {{ number_format($totalMatches) }} ردیف پیدا شد؛ فقط 1000 ردیف اول نمایش داده می‌شود. فیلتر را دقیق‌تر کنید.
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">تعداد اقلام</div><div class="font-bold text-gray-700">{{ number_format($summary['items_count']) }}</div></div>
                <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">مانده تعدادی</div><div class="font-bold text-gray-700">{{ number_format($summary['balance_quantity']) }}</div></div>
                <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">ارزش مانده</div><div class="font-bold text-gray-700">{{ number_format($summary['balance_value']) }} تومان</div></div>
            </div>

            <div class="overflow-x-auto">
                <table class="erp-compact-table w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-2">انبار</th>
                        <th class="border border-gray-300 p-2">کالا</th>
                        <th class="border border-gray-300 p-2">دسته</th>
                        <th class="border border-gray-300 p-2">پروژه</th>
                        <th class="border border-gray-300 p-2">ورود</th>
                        <th class="border border-gray-300 p-2">خروج</th>
                        <th class="border border-gray-300 p-2">موجودی</th>
                        <th class="border border-gray-300 p-2">میانگین قیمت</th>
                        <th class="border border-gray-300 p-2">ارزش موجودی</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="border border-gray-300 p-2">{{ $row['warehouse']?->name }}</td>
                            <td class="border border-gray-300 p-2 font-medium">{{ $row['item_name'] }}</td>
                            <td class="border border-gray-300 p-2">{{ $row['category'] }}</td>
                            <td class="border border-gray-300 p-2">{{ $row['project']?->name ?: '-' }}</td>
                            <td class="border border-gray-300 p-2 text-green-700">{{ number_format($row['quantity_in']) }}</td>
                            <td class="border border-gray-300 p-2 text-red-700">{{ number_format($row['quantity_out']) }}</td>
                            <td class="border border-gray-300 p-2 font-bold">{{ number_format($row['balance_quantity']) }}</td>
                            <td class="border border-gray-300 p-2">{{ number_format($row['average_price']) }}</td>
                            <td class="border border-gray-300 p-2 font-bold">{{ number_format($row['balance_value']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="border border-gray-300 p-4 text-center text-gray-500">رکوردی یافت نشد.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
