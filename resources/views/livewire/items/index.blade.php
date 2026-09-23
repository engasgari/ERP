<x-erp.ui.list-page
    title="فهرست کالا/خدمات"
    description="مدیریت کالاها و خدمات، قیمت‌گذاری و وضعیت فعال بودن."
    route="items.index"
    :actions="[
        ['label' => 'ورود اکسل', 'url' => route('items.import.form'), 'class' => 'erp-action-detail'],
        ['label' => 'تعریف کالا/خدمت', 'url' => route('items.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="نام، کد، دسته">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">نوع
                    <select wire:model.live="type">
                        <option value="">همه</option>
                        <option value="product">کالا</option>
                        <option value="service">خدمت</option>
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">واحد
                    <select wire:model.live="measurement_unit_id">
                        <option value="">همه</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">دسته‌بندی
                    <select wire:model.live="category">
                        <option value="">همه</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-1">وضعیت
                    <select wire:model.live="is_active">
                        <option value="">همه</option>
                        <option value="1">فعال</option>
                        <option value="0">غیرفعال</option>
                    </select>
                </label>
                <div class="col-12 col-md-6 col-lg-2 d-grid">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-erp.ui.data-table
        :headers="['کد', 'نام', 'نوع', 'دسته‌بندی', 'واحد', 'موجودی', 'قیمت فروش', 'قیمت خرید', 'وضعیت', 'عملیات']"
        empty-message="موردی پیدا نشد."
        :colspan="10"
    >
        @foreach($items as $item)
            <tr wire:key="item-{{ $item->id }}" data-item-row="{{ $item->id }}">
                <td class="text-nowrap">{{ $item->code }}</td>
                <td><span class="font-bold text-blue-700">{{ $item->name }}</span></td>
                <td>{{ $item->type === 'service' ? 'خدمت' : 'کالا' }}</td>
                <td>{{ $item->category ?: '-' }}</td>
                <td>{{ $item->unit?->name ?: '-' }}</td>
                <td class="text-nowrap">{{ $item->type === 'product' ? formatQuantity((float) ($stockByItem[$item->id] ?? 0)) : '-' }}</td>
                <td class="text-nowrap">{{ $item->sale_price !== null ? formatMoney((float) $item->sale_price) : '-' }}</td>
                <td class="text-nowrap">{{ $item->purchase_price !== null ? formatMoney((float) $item->purchase_price) : '-' }}</td>
                <td>
                    <x-erp.ui.status-badge
                        :label="$item->is_active ? 'فعال' : 'غیرفعال'"
                        :tone="$item->is_active ? 'success' : 'neutral'"
                    />
                </td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="view" label="جزئیات" wire:click="show({{ $item->id }})" />
                        <x-erp.ui.row-action icon="edit" label="ویرایش" data-item-edit="{{ $item->id }}" />
                        <x-erp.ui.row-action
                            icon="delete"
                            label="حذف"
                            tone="danger"
                            wire:click="delete({{ $item->id }})"
                            wire:confirm="آیا از حذف این کالا/خدمت مطمئن هستید؟"
                            wire:loading.attr="disabled"
                            wire:target="delete({{ $item->id }})"
                        />
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $items->links() }}</div>

    @if($showingItem)
        <x-erp.ui.details-modal :title="$showingItem->name" :actions="[
            ['label' => 'ویرایش', 'type' => 'button', 'class' => 'erp-action-edit', 'attrs' => ['data-item-edit' => $showingItem->id, 'wire:click' => 'closeModal']],
        ]">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>
            <div class="erp-modal-grid">
                <div class="erp-modal-field">کد<div class="erp-modal-value">{{ $showingItem->code }}</div></div>
                <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $showingItem->type === 'service' ? 'خدمت' : 'کالا' }}</div></div>
                <div class="erp-modal-field">واحد<div class="erp-modal-value">{{ $showingItem->unit?->name ?: '-' }}</div></div>
                <div class="erp-modal-field">دسته‌بندی<div class="erp-modal-value">{{ $showingItem->category ?: '-' }}</div></div>
                <div class="erp-modal-field">موجودی<div class="erp-modal-value">{{ $showingItem->type === 'product' ? formatQuantity((float) ($stockByItem[$showingItem->id] ?? 0)) : '-' }}</div></div>
                <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $showingItem->is_active ? 'فعال' : 'غیرفعال' }}</div></div>
                <div class="erp-modal-field">قیمت فروش<div class="erp-modal-value">{{ $showingItem->sale_price !== null ? formatMoney((float) $showingItem->sale_price) : '-' }}</div></div>
                <div class="erp-modal-field">قیمت خرید<div class="erp-modal-value">{{ $showingItem->purchase_price !== null ? formatMoney((float) $showingItem->purchase_price) : '-' }}</div></div>
                <div class="erp-modal-field md:col-span-2">توضیحات<div class="erp-modal-value">{{ $showingItem->description ?: '-' }}</div></div>
            </div>
        </x-erp.ui.details-modal>
    @endif

    <div id="item-edit-modal" class="erp-modal item-edit-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-item-edit-close></div>
        <div class="erp-ui-modal-panel item-edit-modal-panel">
            <div class="erp-modal-header">
                <h3 id="item-edit-modal-title">ویرایش کالا/خدمت</h3>
                <button type="button" class="erp-modal-close" data-item-edit-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body item-edit-modal-body">
                <iframe id="item-edit-iframe" title="ویرایش کالا/خدمت" class="item-edit-iframe"></iframe>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const editModal = document.getElementById('item-edit-modal');
        const editIframe = document.getElementById('item-edit-iframe');
        const editTitle = document.getElementById('item-edit-modal-title');
        const itemUrlBase = @json(url('items')) + '/';

        function openItemEdit(itemId) {
            if (!editModal || !editIframe) {
                return;
            }

            editIframe.src = `${itemUrlBase}${itemId}/edit?embedded=1`;
            if (editTitle) {
                editTitle.textContent = 'ویرایش کالا/خدمت';
            }
            editModal.hidden = false;
            document.body.classList.add('erp-modal-open');
        }

        function closeItemEdit() {
            if (!editModal || !editIframe) {
                return;
            }

            editModal.hidden = true;
            editIframe.src = 'about:blank';
            document.body.classList.remove('erp-modal-open');
        }

        function showItemListFlash(message, tone = 'success') {
            if (typeof window.showErpToast === 'function') {
                window.showErpToast(message, tone);
            }
        }

        function updateItemListRow(item) {
            const row = document.querySelector(`[data-item-row="${item.id}"]`);
            if (!row) {
                return;
            }

            const cells = row.querySelectorAll('td');
            if (cells[1]) {
                cells[1].innerHTML = `<span class="font-bold text-blue-700">${item.name}</span>`;
            }
            if (cells[2]) {
                cells[2].textContent = item.type_label;
            }
            if (cells[3]) {
                cells[3].textContent = item.category;
            }
            if (cells[4]) {
                cells[4].textContent = item.unit_name;
            }
            if (cells[5]) {
                cells[5].textContent = item.stock_formatted;
            }
            if (cells[6]) {
                cells[6].textContent = item.sale_price_formatted;
            }
            if (cells[7]) {
                cells[7].textContent = item.purchase_price_formatted;
            }

            const badge = cells[8]?.querySelector('span');
            if (badge) {
                badge.textContent = item.status_label;
                badge.className = item.is_active
                    ? 'inline-flex rounded-full border px-2 py-1 text-xs font-bold bg-emerald-50 text-emerald-700 border-emerald-200'
                    : 'inline-flex rounded-full border px-2 py-1 text-xs font-bold bg-slate-50 text-slate-600 border-slate-200';
            }
        }

        document.addEventListener('click', (event) => {
            const editTrigger = event.target.closest('[data-item-edit]');
            if (editTrigger) {
                event.preventDefault();
                openItemEdit(editTrigger.dataset.itemEdit);
            }

            if (event.target.closest('[data-item-edit-close]')) {
                closeItemEdit();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const type = event.data?.type;

            if (type === 'item-edit-cancel') {
                closeItemEdit();
                return;
            }

            if (type === 'item-saved') {
                closeItemEdit();
                if (event.data.item) {
                    updateItemListRow(event.data.item);
                }
                if (event.data.message) {
                    showItemListFlash(event.data.message);
                }
            }
        });
    })();
    </script>
</x-erp.ui.list-page>
