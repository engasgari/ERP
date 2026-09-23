<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Task;
use App\Models\Party;

class CrmRelationGuardService
{
    public function contactHasRelations(Contact $contact): bool
    {
        return Lead::query()->where('contact_id', $contact->id)->exists()
            || Lead::query()->where('converted_contact_id', $contact->id)->exists()
            || Opportunity::query()->where('contact_id', $contact->id)->exists()
            || Activity::query()
                ->where('activitable_type', Contact::class)
                ->where('activitable_id', $contact->id)
                ->exists()
            || Task::query()
                ->where('taskable_type', Contact::class)
                ->where('taskable_id', $contact->id)
                ->exists();
    }

    public function customerHasRelations(Party $party): bool
    {
        return $party->crmActivities()->exists()
            || $party->crmOpportunities()->exists()
            || $party->crmLeads()->exists()
            || Lead::query()->where('converted_party_id', $party->id)->exists()
            || $party->crmTasks()->exists()
            || $party->crmNotes()->exists()
            || $party->crmContacts()->exists()
            || $party->invoices()->exists()
            || $party->projects()->exists()
            || $party->accountingLines()->exists();
    }
}
