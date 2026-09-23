<x-erp.ui.list-page
    title="فعالیت‌ها"
    description="تماس، جلسه، ایمیل و سایر فعالیت‌های CRM."
    route="crm.activities.index"
>
    <div class="flex justify-end mb-2">
        <button type="button" wire:click="openCreate" class="erp-action-btn erp-action-edit">ثبت فعالیت</button>
    </div>

    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="موضوع، توضیح">
                </label>
                <label class="erp-filter-field">نوع
                    <select wire:model.live="type">
                        <option value="">همه</option>
                        @foreach($typeOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        <option value="planned">برنامه‌ریزی شده</option>
                        <option value="completed">انجام شده</option>
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="فعالیتی یافت نشد.">
        <x-slot:cards>
            @foreach($items as $activity)
                <x-crm.ui.list-card
                    wire:key="crm-activity-card-{{ $activity->id }}"
                    :title="$activity->subject"
                    :kicker="$activity->type_label"
                    :badge-label="$activity->status === 'completed' ? 'انجام شده' : 'برنامه‌ریزی'"
                    badge-tone="info"
                >
                    <x-crm.ui.list-field label="مشتری">{{ $activity->party?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="موعد">{{ $activity->due_at ? formatJalaliDateTime($activity->due_at) : '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="تاریخ انجام">{{ $activity->completed_at ? formatJalaliDateTime($activity->completed_at) : '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مسئول">{{ $activity->assignedUser?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            @if(auth()->user()?->hasPermission('crm.activities.update'))
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openActivityEdit({{ $activity->id }})" />
                            @endif
                            @if($activity->status !== 'completed')
                                <x-erp.ui.row-action icon="confirm" label="تکمیل" wire:click="complete({{ $activity->id }})" tone="success" />
                            @endif
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['نوع', 'موضوع', 'مشتری', 'موعد', 'تاریخ انجام', 'وضعیت', 'مسئول', 'عملیات']"
                empty-message="فعالیتی یافت نشد."
                :colspan="8"
            >
                @foreach($items as $activity)
                    <tr wire:key="crm-activity-{{ $activity->id }}">
                        <td>{{ $activity->type_label }}</td>
                        <td class="font-semibold">{{ $activity->subject }}</td>
                        <td>{{ $activity->party?->name ?? '-' }}</td>
                        <td class="text-nowrap">{{ $activity->due_at ? formatJalaliDateTime($activity->due_at) : '-' }}</td>
                        <td class="text-nowrap">{{ $activity->completed_at ? formatJalaliDateTime($activity->completed_at) : '-' }}</td>
                        <td><x-erp.ui.status-badge :label="$activity->status === 'completed' ? 'انجام شده' : 'برنامه‌ریزی'" tone="info" /></td>
                        <td>{{ $activity->assignedUser?->name ?? '-' }}</td>
                        <td>
                            <x-erp.ui.row-actions>
                                @if(auth()->user()?->hasPermission('crm.activities.update'))
                                    <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openActivityEdit({{ $activity->id }})" />
                                @endif
                                @if($activity->status !== 'completed')
                                    <x-erp.ui.row-action icon="confirm" label="تکمیل" wire:click="complete({{ $activity->id }})" tone="success" />
                                @endif
                            </x-erp.ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </x-erp.ui.data-table>
        </x-slot:table>
    </x-crm.ui.responsive-list>

    <div>{{ $items->links() }}</div>

    @if($showActivityModal)
        <x-erp.ui.details-modal :title="$editingActivityId ? 'ویرایش فعالیت' : 'ثبت فعالیت'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeActivityModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeActivityModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="storeActivity">ذخیره</button>
            </x-slot>
            <label class="erp-filter-field md:col-span-2 mb-3 block">مشتری (اختیاری)
                <select wire:model="form_party_id">
                    <option value="">—</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->code }})</option>
                    @endforeach
                </select>
            </label>
            @include('livewire.crm.partials.activity-form', ['typeOptions' => $typeOptions])
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.list-page>
