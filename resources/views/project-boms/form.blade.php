<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">{{ $bom->exists ? 'ویرایش فرمول ساخت' : 'فرمول ساخت جدید' }}</h2></x-slot>

    @php($lines = old('lines', $bom->exists ? $bom->lines->toArray() : array_fill(0, 5, [])))

    <div class="py-10">
        <div class="rounded-lg bg-white p-6 shadow">
            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-3 text-sm font-semibold text-red-700">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ $bom->exists ? route('project-boms.update', $bom) : route('project-boms.store') }}" class="space-y-5">
                @csrf
                @if($bom->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                        محصول نهایی *
                        <select name="item_id" required class="rounded-md border-slate-300">
                            <option value="">انتخاب محصول</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(old('item_id', $bom->item_id) == $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        نسخه *
                        <input name="version_number" required value="{{ old('version_number', $bom->version_number ?: '1') }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        بازنگری
                        <input name="revision" value="{{ old('revision', $bom->revision) }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        وضعیت
                        <select name="status" class="rounded-md border-slate-300">
                            <option value="draft" @selected(old('status', $bom->status) === 'draft')>پیش‌نویس</option>
                            <option value="active" @selected(old('status', $bom->status) === 'active')>فعال</option>
                            <option value="archived" @selected(old('status', $bom->status) === 'archived')>بایگانی‌شده</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        تاریخ اثرگذاری
                        <input name="effective_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('effective_date'), $bom->effective_date) }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-4">
                        توضیحات
                        <input name="notes" value="{{ old('notes', $bom->notes) }}" class="rounded-md border-slate-300">
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse border border-gray-300 text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">ماده / قطعه</th>
                            <th class="border border-gray-300 p-2">مقدار</th>
                            <th class="border border-gray-300 p-2">واحد</th>
                            <th class="border border-gray-300 p-2">درصد ضایعات</th>
                            <th class="border border-gray-300 p-2">توضیح</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($lines as $i => $line)
                            <tr>
                                <td class="border border-gray-300 p-2">
                                    <select name="lines[{{ $i }}][component_item_id]" class="w-full rounded-md border-slate-300">
                                        <option value="">انتخاب</option>
                                        @foreach($components as $component)
                                            <option value="{{ $component->id }}" @selected(($line['component_item_id'] ?? null) == $component->id)>{{ $component->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="border border-gray-300 p-2"><input name="lines[{{ $i }}][quantity]" type="number" step="0.001" min="0" dir="ltr" value="{{ $line['quantity'] ?? '' }}" class="w-full rounded-md border-slate-300"></td>
                                <td class="border border-gray-300 p-2">
                                    <select name="lines[{{ $i }}][measurement_unit_id]" class="w-full rounded-md border-slate-300">
                                        <option value="">پیش‌فرض کالا</option>
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}" @selected(($line['measurement_unit_id'] ?? null) == $unit->id)>{{ $unit->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="border border-gray-300 p-2"><input name="lines[{{ $i }}][waste_percentage]" type="number" step="0.01" min="0" dir="ltr" value="{{ $line['waste_percentage'] ?? 0 }}" class="w-full rounded-md border-slate-300"></td>
                                <td class="border border-gray-300 p-2"><input name="lines[{{ $i }}][notes]" value="{{ $line['notes'] ?? '' }}" class="w-full rounded-md border-slate-300"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('project-boms.index') }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">بازگشت</a>
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white">ذخیره فرمول ساخت</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
