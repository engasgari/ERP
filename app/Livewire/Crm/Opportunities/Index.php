<?php

namespace App\Livewire\Crm\Opportunities;

use App\Livewire\Core\UI\BaseListPage;
use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Livewire\Crm\Concerns\ManagesCrmOpportunities;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Pipeline;
use App\Repositories\Crm\CrmOpportunityRepository;
use App\Services\Crm\CrmTimelineService;

class Index extends BaseListPage
{
    use ManagesCrmActivities;
    use ManagesCrmOpportunities;

    public string $search = '';

    public string $status = '';

    public string $pipeline_id = '';

    public bool $showTimelineModal = false;

    public ?int $timelineOpportunityId = null;

    protected array $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'pipeline_id' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->openOpportunityCreate();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'pipeline_id']);
        $this->resetPage();
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

    public function openOpportunityActivity(int $opportunityId): void
    {
        $opp = Opportunity::query()->findOrFail($opportunityId);

        $this->openActivityModal(
            partyId: $opp->party_id,
            activitableType: Opportunity::class,
            activitableId: $opp->id,
        );
    }

    public function render(CrmOpportunityRepository $opportunities, CrmTimelineService $timeline)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'status' => $this->status !== '' ? $this->status : null,
            'pipeline_id' => $this->pipeline_id !== '' ? (int) $this->pipeline_id : null,
        ]);

        $items = $opportunities->paginate($filters, auth()->user(), $this->perPage);
        $pipelines = Pipeline::query()->where('is_active', true)->orderBy('sort_order')->get();
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

        return view('livewire.crm.opportunities.index', array_merge(
            compact('items', 'pipelines', 'typeOptions', 'opportunityTimelineOverview'),
            $this->opportunityFormOptions(),
        ));
    }
}
