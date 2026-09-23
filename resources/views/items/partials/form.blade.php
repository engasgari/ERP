@php
    $embedded = $embedded ?? false;
    $isEdit = isset($item) && $item->exists;
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('items.update', $item) : route('items.store') }}"
    class="bg-white rounded-lg shadow-md p-4 sm:p-6 space-y-4 item-editor-form"
>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    @if($embedded)
        <div id="item-embedded-errors" class="invoice-error" hidden></div>
    @endif

    @if($isEdit && $item->code)
        <div class="text-sm text-slate-600">کد: <span class="font-semibold">{{ $item->code }}</span></div>
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
                <select name="initial_warehouse_id" id="initial-warehouse-id" class="w-full">
                    <option value="">انتخاب انبار</option>
                    @foreach($warehouses ?? [] as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('initial_warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    @endunless

    @if($isEdit && ($item->type ?? '') === 'product' && ! empty($initialStockDocument))
        <div class="grid gap-4 md:grid-cols-2 bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="md:col-span-2 text-sm text-amber-900">
                موجودی اولیه این کالا در سند
                <span class="font-bold">{{ $initialStockDocument->number }}</span>
                در انبار
                <span class="font-bold">{{ $initialStockDocument->warehouse?->name ?: '—' }}</span>
                ثبت شده است.
            </div>
            <label>تغییر انبار موجودی اولیه
                <select name="relocate_initial_warehouse_id" class="w-full">
                    <option value="">بدون تغییر</option>
                    @foreach($warehouses ?? [] as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('relocate_initial_warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="text-xs leading-6 text-amber-800 self-end">
                اگر گردش دیگری روی این کالا در انبار فعلی نباشد، انبار سند اصلاح می‌شود؛ در غیر این صورت سند انتقال بین انبار ثبت می‌شود.
            </div>
        </div>
    @endif

    <label class="block">توضیحات
        <textarea name="description" rows="3" class="w-full">{{ old('description', $item->description ?? '') }}</textarea>
    </label>

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="erp-action-btn erp-action-edit">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت' }}</button>
        @if($embedded)
            <button type="button" id="item-embedded-cancel" class="erp-action-btn erp-action-detail">انصراف</button>
        @else
            <a href="{{ route('items.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
            @unless($isEdit)
                <a href="{{ route('items.import.form') }}" class="erp-action-btn erp-action-detail">ورود اکسل</a>
            @endunless
        @endif
    </div>
</form>

@unless($isEdit)
    <script>
        (() => {
            const itemType = document.getElementById('item-type');
            const initialStockBox = document.getElementById('initial-stock-box');
            if (!itemType || !initialStockBox) {
                return;
            }

            function toggleInitialStock() {
                const isProduct = itemType.value === 'product';
                initialStockBox.style.display = isProduct ? 'grid' : 'none';

                const qtyInput = initialStockBox.querySelector('[name="initial_quantity"]');
                const warehouseSelect = document.getElementById('initial-warehouse-id');

                if (warehouseSelect && qtyInput) {
                    warehouseSelect.required = isProduct && Number(qtyInput.value) > 0;
                }
            }

            initialStockBox.querySelector('[name="initial_quantity"]')?.addEventListener('input', toggleInitialStock);

            itemType.addEventListener('change', toggleInitialStock);
            toggleInitialStock();
        })();
    </script>
@endunless

@if($embedded && $isEdit)
    <script>
        (() => {
            const editorForm = document.querySelector('.item-editor-form');
            const embeddedErrors = document.getElementById('item-embedded-errors');
            const saveButton = editorForm?.querySelector('button[type="submit"]');

            document.getElementById('item-embedded-cancel')?.addEventListener('click', () => {
                window.parent.postMessage({ type: 'item-edit-cancel' }, window.location.origin);
            });

            editorForm?.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (embeddedErrors) {
                    embeddedErrors.hidden = true;
                    embeddedErrors.textContent = '';
                }

                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.textContent = 'در حال ذخیره...';
                }

                try {
                    const response = await fetch(editorForm.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(editorForm),
                    });

                    let payload = {};
                    try {
                        payload = await response.json();
                    } catch {
                        payload = {};
                    }

                    if (!response.ok) {
                        const errors = payload.errors
                            ? Object.values(payload.errors).flat()
                            : [payload.message || 'ذخیره کالا/خدمت انجام نشد.'];
                        throw new Error(errors.join(' '));
                    }

                    window.parent.postMessage({
                        type: 'item-saved',
                        message: payload.message,
                        item: payload.item,
                    }, window.location.origin);
                } catch (error) {
                    if (embeddedErrors) {
                        embeddedErrors.hidden = false;
                        embeddedErrors.textContent = error.message || 'ذخیره کالا/خدمت انجام نشد.';
                    }
                } finally {
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.textContent = 'ذخیره تغییرات';
                    }
                }
            });
        })();
    </script>
@endif
