<?php

namespace App\Livewire\Crm\Pipeline;

use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Opportunity;
use App\Repositories\Crm\CrmOpportunityRepository;
use App\Support\CrmOpportunityDialPhone;
use Livewire\Component;

class LogCall extends Component
{
    use ManagesCrmActivities;

    public int $opportunityId;

    public string $pipeline_id = '';

    public string $search = '';

    protected $queryString = [
        'pipeline_id' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount(int $opportunityId, CrmOpportunityRepository $opportunities): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $opportunity = $opportunities->findAccessible($opportunityId, $user);
        abort_unless($opportunity, 404);

        $opportunity->load(['party:id,name', 'stage:id,name']);

        $this->opportunityId = $opportunity->id;
        $this->resetActivityForm();
        $this->activity_party_id = $opportunity->party_id;
        $this->activity_activitable_type = Opportunity::class;
        $this->activity_activitable_id = $opportunity->id;
        $this->activity_type = 'call';
        $this->activity_status = 'completed';

        $customerName = $opportunity->party?->name ?? $opportunity->title;
        $this->activity_subject = 'تماس با '.$customerName;
    }

    public function cancel(): void
    {
        $this->redirectToPipeline();
    }

    protected function afterActivitySaved(): void
    {
        $this->redirectToPipeline();
    }

    public function render(CrmOpportunityRepository $opportunities)
    {
        $user = auth()->user();
        $opportunity = $opportunities->findAccessible($this->opportunityId, $user);
        abort_unless($opportunity, 404);

        $opportunity->load(['party:id,name,mobile,phone', 'contact:id,mobile,phone,first_name,last_name', 'stage:id,name']);

        $dialPhone = CrmOpportunityDialPhone::resolve($opportunity);
        $typeOptions = CrmModel::ACTIVITY_TYPES;

        return view('livewire.crm.pipeline.log-call', compact('opportunity', 'dialPhone', 'typeOptions'));
    }

    private function redirectToPipeline(): void
    {
        $params = array_filter([
            'pipeline_id' => $this->pipeline_id !== '' ? $this->pipeline_id : null,
            'search' => $this->search !== '' ? $this->search : null,
        ]);

        $this->redirect(route('crm.pipeline.index', $params), navigate: true);
    }
}
