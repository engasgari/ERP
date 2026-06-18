<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">جزئیات فرمول ساخت</h2></x-slot>

    <div class="py-10">
        <div class="space-y-5 rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-900">{{ $bom->item?->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">نسخه {{ $bom->version_number }} - {{ $bom->status_label }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('project-boms.edit', $bom) }}" class="rounded-md bg-amber-500 px-4 py-2 text-sm font-bold text-white">ویرایش</a>
                    @if((int) $bom->production_orders_count === 0)
                        <form method="POST" action="{{ route('project-boms.destroy', $bom) }}" onsubmit="return confirm('فرمول ساخت حذف شود؟')">
                            @csrf
                            @method('DELETE')
                            <button class="erp-action-btn erp-action-delete">حذف</button>
                        </form>
                    @else
                        <span class="erp-action-btn erp-action-detail">استفاده شده - قابل حذف نیست</span>
                    @endif
                    <a href="{{ route('project-boms.index') }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">بازگشت</a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-300 p-2">ماده / قطعه</th>
                        <th class="border border-gray-300 p-2">مقدار پایه</th>
                        <th class="border border-gray-300 p-2">ضایعات</th>
                        <th class="border border-gray-300 p-2">مقدار خالص</th>
                        <th class="border border-gray-300 p-2">واحد</th>
                        <th class="border border-gray-300 p-2">توضیح</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($bom->lines as $line)
                        <tr>
                            <td class="border border-gray-300 p-2 font-bold">{{ $line->component?->name }}</td>
                            <td class="border border-gray-300 p-2">{{ number_format((float) $line->quantity, 3) }}</td>
                            <td class="border border-gray-300 p-2">{{ number_format((float) $line->waste_percentage, 2) }}%</td>
                            <td class="border border-gray-300 p-2">{{ number_format($line->net_quantity, 3) }}</td>
                            <td class="border border-gray-300 p-2">{{ $line->unit?->name ?: $line->component?->unit?->name ?: '-' }}</td>
                            <td class="border border-gray-300 p-2">{{ $line->notes ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
