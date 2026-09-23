<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\User;

class CrmContactService
{
    public function __construct(
        private readonly CrmAuditService $audit,
        private readonly CrmDuplicateGuardService $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Contact
    {
        $this->duplicates->assertUniqueContact($data);

        $contact = Contact::create([
            'party_id' => $data['party_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
            'is_active' => true,
            'assigned_user_id' => $data['assigned_user_id'] ?? $actor->id,
            'created_by' => $actor->id,
        ]);

        $this->audit->log($contact, 'created', null, $contact->only(['first_name', 'last_name']), $contact->party_id);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data, User $actor): Contact
    {
        $this->duplicates->assertUniqueContact($data, $contact->id);

        $contact->update(collect($data)->only([
            'party_id', 'first_name', 'last_name', 'job_title', 'department',
            'mobile', 'phone', 'email', 'is_primary', 'description', 'status', 'assigned_user_id',
        ])->merge(['updated_by' => $actor->id])->all());

        $this->audit->log($contact, 'updated', null, $contact->only(['first_name', 'last_name']), $contact->party_id);

        return $contact->fresh(['party', 'assignedUser']);
    }

    public function deactivate(Contact $contact, User $actor): Contact
    {
        $contact->update([
            'status' => 'inactive',
            'is_active' => false,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log($contact, 'deactivated', null, $contact->only(['first_name', 'last_name', 'status']), $contact->party_id);

        return $contact->fresh(['party', 'assignedUser']);
    }

    public function reactivate(Contact $contact, User $actor): Contact
    {
        $contact->update([
            'status' => 'active',
            'is_active' => true,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log($contact, 'reactivated', null, $contact->only(['first_name', 'last_name', 'status']), $contact->party_id);

        return $contact->fresh(['party', 'assignedUser']);
    }

    public function remove(Contact $contact, User $actor): CrmRemovalResult
    {
        $this->deactivate($contact, $actor);

        return new CrmRemovalResult(CrmRemovalResult::ACTION_DEACTIVATED);
    }

    /** @deprecated Use remove() — contacts are never hard-deleted */
    public function delete(Contact $contact, User $actor): void
    {
        $this->remove($contact, $actor);
    }
}
