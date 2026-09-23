<?php

namespace App\Livewire\Crm\Pipeline;

use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Livewire\Crm\Concerns\ManagesCrmAttachments;
use App\Livewire\Crm\Concerns\ManagesCrmOpportunities;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\Task;
use App\Repositories\Crm\CrmOpportunityRepository;
use App\Services\Crm\CrmOpportunityService;
use App\Services\Crm\CrmTaskService;
use App\Services\Crm\CrmTimelineService;
use Livewire\Component;

class Kanban extends Component
{
    use ManagesCrmActivities;
    use ManagesCrmAttachments;
    use ManagesCrmOpportunities;

    public string $pipeline_id = '';

    public string $search = '';

    public ?int $activityOpportunityId = null;

    public bool $showTimelineModal = false;

    public ?int $timelineOpportunityId = null;

    protected $queryString = [
        'pipeline_id' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $default = Pipeline::query()->where('is_default', true)->first();

        if ($this->pipeline_id === '' || ! Pipeline::query()->where('is_active', true)->whereKey((int) $this->pipeline_id)->exists()) {
            $this->pipeline_id = $default ? (string) $default->id : '';
        }
    }

    public ?int $draggingOpportunityId = null;

    public function startDrag(int $opportunityId): void
    {
        $this->draggingOpportunityId = $opportunityId;
    }

    public function dropOnStage(int $stageId, CrmOpportunityService $opportunities): void
    {
        if (! $this->draggingOpportunityId) {
            return;
        }

        $this->moveStage($this->draggingOpportunityId, $stageId, $opportunities);
        $this->draggingOpportunityId = null;
    }

    public function completeNextTask(int $opportunityId, CrmTaskService $tasks): void
    {
        $task = Task::query()
            ->where('taskable_type', Opportunity::class)
            ->where('taskable_id', $opportunityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('due_at')
            ->first();

        if (! $task) {
            session()->flash('error', 'وظیفه بازی برای این فرصت وجود ندارد.');

            return;
        }

        $tasks->complete($task, auth()->user());
        session()->flash('success', 'وظیفه «'.$task->title.'» انجام شد.');
    }

    public function moveStage(int $opportunityId, int $stageId, CrmOpportunityService $opportunities): void
    {
        $opportunity = Opportunity::query()->findOrFail($opportunityId);
        $stage = PipelineStage::query()->findOrFail($stageId);

        try {
            $opportunities->changeStage($opportunity, $stage, auth()->user());
            $taskCount = Task::query()
                ->where('taskable_type', Opportunity::class)
                ->where('taskable_id', $opportunityId)
                ->whereIn('status', ['pending', 'in_progress'])
                ->count();

            session()->flash('success', 'مرحله به «'.$stage->name.'» تغییر کرد. وظایف باز این فرصت: '.$taskCount);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openOpportunityActivity(int $opportunityId): void
    {
        $opp = Opportunity::query()->findOrFail($opportunityId);
        $this->activityOpportunityId = $opportunityId;

        $this->openActivityModal(
            partyId: $opp->party_id,
            activitableType: Opportunity::class,
            activitableId: $opp->id,
        );
    }

    public function openOpportunityTimeline(int $opportunityId): void
    {
        Opportunity::query()->findOrFail($opportunityId);

        $this->timelineOpportunityId = $opportunityId;
        $this->showTimelineModal = true;
    }

    public function closeOpportunityTimeline(): void
    {
        $this->showTimelineModal = false;
        $this->timelineOpportunityId = null;
    }

    public function render(CrmOpportunityRepository $opportunities, CrmTimelineService $timeline)
    {
        $pipelines = Pipeline::query()->where('is_active', true)->with('stages')->orderBy('sort_order')->get();

        $pipeline = $this->pipeline_id !== ''
            ? $pipelines->firstWhere('id', (int) $this->pipeline_id)
            : $pipelines->firstWhere('is_default', true);

        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'pipeline_id' => $pipeline?->id,
        ]);

        $grouped = $pipeline
            ? $opportunities->kanbanCollection($filters, auth()->user())
            : collect();

        $stages = $pipeline?->stages ?? collect();
        $typeOptions = CrmModel::ACTIVITY_TYPES;

        $opportunityTimelineOverview = null;

        if ($this->timelineOpportunityId) {
            $timelineOpportunity = Opportunity::query()
                ->with(['party:id,name', 'assignedUser:id,name', 'stage:id,name', 'pipeline:id,name', 'invoice:id,number'])
                ->find($this->timelineOpportunityId);

            if ($timelineOpportunity) {
                $opportunityTimelineOverview = $timeline->opportunityOverview($timelineOpportunity);
            } else {
                $this->timelineOpportunityId = null;
                $this->showTimelineModal = false;
            }
        }

        return view('livewire.crm.pipeline.kanban', array_merge(
            $this->opportunityFormOptions(),
            compact('pipelines', 'pipeline', 'stages', 'grouped', 'typeOptions', 'opportunityTimelineOverview'),
        ));
    }
}
