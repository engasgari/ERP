<?php

namespace App\Services\Crm;

use App\Models\Crm\CustomerProfile;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Pipeline;
use App\Models\Party;
use App\Models\User;
use App\Repositories\Crm\CrmPartyMatchRepository;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    public function __construct(
        private readonly CrmPartyMatchRepository $partyMatch,
        private readonly PartyCreationService $partyCreation,
        private readonly CrmCustomerService $customerService,
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmContactService $contactService,
        private readonly CrmDuplicateGuardService $duplicates,
        private readonly CrmAuditService $audit,
        private readonly NumberingService $numbering,
    ) {}

    /**
     * @param  array{create_contact?:bool,create_opportunity?:bool,opportunity_title?:string,opportunity_amount?:float}  $options
     */
    public function convert(Lead $lead, User $actor, array $options = []): Lead
    {
        if ($lead->status === 'converted' || $lead->trashed()) {
            throw new \RuntimeException('این سرنخ قبلاً تبدیل شده است.');
        }

        return DB::transaction(function () use ($lead, $actor, $options) {
            $party = $this->partyMatch->findExisting(
                $lead->mobile,
                $lead->email,
                null,
                $lead->company_name ?: $lead->display_name,
            );

            if (! $party) {
                $party = $this->partyCreation->createCustomer([
                    'kind' => $lead->company_name ? 'company' : 'person',
                    'name' => $lead->company_name ?: trim("{$lead->first_name} {$lead->last_name}") ?: $lead->title,
                    'mobile' => $lead->mobile,
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                ], $actor);
            }

            $profile = $this->customerService->ensureProfile($party, $actor, [
                'assigned_user_id' => $lead->assigned_user_id,
                'source_id' => $lead->source_id,
            ]);

            $contact = null;
            if ($options['create_contact'] ?? true) {
                $contactData = [
                    'party_id' => $party->id,
                    'first_name' => $lead->first_name ?: $party->name,
                    'last_name' => $lead->last_name ?: '',
                    'mobile' => $lead->mobile,
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                    'is_primary' => ! $party->crmContacts()->exists(),
                    'assigned_user_id' => $lead->assigned_user_id,
                ];

                $existingContact = $this->duplicates->findDuplicateContact($contactData);

                if ($existingContact) {
                    $contact = $existingContact->is_active
                        ? $existingContact
                        : $this->contactService->reactivate($existingContact, $actor);
                } else {
                    $contact = $this->contactService->create($contactData, $actor);
                }
            }

            $opportunity = null;
            if ($options['create_opportunity'] ?? false) {
                $pipeline = Pipeline::query()->where('is_default', true)->firstOrFail();
                $stage = $pipeline->stages()->where('is_won', false)->where('is_lost', false)->orderBy('sort_order')->firstOrFail();

                $opportunity = $this->opportunityService->create([
                    'title' => $options['opportunity_title'] ?? $lead->title,
                    'party_id' => $party->id,
                    'contact_id' => $contact?->id,
                    'lead_id' => $lead->id,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'assigned_user_id' => $lead->assigned_user_id,
                    'amount' => $options['opportunity_amount'] ?? ($lead->estimated_value ?? 0),
                    'probability' => $stage->probability,
                    'expected_close_date' => $lead->expected_close_date,
                    'source_id' => $lead->source_id,
                    'description' => $lead->description,
                ], $actor);
            }

            $lead->update([
                'status' => 'converted',
                'converted_party_id' => $party->id,
                'converted_contact_id' => $contact?->id,
                'converted_opportunity_id' => $opportunity?->id,
                'converted_at' => now(),
                'converted_by' => $actor->id,
                'party_id' => $party->id,
            ]);

            $this->audit->log($lead, 'converted', null, [
                'party_id' => $party->id,
                'contact_id' => $contact?->id,
                'opportunity_id' => $opportunity?->id,
            ], $party->id);

            $profile->update(['last_activity_at' => now()]);

            $converted = $lead->fresh(['convertedParty', 'convertedOpportunity']);
            $lead->delete();

            return $converted;
        });
    }
}
