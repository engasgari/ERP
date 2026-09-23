<x-erp.ui.detail-page

    :title="'سرنخ ' . $lead->number"

    :description="$lead->title"

    route="crm.leads.index"

    :actions="[

        ['label' => 'بازگشت', 'url' => route('crm.leads.index'), 'class' => 'erp-action-btn'],

    ]"

>

    @if($lead->status !== 'converted')

        <div class="crm-lead-show-actions">

            <button type="button" wire:click="openLeadActivity" class="erp-action-btn erp-action-edit">ثبت تماس / فعالیت</button>

            @if($lead->status === 'new')

                <button type="button" wire:click="markContacted" class="erp-action-btn">علامت «تماس گرفته»</button>

            @endif

        </div>

    @endif



    @include('livewire.crm.partials.lead-show-summary', ['lead' => $lead])



    <div class="crm-lead-show-section">

        @include('livewire.crm.partials.lead-attachments', [

            'leadId' => $lead->id,

            'attachments' => $lead->attachments,

        ])

    </div>



    @if($lead->status === 'converted')

        <div class="crm-lead-show-converted">

            <h3 class="crm-lead-show-converted__title">اطلاعات تبدیل</h3>

            <div class="crm-lead-show-converted__body">

                <div>مشتری: {{ $lead->convertedParty?->name ?? '-' }}</div>

                <div>فرصت: {{ $lead->convertedOpportunity?->title ?? '-' }}</div>

                <div>تاریخ تبدیل: {{ $lead->converted_at ? formatJalaliDateTime($lead->converted_at) : '-' }}</div>

            </div>

            @if($lead->converted_party_id)

                <a href="{{ route('crm.customers.show', $lead->converted_party_id) }}" class="crm-lead-show-converted__link">مشاهده مشتری</a>

            @endif

        </div>

    @endif



    <div class="crm-lead-show-section">

        <h3 class="crm-lead-show-section__title">فعالیت‌های این سرنخ</h3>

        <x-erp.ui.data-table :headers="['نوع', 'موضوع', 'خلاصه', 'موعد', 'تاریخ انجام', 'وضعیت', 'عملیات']" empty-message="هنوز فعالیتی ثبت نشده." :colspan="7">

            @foreach($lead->activities as $activity)

                <tr wire:key="lead-act-{{ $activity->id }}">

                    <td>{{ $activity->type_label }}</td>

                    <td>{{ $activity->subject }}</td>

                    <td>{{ str($activity->description)->limit(80) ?: '-' }}</td>

                    <td>{{ $activity->due_at ? formatJalaliDateTime($activity->due_at) : '-' }}</td>

                    <td>{{ $activity->completed_at ? formatJalaliDateTime($activity->completed_at) : '-' }}</td>

                    <td>{{ $activity->status === 'completed' ? 'انجام شده' : 'برنامه‌ریزی' }}</td>

                    <td>

                        @if($lead->status !== 'converted' && auth()->user()?->hasPermission('crm.activities.update'))

                            <x-erp.ui.row-actions>

                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openActivityEdit({{ $activity->id }})" />

                            </x-erp.ui.row-actions>

                        @endif

                    </td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>

    </div>



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

</x-erp.ui.detail-page>

