<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">جزئیات سفارش تولید</h2></x-slot>

    <div class="py-10">
        <div class="space-y-5 rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-900">{{ $order->number }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $order->project?->name }} - {{ $order->item?->name }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('projects.costing', $order->project) }}" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white">بهای تمام‌شده پروژه</a>
                    <a href="{{ route('production-orders.edit', $order) }}" class="rounded-md bg-amber-500 px-4 py-2 text-sm font-bold text-white">ویرایش</a>
                    <a href="{{ route('production-orders.index') }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">بازگشت</a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><div class="text-sm font-bold text-slate-600">وضعیت</div><div class="mt-1 text-xl font-black">{{ $order->status_label }}</div></div>
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4"><div class="text-sm font-bold text-blue-700">تعداد تولید</div><div class="mt-1 text-xl font-black">{{ number_format((float) $order->quantity, 3) }}</div></div>
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4"><div class="text-sm font-bold text-emerald-700">مواد واقعی</div><div class="mt-1 text-xl font-black">{{ number_format($summary['actual_material_cost']) }}</div></div>
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4"><div class="text-sm font-bold text-amber-700">مغایرت مواد</div><div class="mt-1 text-xl font-black">{{ number_format($summary['material_variance']) }}</div></div>
            </div>

            <form method="POST" action="{{ route('production-orders.consume', $order) }}" class="space-y-4">
                @csrf
                @if($errors->any())
                    <div class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">ماده / قطعه</th>
                            <th class="border border-gray-300 p-2">مقدار برنامه‌ای</th>
                            <th class="border border-gray-300 p-2">مقدار واقعی</th>
                            <th class="border border-gray-300 p-2">قیمت واحد (ریال)</th>
                            <th class="border border-gray-300 p-2">انبار</th>
                            <th class="border border-gray-300 p-2">وضعیت مصرف</th>
                            <th class="border border-gray-300 p-2">حواله مصرف</th>
                            <th class="border border-gray-300 p-2">مغایرت مقدار</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($order->materialConsumptions as $i => $line)
                            <tr>
                                <td class="border border-gray-300 p-2 font-bold">
                                    {{ $line->item?->name }}
                                    <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line->id }}">
                                </td>
                                <td class="border border-gray-300 p-2">{{ number_format((float) $line->planned_quantity, 3) }}</td>
                                <td class="border border-gray-300 p-2"><input name="lines[{{ $i }}][actual_quantity]" type="number" step="0.001" min="0" dir="ltr" value="{{ $line->actual_quantity }}" class="w-full rounded-md border-slate-300"></td>
                                <td class="border border-gray-300 p-2"><input name="lines[{{ $i }}][unit_cost]" type="number" step="0.01" min="0" dir="ltr" value="{{ $line->unit_cost }}" class="w-full rounded-md border-slate-300"></td>
                                <td class="border border-gray-300 p-2">
                                    <select name="lines[{{ $i }}][warehouse_id]" class="w-full rounded-md border-slate-300">
                                        <option value="">انتخاب انبار</option>
                                        @foreach($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" @selected($line->warehouse_id == $warehouse->id)>{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="border border-gray-300 p-2">
                                    <select name="lines[{{ $i }}][status]" class="w-full rounded-md border-slate-300">
                                        <option value="planned" @selected($line->status === 'planned')>برنامه‌ای</option>
                                        <option value="reserved" @selected($line->status === 'reserved')>رزرو شده</option>
                                        <option value="consumed" @selected($line->status === 'consumed')>مصرف شده</option>
                                    </select>
                                </td>
                                <td class="border border-gray-300 p-2">
                                    @if($line->inventoryDocument)
                                        <a class="erp-action-btn erp-action-detail" href="{{ route('inventory-documents.index', ['search' => $line->inventoryDocument->number]) }}">
                                            {{ $line->inventoryDocument->number }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="border border-gray-300 p-2">{{ number_format((float) $line->variance_quantity, 3) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="border border-gray-300 p-5 text-center text-slate-500">برای این سفارش ردیف مصرف مواد ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($order->materialConsumptions->isNotEmpty())
                    <div class="flex justify-end">
                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white">ثبت مصرف مواد</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
