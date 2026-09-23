@props(['milestone', 'showOpportunityLabel' => false])

<article @class([
    'crm-timeline-milestone',
    'crm-timeline-milestone--' . ($milestone['kind'] ?? 'other'),
])>
    <div class="crm-timeline-milestone-rail" aria-hidden="true"></div>

    <div class="crm-timeline-milestone-content">
        <div class="crm-timeline-milestone-card">
            <div class="crm-timeline-milestone-main">
            @if($showOpportunityLabel && ! empty($milestone['opportunity_label']))
                <div class="crm-timeline-entry-opportunity" dir="ltr">{{ $milestone['opportunity_label'] }}</div>
            @endif

            <div class="crm-timeline-milestone-head">
                <span class="crm-timeline-entry-badge">{{ $milestone['badge'] ?? 'رویداد' }}</span>
                <time class="crm-timeline-entry-meta">{{ formatJalaliDateTime($milestone['occurred_at']) }}</time>
            </div>

            <h4 class="crm-timeline-entry-title">{{ $milestone['title'] }}</h4>

            @if(! empty($milestone['summary']))
                <p class="crm-timeline-entry-summary">{{ $milestone['summary'] }}</p>
            @endif

            @if(! empty($milestone['user_name']))
                <div class="crm-timeline-entry-user">توسط {{ $milestone['user_name'] }}</div>
            @endif
        </div>

        @if(! empty($milestone['stage_task_labels']))
            <div class="crm-timeline-milestone-tasks">
                <div class="crm-timeline-milestone-tasks-label">وظایف این مرحله</div>
                <ul class="crm-timeline-milestone-task-list">
                    @foreach($milestone['stage_task_labels'] as $taskLabel)
                        <li>{{ $taskLabel }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        </div>

        @if(! empty($milestone['pending_tasks']) || ! empty($milestone['children']))
            @php
                $branchEntries = collect($milestone['pending_tasks'] ?? [])
                    ->merge($milestone['children'] ?? [])
                    ->sortByDesc(fn (array $entry) => ($entry['occurred_at'] ?? null)?->timestamp ?? 0)
                    ->values();
            @endphp
            <div class="crm-timeline-branches">
                @foreach($branchEntries as $entry)
                    @include('livewire.crm.partials.timeline-branch-entry', ['entry' => $entry])
                @endforeach
            </div>
        @endif
    </div>
</article>
