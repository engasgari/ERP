<x-erp.ui.detail-page

    :title="$party->name"

    description="پروفایل CRM مشتری"

    route="crm.customers.index"

    :actions="[

        ['label' => 'بازگشت', 'url' => route('crm.customers.index'), 'class' => 'erp-action-btn'],

    ]"

>

    <x-slot name="toolbar">

        @if(($party->crmProfile?->status ?? 'active') === 'inactive' || ! $party->is_active)

            <button type="button" wire:click="reactivateCustomer" class="erp-action-btn erp-action-edit">فعال‌سازی مشتری</button>

        @else

            <button

                type="button"

                wire:click="deactivateCustomer"

                wire:confirm="مشتری غیرفعال شود؟ سرنخ‌ها و وظایف مرتبط حذف و فرصت‌های باز بسته می‌شوند."

                class="erp-action-btn erp-action-delete"

            >غیرفعال‌سازی</button>

        @endif

    </x-slot>

    <div class="crm-tab-bar">
        @foreach([
            'overview' => 'خلاصه',
            'activities' => 'فعالیت‌ها',
            'tasks' => 'وظایف',
            'contacts' => 'مخاطبین',
            'leads' => 'سرنخ‌ها',
            'opportunities' => 'فرصت‌ها',
            'timeline' => 'خط زمانی',
            'commerce' => 'تجاری',
        ] as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class(['crm-tab-btn', 'is-active' => $activeTab === $key])
            >{{ $label }}</button>
        @endforeach
    </div>



    @if($activeTab === 'overview')

        <div class="erp-modal-grid">

            <div class="erp-modal-field">کد<div class="erp-modal-value" dir="ltr">{{ $party->code ?: '-' }}</div></div>

            <div class="erp-modal-field">موبایل<div class="erp-modal-value" dir="ltr">{{ $party->mobile ?: '-' }}</div></div>

            <div class="erp-modal-field">وضعیت CRM<div class="erp-modal-value">{{ $party->crmProfile?->status_label ?? '-' }}</div></div>

            <div class="erp-modal-field">مسئول<div class="erp-modal-value">{{ $party->crmProfile?->assignedUser?->name ?? '-' }}</div></div>

            <div class="erp-modal-field">منبع<div class="erp-modal-value">{{ $party->crmProfile?->source?->title ?? '-' }}</div></div>

            <div class="erp-modal-field">آخرین فعالیت<div class="erp-modal-value">{{ $party->crmProfile?->last_activity_at ? formatJalaliDateTime($party->crmProfile->last_activity_at) : '-' }}</div></div>

            <div class="erp-modal-field md:col-span-2">یادداشت CRM<div class="erp-modal-value">{{ $party->crmProfile?->crm_notes ?: '-' }}</div></div>

        </div>

    @elseif($activeTab === 'activities')

        <div class="flex justify-end mb-3">

            <button type="button" wire:click="openCustomerActivity" class="erp-action-btn erp-action-edit">ثبت فعالیت</button>

        </div>

        <x-erp.ui.data-table :headers="['نوع', 'موضوع', 'خلاصه', 'موعد', 'تاریخ انجام', 'وضعیت', 'عملیات']" empty-message="فعالیتی ثبت نشده." :colspan="7">

            @foreach($party->crmActivities as $activity)

                <tr wire:key="cust-act-{{ $activity->id }}">

                    <td>{{ $activity->type_label }}</td>

                    <td>{{ $activity->subject }}</td>

                    <td>{{ str($activity->description)->limit(80) ?: '-' }}</td>

                    <td>{{ $activity->due_at ? formatJalaliDateTime($activity->due_at) : '-' }}</td>

                    <td>{{ $activity->completed_at ? formatJalaliDateTime($activity->completed_at) : '-' }}</td>

                    <td>{{ $activity->status === 'completed' ? 'انجام شده' : 'برنامه‌ریزی' }}</td>

                    <td>
                        @if(auth()->user()?->hasPermission('crm.activities.update'))
                            <x-erp.ui.row-actions>
                                <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openActivityEdit({{ $activity->id }})" />
                            </x-erp.ui.row-actions>
                        @endif
                    </td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>

    @elseif($activeTab === 'tasks')

        <div class="flex justify-end mb-3">

            <button type="button" wire:click="openCustomerTask" class="erp-action-btn erp-action-edit">وظیفه جدید</button>

        </div>

        <x-erp.ui.data-table :headers="['عنوان', 'موعد', 'اولویت', 'وضعیت', 'مسئول', 'عملیات']" empty-message="وظیفه باز وجود ندارد." :colspan="6">

            @foreach($party->crmTasks as $task)

                <tr wire:key="cust-task-{{ $task->id }}">

                    <td>{{ $task->title }}</td>

                    <td>{{ $task->due_at ? formatJalaliDateTime($task->due_at) : '-' }}</td>

                    <td>{{ $task->priority_label }}</td>

                    <td>{{ $task->status_label }}</td>

                    <td>{{ $task->assignedUser?->name ?? '-' }}</td>

                    <td>

                        <x-erp.ui.row-actions>

                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openTaskEdit({{ $task->id }})" />

                        </x-erp.ui.row-actions>

                    </td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>

        <p class="text-xs text-slate-500 mt-2">وظایف مرحله‌ای خط فروش پس از انتقال فرصت به مرحله جدید، خودکار ایجاد می‌شوند.</p>

    @elseif($activeTab === 'contacts')

        <x-erp.ui.data-table :headers="['نام', 'موبایل', 'ایمیل', 'سمت']" empty-message="مخاطبی ثبت نشده." :colspan="4">

            @foreach($party->crmContacts as $contact)

                <tr wire:key="contact-{{ $contact->id }}">

                    <td>
                        {{ $contact->full_name }}
                        @if(! $contact->is_active)
                            <span class="text-xs text-slate-500">(غیرفعال)</span>
                        @endif
                    </td>

                    <td dir="ltr">{{ $contact->mobile ?: '-' }}</td>

                    <td dir="ltr">{{ $contact->email ?: '-' }}</td>

                    <td>{{ $contact->job_title ?: '-' }}</td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>

    @elseif($activeTab === 'leads')

        <x-erp.ui.data-table :headers="['شماره', 'عنوان', 'وضعیت', 'ارزش']" empty-message="سرنخی ثبت نشده." :colspan="4">

            @foreach($party->crmLeads as $lead)

                <tr wire:key="lead-{{ $lead->id }}">

                    <td><a href="{{ route('crm.leads.show', $lead->id) }}" class="text-blue-700">{{ $lead->number }}</a></td>

                    <td>{{ $lead->title }}</td>

                    <td>{{ $lead->status_label }}</td>

                    <td>{{ $lead->estimated_value ? formatMoney((float) $lead->estimated_value) : '-' }}</td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>

    @elseif($activeTab === 'opportunities')

        <x-erp.ui.data-table :headers="['شماره', 'عنوان', 'مرحله', 'مبلغ', 'وضعیت', 'عملیات']" empty-message="فرصتی ثبت نشده." :colspan="6">

            @foreach($party->crmOpportunities as $opp)

                <tr wire:key="opp-{{ $opp->id }}">
                    <td>
                        <button type="button" class="text-blue-700 font-semibold hover:underline" dir="ltr" wire:click="openOpportunityTimeline({{ $opp->id }})">
                            {{ $opp->number }}
                        </button>
                    </td>
                    <td>
                        <button type="button" class="text-blue-700 hover:underline text-right" wire:click="openOpportunityTimeline({{ $opp->id }})">
                            {{ $opp->title }}
                        </button>
                    </td>

                    <td>{{ $opp->stage?->name ?? '-' }}</td>

                    <td>{{ formatMoney((float) $opp->amount) }}</td>

                    <td>{{ $opp->status }}</td>

                    <td>

                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action icon="view" label="خط زمانی" wire:click="openOpportunityTimeline({{ $opp->id }})" />
                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="openOpportunityEdit({{ $opp->id }})" />
                            @if($opp->status === 'open')
                                <x-erp.ui.row-action icon="edit" label="ثبت فعالیت" wire:click="openOpportunityActivity({{ $opp->id }})" />
                            @endif
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

    @elseif($activeTab === 'timeline')

        @include('livewire.crm.partials.timeline-feed', [
            'timelineTree' => $timelineTree,
            'timelineSummary' => $timelineSummary,
            'timelineOpportunityOptions' => $timelineOpportunityOptions,
            'showOpportunityFilter' => true,
            'showOpportunityGroupLabels' => empty($timelineOpportunityFilter),
        ])

    @elseif($activeTab === 'commerce')

        <h3 class="font-semibold mb-2">فاکتورهای فروش</h3>

        <x-erp.ui.data-table :headers="['شماره', 'تاریخ', 'مبلغ']" empty-message="فاکتوری یافت نشد." :colspan="3">

            @foreach($party->invoices as $invoice)

                <tr wire:key="invoice-{{ $invoice->id }}">

                    <td>
                        <button
                            type="button"
                            class="text-blue-700 font-semibold hover:underline"
                            data-invoice-show="{{ $invoice->id }}"
                            data-invoice-show-crm="1"
                            data-invoice-show-title="فاکتور {{ $invoice->number }}"
                        >
                            {{ $invoice->number }}
                        </button>
                    </td>

                    <td>{{ gregorianToJalaliDate($invoice->invoice_date) }}</td>

                    <td>{{ formatMoney((float) $invoice->total_amount) }}</td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>



        <h3 class="font-semibold mt-6 mb-2">پروژه‌ها</h3>

        <x-erp.ui.data-table :headers="['شماره', 'نام']" empty-message="پروژه‌ای یافت نشد." :colspan="2">

            @foreach($party->projects as $project)

                <tr wire:key="project-{{ $project->id }}">

                    <td dir="ltr">{{ $project->project_number ?: '-' }}</td>

                    <td>{{ $project->name }}</td>

                </tr>

            @endforeach

        </x-erp.ui.data-table>

    @endif



    @if($showActivityModal)

        <x-erp.ui.details-modal :title="$editingActivityId ? 'ویرایش فعالیت' : 'ثبت فعالیت'">

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



    @if($showTaskModal)

        <x-erp.ui.details-modal :title="$editingTaskId ? 'ویرایش وظیفه' : 'وظیفه جدید'">

            <x-slot name="close">

                <button type="button" class="erp-modal-close" wire:click="closeTaskModal">×</button>

            </x-slot>

            <x-slot name="footer">

                <button type="button" class="erp-action-btn" wire:click="closeTaskModal">انصراف</button>

                <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveTask">{{ $editingTaskId ? 'به‌روزرسانی' : 'ذخیره' }}</button>

            </x-slot>

            @include('livewire.crm.partials.task-form', ['priorityOptions' => $priorityOptions, 'statusOptions' => $statusOptions, 'users' => $users])

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

    @if($opportunityTimelineOverview)
        <x-erp.ui.details-modal :title="'خط زمانی فرصت ' . $opportunityTimelineOverview['opportunity']->number">
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

    <x-erp.ui.invoice-iframe-modals />

</x-erp.ui.detail-page>

