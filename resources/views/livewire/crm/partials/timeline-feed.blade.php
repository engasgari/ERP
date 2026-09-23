@php
    $summary = $timelineSummary ?? ['calls' => 0, 'sms' => 0, 'stages' => 0, 'activities' => 0];
    $showOpportunityFilter = $showOpportunityFilter ?? false;
    $timelineTree = $timelineTree ?? collect();
    $showOpportunityGroupLabels = $showOpportunityGroupLabels ?? true;
@endphp

<div class="crm-timeline-panel">
    @if($showOpportunityFilter)
        <div class="crm-timeline-toolbar">
            <label class="crm-timeline-filter-label" for="crm-timeline-opportunity-filter">فیلتر فرصت</label>
            <select id="crm-timeline-opportunity-filter" class="erp-ui-select crm-timeline-filter" wire:model.live="timelineOpportunityFilter">
                <option value="">همه فرصت‌ها</option>
                @foreach($timelineOpportunityOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['label'] }} @if($option['stage']) ({{ $option['stage'] }}) @endif</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="crm-timeline-summary">
        <div class="crm-timeline-stat">
            <span class="crm-timeline-stat-value">{{ number_format($summary['calls']) }}</span>
            <span class="crm-timeline-stat-label">تماس</span>
        </div>
        <div class="crm-timeline-stat">
            <span class="crm-timeline-stat-value">{{ number_format($summary['sms']) }}</span>
            <span class="crm-timeline-stat-label">پیامک</span>
        </div>
        <div class="crm-timeline-stat">
            <span class="crm-timeline-stat-value">{{ number_format($summary['stages']) }}</span>
            <span class="crm-timeline-stat-label">تغییر مرحله</span>
        </div>
        <div class="crm-timeline-stat">
            <span class="crm-timeline-stat-value">{{ number_format($summary['activities']) }}</span>
            <span class="crm-timeline-stat-label">سایر فعالیت‌ها</span>
        </div>
    </div>

    <div class="crm-timeline-tree">
        @forelse($timelineTree as $group)
            <section class="crm-timeline-opportunity-group" wire:key="timeline-group-{{ $group['opportunity_id'] ?? 'misc' }}">
                @if($showOpportunityGroupLabels && ! empty($group['opportunity_label']))
                    <header class="crm-timeline-opportunity-group-head">
                        <span class="crm-timeline-opportunity-group-label" @if(! empty($group['opportunity_id'])) dir="ltr" @endif>
                            {{ $group['opportunity_label'] }}
                        </span>
                    </header>
                @endif

                <div class="crm-timeline-milestones">
                    @foreach($group['milestones'] ?? [] as $milestone)
                        @include('livewire.crm.partials.timeline-milestone', [
                            'milestone' => $milestone,
                            'showOpportunityLabel' => false,
                        ])
                    @endforeach
                </div>
            </section>
        @empty
            <x-erp.ui.empty-state message="رویدادی در خط زمانی وجود ندارد." />
        @endforelse
    </div>
</div>
