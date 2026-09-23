@php

    use App\Models\Crm\CrmModel;



    $overview = $opportunityTimelineOverview;

    $opportunity = $overview['opportunity'];

    $pendingTasks = $overview['pending_tasks'] ?? collect();

    $statusLabel = CrmModel::STATUSES_OPPORTUNITY[$opportunity->status] ?? $opportunity->status;

@endphp



<div class="crm-opportunity-timeline-panel">

    <div class="crm-opportunity-timeline-header">

        <div class="crm-opportunity-timeline-heading">

            <span class="crm-opportunity-timeline-number" dir="ltr">{{ $opportunity->number }}</span>

            <h3 class="crm-opportunity-timeline-title">{{ $opportunity->title }}</h3>

        </div>



        <div class="crm-opportunity-timeline-meta">

            <span class="crm-opportunity-timeline-chip">مشتری: {{ $opportunity->party?->name ?? '—' }}</span>

            <span class="crm-opportunity-timeline-chip">مرحله: {{ $opportunity->stage?->name ?? '—' }}</span>

            <span class="crm-opportunity-timeline-chip">وضعیت: {{ $statusLabel }}</span>

            <span class="crm-opportunity-timeline-chip">مبلغ: {{ formatMoney((float) $opportunity->amount) }}</span>

            @if((float) $opportunity->probability > 0)

                <span class="crm-opportunity-timeline-chip">احتمال: {{ rtrim(rtrim(number_format((float) $opportunity->probability, 2, '.', ''), '0'), '.') }}%</span>

            @endif

            @if($opportunity->expected_close_date)

                <span class="crm-opportunity-timeline-chip">پیش‌بینی بستن: {{ gregorianToJalaliDate($opportunity->expected_close_date) }}</span>

            @endif

            <span class="crm-opportunity-timeline-chip">مسئول: {{ $opportunity->assignedUser?->name ?? '—' }}</span>

            @if($opportunity->pipeline?->name)

                <span class="crm-opportunity-timeline-chip">پایپ‌لاین: {{ $opportunity->pipeline->name }}</span>

            @endif

            @if($opportunity->invoice)

                <span class="crm-opportunity-timeline-chip crm-opportunity-timeline-chip--proforma" dir="ltr">

                    پیش‌فاکتور: {{ $opportunity->invoice->number }}

                </span>

            @endif

        </div>



        @if($opportunity->description)

            <p class="crm-opportunity-timeline-description">{{ $opportunity->description }}</p>

        @endif

    </div>



    <section class="crm-opportunity-timeline-section">
        <h4 class="crm-opportunity-timeline-section-title">پیوست‌ها / لیست درخواست</h4>
        @include('livewire.crm.partials.lead-attachments', [
            'attachments' => $opportunity->attachments,
            'compact' => true,
            'uploadUrl' => route('crm.opportunities.attachments.store', $opportunity),
            'canUpload' => auth()->user()?->hasPermission('crm.opportunities.update') || auth()->user()?->hasPermission('crm.opportunities.create'),
            'canDelete' => auth()->user()?->hasPermission('crm.opportunities.update'),
        ])
    </section>

    @if($pendingTasks->isNotEmpty())

        <section class="crm-opportunity-timeline-section">

            <h4 class="crm-opportunity-timeline-section-title">وظایف باز ({{ $pendingTasks->count() }})</h4>

            <div class="crm-opportunity-task-list">

                @foreach($pendingTasks as $task)

                    <article class="crm-opportunity-task-item" wire:key="opp-pending-task-{{ $task->id }}">

                        <div class="crm-opportunity-task-title">{{ $task->title }}</div>

                        <div class="crm-opportunity-task-meta">

                            @if($task->due_at)

                                <span>سررسید: {{ formatJalaliDateTime($task->due_at) }}</span>

                            @endif

                            @if($task->assignedUser?->name)

                                <span>مسئول: {{ $task->assignedUser->name }}</span>

                            @endif

                            <span>{{ CrmModel::PRIORITIES_TASK[$task->priority] ?? $task->priority }}</span>

                        </div>

                        @if($task->description)

                            <div class="crm-opportunity-task-desc">{{ $task->description }}</div>

                        @endif

                    </article>

                @endforeach

            </div>

        </section>

    @endif



    <section class="crm-opportunity-timeline-section">

        <h4 class="crm-opportunity-timeline-section-title">خط زمانی فرآیند</h4>

        @include('livewire.crm.partials.timeline-feed', [

            'timelineTree' => collect([[

                'node_type' => 'opportunity_group',

                'opportunity_id' => $opportunity->id,

                'opportunity_label' => null,

                'milestones' => $overview['timeline_tree']->all(),

            ]]),

            'timelineSummary' => $overview['timeline_summary'],

            'showOpportunityFilter' => false,

            'showOpportunityGroupLabels' => false,

        ])

    </section>

</div>

