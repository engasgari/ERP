@php($isEdit = isset($item) && $item->exists)

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش کالا/خدمت' : 'تعریف کالا/خدمت' }}</h2>
    </x-slot>

    <form method="POST" action="{{ $isEdit ? route('items.update', $item) : route('items.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <label>نوع
                <select name="type" id="item-type" class="w-full" required>
                    <option value="product" @selected(old('type', $item->type ?? 'product') === 'product')>کالا</option>
                    <option value="service" @selected(old('type', $item->type ?? '') === 'service')>خدمت</option>
                </select>
            </label>
            <label>نام
                <input name="name" value="{{ old('name', $item->name ?? '') }}" required class="w-full">
            </label>
            <label>واحد سنجش
                <select name="measurement_unit_id" class="w-full">
                    <option value="">انتخاب</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('measurement_unit_id', $item->measurement_unit_id ?? null) == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>دسته‌بندی
                <input name="category" value="{{ old('category', $item->category ?? '') }}" class="w-full">
            </label>
            <label>قیمت فروش (ریال)
                <input name="sale_price" value="{{ old('sale_price', $item->sale_price ?? '') }}" type="number" min="0" step="0.01" class="w-full">
            </label>
            <label>قیمت خرید (ریال)
                <input name="purchase_price" value="{{ old('purchase_price', $item->purchase_price ?? '') }}" type="number" min="0" step="0.01" class="w-full">
            </label>
            @if($isEdit)
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))> فعال
                </label>
            @endif
        </div>

        @unless($isEdit)
            <div id="initial-stock-box" class="grid gap-4 md:grid-cols-2 bg-slate-50 border border-slate-200 rounded-lg p-4">
                <label>موجودی اولیه
                    <input name="initial_quantity" value="{{ old('initial_quantity') }}" type="number" min="0" step="0.001" class="w-full">
                </label>
                <label>انبار موجودی اولیه
                    <select name="initial_warehouse_id" class="w-full">
                        <option value="">اولین انبار فعال</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('initial_warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        @endunless

        <label class="block">توضیحات
            <textarea name="description" rows="3" class="w-full">{{ old('description', $item->description ?? '') }}</textarea>
        </label>
        <div class="flex gap-2">
            <button class="erp-action-btn erp-action-edit">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت' }}</button>
            <a href="{{ route('items.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
            @unless($isEdit)
                <a href="{{ route('items.import.form') }}" class="erp-action-btn erp-action-detail">ورود اکسل</a>
            @endunless
        </div>
    </form>

    @unless($isEdit)
        <script>
            const itemType = document.getElementById('item-type');
            const initialStockBox = document.getElementById('initial-stock-box');
            function toggleInitialStock() {
                initialStockBox.style.display = itemType.value === 'product' ? 'grid' : 'none';
            }
            itemType.addEventListener('change', toggleInitialStock);
            toggleInitialStock();
        </script>
    @endunless
</x-app-layout>
