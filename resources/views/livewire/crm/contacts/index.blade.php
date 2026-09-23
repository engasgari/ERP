<x-erp.ui.list-page
    title="مخاطبین CRM"
    description="مدیریت مخاطبین مرتبط با مشتریان."
    route="crm.contacts.index"
>
    <div class="flex justify-end mb-2">
        <button type="button" wire:click="openCreate" class="erp-action-btn erp-action-edit">مخاطب جدید</button>
    </div>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="نام، موبایل، ایمیل، مشتری">
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">فعال‌ها</option>
                        @foreach(\App\Models\Crm\CrmModel::CONTACT_STATUSES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="مخاطبی یافت نشد.">
        <x-slot:cards>
            @foreach($items as $contact)
                <x-crm.ui.list-card
                    wire:key="crm-contact-card-{{ $contact->id }}"
                    :title="$contact->full_name"
                    :kicker="$contact->party?->name ?? 'بدون مشتری'"
                    :badge-label="$contact->status_label"
                    :badge-tone="$contact->is_active ? 'success' : 'neutral'"
                >
                    <x-crm.ui.list-field label="موبایل"><span dir="ltr">{{ $contact->mobile ?: '-' }}</span></x-crm.ui.list-field>
                    <x-crm.ui.list-field label="ایمیل"><span dir="ltr">{{ $contact->email ?: '-' }}</span></x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مسئول">{{ $contact->assignedUser?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $contact->id }})" />
                            @if($contact->is_active)
                                <x-erp.ui.row-action
                                    icon="delete"
                                    label="غیرفعال"
                                    tone="danger"
                                    wire:click="remove({{ $contact->id }})"
                                    wire:confirm="مخاطب غیرفعال شود؟ (از پروفایل مشتری حذف نمی‌شود)"
                                />
                            @else
                                <x-erp.ui.row-action icon="edit" label="فعال‌سازی" wire:click="reactivate({{ $contact->id }})" />
                            @endif
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['نام', 'مشتری', 'موبایل', 'ایمیل', 'وضعیت', 'مسئول', 'عملیات']"
                empty-message="مخاطبی یافت نشد."
                :colspan="7"
            >
                @foreach($items as $contact)
                    <tr wire:key="crm-contact-{{ $contact->id }}">
                        <td class="font-semibold">{{ $contact->full_name }}</td>
                        <td>{{ $contact->party?->name ?? '-' }}</td>
                        <td dir="ltr">{{ $contact->mobile ?: '-' }}</td>
                        <td dir="ltr">{{ $contact->email ?: '-' }}</td>
                        <td>
                            <x-erp.ui.status-badge
                                :label="$contact->status_label"
                                :tone="$contact->is_active ? 'success' : 'neutral'"
                            />
                        </td>
                        <td>{{ $contact->assignedUser?->name ?? '-' }}</td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $contact->id }})" />
                                @if($contact->is_active)
                                    <x-erp.ui.row-action
                                        icon="delete"
                                        label="غیرفعال"
                                        tone="danger"
                                        wire:click="remove({{ $contact->id }})"
                                        wire:confirm="مخاطب غیرفعال شود؟ (از پروفایل مشتری حذف نمی‌شود)"
                                    />
                                @else
                                    <x-erp.ui.row-action icon="edit" label="فعال‌سازی" wire:click="reactivate({{ $contact->id }})" />
                                @endif
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </x-erp.ui.data-table>
        </x-slot:table>
    </x-crm.ui.responsive-list>

    <div>{{ $items->links() }}</div>

    @if($showModal)
        <x-erp.ui.details-modal :title="$editingId ? 'ویرایش مخاطب' : 'مخاطب جدید'">
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
                        <option value="">انتخاب کنید</option>
                        @foreach($parties as $party)
                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                        @endforeach
                    </select>
                    @error('party_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field">نام
                    <input wire:model="first_name">
                    @error('first_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field">نام خانوادگی
                    <input wire:model="last_name">
                </label>
                <label class="erp-filter-field">موبایل
                    <input wire:model="mobile" dir="ltr">
                </label>
                <label class="erp-filter-field">ایمیل
                    <input wire:model="email" dir="ltr">
                </label>
                <label class="erp-filter-field">سمت
                    <input wire:model="job_title">
                </label>
            </div>
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.list-page>
