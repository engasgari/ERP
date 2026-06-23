<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">صدور فاکتور</h2>
    </x-slot>

    @php
        $isEdit = $isEdit ?? false;
        $invoice = $invoice ?? null;
        $title = ($direction === 'sale' ? 'فروش' : 'خرید') . ' / ' . ($documentType === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور');
        $partyLabel = $direction === 'purchase' ? 'فروشنده' : 'مشتری';
        $warehouseLabel = $direction === 'purchase' ? 'انبار ورودی' : 'انبار خروجی';
        $itemData = $items->mapWithKeys(fn ($item) => [
            $item->id => [
                'unit' => $item->unit?->name ?? '',
                'price' => (float) ($direction === 'purchase' ? $item->purchase_price : $item->sale_price),
            ],
        ]);
        $existingLines = $invoice
            ? $invoice->lines->map(fn ($line) => [
                'item_id' => $line->item_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount_amount' => $line->discount_amount,
                'tax_rate' => $line->tax_rate,
            ])->all()
            : [];
        $oldLines = collect(old('lines', $existingLines))->filter(fn ($line) => !empty($line['item_id']))->values();
        $initialRows = max(1, $oldLines->count());
        $invoiceDateValue = old('invoice_date')
            ? jalaliDateInputValue(old('invoice_date'))
            : ($invoice ? gregorianToJalaliDate($invoice->invoice_date) : todayJalaliDate());
    @endphp

    <form method="POST" action="{{ $isEdit ? route('invoices.update', $invoice) : route('invoices.store') }}" class="invoice-editor space-y-4">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="direction" value="{{ $direction }}">
        <input type="hidden" name="document_type" value="{{ $documentType }}">

        @if($errors->any())
            <div class="invoice-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="invoice-panel">
            <div class="invoice-panel-title">
                <div>
                    <h2>مشخصات فاکتور</h2>
                    <p>{{ $title }}</p>
                </div>
                <span>ثبت موقت</span>
            </div>

            <div class="invoice-header-grid">
                <label>
                    شماره فاکتور
                    <input name="number" value="{{ old('number', $invoice?->number) }}" placeholder="خالی = شماره اتوماتیک">
                </label>
                <label>
                    تاریخ
                    <input name="invoice_date" type="text" inputmode="numeric" dir="ltr" data-jalali-datepicker required placeholder="1403/03/17" value="{{ $invoiceDateValue }}">
                </label>
                <label class="invoice-party-field">
                    {{ $partyLabel }}
                    <span class="party-select-row">
                        <select name="party_id" id="invoice-party-select" required>
                            <option value="">انتخاب {{ $partyLabel }}</option>
                            @foreach($parties as $party)
                                <option value="{{ $party->id }}" @selected(old('party_id', $invoice?->party_id) == $party->id)>{{ $party->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="party-add-button" id="open-party-modal" title="تعریف {{ $partyLabel }} جدید">+</button>
                    </span>
                </label>
                <label>
                    پروژه
                    <select name="project_id">
                        <option value="">بدون پروژه</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $invoice?->project_id) == $project->id)>{{ $project->code }} - {{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    {{ $warehouseLabel }}
                    <select name="warehouse_id">
                        <option value="">انتخاب {{ $warehouseLabel }}</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $invoice?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    نوع سند
                    <input value="{{ $title }}" disabled>
                </label>
            </div>

            <label class="invoice-description">
                توضیحات
                <textarea name="description" rows="2">{{ old('description', $invoice?->description) }}</textarea>
            </label>
        </section>

        <section class="invoice-panel">
            <div class="invoice-panel-title">
                <div>
                    <h2>ردیف‌های فاکتور</h2>
                    <p>پس از تکمیل هر ردیف، ردیف بعدی اضافه می‌شود.</p>
                </div>
            </div>

            @error('lines')
                <div class="invoice-error">{{ $message }}</div>
            @enderror

            <div class="invoice-table-wrap">
                <table class="invoice-entry-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>کالا/خدمت</th>
                        <th>شرح</th>
                        <th>واحد</th>
                        <th>تعداد</th>
                        <th>قیمت فی (ریال)</th>
                        <th>تخفیف</th>
                        <th>ارزش افزوده %</th>
                        <th>مبلغ ردیف (ریال)</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="invoice-lines-body"></tbody>
                </table>
            </div>

            <div class="invoice-summary">
                <div><span>جمع ناخالص</span><strong id="invoice-subtotal">0</strong></div>
                <div><span>جمع تخفیف</span><strong id="invoice-discount">0</strong></div>
                <div><span>جمع ارزش افزوده</span><strong id="invoice-tax">0</strong></div>
                <div><span>مبلغ نهایی (ریال)</span><strong id="invoice-total">0</strong></div>
            </div>

            <div class="invoice-actions">
                <a href="{{ route('invoices.index') }}">بازگشت</a>
                @unless($isEdit)
                    <button type="submit" formtarget="_blank" formaction="{{ route('invoices.preview') }}">پیش‌نمایش چاپ</button>
                @endunless
                <button type="submit">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت موقت و نمایش چاپ' }}</button>
            </div>
        </section>
    </form>

    <div class="party-modal-backdrop" id="party-modal" hidden>
        <div class="party-modal" role="dialog" aria-modal="true" aria-labelledby="party-modal-title">
            <div class="party-modal-header">
                <h3 id="party-modal-title">تعریف {{ $partyLabel }} جدید</h3>
                <button type="button" id="close-party-modal">×</button>
            </div>
            <form id="quick-party-form" class="party-modal-body">
                @csrf
                @if($partyTypeId)
                    <input type="hidden" name="types[]" value="{{ $partyTypeId }}">
                @endif
                <div class="party-modal-grid">
                    <label>نوع
                        <select name="kind" required>
                            <option value="company">شرکت</option>
                            <option value="person">شخص</option>
                        </select>
                    </label>
                    <label>نام
                        <input name="name" required>
                    </label>
                    <label>شناسه/کد ملی
                        <input name="national_id">
                    </label>
                    <label>کد اقتصادی
                        <input name="economic_code">
                    </label>
                    <label>تلفن
                        <input name="phone">
                    </label>
                    <label>موبایل
                        <input name="mobile">
                    </label>
                </div>
                <label>آدرس
                    <textarea name="address" rows="2"></textarea>
                </label>
                <div class="party-modal-error" id="party-modal-error" hidden></div>
                <div class="party-modal-actions">
                    <button type="button" id="cancel-party-modal">انصراف</button>
                    <button type="submit">ثبت و انتخاب</button>
                </div>
            </form>
        </div>
    </div>

    <template id="invoice-line-template">
        <tr class="invoice-line">
            <td class="line-index"></td>
            <td>
                <select class="line-item">
                    <option value="">انتخاب</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input class="line-description"></td>
            <td><input class="line-unit" readonly tabindex="-1"></td>
            <td><input class="line-quantity" type="text" inputmode="decimal" autocomplete="off" dir="ltr" data-erp-number="0"></td>
            <td><input class="line-price" type="text" inputmode="decimal" autocomplete="off" dir="ltr" data-erp-number="0"></td>
            <td><input class="line-discount" type="text" inputmode="decimal" autocomplete="off" dir="ltr" value="0" data-erp-number="0"></td>
            <td><input class="line-tax-rate" type="text" inputmode="decimal" autocomplete="off" dir="ltr" value="10" data-erp-number="0"></td>
            <td><input class="line-total" readonly tabindex="-1" dir="ltr" data-erp-number="0"></td>
            <td><button type="button" class="line-remove">×</button></td>
        </tr>
    </template>

    <style>
        .invoice-editor { color: #0f172a; }
        .invoice-panel { border: 1px solid #e2e8f0; border-radius: 0.45rem; background: #fff; padding: .7rem; box-shadow: 0 4px 14px rgba(15,23,42,.045); }
        .invoice-panel-title { display: flex; align-items: center; justify-content: space-between; gap: .7rem; margin-bottom: .55rem; border-bottom: 1px solid #e2e8f0; padding-bottom: .45rem; }
        .invoice-panel-title h2 { margin: 0; font-size: .88rem; font-weight: 900; }
        .invoice-panel-title p { margin-top: .1rem; color: #64748b; font-size: .68rem; }
        .invoice-panel-title span { border-radius: .3rem; background: #f1f5f9; padding: .22rem .45rem; font-size: .68rem; font-weight: 800; color: #475569; }
        .invoice-header-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .5rem; }
        .invoice-header-grid label, .invoice-description { display: grid; gap: .22rem; color: #475569; font-size: .7rem; font-weight: 800; }
        .invoice-header-grid input, .invoice-header-grid select, .invoice-description textarea, .invoice-entry-table input, .invoice-entry-table select { width: 100%; border: 1px solid #cbd5e1; border-radius: .32rem; padding: .32rem .42rem; font-size: .74rem; min-height: 2rem; }
        .invoice-description { margin-top: .5rem; }
        .invoice-description textarea { min-height: 2.25rem; }
        .party-select-row { display: grid; grid-template-columns: minmax(0, 1fr) 2rem; gap: .25rem; align-items: end; }
        .party-add-button { display: grid; place-items: center; width: 2rem; height: 2rem; border: 0; border-radius: .32rem; background: #0d9488; color: #fff; font-size: 1.05rem; font-weight: 900; line-height: 1; }
        .invoice-table-wrap { overflow-x: auto; }
        .invoice-entry-table { min-width: 1180px; width: 100%; border-collapse: collapse; font-size: .76rem; }
        .invoice-entry-table th { border: 1px solid #cbd5e1; background: #f8fafc; padding: .45rem; color: #334155; text-align: center; }
        .invoice-entry-table td { border: 1px solid #e2e8f0; padding: .3rem; background: #fff; }
        .invoice-entry-table th:nth-child(1), .invoice-entry-table td:nth-child(1) { width: 2.5rem; text-align: center; }
        .invoice-entry-table th:nth-child(4), .invoice-entry-table td:nth-child(4) { width: 6rem; }
        .invoice-entry-table th:nth-child(5), .invoice-entry-table td:nth-child(5) { width: 7rem; }
        .invoice-entry-table th:nth-child(6), .invoice-entry-table td:nth-child(6), .invoice-entry-table th:nth-child(7), .invoice-entry-table td:nth-child(7), .invoice-entry-table th:nth-child(9), .invoice-entry-table td:nth-child(9) { width: 9rem; }
        .line-unit, .line-total { background: #f8fafc; font-weight: 800; }
        .line-quantity, .line-price, .line-discount, .line-tax-rate {
            color: #475569;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            text-align: left;
        }
        .line-quantity::placeholder, .line-price::placeholder, .line-discount::placeholder, .line-tax-rate::placeholder {
            color: #94a3b8;
        }
        .line-remove { display: grid; place-items: center; width: 1.8rem; height: 1.8rem; border-radius: .3rem; background: #fef2f2; color: #b91c1c; font-weight: 900; }
        .invoice-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; margin-top: 1rem; }
        .invoice-summary div { border: 1px solid #e2e8f0; border-radius: .4rem; background: #f8fafc; padding: .7rem; }
        .invoice-summary span { display: block; color: #64748b; font-size: .72rem; }
        .invoice-summary strong { display: block; margin-top: .25rem; font-size: 1rem; }
        .invoice-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1rem; }
        .invoice-actions a, .invoice-actions button { border: 0; border-radius: .35rem; background: #475569; color: #fff; padding: .55rem .85rem; font-size: .78rem; font-weight: 800; }
        .invoice-actions button[type="submit"] { background: #2563eb; }
        .invoice-error { margin-bottom: .75rem; border-radius: .35rem; background: #fef2f2; padding: .65rem; color: #b91c1c; font-size: .8rem; font-weight: 800; }
        .party-modal-backdrop { position: fixed; inset: 0; z-index: 90; display: grid; place-items: center; background: rgba(15, 23, 42, .42); padding: 1rem; }
        .party-modal-backdrop[hidden] { display: none; }
        .party-modal { width: min(46rem, 100%); max-height: calc(100vh - 2rem); overflow: auto; border-radius: .5rem; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, .22); }
        .party-modal-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #e2e8f0; padding: .75rem 1rem; }
        .party-modal-header h3 { margin: 0; font-size: .95rem; font-weight: 900; }
        .party-modal-header button { display: grid; place-items: center; width: 2rem; height: 2rem; border: 0; border-radius: .35rem; background: #f1f5f9; color: #334155; font-size: 1.2rem; font-weight: 900; }
        .party-modal-body { display: grid; gap: .75rem; padding: 1rem; }
        .party-modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
        .party-modal label { display: grid; gap: .25rem; color: #475569; font-size: .75rem; font-weight: 800; }
        .party-modal input, .party-modal select, .party-modal textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: .35rem; padding: .45rem .55rem; font-size: .8rem; }
        .party-modal-error { border-radius: .35rem; background: #fef2f2; color: #b91c1c; padding: .55rem .65rem; font-size: .78rem; font-weight: 800; }
        .party-modal-actions { display: flex; justify-content: flex-end; gap: .5rem; }
        .party-modal-actions button { border: 0; border-radius: .35rem; background: #64748b; color: #fff; padding: .5rem .8rem; font-size: .78rem; font-weight: 800; }
        .party-modal-actions button[type="submit"] { background: #0d9488; }
        @media (max-width: 1100px) { .invoice-header-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 900px) { .invoice-header-grid, .invoice-summary, .party-modal-grid { grid-template-columns: 1fr; } }
        @media print { .erp-top-nav, header, .invoice-actions { display: none !important; } .invoice-panel { box-shadow: none; } }
    </style>

    <script>
    (() => {
        const invoiceItems = @json($itemData);
        const oldLines = @json($oldLines);
        const initialRows = {{ $initialRows }};
        const linesBody = document.getElementById('invoice-lines-body');
        const template = document.getElementById('invoice-line-template');
        const partyModal = document.getElementById('party-modal');
        const quickPartyForm = document.getElementById('quick-party-form');
        const partySelect = document.getElementById('invoice-party-select');
        const partyModalError = document.getElementById('party-modal-error');

        function openPartyModal() {
            partyModal.hidden = false;
            partyModal.querySelector('input[name="name"]').focus();
        }

        function closePartyModal() {
            partyModal.hidden = true;
            quickPartyForm.reset();
            partyModalError.hidden = true;
            partyModalError.textContent = '';
        }

        document.getElementById('open-party-modal')?.addEventListener('click', openPartyModal);
        document.getElementById('close-party-modal')?.addEventListener('click', closePartyModal);
        document.getElementById('cancel-party-modal')?.addEventListener('click', closePartyModal);
        partyModal?.addEventListener('click', (event) => {
            if (event.target === partyModal) {
                closePartyModal();
            }
        });

        quickPartyForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            partyModalError.hidden = true;
            partyModalError.textContent = '';

            const submitButton = quickPartyForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'در حال ثبت...';

            try {
                const response = await fetch(@json(route('parties.store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(quickPartyForm),
                });

                const payload = await response.json();

                if (!response.ok) {
                    const errors = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'ثبت مشتری انجام نشد.'];
                    throw new Error(errors.join(' '));
                }

                const option = new Option(payload.party.name, payload.party.id, true, true);
                partySelect.add(option);
                partySelect.value = payload.party.id;
                partySelect.dispatchEvent(new Event('change', { bubbles: true }));
                closePartyModal();
            } catch (error) {
                partyModalError.textContent = error.message || 'ثبت مشتری انجام نشد.';
                partyModalError.hidden = false;
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'ثبت و انتخاب';
            }
        });

        function toNumber(value) {
            const digitMap = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
            };
            const normalized = String(value ?? '')
                .replace(/[,\s٬]/g, '')
                .replace(/[٫]/g, '.')
                .replace(/[۰-۹٠-٩]/g, (digit) => digitMap[digit] ?? digit);

            return Number(normalized || 0) || 0;
        }

        function money(value) {
            return Math.round(value).toLocaleString('fa-IR');
        }

        function formatNumericDisplay(value, maxFractionDigits = 2) {
            const number = toNumber(value);

            if (!Number.isFinite(number)) {
                return '';
            }

            return new Intl.NumberFormat('fa-IR', {
                maximumFractionDigits: maxFractionDigits,
            }).format(number);
        }

        function normalizeEditableNumber(value) {
            const digitMap = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
            };

            return String(value ?? '')
                .replace(/[,\s٬]/g, '')
                .replace(/[٫]/g, '.')
                .replace(/[۰-۹٠-٩]/g, (digit) => digitMap[digit] ?? digit);
        }

        function setFormattedValue(input) {
            if (!input || input.readOnly) {
                return;
            }

            input.value = input.value === '' ? '' : formatNumericDisplay(input.value);
        }

        function setRawValue(input) {
            if (!input || input.readOnly) {
                return;
            }

            input.value = normalizeEditableNumber(input.value);
        }

        function selectLineInputValue(input) {
            if (!input || input.readOnly) {
                return;
            }

            window.requestAnimationFrame(() => {
                input.select?.();
                input.setSelectionRange?.(0, String(input.value ?? '').length);
            });
        }

        function renameRows() {
            linesBody.querySelectorAll('.invoice-line').forEach((row, index) => {
                row.dataset.index = index;
                row.querySelector('.line-index').textContent = index + 1;
                row.querySelector('.line-item').name = `lines[${index}][item_id]`;
                row.querySelector('.line-description').name = `lines[${index}][description]`;
                row.querySelector('.line-quantity').name = `lines[${index}][quantity]`;
                row.querySelector('.line-price').name = `lines[${index}][unit_price]`;
                row.querySelector('.line-discount').name = `lines[${index}][discount_amount]`;
                row.querySelector('.line-tax-rate').name = `lines[${index}][tax_rate]`;
                row.querySelector('.line-remove').style.visibility = linesBody.children.length > 1 ? 'visible' : 'hidden';
            });
        }

        function addRow(values = {}) {
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelector('.line-item').value = values.item_id || '';
            row.querySelector('.line-description').value = values.description || '';
            row.querySelector('.line-quantity').value = values.quantity || '';
            row.querySelector('.line-price').value = values.unit_price || '';
            row.querySelector('.line-discount').value = values.discount_amount ?? 0;
            row.querySelector('.line-tax-rate').value = values.tax_rate ?? 10;
            row.querySelectorAll('.line-quantity, .line-price, .line-discount, .line-tax-rate').forEach(setFormattedValue);
            linesBody.appendChild(row);
            renameRows();
            recalculateInvoice();
        }

        function isRowStarted(row) {
            return Boolean(row.querySelector('.line-item').value);
        }

        function maybeAddNextRow() {
            const rows = linesBody.querySelectorAll('.invoice-line');
            const last = rows[rows.length - 1];
            if (last && isRowStarted(last)) {
                addRow();
            }
        }

        function recalculateInvoice() {
            let subtotal = 0;
            let discountTotal = 0;
            let taxTotal = 0;
            let grandTotal = 0;

            linesBody.querySelectorAll('.invoice-line').forEach((row) => {
                const item = row.querySelector('.line-item');
                const unit = row.querySelector('.line-unit');
                const quantity = row.querySelector('.line-quantity');
                const price = row.querySelector('.line-price');
                const discount = row.querySelector('.line-discount');
                const taxRate = row.querySelector('.line-tax-rate');
                const total = row.querySelector('.line-total');
                const itemMeta = invoiceItems[item.value] || {};

                unit.value = itemMeta.unit || '';

                const baseAmount = toNumber(quantity.value) * toNumber(price.value);
                const discountAmount = Math.min(toNumber(discount.value), baseAmount);
                const taxableAmount = Math.max(baseAmount - discountAmount, 0);
                const taxAmount = taxableAmount * (toNumber(taxRate.value) / 100);
                const lineTotal = taxableAmount + taxAmount;

                total.value = item.value ? money(lineTotal) : '';
                subtotal += item.value ? baseAmount : 0;
                discountTotal += item.value ? discountAmount : 0;
                taxTotal += item.value ? taxAmount : 0;
                grandTotal += item.value ? lineTotal : 0;
            });

            document.getElementById('invoice-subtotal').textContent = money(subtotal);
            document.getElementById('invoice-discount').textContent = money(discountTotal);
            document.getElementById('invoice-tax').textContent = money(taxTotal);
            document.getElementById('invoice-total').textContent = money(grandTotal);
        }

        linesBody.addEventListener('input', recalculateInvoice);
        linesBody.addEventListener('focusin', (event) => {
            if (event.target.classList.contains('line-quantity') || event.target.classList.contains('line-price') || event.target.classList.contains('line-discount') || event.target.classList.contains('line-tax-rate')) {
                setRawValue(event.target);
                selectLineInputValue(event.target);
            }
        });
        linesBody.addEventListener('mousedown', (event) => {
            if (event.target.classList.contains('line-quantity') || event.target.classList.contains('line-price') || event.target.classList.contains('line-discount') || event.target.classList.contains('line-tax-rate')) {
                event.preventDefault();
                event.target.focus({ preventScroll: true });
                setRawValue(event.target);
                selectLineInputValue(event.target);
            }
        });
        linesBody.addEventListener('focusout', (event) => {
            if (event.target.classList.contains('line-quantity') || event.target.classList.contains('line-price') || event.target.classList.contains('line-discount') || event.target.classList.contains('line-tax-rate')) {
                setFormattedValue(event.target);
            }
        });
        linesBody.addEventListener('change', (event) => {
            if (event.target.classList.contains('line-item')) {
                const row = event.target.closest('.invoice-line');
                const itemMeta = invoiceItems[event.target.value] || {};
                row.querySelector('.line-price').value = itemMeta.price || '';
                if (!row.querySelector('.line-quantity').value && event.target.value) {
                    row.querySelector('.line-quantity').value = 1;
                }
                row.querySelectorAll('.line-quantity, .line-price, .line-discount, .line-tax-rate').forEach(setFormattedValue);
                maybeAddNextRow();
                recalculateInvoice();
            }
        });
        linesBody.addEventListener('click', (event) => {
            if (event.target.classList.contains('line-remove')) {
                event.target.closest('.invoice-line').remove();
                if (!linesBody.children.length) {
                    addRow();
                }
                renameRows();
                recalculateInvoice();
            }
        });

        if (oldLines.length) {
            oldLines.forEach((line) => addRow(line));
            addRow();
        } else {
            for (let i = 0; i < initialRows; i++) {
                addRow();
            }
        }
    })();
    </script>
</x-app-layout>

