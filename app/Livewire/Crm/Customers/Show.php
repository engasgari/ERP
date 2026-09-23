<?php

namespace App\Livewire\Crm\Customers;

use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Livewire\Crm\Concerns\ManagesCrmOpportunities;
use App\Livewire\Crm\Concerns\ManagesCrmTasks;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Opportunity;
use App\Models\Party;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\Crm\CrmCustomerService;
use App\Services\Crm\CrmTimelineService;
use Livewire\Component;

class Show extends Component
{
    use ManagesCrmActivities;
    use ManagesCrmOpportunities;
    use ManagesCrmTasks;

    public int $partyId;

    public string $activeTab = 'overview';

    public ?int $timelineOpportunityFilter = null;

    public ?int $showingOpportunityTimelineId = null;

    public function mount(int $partyId): void
    {
        $this->partyId = $partyId;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openCustomerActivity(): void
    {
        $this->openActivityModal(partyId: $this->partyId);
    }

    public function openCustomerTask(): void
    {
        $this->openTaskModal(partyId: $this->partyId);
    }

    public function openOpportunityActivity(int $opportunityId): void
    {
        $opp = Opportunity::query()->findOrFail($opportunityId);

        $this->openActivityModal(
            partyId: $this->partyId,
            activitableType: Opportunity::class,
            activitableId: $opp->id,
        );
    }

    public function openOpportunityTimeline(int $opportunityId): void
    {
        $this->showingOpportunityTimelineId = $opportunityId;
    }

    public function closeOpportunityTimeline(): void
    {
        $this->showingOpportunityTimelineId = null;
    }

    public function updatedTimelineOpportunityFilter(mixed $value): void
    {
        $this->timelineOpportunityFilter = ($value === '' || $value === null) ? null : (int) $value;
    }

    public function deactivateCustomer(CrmCustomerService $customers): void
    {
        $party = Party::query()->customers()->findOrFail($this->partyId);
        $customers->deactivate($party, auth()->user());
        session()->flash('success', 'مشتری غیرفعال شد. سرنخ‌ها و وظایف مرتبط حذف و فرصت‌های باز بسته شدند.');
    }

    public function reactivateCustomer(CrmCustomerService $customers): void
    {
        $party = Party::query()->customers()->findOrFail($this->partyId);
        $customers->reactivate($party, auth()->user());
        session()->flash('success', 'مشتری فعال شد.');
    }

    public function render(CrmCustomerRepository $customers, CrmTimelineService $timeline)
    {
        $party = $customers->findForShow($this->partyId, auth()->user());

        abort_if(! $party, 404);

        $timelineTree = $this->activeTab === 'timeline'
            ? $timeline->forPartyTree($party, $this->timelineOpportunityFilter)
            : collect();

        $timelineEntries = $this->activeTab === 'timeline'
            ? $timeline->flattenTreeEntries($timelineTree)
            : collect();

        $timelineSummary = $this->activeTab === 'timeline'
            ? $timeline->partySummary($timelineEntries)
            : ['calls' => 0, 'sms' => 0, 'stages' => 0, 'activities' => 0];

        $timelineOpportunityOptions = $timeline->opportunityFilterOptions($party);

        $opportunityTimelineOverview = null;

        if ($this->showingOpportunityTimelineId) {
            $timelineOpportunity = $party->crmOpportunities
                ->firstWhere('id', $this->showingOpportunityTimelineId);

            if ($timelineOpportunity) {
                $opportunityTimelineOverview = $timeline->opportunityOverview($timelineOpportunity);
            } else {
                $this->showingOpportunityTimelineId = null;
            }
        }

        $statusOptions = CrmModel::CUSTOMER_STATUSES;
        $typeOptions = CrmModel::ACTIVITY_TYPES;
        $priorityOptions = CrmModel::PRIORITIES_TASK;

        return view('livewire.crm.customers.show', array_merge(
            compact(
                'party',
                'timelineEntries',
                'timelineTree',
                'timelineSummary',
                'timelineOpportunityOptions',
                'opportunityTimelineOverview',
                'statusOptions',
                'typeOptions',
                'priorityOptions',
            ),
            $this->opportunityFormOptions(),
            $this->taskFormOptions(),
        ));
    }
}
