<x-erp.ui.list-page
    title="فاکتورها و پیش‌فاکتورها"
    description="فهرست اسناد بازرگانی فروش و خرید با امکان تایید، تسویه و تبدیل پیش‌فاکتور."
    route="invoices.index"
    :actions="[
        ['label' => 'پیش‌فاکتور فروش', 'url' => route('invoices.create', ['direction' => 'sale', 'document_type' => 'proforma']), 'class' => 'erp-action-edit'],
        ['label' => 'فاکتور فروش', 'url' => route('invoices.create', ['direction' => 'sale']), 'class' => 'erp-action-edit'],
        ['label' => 'فاکتور خرید', 'url' => route('invoices.create', ['direction' => 'purchase']), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="شماره، طرف حساب، کالا، توضیح">
                </label>
                <label class="erp-filter-field">جهت
                    <select wire:model.live="direction">
                        <option value="">همه</option>
                        <option value="sale">فروش</option>
                        <option value="purchase">خرید</option>
                    </select>
                </label>
                <label class="erp-filter-field">نوع سند
                    <select wire:model.live="document_type">
                        <option value="">همه</option>
                        <option value="invoice">فاکتور</option>
                        <option value="proforma">پیش‌فاکتور</option>
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        <option value="draft">موقت</option>
                        <option value="confirmed">تایید شده</option>
                    </select>
                </label>
                <label class="erp-filter-field">از تاریخ
                    <x-erp.ui.jalali-date-input wire:model.live.debounce.500ms="date_from" placeholder="1405/01/01" class="w-full" />
                </label>
                <label class="erp-filter-field">تا تاریخ
                    <x-erp.ui.jalali-date-input wire:model.live.debounce.500ms="date_to" placeholder="1405/12/29" class="w-full" />
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach
    </x-slot>

    <x-erp.ui.data-table
        :headers="['شماره', 'نوع', 'تاریخ', 'طرف حساب', 'کد پروژه', 'مبلغ (ریال)', 'وضعیت', 'عملیات']"
        empty-message="فاکتوری یافت نشد."
        :colspan="8"
    >
        @foreach($items as $invoice)
            @php
                $statusLabel = $invoice->settled_at
                    ? 'تسویه شده'
                    : ($invoice->status === 'draft' ? 'موقت' : ($invoice->status === 'confirmed' ? 'تایید شده' : $invoice->status));
                $statusTone = $invoice->settled_at ? 'success' : ($invoice->status === 'draft' ? 'warning' : 'info');
                $typeLabel = ($invoice->direction === 'sale' ? 'فروش' : 'خرید') . ' / ' . ($invoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور');
            @endphp
            <tr wire:key="invoice-{{ $invoice->id }}" data-invoice-row="{{ $invoice->id }}">
                <td class="text-nowrap font-semibold">
                    <button type="button" class="erp-modal-trigger text-blue-700" wire:click="show({{ $invoice->id }})">{{ $invoice->number }}</button>
                </td>
                <td>{{ $typeLabel }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                <td>{{ $invoice->party?->name ?: 'طرف حساب حذف شده' }}</td>
                <td class="text-nowrap font-semibold text-slate-700" dir="ltr">{{ $invoice->project?->project_number ?: '-' }}</td>
                <td class="text-nowrap">{{ formatMoney((float) $invoice->total_amount) }}</td>
                <td>
                    <x-erp.ui.status-badge :label="$statusLabel" :tone="$statusTone" />
                </td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="view" label="جزئیات" wire:click="show({{ $invoice->id }})" />
                        <x-erp.ui.row-action icon="expand" label="نمایش کامل" data-invoice-show="{{ $invoice->id }}" />
                        <x-erp.ui.row-action icon="edit" label="ویرایش" data-invoice-edit="{{ $invoice->id }}" />
                        @if($invoice->document_type === 'proforma')
                            <form method="POST" action="{{ route('invoices.convert', $invoice) }}">
                                @csrf
                                <x-erp.ui.row-action type="submit" icon="convert" label="تبدیل به فاکتور" />
                            </form>
                        @elseif($invoice->status === 'draft')
                            <form method="POST" action="{{ route('invoices.confirm', $invoice) }}">
                                @csrf
                                <x-erp.ui.row-action type="submit" icon="confirm" label="تایید و سند" tone="success" />
                            </form>
                        @elseif($invoice->status === 'confirmed' && ! $invoice->settled_at)
                            <form method="POST" action="{{ route('invoices.settle', $invoice) }}">
                                @csrf
                                <x-erp.ui.row-action type="submit" icon="settle" label="تسویه" tone="success" />
                            </form>
                        @elseif($invoice->settled_at)
                            <form method="POST" action="{{ route('invoices.unsettle', $invoice) }}">
                                @csrf
                                <x-erp.ui.row-action type="submit" icon="revert" label="خروج از تسویه" />
                            </form>
                        @endif
                        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('فاکتور و همه سندهای مالی و انبار وابسته حذف شوند؟')">
                            @csrf
                            @method('DELETE')
                            <x-erp.ui.row-action type="submit" icon="delete" label="حذف" tone="danger" />
                        </form>
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $items->links() }}</div>

    @if($showingInvoice)
        @php
            $modalStatusLabel = $showingInvoice->settled_at
                ? 'تسویه شده'
                : ($showingInvoice->status === 'draft' ? 'موقت' : ($showingInvoice->status === 'confirmed' ? 'تایید شده' : $showingInvoice->status));
        @endphp
        <x-erp.ui.details-modal :title="'فاکتور ' . $showingInvoice->number" :actions="[
            ['label' => 'نمایش کامل', 'type' => 'button', 'class' => 'erp-action-edit', 'attrs' => ['data-invoice-show' => $showingInvoice->id, 'wire:click' => 'closeModal']],
            ['label' => 'ویرایش', 'type' => 'button', 'class' => 'erp-action-edit', 'attrs' => ['data-invoice-edit' => $showingInvoice->id, 'wire:click' => 'closeModal']],
        ]">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <a href="{{ route('invoices.print', $showingInvoice) }}" target="_blank" data-no-spa class="erp-action-btn erp-action-detail">چاپ</a>
                @if($showingInvoice->settled_at)
                    <form method="POST" action="{{ route('invoices.unsettle', $showingInvoice) }}">
                        @csrf
                        <button type="submit" class="erp-action-btn erp-action-detail">خروج از تسویه</button>
                    </form>
                @endif
            </x-slot>
            <div class="erp-modal-grid">
                <div class="erp-modal-field">شماره<div class="erp-modal-value">{{ $showingInvoice->number }}</div></div>
                <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ ($showingInvoice->direction === 'sale' ? 'فروش' : 'خرید') }} / {{ $showingInvoice->document_type === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور' }}</div></div>
                <div class="erp-modal-field">تاریخ<div class="erp-modal-value">{{ gregorianToJalaliDate($showingInvoice->invoice_date) }}</div></div>
                <div class="erp-modal-field">طرف حساب<div class="erp-modal-value">{{ $showingInvoice->party?->name ?: '-' }}</div></div>
                <div class="erp-modal-field">پروژه<div class="erp-modal-value" dir="ltr">{{ $showingInvoice->project?->project_number ?: '-' }}</div></div>
                <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $modalStatusLabel }}</div></div>
                <div class="erp-modal-field">مبلغ نهایی (ریال)<div class="erp-modal-value">{{ formatMoney((float) $showingInvoice->total_amount) }}</div></div>
                <div class="erp-modal-field md:col-span-2">توضیحات<div class="erp-modal-value">{{ $showingInvoice->description ?: '-' }}</div></div>
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="erp-ui-data-table min-w-full">
                    <thead>
                        <tr>
                            <th>کالا/خدمت</th>
                            <th>شرح</th>
                            <th>تعداد</th>
                            <th>قیمت واحد (ریال)</th>
                            <th>مبلغ ردیف (ریال)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($showingInvoice->lines as $line)
                            <tr>
                                <td>{{ $line->item?->name ?: '-' }}</td>
                                <td>{{ $line->description ?: '-' }}</td>
                                <td>{{ formatQuantity((float) $line->quantity) }}</td>
                                <td>{{ formatMoney((float) $line->unit_price) }}</td>
                                <td>{{ formatMoney((float) $line->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-erp.ui.details-modal>
    @endif

    <div id="invoice-show-modal" class="erp-modal invoice-iframe-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-invoice-show-close></div>
        <div class="erp-ui-modal-panel invoice-iframe-modal-panel">
            <div class="erp-modal-header">
                <h3 id="invoice-show-modal-title">نمایش فاکتور</h3>
                <button type="button" class="erp-modal-close" data-invoice-show-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body invoice-iframe-modal-body">
                <iframe id="invoice-show-iframe" title="نمایش فاکتور" class="invoice-iframe"></iframe>
            </div>
        </div>
    </div>

    <div id="invoice-edit-modal" class="erp-modal invoice-iframe-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-invoice-edit-close></div>
        <div class="erp-ui-modal-panel invoice-iframe-modal-panel">
            <div class="erp-modal-header">
                <h3 id="invoice-edit-modal-title">ویرایش فاکتور</h3>
                <button type="button" class="erp-modal-close" data-invoice-edit-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body invoice-iframe-modal-body">
                <iframe id="invoice-edit-iframe" title="ویرایش فاکتور" class="invoice-iframe"></iframe>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const showModal = document.getElementById('invoice-show-modal');
        const showIframe = document.getElementById('invoice-show-iframe');
        const showTitle = document.getElementById('invoice-show-modal-title');
        const editModal = document.getElementById('invoice-edit-modal');
        const editIframe = document.getElementById('invoice-edit-iframe');
        const editTitle = document.getElementById('invoice-edit-modal-title');
        const invoiceUrlBase = @json(url('invoices')) + '/';

        function openInvoiceShow(invoiceId) {
            if (!showModal || !showIframe) {
                return;
            }
            closeInvoiceEdit();
            showIframe.src = `${invoiceUrlBase}${invoiceId}?embedded=1`;
            if (showTitle) {
                showTitle.textContent = 'نمایش فاکتور';
            }
            showModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function closeInvoiceShow() {
            if (!showModal || !showIframe) {
                return;
            }
            showModal.hidden = true;
            showIframe.src = 'about:blank';
            if (!editModal || editModal.hidden) {
                document.body.classList.remove('erp-modal-open');
            }
        }

        function openInvoiceEdit(invoiceId) {
            if (!editModal || !editIframe) {
                return;
            }
            closeInvoiceShow();
            editIframe.src = `${invoiceUrlBase}${invoiceId}/edit?embedded=1`;
            if (editTitle) {
                editTitle.textContent = 'ویرایش فاکتور';
            }
            editModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function closeInvoiceEdit() {
            if (!editModal || !editIframe) {
                return;
            }
            editModal.hidden = true;
            editIframe.src = 'about:blank';
            if (!showModal || showModal.hidden) {
                document.body.classList.remove('erp-modal-open');
            }
        }

        function showInvoiceListFlash(message, tone = 'success') {
            if (typeof window.showErpToast === 'function') {
                window.showErpToast(message, tone);
            }
        }

        function updateInvoiceListRow(invoice) {
            const row = document.querySelector(`[data-invoice-row="${invoice.id}"]`);
            if (!row) {
                return;
            }

            const cells = row.querySelectorAll('td');
            const numberButton = cells[0]?.querySelector('button');
            if (numberButton) {
                numberButton.textContent = invoice.number;
            }
            if (cells[1]) {
                cells[1].textContent = invoice.type_label;
            }
            if (cells[2]) {
                cells[2].textContent = invoice.invoice_date;
            }
            if (cells[3]) {
                cells[3].textContent = invoice.party_name;
            }
            if (cells[4]) {
                cells[4].textContent = invoice.project_number;
            }
            if (cells[5]) {
                cells[5].textContent = invoice.total_amount_formatted;
            }

            const badge = cells[6]?.querySelector('span');
            if (badge) {
                let label = 'موقت';
                let classes = 'inline-flex rounded-full border px-2 py-1 text-xs font-bold bg-amber-50 text-amber-700 border-amber-200';
                if (invoice.settled_at) {
                    label = 'تسویه شده';
                    classes = 'inline-flex rounded-full border px-2 py-1 text-xs font-bold bg-emerald-50 text-emerald-700 border-emerald-200';
                } else if (invoice.status === 'confirmed') {
                    label = 'تایید شده';
                    classes = 'inline-flex rounded-full border px-2 py-1 text-xs font-bold bg-blue-50 text-blue-700 border-blue-200';
                }
                badge.className = classes;
                badge.textContent = label;
            }
        }

        function removeInvoiceListRow(invoiceId) {
            document.querySelector(`[data-invoice-row="${invoiceId}"]`)?.remove();
        }

        document.addEventListener('click', (event) => {
            const showTrigger = event.target.closest('[data-invoice-show]');
            if (showTrigger) {
                event.preventDefault();
                openInvoiceShow(showTrigger.dataset.invoiceShow);
            }

            const editTrigger = event.target.closest('[data-invoice-edit]');
            if (editTrigger) {
                event.preventDefault();
                openInvoiceEdit(editTrigger.dataset.invoiceEdit);
            }

            if (event.target.closest('[data-invoice-show-close]')) {
                closeInvoiceShow();
            }

            if (event.target.closest('[data-invoice-edit-close]')) {
                closeInvoiceEdit();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const type = event.data?.type;

            if (type === 'invoice-show-cancel') {
                closeInvoiceShow();
                return;
            }

            if (type === 'invoice-edit-cancel') {
                closeInvoiceEdit();
                return;
            }

            if (type === 'invoice-open-edit') {
                closeInvoiceShow();
                if (event.data.invoiceId) {
                    openInvoiceEdit(event.data.invoiceId);
                }
                return;
            }

            if (type === 'invoice-saved') {
                closeInvoiceEdit();
                if (event.data.invoice) {
                    updateInvoiceListRow(event.data.invoice);
                }
                if (event.data.message) {
                    showInvoiceListFlash(event.data.message);
                }
                return;
            }

            if (type === 'invoice-deleted') {
                closeInvoiceShow();
                closeInvoiceEdit();
                if (event.data.id) {
                    removeInvoiceListRow(event.data.id);
                }
                if (event.data.message) {
                    showInvoiceListFlash(event.data.message);
                }
                return;
            }

            if (type === 'invoice-updated') {
                if (event.data.invoice) {
                    updateInvoiceListRow(event.data.invoice);
                }
                if (showIframe && showModal && !showModal.hidden) {
                    const reloadId = event.data.reloadShowId || event.data.invoice?.id;
                    if (reloadId) {
                        showIframe.src = `${invoiceUrlBase}${reloadId}?embedded=1`;
                    }
                }
                if (event.data.message) {
                    showInvoiceListFlash(event.data.message);
                }
            }
        });
    })();
    </script>
</x-erp.ui.list-page>
