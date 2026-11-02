<x-erp.ui.list-page
    title="قیمت‌گذاری کالا و خدمات"
    description="فهرست کالا و خدمات با امکان ویرایش قیمت فروش برای استفاده در فاکتورها."
    route="commerce.item-pricing.index"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="نام، کد، دسته">
                </label>
                <label class="erp-filter-field">نوع
                    <select wire:model.live="type">
                        <option value="">همه</option>
                        <option value="product">کالا</option>
                        <option value="service">خدمت</option>
                    </select>
                </label>
                <label class="erp-filter-field">دسته‌بندی
                    <select wire:model.live="category">
                        <option value="">همه</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="is_active">
                        <option value="">همه</option>
                        <option value="1">فعال</option>
                        <option value="0">غیرفعال</option>
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-erp.ui.data-table
        :headers="['کد', 'نام', 'نوع', 'دسته‌بندی', 'واحد', 'آخرین فروش', 'قیمت فروش', 'عملیات']"
        empty-message="کالا یا خدمتی یافت نشد."
        :colspan="8"
    >
        @foreach($items as $item)
            @php
                $lastSale = $lastSalePrices[$item->id] ?? null;
            @endphp
            <tr wire:key="pricing-item-{{ $item->id }}">
                <td class="text-nowrap font-semibold" dir="ltr">{{ $item->code }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->type === 'service' ? 'خدمت' : 'کالا' }}</td>
                <td>{{ $item->category ?: '-' }}</td>
                <td>{{ $item->unit?->name ?: '-' }}</td>
                <td class="text-nowrap" dir="ltr">{{ $lastSale !== null ? formatMoney((float) $lastSale) : '-' }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            inputmode="decimal"
                            dir="ltr"
                            class="w-36 rounded-md border border-slate-300 px-2 py-1 text-sm"
                            wire:model.defer="salePrices.{{ $item->id }}"
                            @disabled(! $canManage)
                        >
                        <span class="text-xs text-slate-500">ریال</span>
                    </div>
                    @error('salePrices.'.$item->id)
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </td>
                <td>
                    @if($canManage)
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action
                                type="button"
                                icon="confirm"
                                label="ذخیره قیمت"
                                tone="success"
                                wire:click="saveSalePrice({{ $item->id }})"
                                wire:loading.attr="disabled"
                                wire:target="saveSalePrice({{ $item->id }})"
                            />
                        </x-erp.ui.row-actions>
                    @else
                        <span class="text-xs text-slate-400">فقط مشاهده</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $items->links() }}</div>
</x-erp.ui.list-page>
