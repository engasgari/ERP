<x-erp.ui.list-page
    title="فرصت‌های فروش"
    description="فهرست فرصت‌ها با مرحله، مبلغ و وضعیت."
    route="crm.opportunities.index"
    :actions="[
        ['label' => 'نمای کانبان', 'url' => route('crm.pipeline.index'), 'class' => 'erp-action-btn'],
    ]"
>
    <div class="flex justify-end mb-2">
        <button type="button" wire:click="openOpportunityCreate" class="erp-action-btn erp-action-edit">فرصت جدید</button>
    </div>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row">
                <label class="erp-filter-field md:col-span-2">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="عنوان، شماره، نام مشتری">
                </label>
                <label class="erp-filter-field">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        <option value="open">باز</option>
                        <option value="won">برنده</option>
                        <option value="lost">از دست رفته</option>
                    </select>
                </label>
                <label class="erp-filter-field">پایپ‌لاین
                    <select wire:model.live="pipeline_id">
                        <option value="">همه</option>
                        @foreach($pipelines as $pipeline)
                            <option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-crm.ui.responsive-list :has-items="$items->isNotEmpty()" empty-message="فرصتی یافت نشد.">
        <x-slot:cards>
            @foreach($items as $opp)
                @php
                    $oppStatusLabel = match ($opp->status) {
                        'won' => 'برنده',
                        'lost' => 'از دست رفته',
                        default => 'باز',
                    };
                @endphp
                <x-crm.ui.list-card
                    wire:key="crm-opp-card-{{ $opp->id }}"
                    :title="$opp->title"
                    :kicker="$opp->number"
                    :badge-label="$oppStatusLabel"
                    badge-tone="info"
                >
                    <x-crm.ui.list-field label="مشتری">{{ $opp->party?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مرحله">{{ $opp->stage?->name ?? '-' }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مبلغ">{{ formatMoney((float) $opp->amount) }}</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="احتمال">{{ number_format((float) $opp->probability, 0) }}%</x-crm.ui.list-field>
                    <x-crm.ui.list-field label="مسئول">{{ $opp->assignedUser?->name ?? '-' }}</x-crm.ui.list-field>
                    <button type="button" class="crm-list-card__tap" wire:click="openOpportunityTimeline({{ $opp->id }})">مشاهده تایم‌لاین فرصت</button>
                    <x-slot:actions>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openOpportunityEdit({{ $opp->id }})" />
                            @if($opp->status === 'open' && auth()->user()?->hasPermission('crm.opportunities.delete'))
                                <x-erp.ui.row-action
                                    icon="delete"
                                    label="حذف"
                                    tone="danger"
                                    wire:click="deleteOpportunity({{ $opp->id }})"
                                    wire:confirm="این فرصت باز حذف شود؟"
                                />
                            @endif
                            @if($opp->status === 'won' && auth()->user()?->hasPermission('crm.opportunities.move_stage'))
                                <x-erp.ui.row-action
                                    icon="revert"
                                    label="بازگشت به مرحله قبل"
                                    wire:click="reopenFromWon({{ $opp->id }})"
                                    wire:confirm="فرصت به مرحله قبل برمی‌گردد و دوباره باز می‌شود. ادامه می‌دهید؟"
                                />
                            @endif
                        </x-erp.ui.row-actions>
                    </x-slot:actions>
                </x-crm.ui.list-card>
            @endforeach
        </x-slot:cards>
        <x-slot:table>
            <x-erp.ui.data-table
                :headers="['شماره', 'عنوان', 'مشتری', 'مرحله', 'مبلغ', 'احتمال', 'وضعیت', 'مسئول', 'عملیات']"
                empty-message="فرصتی یافت نشد."
                :colspan="9"
            >
                @foreach($items as $opp)
                    <tr wire:key="crm-opp-{{ $opp->id }}">
                        <td
                            class="font-semibold text-nowrap cursor-pointer"
                            dir="ltr"
                            wire:click="openOpportunityTimeline({{ $opp->id }})"
                        >{{ $opp->number }}</td>
                        <td
                            class="cursor-pointer"
                            wire:click="openOpportunityTimeline({{ $opp->id }})"
                        >{{ $opp->title }}</td>
                        <td>{{ $opp->party?->name ?? '-' }}</td>
                        <td>{{ $opp->stage?->name ?? '-' }}</td>
                        <td>{{ formatMoney((float) $opp->amount) }}</td>
                        <td>{{ number_format((float) $opp->probability, 0) }}%</td>
                        <td><x-erp.ui.status-badge :label="$opp->status" tone="info" /></td>
                        <td>{{ $opp->assignedUser?->name ?? '-' }}</td>
                        <td>
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openOpportunityEdit({{ $opp->id }})" />
                                @if($opp->status === 'open' && auth()->user()?->hasPermission('crm.opportunities.delete'))
                                    <x-erp.ui.row-action
                                        icon="delete"
                                        label="حذف"
                                        tone="danger"
                                        wire:click="deleteOpportunity({{ $opp->id }})"
                                        wire:confirm="این فرصت باز حذف شود؟"
                                    />
                                @endif
                                @if($opp->status === 'won' && auth()->user()?->hasPermission('crm.opportunities.move_stage'))
                                    <x-erp.ui.row-action
                                        icon="revert"
                                        label="بازگشت به مرحله قبل"
                                        wire:click="reopenFromWon({{ $opp->id }})"
                                        wire:confirm="فرصت به مرحله قبل برمی‌گردد و دوباره باز می‌شود. ادامه می‌دهید؟"
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

    @if($showTimelineModal && $opportunityTimelineOverview)
        <x-erp.ui.details-modal :title="'گزارش فرصت ' . $opportunityTimelineOverview['opportunity']->number">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeOpportunityTimeline">×</button>
            </x-slot>
            <x-slot name="footer">
                @include('livewire.crm.partials.opportunity-timeline-modal-footer', [
                    'opportunityTimelineOverview' => $opportunityTimelineOverview,
                ])
            </x-slot>
            @include('livewire.crm.partials.opportunity-timeline-panel', [
                'opportunityTimelineOverview' => $opportunityTimelineOverview,
            ])
        </x-erp.ui.details-modal>
    @endif

    @if($showActivityModal)
        <x-erp.ui.details-modal :title="$editingActivityId ? 'ویرایش تماس / فعالیت' : 'ثبت تماس / فعالیت'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeActivityModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeActivityModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveActivity">ذخیره</button>
            </x-slot>
            @include('livewire.crm.partials.activity-form', ['typeOptions' => $typeOptions])
        </x-erp.ui.details-modal>
    @endif

    @if($showOpportunityModal)
        <x-erp.ui.details-modal :title="$editingOpportunityId ? 'ویرایش فرصت' : 'فرصت جدید'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeOpportunityModal">×</button>
            </x-slot>
            <x-slot name="footer">
                <button type="button" class="erp-action-btn" wire:click="closeOpportunityModal">انصراف</button>
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveOpportunity">ذخیره</button>
            </x-slot>
            @include('livewire.crm.partials.opportunity-form')
        </x-erp.ui.details-modal>
    @endif

    @include('livewire.crm.partials.opportunity-quick-create-modals')

    <x-erp.ui.invoice-iframe-modals />
</x-erp.ui.list-page>
