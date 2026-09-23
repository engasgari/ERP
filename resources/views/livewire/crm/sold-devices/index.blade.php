<x-erp.ui.list-page
    title="گارانتی"
    description="ثبت سریال دستگاه‌های فروخته‌شده، مشتری، تاریخ فروش و پایان گارانتی."
    route="crm.sold-devices.index"
>
    <div class="flex justify-end mb-2">
        <button type="button" wire:click="openCreate" class="erp-action-btn erp-action-edit">ثبت دستگاه جدید</button>
    </div>

    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="سریال، کالا، مشتری">
                </label>
                <label class="erp-filter-field">وضعیت گارانتی
                    <select wire:model.live="warranty_status">
                        <option value="">همه</option>
                        <option value="active">تحت گارانتی</option>
                        <option value="expired">پایان گارانتی</option>
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="دستگاهی ثبت نشده است.">
        <x-slot:cards>
            @foreach($items as $device)
                <x-crm.ui.list-card
                    wire:key="crm-sold-device-card-{{ $device->id }}"
                    class="{{ $device->isUnderWarranty() ? 'crm-list-card--warranty-active' : 'crm-list-card--warranty-expired' }}"
                    :title="$device->party?->name ?? 'بدون مشتری'"
                    :kicker="$device->display_name"
                    :badge-label="$device->warranty_status_label"
                    :badge-tone="$device->isUnderWarranty() ? 'success' : 'neutral'"
                >
                    <x-crm.ui.list-field label="سریال" emphasis ltr>{{ $device->serial_number }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="پایان گارانتی" emphasis>{{ gregorianToJalaliDate($device->warranty_ends_at) }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="تاریخ فروش">{{ gregorianToJalaliDate($device->sold_at) }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مدت گارانتی">{{ $device->warranty_years }} سال</x-crm.ui.list-field>
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $device->id }})" />
                            <x-erp.ui.row-action
                                icon="delete"
                                label="حذف"
                                tone="danger"
                                wire:click="remove({{ $device->id }})"
                                wire:confirm="این ثبت دستگاه حذف شود؟"
                            />
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['سریال', 'کالا / دستگاه', 'مشتری', 'تاریخ فروش', 'گارانتی (سال)', 'پایان گارانتی', 'وضعیت', 'عملیات']"
                empty-message="دستگاهی ثبت نشده است."
                :colspan="8"
            >
                @foreach($items as $device)
                    <tr wire:key="crm-sold-device-{{ $device->id }}">
                        <td class="font-semibold" dir="ltr">{{ $device->serial_number }}</td>
                        <td>{{ $device->display_name }}</td>
                        <td>{{ $device->party?->name ?? '-' }}</td>
                        <td>{{ gregorianToJalaliDate($device->sold_at) }}</td>
                        <td>{{ $device->warranty_years }}</td>
                        <td>{{ gregorianToJalaliDate($device->warranty_ends_at) }}</td>
                        <td>
                            <x-erp.ui.status-badge
                                :label="$device->warranty_status_label"
                                :tone="$device->isUnderWarranty() ? 'success' : 'neutral'"
                            />
                        </td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $device->id }})" />
                                <x-erp.ui.row-action
                                    icon="delete"
                                    label="حذف"
                                    tone="danger"
                                    wire:click="remove({{ $device->id }})"
                                    wire:confirm="این ثبت دستگاه حذف شود؟"
                                />
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </x-erp.ui.data-table>
        </x-slot:table>
    </x-crm.ui.responsive-list>

    <div>{{ $items->links() }}</div>

    <div id="erp-sold-device-item-options-json" hidden wire:key="sold-device-item-options">@json(itemSearchSelectOptions($catalogItems))</div>

    @if($showModal)
        <x-erp.ui.details-modal
            wire:key="sold-device-modal-{{ $editingId ?? 'new' }}"
            :title="$editingId ? 'ویرایش دستگاه' : 'ثبت دستگاه فروخته‌شده'"
        >
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="save">ذخیره</button>
            </x-slot>

            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">مشتری
                    <select wire:model="party_id">
                        <option value="">انتخاب مشتری</option>
                        @foreach($parties as $party)
                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                        @endforeach
                    </select>
                    @error('party_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="erp-filter-field md:col-span-2">کالا / دستگاه
                    <x-erp.ui.item-search-select
                        wire:model="item_id"
                        wire:key="sold-device-item-{{ $editingId ?? 'new' }}"
                        :value="$item_id"
                        :items="$catalogItems"
                        optionsSource="sold-device-items"
                        inputClass="w-full"
                        class="w-full"
                        placeholder="جستجو نام، کد یا دسته..."
                    />
                    @error('item_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="erp-filter-field">شماره سریال
                    <input wire:model="serial_number" dir="ltr" placeholder="Serial Number">
                    @error('serial_number') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="erp-filter-field">تاریخ فروش
                    <x-erp.ui.jalali-date-input
                        wire:model.live="sold_at"
                        wire:key="sold-device-sold-at-{{ $editingId ?? 'new' }}"
                        class="w-full"
                        placeholder="{{ todayJalaliDate() }}"
                    />
                    @error('sold_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="erp-filter-field">مدت گارانتی (سال)
                    <input
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        dir="ltr"
                        maxlength="2"
                        placeholder="مثلاً 2"
                        wire:model.blur="warranty_years"
                    >
                    @error('warranty_years') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>

                <label class="erp-filter-field">پایان گارانتی (محاسبه‌شده)
                    <input value="{{ $this->previewWarrantyEndsAt() }}" readonly class="bg-slate-50">
                </label>

                <label class="erp-filter-field md:col-span-2">توضیحات
                    <textarea wire:model="notes" rows="2" placeholder="یادداشت پشتیبانی"></textarea>
                </label>
            </div>
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.list-page>
