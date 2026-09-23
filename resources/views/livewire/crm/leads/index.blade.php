<x-erp.ui.list-page
    title="سرنخ‌ها"
    description="مدیریت سرنخ‌های فروش و تبدیل به مشتری."
    route="crm.leads.index"
>
    <div class="flex justify-end mb-2">
        <button type="button" wire:click="openCreate" class="erp-action-btn erp-action-edit">سرنخ جدید</button>
    </div>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="عنوان، شرکت، موبایل، شماره">
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">منبع
                    <select wire:model.live="source_id">
                        <option value="">همه</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->title }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="سرنخی یافت نشد.">
        <x-slot:cards>
            @foreach($items as $lead)
                <x-crm.ui.list-card
                    wire:key="crm-lead-card-{{ $lead->id }}"
                    class="crm-list-card--clickable"
                    wire:click="openLeadDetail({{ $lead->id }})"
                    role="button"
                    tabindex="0"
                    :title="$lead->title"
                    :kicker="$lead->number"
                    :badge-label="$lead->status_label"
                    badge-tone="info"
                >
                    <x-crm.ui.list-field label="نام / شرکت">{{ $lead->display_name }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="منبع">{{ $lead->source?->title ?? '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مسئول">{{ $lead->assignedUser?->name ?? '-' }}</x-crm.ui.list-field>
                    @if(($lead->attachments_count ?? 0) > 0)
                        <x-crm.ui.list-field label="پیوست">{{ $lead->attachments_count }} فایل</x-crm.ui.list-field>
                    @endif
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="view" label="جزئیات" wire:click="openLeadDetail({{ $lead->id }})" />
                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $lead->id }})" />
                            @if($lead->status !== 'converted')
                                <x-erp.ui.row-action icon="convert" label="تبدیل" wire:click="openConvert({{ $lead->id }})" tone="success" />
                            @endif
                            @if($lead->status !== 'converted' && auth()->user()?->hasPermission('crm.leads.delete'))
                                <x-erp.ui.row-action
                                    icon="delete"
                                    label="حذف"
                                    tone="danger"
                                    wire:click="deleteLead({{ $lead->id }})"
                                    wire:confirm="این سرنخ حذف شود؟"
                                />
                            @endif
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['شماره', 'عنوان', 'نام/شرکت', 'وضعیت', 'منبع', 'مسئول', 'عملیات']"
                empty-message="سرنخی یافت نشد."
                :colspan="7"
            >
                @foreach($items as $lead)
                    <tr
                        wire:key="crm-lead-{{ $lead->id }}"
                        class="crm-leads-row-clickable"
                        wire:click="openLeadDetail({{ $lead->id }})"
                        role="button"
                        tabindex="0"
                    >
                        <td class="text-nowrap font-semibold">{{ $lead->number }}</td>
                        <td>{{ $lead->title }}</td>
                        <td>{{ $lead->display_name }}</td>
                        <td><x-erp.ui.status-badge :label="$lead->status_label" tone="info" /></td>
                        <td>{{ $lead->source?->title ?? '-' }}</td>
                        <td>{{ $lead->assignedUser?->name ?? '-' }}</td>
                        <td @click.stop>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="view" label="جزئیات" wire:click="openLeadDetail({{ $lead->id }})" />
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openEdit({{ $lead->id }})" />
                                @if($lead->status !== 'converted')
                                    <x-erp.ui.row-action icon="convert" label="تبدیل" wire:click="openConvert({{ $lead->id }})" tone="success" />
                                @endif
                                @if($lead->status !== 'converted' && auth()->user()?->hasPermission('crm.leads.delete'))
                                    <x-erp.ui.row-action
                                        icon="delete"
                                        label="حذف"
                                        tone="danger"
                                        wire:click="deleteLead({{ $lead->id }})"
                                        wire:confirm="این سرنخ حذف شود؟"
                                    />
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
        <x-erp.ui.details-modal :title="$editingId ? 'ویرایش سرنخ' : 'سرنخ جدید'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="save">ذخیره</button>
            </x-slot>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">عنوان
                    <input wire:model="title">
                    @error('title') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>
                <label class="erp-filter-field">نام
                    <input wire:model="first_name">
                </label>
                <label class="erp-filter-field">نام خانوادگی
                    <input wire:model="last_name">
                </label>
                <label class="erp-filter-field">شرکت
                    <input wire:model="company_name">
                </label>
                <label class="erp-filter-field">موبایل
                    <input wire:model="mobile" dir="ltr">
                </label>
                <label class="erp-filter-field">ایمیل
                    <input wire:model="email" dir="ltr">
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model="lead_status">
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">منبع
                    <select wire:model="form_source_id">
                        <option value="">بدون منبع</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">ارزش تخمینی (ریال)
                    <input wire:model="estimated_value" dir="ltr">
                </label>
                <label class="erp-filter-field md:col-span-2">توضیحات / دلیل ثبت سرنخ
                    <textarea wire:model="description" rows="3" placeholder="مثلاً: درخواست لیست قیمت تجهیزات، پیگیری نصب، معرفی از طریق نمایشگاه..."></textarea>
                    @error('description') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </label>
            </div>
            @if($editingId)
                <div class="mt-4 pt-4 border-t border-slate-200">
                    @include('livewire.crm.partials.lead-attachments', [
                        'leadId' => $editingId,
                        'attachments' => $editingLeadAttachments,
                        'compact' => true,
                    ])
                </div>
            @else
                <p class="mt-3 text-sm text-slate-600">بعد از ذخیره سرنخ، به صفحه جزئیات می‌روید و می‌توانید لیست یا PDF درخواست را پیوست کنید.</p>
            @endif
        </x-erp.ui.details-modal>
    @endif

    @if($showConvertModal)
        <x-erp.ui.details-modal title="تبدیل سرنخ">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeConvertModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeConvertModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="convert">تبدیل</button>
            </x-slot>
            <p class="text-sm text-slate-700 mb-3">سرنخ به مشتری تبدیل می‌شود و مخاطب ایجاد می‌گردد.</p>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="create_opportunity">
                ایجاد فرصت فروش
            </label>
        </x-erp.ui.details-modal>
    @endif

    @if($showDetailModal && $detailLead)
        <x-erp.ui.details-modal :title="'سرنخ ' . $detailLead->number">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeLeadDetail">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeLeadDetail">بستن</button>
                <a href="{{ route('crm.leads.show', $detailLead->id) }}" wire:navigate class="erp-action-btn">صفحه کامل</a>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="openEdit({{ $detailLead->id }})">ویرایش</button>
                @if($detailLead->status !== 'converted')
                    <button type="button" class="erp-action-btn erp-action-edit" wire:click="openConvert({{ $detailLead->id }})">تبدیل</button>
                @endif
            </x-slot>

            @include('livewire.crm.partials.lead-show-summary', ['lead' => $detailLead])

            <div class="crm-lead-show-section mt-4">
                @include('livewire.crm.partials.lead-attachments', [
                    'leadId' => $detailLead->id,
                    'attachments' => $detailLead->attachments,
                    'compact' => true,
                ])
            </div>

            @if($detailLead->status === 'converted')
                <div class="crm-lead-show-converted mt-4">
                    <h3 class="crm-lead-show-converted__title">اطلاعات تبدیل</h3>
                    <div class="crm-lead-show-converted__body">
                        <div>مشتری: {{ $detailLead->convertedParty?->name ?? '-' }}</div>
                        <div>فرصت: {{ $detailLead->convertedOpportunity?->title ?? '-' }}</div>
                    </div>
                </div>
            @endif
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.list-page>
