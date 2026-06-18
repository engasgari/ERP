<div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
        <h2 class="text-lg font-bold mb-0">فهرست کالا/خدمات</h2>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="{{ route('items.import.form') }}" class="erp-action-btn erp-action-detail text-center">ورود اکسل</a>
            <a href="{{ route('items.create') }}" class="erp-action-btn erp-action-edit text-center">تعریف کالا/خدمت</a>
        </div>
    </div>

    @include('livewire.partials.flash')

    <form wire:submit.prevent class="erp-ui-filter-bar">
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
    </form>

    <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

    <div class="table-responsive overflow-x-auto">
        <table class="erp-ui-data-table">
            <thead>
                <tr>
                    <th>کد</th>
                    <th>نام</th>
                    <th>نوع</th>
                    <th>دسته‌بندی</th>
                    <th>واحد</th>
                    <th>موجودی</th>
                    <th>قیمت فروش</th>
                    <th>قیمت خرید</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr wire:key="item-{{ $item->id }}">
                    <td>{{ $item->code }}</td>
                    <td>
                        <input
                            class="w-full rounded-md border-gray-300 text-sm"
                            value="{{ $item->name }}"
                            wire:change="updateField({{ $item->id }}, 'name', $event.target.value)"
                        >
                    </td>
                    <td>
                        <select
                            class="w-full rounded-md border-gray-300 text-sm"
                            wire:change="updateField({{ $item->id }}, 'type', $event.target.value)"
                        >
                            <option value="product" @selected($item->type === 'product')>کالا</option>
                            <option value="service" @selected($item->type === 'service')>خدمت</option>
                        </select>
                    </td>
                    <td>
                        <input
                            class="w-full rounded-md border-gray-300 text-sm"
                            value="{{ $item->category }}"
                            list="item-categories-inline"
                            wire:change="updateField({{ $item->id }}, 'category', $event.target.value)"
                        >
                    </td>
                    <td>
                        <select
                            class="w-full rounded-md border-gray-300 text-sm"
                            @disabled($item->type === 'service')
                            wire:change="updateField({{ $item->id }}, 'measurement_unit_id', $event.target.value)"
                        >
                            <option value="">بدون واحد</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected($item->measurement_unit_id == $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>{{ $item->type === 'product' ? number_format((float) ($stockByItem[$item->id] ?? 0), 3) : '-' }}</td>
                    <td>
                        <input
                            class="w-28 rounded-md border-gray-300 text-sm"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ $item->sale_price }}"
                            wire:change="updateField({{ $item->id }}, 'sale_price', $event.target.value)"
                        >
                    </td>
                    <td>
                        <input
                            class="w-28 rounded-md border-gray-300 text-sm"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ $item->purchase_price }}"
                            wire:change="updateField({{ $item->id }}, 'purchase_price', $event.target.value)"
                        >
                    </td>
                    <td>
                        <select
                            class="w-full rounded-md border-gray-300 text-sm"
                            wire:change="updateField({{ $item->id }}, 'is_active', $event.target.value)"
                        >
                            <option value="1" @selected($item->is_active)>فعال</option>
                            <option value="0" @selected(! $item->is_active)>غیرفعال</option>
                        </select>
                    </td>
                    <td>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button type="button" wire:click="show({{ $item->id }})" class="erp-action-btn erp-action-detail">جزئیات</button>
                            <button
                                type="button"
                                wire:click="delete({{ $item->id }})"
                                wire:confirm="آیا از حذف این کالا/خدمت مطمئن هستید؟"
                                wire:loading.attr="disabled"
                                wire:target="delete({{ $item->id }})"
                                class="erp-action-btn erp-action-delete"
                            >
                                حذف
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-slate-500 py-6">موردی پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <datalist id="item-categories-inline">
        @foreach($categories as $categoryOption)
            <option value="{{ $categoryOption }}"></option>
        @endforeach
    </datalist>

    <div>{{ $items->links() }}</div>

    @if($showingItem)
        <div class="erp-ui-modal-backdrop">
            <div class="erp-ui-modal-panel">
                <div class="erp-modal-header"><h3>{{ $showingItem->name }}</h3><button type="button" class="erp-modal-close" wire:click="closeModal">×</button></div>
                <div class="erp-modal-body">
                    <div class="erp-modal-grid">
                        <div class="erp-modal-field">کد<div class="erp-modal-value">{{ $showingItem->code }}</div></div>
                        <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $showingItem->type === 'service' ? 'خدمت' : 'کالا' }}</div></div>
                        <div class="erp-modal-field">واحد<div class="erp-modal-value">{{ $showingItem->unit?->name ?: '-' }}</div></div>
                        <div class="erp-modal-field">دسته‌بندی<div class="erp-modal-value">{{ $showingItem->category ?: '-' }}</div></div>
                        <div class="erp-modal-field">موجودی<div class="erp-modal-value">{{ $showingItem->type === 'product' ? number_format((float) ($stockByItem[$showingItem->id] ?? 0), 3) : '-' }}</div></div>
                        <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $showingItem->is_active ? 'فعال' : 'غیرفعال' }}</div></div>
                        <div class="erp-modal-field">قیمت فروش<div class="erp-modal-value">{{ $showingItem->sale_price !== null ? number_format((float) $showingItem->sale_price) : '-' }}</div></div>
                        <div class="erp-modal-field">قیمت خرید<div class="erp-modal-value">{{ $showingItem->purchase_price !== null ? number_format((float) $showingItem->purchase_price) : '-' }}</div></div>
                        <div class="erp-modal-field md:col-span-2">توضیحات<div class="erp-modal-value">{{ $showingItem->description ?: '-' }}</div></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
