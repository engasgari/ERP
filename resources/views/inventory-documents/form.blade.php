<x-app-layout>
    @php
        $type = old('type', $document->type ?: request('type', 'receipt'));
        $title = match ($type) {
            'issue' => 'حواله خروج انبار',
            'consumption' => 'حواله مصرف انبار',
            'transfer' => 'انتقال بین انبار',
            default => 'رسید انبار',
        };
        $documentDateValue = old('document_date')
            ? jalaliDateInputValue(old('document_date'))
            : (gregorianToJalaliDate($document->document_date) ?: todayJalaliDate());
        $oldLines = collect(old('lines', $isEdit ? $document->lines->map(fn ($line) => [
            'item_id' => $line->item_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'description' => $line->description,
        ])->all() : []))->filter(fn ($line) => !empty($line['item_id']))->values();
        $itemData = $items->mapWithKeys(fn ($item) => [$item->id => ['unit' => $item->unit?->name ?? '']]);
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش' : 'ثبت' }} {{ $title }}</h2>
    </x-slot>

    <form method="POST" action="{{ $isEdit ? route('inventory-documents.update', $document) : route('inventory-documents.store') }}" class="erp-inventory-document-form bg-white rounded-lg shadow-md p-4 space-y-3">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="inventory-document-header-grid">
            <label>نوع سند
                <select name="type" id="inventory-document-type" class="w-full">
                    <option value="receipt" @selected($type === 'receipt')>رسید انبار</option>
                    <option value="issue" @selected($type === 'issue')>حواله خروج</option>
                    <option value="consumption" @selected($type === 'consumption')>حواله مصرف</option>
                    <option value="transfer" @selected($type === 'transfer')>انتقال بین انبار</option>
                </select>
            </label>
            <label>تاریخ
                <input name="document_date" type="text" inputmode="numeric" dir="ltr" data-jalali-datepicker value="{{ $documentDateValue }}" required class="w-full">
            </label>
            <label>ساعت ثبت
                <input name="document_time" type="time" value="{{ old('document_time', $document->document_time ? \Illuminate\Support\Carbon::parse($document->document_time)->format('H:i') : now()->format('H:i')) }}" class="w-full">
            </label>
            <label>انبار
                <select name="warehouse_id" required class="w-full">
                    <option value="">انتخاب انبار</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $document->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
            <label id="target-warehouse-field" @class(['hidden' => $type !== 'transfer'])>انبار مقصد
                <select name="target_warehouse_id" class="w-full">
                    <option value="">انتخاب انبار مقصد</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('target_warehouse_id', $document->target_warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>پروژه
                <select name="project_id" class="w-full">
                    <option value="">بدون پروژه</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $document->project_id) == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>وضعیت
                <select name="status" class="w-full">
                    <option value="draft" @selected(old('status', $document->status ?: 'draft') === 'draft')>موقت</option>
                    <option value="confirmed" @selected(old('status', $document->status) === 'confirmed')>تایید شده</option>
                </select>
            </label>
        </div>

        <label class="block">توضیحات سند
            <textarea name="description" rows="1" class="w-full">{{ old('description', $document->description) }}</textarea>
        </label>

        <div class="inventory-lines-scroll overflow-x-auto">
            <table class="erp-ui-data-table inventory-lines-table min-w-[860px]">
                <colgroup>
                    <col class="line-col-index">
                    <col class="line-col-item">
                    <col class="line-col-unit">
                    <col class="line-col-quantity">
                    <col class="line-col-price">
                    <col class="line-col-description">
                    <col class="line-col-action">
                </colgroup>
                <thead>
                <tr>
                    <th>#</th>
                    <th>کالا</th>
                    <th>واحد</th>
                    <th>تعداد</th>
                    <th>فی (ریال)</th>
                    <th>توضیح ردیف</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="inventory-lines-body"></tbody>
            </table>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('inventory-documents.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded">بازگشت</a>
            <button class="bg-blue-500 text-white px-4 py-2 rounded">ذخیره سند</button>
        </div>
    </form>

    <template id="inventory-line-template">
        <tr class="inventory-line">
            <td class="line-index"></td>
            <td>
                <select class="line-item">
                    <option value="">انتخاب کالا</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input class="line-unit compact-line-field" readonly tabindex="-1"></td>
            <td><input class="line-quantity compact-line-field" type="number" step="0.001" min="0.001" dir="ltr"></td>
            <td><input class="line-price compact-line-field" type="number" step="0.01" min="0" dir="ltr"></td>
            <td><input class="line-description"></td>
            <td><button type="button" class="line-remove text-red-600 font-bold">×</button></td>
        </tr>
    </template>

    <script>
    (() => {
        const itemData = @json($itemData);
        const oldLines = @json($oldLines);
        const body = document.getElementById('inventory-lines-body');
        const template = document.getElementById('inventory-line-template');
        const typeSelect = document.getElementById('inventory-document-type');
        const targetWarehouseField = document.getElementById('target-warehouse-field');

        function toggleTargetWarehouse() {
            targetWarehouseField?.classList.toggle('hidden', typeSelect?.value !== 'transfer');
        }

        function renameRows() {
            body.querySelectorAll('.inventory-line').forEach((row, index) => {
                row.querySelector('.line-index').textContent = index + 1;
                row.querySelector('.line-item').name = `lines[${index}][item_id]`;
                row.querySelector('.line-quantity').name = `lines[${index}][quantity]`;
                row.querySelector('.line-price').name = `lines[${index}][unit_price]`;
                row.querySelector('.line-description').name = `lines[${index}][description]`;
                row.querySelector('.line-remove').style.visibility = body.children.length > 1 ? 'visible' : 'hidden';
            });
        }

        function addRow(values = {}) {
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelector('.line-item').value = values.item_id || '';
            row.querySelector('.line-quantity').value = values.quantity || '';
            row.querySelector('.line-price').value = values.unit_price || '';
            row.querySelector('.line-description').value = values.description || '';
            body.appendChild(row);
            hydrateUnit(row);
            renameRows();
        }

        function hydrateUnit(row) {
            const itemId = row.querySelector('.line-item').value;
            row.querySelector('.line-unit').value = itemData[itemId]?.unit || '';
        }

        function maybeAddRow() {
            const last = body.querySelector('.inventory-line:last-child');
            if (last && last.querySelector('.line-item').value) {
                addRow();
            }
        }

        body.addEventListener('change', (event) => {
            if (event.target.classList.contains('line-item')) {
                hydrateUnit(event.target.closest('.inventory-line'));
                maybeAddRow();
            }
        });

        body.addEventListener('click', (event) => {
            if (event.target.classList.contains('line-remove')) {
                event.target.closest('.inventory-line').remove();
                if (!body.children.length) {
                    addRow();
                }
                renameRows();
            }
        });

        typeSelect?.addEventListener('change', toggleTargetWarehouse);
        toggleTargetWarehouse();

        if (oldLines.length) {
            oldLines.forEach((line) => addRow(line));
            addRow();
        } else {
            addRow();
        }
    })();
    </script>
</x-app-layout>
