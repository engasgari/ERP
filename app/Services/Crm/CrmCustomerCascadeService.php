<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Task;
use App\Models\Party;
use App\Models\User;

class CrmCustomerCascadeService
{
    public function __construct(
        private readonly CrmOpportunityService $opportunities,
        private readonly CrmAuditService $audit,
    ) {}

    public function onCustomerDeactivated(Party $party, User $actor): void
    {
        $this->deletePartyTasks($party);
        $this->opportunities->closeOpenForParty($party, $actor, 'غیرفعال‌سازی مشتری');
        $this->deletePartyLeads($party, $actor);
        $this->deactivatePartyContacts($party, $actor);
    }

    private function deletePartyTasks(Party $party): void
    {
        $opportunityIds = Opportunity::query()
            ->where('party_id', $party->id)
            ->pluck('id');

        $leadIds = Lead::withTrashed()
            ->where(function ($query) use ($party) {
                $query->where('party_id', $party->id)
                    ->orWhere('converted_party_id', $party->id);
            })
            ->pluck('id');

        Task::query()->where('party_id', $party->id)->delete();

        if ($opportunityIds->isNotEmpty()) {
            Task::query()
                ->where('taskable_type', Opportunity::class)
                ->whereIn('taskable_id', $opportunityIds)
                ->delete();
        }

        if ($leadIds->isNotEmpty()) {
            Task::query()
                ->where('taskable_type', Lead::class)
                ->whereIn('taskable_id', $leadIds)
                ->delete();
        }
    }

    private function deletePartyLeads(Party $party, User $actor): void
    {
        Lead::query()
            ->where(function ($query) use ($party) {
                $query->where('party_id', $party->id)
                    ->orWhere('converted_party_id', $party->id);
            })
            ->each(function (Lead $lead) use ($actor, $party) {
                $this->audit->log($lead, 'removed_on_customer_deactivation', null, null, $party->id);
                $lead->delete();
            });
    }

    private function deactivatePartyContacts(Party $party, User $actor): void
    {
        Contact::query()
            ->where('party_id', $party->id)
            ->where('is_active', true)
            ->each(function (Contact $contact) use ($actor) {
                $contact->update([
                    'status' => 'inactive',
                    'is_active' => false,
                    'updated_by' => $actor->id,
                ]);

                $this->audit->log($contact, 'deactivated', null, ['status' => 'inactive'], $contact->party_id);
            });
    }
}
