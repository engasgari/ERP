<?php

namespace App\Livewire\Crm\Leads;

use App\Livewire\Crm\Concerns\ManagesCrmActivities;
use App\Livewire\Crm\Concerns\ManagesCrmAttachments;
use App\Models\Crm\CrmModel;
use App\Models\Crm\Lead;
use App\Repositories\Crm\CrmLeadRepository;
use App\Services\Crm\CrmLeadService;
use Livewire\Component;

class Show extends Component
{
    use ManagesCrmActivities;
    use ManagesCrmAttachments;

    public int $leadId;

    public function mount(int $leadId): void
    {
        $this->leadId = $leadId;
    }

    public function openLeadActivity(): void
    {
        $lead = Lead::query()->findOrFail($this->leadId);

        $this->openActivityModal(
            partyId: $lead->party_id,
            activitableType: Lead::class,
            activitableId: $lead->id,
        );
    }

    protected function afterActivitySaved(): void
    {
        if ($this->activity_type !== 'call' || $this->activity_status !== 'completed') {
            return;
        }

        $lead = Lead::query()->find($this->leadId);

        if ($lead && $lead->status === 'new') {
            app(CrmLeadService::class)->update($lead, ['status' => 'contacted'], auth()->user());
        }
    }

    public function markContacted(CrmLeadService $leads): void
    {
        $lead = Lead::query()->findOrFail($this->leadId);

        if ($lead->status === 'new') {
            $leads->update($lead, ['status' => 'contacted'], auth()->user());
            session()->flash('success', 'وضعیت سرنخ به «تماس گرفته» تغییر کرد.');
        }
    }

    public function render(CrmLeadRepository $leads)
    {
        $lead = $leads->findForShow($this->leadId, auth()->user());

        abort_if(! $lead, 404);

        $typeOptions = CrmModel::ACTIVITY_TYPES;

        return view('livewire.crm.leads.show', compact('lead', 'typeOptions'));
    }
}
