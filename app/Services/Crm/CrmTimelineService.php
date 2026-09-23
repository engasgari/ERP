<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Audit;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\Task;
use App\Models\Party;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CrmTimelineService
{
    /** @var list<string> */
    private const PARTY_AUDIT_EVENTS = [
        'stage_changed',
        'converted',
        'closed',
        'assigned',
    ];

    /** @var list<string> */
    private const OPPORTUNITY_AUDIT_EVENTS = [
        'created',
        'stage_changed',
        'closed',
        'assigned',
    ];

    public function __construct(
        private readonly CrmPipelineWorkflowService $pipelineWorkflow,
    ) {}

    public function forParty(Party $party, ?int $opportunityId = null, int $limit = 50): Collection
    {
        return $this->flattenTreeEntries($this->forPartyTree($party, $opportunityId, $limit));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forPartyTree(Party $party, ?int $opportunityId = null, int $limit = 50): Collection
    {
        if ($opportunityId) {
            $opportunity = Opportunity::query()
                ->where('party_id', $party->id)
                ->find($opportunityId);

            return $opportunity
                ? collect([$this->buildOpportunityGroup($opportunity, $limit)])
                : collect();
        }

        $groups = Opportunity::query()
            ->where('party_id', $party->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Opportunity $opportunity) => $this->buildOpportunityGroup($opportunity, $limit))
            ->filter(fn (array $group) => ! empty($group['milestones']));

        $unlinkedEntries = $this->collectUnlinkedPartyEntries($party, $limit);

        if ($unlinkedEntries->isNotEmpty()) {
            $groups->push([
                'node_type' => 'opportunity_group',
                'opportunity_id' => null,
                'opportunity_label' => 'سایر فعالیت‌ها',
                'milestones' => $this->buildProcessTree($unlinkedEntries)->all(),
            ]);
        }

        return $groups->values();
    }

    /**
     * @return array{
     *     opportunity: Opportunity,
     *     timeline_entries: Collection<int, array<string, mixed>>,
     *     timeline_tree: Collection<int, array<string, mixed>>,
     *     timeline_summary: array{calls: int, sms: int, stages: int, activities: int},
     *     pending_tasks: Collection<int, Task>
     * }
     */
    public function opportunityOverview(Opportunity $opportunity, int $limit = 50): array
    {
        $opportunity->loadMissing([
            'stage',
            'assignedUser',
            'party',
            'pipeline',
            'invoice',
            'attachments' => fn ($q) => $q->latest('id'),
        ]);
        $group = $this->buildOpportunityGroup($opportunity, $limit);
        $flatEntries = $this->flattenTreeEntries(collect([$group]));

        return [
            'opportunity' => $opportunity,
            'timeline_entries' => $flatEntries,
            'timeline_tree' => collect($group['milestones']),
            'timeline_summary' => $this->partySummary($flatEntries),
            'pending_tasks' => $this->pendingTasksForOpportunity($opportunity),
        ];
    }

    /**
     * @return Collection<int, array{id: int, label: string, number: string, title: string, stage: ?string, status: string}>
     */
    public function opportunityFilterOptions(Party $party): Collection
    {
        return Opportunity::query()
            ->where('party_id', $party->id)
            ->with('stage:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'number', 'title', 'stage_id', 'status'])
            ->map(fn (Opportunity $opportunity) => [
                'id' => $opportunity->id,
                'number' => $opportunity->number,
                'title' => $opportunity->title,
                'label' => $opportunity->number.' — '.$opportunity->title,
                'stage' => $opportunity->stage?->name,
                'status' => $opportunity->status,
            ]);
    }

    public function forOpportunity(Opportunity $opportunity, int $limit = 40): Collection
    {
        return $this->flattenTreeEntries(collect([$this->buildOpportunityGroup($opportunity, $limit)]));
    }

    /**
     * @return Collection<int, Task>
     */
    public function pendingTasksForOpportunity(Opportunity $opportunity): Collection
    {
        return Task::query()
            ->where('taskable_type', Opportunity::class)
            ->where('taskable_id', $opportunity->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['assignedUser:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array{calls: int, sms: int, stages: int, activities: int}
     */
    public function partySummary(Collection $entries): array
    {
        return [
            'calls' => $entries->where('meta_type', 'call')->count(),
            'sms' => $entries->where('meta_type', 'sms')->count(),
            'stages' => $entries->where('kind', 'status')->count(),
            'activities' => $entries
                ->where('kind', 'activity')
                ->whereNotIn('meta_type', ['call', 'sms'])
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    public function flattenTreeEntries(Collection $groups): Collection
    {
        $flat = collect();

        foreach ($groups as $group) {
            foreach ($group['milestones'] ?? [] as $milestone) {
                $flat->push(collect($milestone)->except(['children', 'pending_tasks', 'stage_task_labels', 'node_type'])->all());

                foreach ($milestone['children'] ?? [] as $child) {
                    $flat->push($child);
                }

                foreach ($milestone['pending_tasks'] ?? [] as $pending) {
                    $flat->push($pending);
                }
            }
        }

        return $flat
            ->filter(fn ($entry) => ($entry['occurred_at'] ?? null) instanceof Carbon)
            ->unique('id')
            ->sortByDesc(fn ($entry) => $entry['occurred_at']->timestamp)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOpportunityGroup(Opportunity $opportunity, int $limit): array
    {
        $entries = $this->collectOpportunityEntries($opportunity, $limit * 3);
        $tasks = Task::query()
            ->where('taskable_type', Opportunity::class)
            ->where('taskable_id', $opportunity->id)
            ->with(['assignedUser:id,name'])
            ->get();

        return [
            'node_type' => 'opportunity_group',
            'opportunity_id' => $opportunity->id,
            'opportunity_label' => $this->opportunityMeta($opportunity)['opportunity_label'],
            'milestones' => $this->buildProcessTree($entries, $tasks)->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, array<string, mixed>>
     */
    private function buildProcessTree(Collection $entries, ?Collection $tasks = null): Collection
    {
        $tasks ??= collect();
        $milestones = $entries
            ->filter(fn (array $entry) => $this->isMilestone($entry))
            ->sortBy(fn (array $entry) => $entry['occurred_at']->timestamp)
            ->values();

        $childEntries = $entries
            ->filter(fn (array $entry) => ! $this->isMilestone($entry));

        $taskEntries = $tasks->map(fn (Task $task) => $this->mapTaskForTree($task));
        $allChildren = $childEntries->concat($taskEntries)->unique('id');

        if ($milestones->isEmpty()) {
            if ($allChildren->isEmpty()) {
                return collect();
            }

            return collect([[
                'node_type' => 'milestone',
                'id' => 'misc:activities',
                'kind' => 'activity',
                'badge' => 'فعالیت',
                'title' => 'فعالیت‌های ثبت‌شده',
                'summary' => null,
                'occurred_at' => $allChildren->sortByDesc(fn (array $entry) => $entry['occurred_at']->timestamp)->first()['occurred_at'],
                'user_name' => null,
                'stage_task_labels' => [],
                'pending_tasks' => $allChildren->filter(fn (array $entry) => ($entry['is_pending_task'] ?? false))->sortByDesc(fn (array $entry) => $entry['occurred_at']->timestamp)->values()->all(),
                'children' => $allChildren->reject(fn (array $entry) => ($entry['is_pending_task'] ?? false))->sortByDesc(fn (array $entry) => $entry['occurred_at']->timestamp)->values()->all(),
            ]]);
        }

        $tree = collect();
        $assignments = collect();

        foreach ($milestones as $milestone) {
            $assignments[$milestone['id']] = collect();
        }

        foreach ($allChildren as $child) {
            if (($child['timeline_anchor'] ?? null) === 'lead_created') {
                $leadMilestone = $milestones->first(
                    fn (array $milestone) => ($milestone['badge'] ?? '') === 'ایجاد سرنخ',
                );

                if ($leadMilestone) {
                    $assignments[$leadMilestone['id']]->push($child);

                    continue;
                }
            }

            $milestoneIndex = null;

            foreach ($milestones as $index => $milestone) {
                if ($child['occurred_at']->timestamp >= $milestone['occurred_at']->timestamp) {
                    $milestoneIndex = $index;
                }
            }

            if ($milestoneIndex === null) {
                continue;
            }

            $assignments[$milestones[$milestoneIndex]['id']]->push($child);
        }

        foreach ($milestones as $milestone) {
            $periodChildren = ($assignments[$milestone['id']] ?? collect())
                ->sortByDesc(fn (array $child) => $child['occurred_at']->timestamp)
                ->values();

            $tree->push(array_merge($milestone, [
                'node_type' => 'milestone',
                'stage_task_labels' => $this->stageTaskLabels($this->milestoneStageId($milestone)),
                'pending_tasks' => $periodChildren
                    ->filter(fn (array $child) => ($child['is_pending_task'] ?? false))
                    ->values()
                    ->all(),
                'children' => $periodChildren
                    ->reject(fn (array $child) => ($child['is_pending_task'] ?? false))
                    ->values()
                    ->all(),
            ]));
        }

        return $tree->sortByDesc(fn (array $node) => $node['occurred_at']->timestamp)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectOpportunityEntries(Opportunity $opportunity, int $limit): Collection
    {
        $entries = collect();
        $meta = $this->opportunityMeta($opportunity);

        $this->collectLeadPhaseEntries($opportunity, $meta, $limit)
            ->each(fn (array $entry) => $entries->push($entry));

        Audit::query()
            ->where('auditable_type', Opportunity::class)
            ->where('auditable_id', $opportunity->id)
            ->whereIn('event', self::OPPORTUNITY_AUDIT_EVENTS)
            ->with(['user:id,name', 'auditable'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(function (Audit $audit) use ($entries, $meta) {
                $entries->push($this->enrichEntry($this->mapAuditEntry($audit), $meta));
            });

        Activity::query()
            ->where('activitable_type', Opportunity::class)
            ->where('activitable_id', $opportunity->id)
            ->where('status', 'completed')
            ->with(['assignedUser:id,name'])
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->each(function (Activity $activity) use ($entries, $meta) {
                $entries->push($this->enrichEntry($this->mapActivityEntry($activity), $meta));
            });

        if ($opportunity->won_at) {
            $entries->push($this->enrichEntry(
                $this->mapOpportunityOutcomeEntry($opportunity, 'won'),
                $meta,
            ));
        }

        if ($opportunity->lost_at) {
            $entries->push($this->enrichEntry(
                $this->mapOpportunityOutcomeEntry($opportunity, 'lost'),
                $meta,
            ));
        }

        return $entries
            ->filter(fn ($entry) => $entry['occurred_at'] instanceof Carbon)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectUnlinkedPartyEntries(Party $party, int $limit): Collection
    {
        $entries = collect();
        $linkedLeadIds = $this->leadIdsLinkedToPartyOpportunities($party);

        Activity::query()
            ->where('party_id', $party->id)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('activitable_type')
                    ->orWhere('activitable_type', '!=', Opportunity::class);
            })
            ->when($linkedLeadIds->isNotEmpty(), function ($query) use ($linkedLeadIds) {
                $query->where(function ($inner) use ($linkedLeadIds) {
                    $inner->where('activitable_type', '!=', Lead::class)
                        ->orWhereNotIn('activitable_id', $linkedLeadIds);
                });
            })
            ->with(['assignedUser:id,name'])
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->each(function (Activity $activity) use ($entries) {
                $entries->push($this->enrichEntry(
                    $this->mapActivityEntry($activity),
                    $this->opportunityMeta(null),
                ));
            });

        return $entries
            ->filter(fn ($entry) => $entry['occurred_at'] instanceof Carbon)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isMilestone(array $entry): bool
    {
        if (($entry['node_type'] ?? null) === 'milestone') {
            return true;
        }

        if (($entry['kind'] ?? null) === 'activity' || ($entry['kind'] ?? null) === 'task') {
            return false;
        }

        return in_array($entry['badge'] ?? '', [
            'ایجاد سرنخ',
            'ایجاد فرصت',
            'تغییر مرحله',
            'تبدیل سرنخ',
            'بستن فرصت',
            'تغییر مسئول',
            'فرصت برنده',
            'فرصت از دست رفته',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $milestone
     */
    private function milestoneStageId(array $milestone): ?int
    {
        if (! empty($milestone['new_stage_id'])) {
            return (int) $milestone['new_stage_id'];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function stageTaskLabels(?int $stageId): array
    {
        if (! $stageId) {
            return [];
        }

        $stage = PipelineStage::query()->find($stageId);

        if (! $stage) {
            return [];
        }

        return collect($this->pipelineWorkflow->taskTemplatesForStage($stage))
            ->pluck('title')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTaskForTree(Task $task): array
    {
        if ($task->status === 'completed') {
            return $this->mapCompletedTaskEntry($task);
        }

        return [
            'id' => 'task-pending:'.$task->id,
            'source' => 'task',
            'kind' => 'task',
            'badge' => 'وظیفه باز',
            'title' => $task->title,
            'summary' => $task->description,
            'occurred_at' => $task->created_at ?? $task->updated_at,
            'user_name' => $task->assignedUser?->name,
            'is_pending_task' => true,
            'task_status' => $task->status,
            'due_at' => $task->due_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function enrichEntry(array $entry, array $meta): array
    {
        $entry['kind'] ??= 'other';
        $entry['badge'] ??= 'رویداد';

        return array_merge($entry, $meta);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAuditEntry(Audit $audit): array
    {
        $stageMeta = [
            'new_stage_id' => isset($audit->new_values['stage_id']) ? (int) $audit->new_values['stage_id'] : null,
            'old_stage_id' => isset($audit->old_values['stage_id']) ? (int) $audit->old_values['stage_id'] : null,
        ];

        if ($audit->event === 'created' && $audit->auditable instanceof Opportunity) {
            $summaryParts = [
                'شماره '.$audit->auditable->number,
                'مبلغ '.formatMoney((float) $audit->auditable->amount),
            ];
            if (filled($audit->auditable->description)) {
                $summaryParts[] = $audit->auditable->description;
            }

            return array_merge([
                'id' => 'audit:'.$audit->id,
                'source' => 'audit',
                'kind' => 'opportunity',
                'badge' => 'ایجاد فرصت',
                'title' => 'فرصت «'.$audit->auditable->title.'» ایجاد شد',
                'summary' => implode(' — ', $summaryParts),
                'occurred_at' => $audit->created_at,
                'user_name' => $audit->user?->name,
            ], $stageMeta);
        }

        return array_merge([
            'id' => 'audit:'.$audit->id,
            'source' => 'audit',
            'kind' => $this->auditKind($audit),
            'badge' => $this->auditBadge($audit),
            'title' => $this->auditTitle($audit),
            'summary' => $this->auditSummary($audit),
            'occurred_at' => $audit->created_at,
            'user_name' => $audit->user?->name,
        ], $stageMeta);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapActivityEntry(Activity $activity): array
    {
        return [
            'id' => 'activity:'.$activity->id,
            'source' => 'activity',
            'kind' => 'activity',
            'badge' => $this->activityBadge($activity),
            'title' => $this->activityTitle($activity),
            'summary' => $activity->description,
            'occurred_at' => $activity->completed_at ?? $activity->updated_at,
            'user_name' => $activity->assignedUser?->name,
            'meta_type' => $activity->type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCompletedTaskEntry(Task $task): array
    {
        return [
            'id' => 'task:'.$task->id,
            'source' => 'task',
            'kind' => 'task',
            'badge' => 'وظیفه انجام‌شده',
            'title' => $task->title,
            'summary' => $task->description,
            'occurred_at' => $task->completed_at ?? $task->updated_at,
            'user_name' => $task->assignedUser?->name,
            'is_pending_task' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOpportunityOutcomeEntry(Opportunity $opp, string $type): array
    {
        if ($type === 'won') {
            return [
                'id' => 'opp-won:'.$opp->id,
                'source' => 'opportunity',
                'kind' => 'opportunity',
                'badge' => 'فرصت برنده',
                'title' => 'فرصت «'.$opp->title.'» برنده شد',
                'summary' => formatMoney((float) $opp->amount),
                'occurred_at' => $opp->won_at,
                'user_name' => $opp->assignedUser?->name,
            ];
        }

        return [
            'id' => 'opp-lost:'.$opp->id,
            'source' => 'opportunity',
            'kind' => 'opportunity',
            'badge' => 'فرصت از دست رفته',
            'title' => 'فرصت «'.$opp->title.'» از دست رفت',
            'summary' => $opp->lost_reason,
            'occurred_at' => $opp->lost_at,
            'user_name' => $opp->assignedUser?->name,
        ];
    }

    /**
     * @return array{
     *     opportunity_id: ?int,
     *     opportunity_number: ?string,
     *     opportunity_title: ?string,
     *     opportunity_label: ?string
     * }
     */
    private function opportunityMeta(?Opportunity $opportunity): array
    {
        if (! $opportunity) {
            return [
                'opportunity_id' => null,
                'opportunity_number' => null,
                'opportunity_title' => null,
                'opportunity_label' => null,
            ];
        }

        return [
            'opportunity_id' => $opportunity->id,
            'opportunity_number' => $opportunity->number,
            'opportunity_title' => $opportunity->title,
            'opportunity_label' => $opportunity->number.' — '.$opportunity->title,
        ];
    }

    private function auditKind(Audit $audit): string
    {
        if ($audit->event === 'created' && $audit->auditable_type === Opportunity::class) {
            return 'opportunity';
        }

        return in_array($audit->event, ['stage_changed', 'closed', 'converted'], true) ? 'status' : 'audit';
    }

    private function auditBadge(Audit $audit): string
    {
        return match ($audit->event) {
            'stage_changed' => 'تغییر مرحله',
            'converted' => 'تبدیل سرنخ',
            'closed' => 'بستن فرصت',
            'assigned' => 'تغییر مسئول',
            'created' => 'ایجاد فرصت',
            default => 'رویداد',
        };
    }

    private function activityBadge(Activity $activity): string
    {
        return match ($activity->type) {
            'call' => 'تماس',
            'meeting' => 'جلسه',
            'email' => 'ایمیل',
            'sms' => 'پیامک',
            'visit' => 'بازدید',
            'follow_up' => 'پیگیری',
            default => $activity->type_label,
        };
    }

    private function activityTitle(Activity $activity): string
    {
        return $this->activityBadge($activity).' انجام شد: '.$activity->subject;
    }

    private function auditSummary(Audit $audit): ?string
    {
        if ($audit->event === 'stage_changed') {
            $oldStageId = $audit->old_values['stage_id'] ?? null;
            $newStageId = $audit->new_values['stage_id'] ?? null;

            if (! $oldStageId && ! $newStageId) {
                return null;
            }

            $stageNames = PipelineStage::query()
                ->whereIn('id', array_filter([$oldStageId, $newStageId]))
                ->pluck('name', 'id');

            $from = $oldStageId ? ($stageNames[$oldStageId] ?? '—') : '—';
            $to = $newStageId ? ($stageNames[$newStageId] ?? '—') : '—';

            return 'از «'.$from.'» به «'.$to.'»';
        }

        if ($audit->event === 'converted') {
            return 'سرنخ به مشتری/فرصت تبدیل شد';
        }

        if ($audit->event === 'closed') {
            return $audit->new_values['status'] ?? null ? 'وضعیت: '.$audit->new_values['status'] : null;
        }

        if ($audit->event === 'assigned') {
            return 'مسئول پیگیری تغییر کرد';
        }

        return null;
    }

    private function auditTitle(Audit $audit): string
    {
        return match ($audit->event) {
            'converted' => 'تبدیل سرنخ',
            'stage_changed' => 'تغییر مرحله فرصت',
            'assigned' => 'تغییر مسئول',
            'closed' => 'بستن فرصت',
            'created' => 'ایجاد فرصت',
            default => $audit->event,
        };
    }

    /**
     * @return Collection<int, int>
     */
    private function leadIdsLinkedToPartyOpportunities(Party $party): Collection
    {
        $opportunityIds = Opportunity::query()
            ->where('party_id', $party->id)
            ->pluck('id');

        $fromLeadId = Opportunity::query()
            ->where('party_id', $party->id)
            ->whereNotNull('lead_id')
            ->pluck('lead_id');

        $fromConversion = Lead::withTrashed()
            ->whereIn('converted_opportunity_id', $opportunityIds)
            ->pluck('id');

        return $fromLeadId->merge($fromConversion)->unique()->values();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return Collection<int, array<string, mixed>>
     */
    private function collectLeadPhaseEntries(Opportunity $opportunity, array $meta, int $limit): Collection
    {
        $lead = $this->resolveLeadForOpportunity($opportunity);

        if (! $lead) {
            return collect();
        }

        $lead->loadMissing(['assignedUser:id,name']);

        $entries = collect();
        $entries->push($this->enrichEntry($this->mapLeadCreatedEntry($lead), $meta));

        Activity::query()
            ->where('activitable_type', Lead::class)
            ->where('activitable_id', $lead->id)
            ->where('status', 'completed')
            ->with(['assignedUser:id,name'])
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->each(function (Activity $activity) use ($entries, $meta) {
                $entry = $this->mapActivityEntry($activity);
                $entry['timeline_anchor'] = 'lead_created';
                $entries->push($this->enrichEntry($entry, $meta));
            });

        Audit::query()
            ->where('auditable_type', Lead::class)
            ->where('auditable_id', $lead->id)
            ->where('event', 'converted')
            ->with(['user:id,name'])
            ->latest('created_at')
            ->limit(1)
            ->get()
            ->each(function (Audit $audit) use ($entries, $meta, $lead) {
                $entries->push($this->enrichEntry($this->mapLeadConvertedAudit($audit, $lead), $meta));
            });

        return $entries;
    }

    private function resolveLeadForOpportunity(Opportunity $opportunity): ?Lead
    {
        if ($opportunity->lead_id) {
            return Lead::withTrashed()->find($opportunity->lead_id);
        }

        return Lead::withTrashed()
            ->where('converted_opportunity_id', $opportunity->id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLeadCreatedEntry(Lead $lead): array
    {
        return [
            'id' => 'lead-created:'.$lead->id,
            'source' => 'lead',
            'kind' => 'lead',
            'node_type' => 'milestone',
            'badge' => 'ایجاد سرنخ',
            'title' => 'سرنخ «'.$lead->number.' — '.$lead->title.'» ثبت شد',
            'summary' => filled($lead->description) ? $lead->description : null,
            'occurred_at' => $lead->created_at,
            'user_name' => $lead->assignedUser?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLeadConvertedAudit(Audit $audit, Lead $lead): array
    {
        return [
            'id' => 'audit:'.$audit->id,
            'source' => 'audit',
            'kind' => 'status',
            'badge' => 'تبدیل سرنخ',
            'title' => 'سرنخ «'.$lead->number.'» به مشتری/فرصت تبدیل شد',
            'summary' => 'سرنخ به مشتری/فرصت تبدیل شد',
            'occurred_at' => $lead->converted_at ?? $audit->created_at,
            'user_name' => $audit->user?->name,
        ];
    }
}
