<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\CustomerProfile;
use App\Models\User;

class CrmActivityService
{
    public function __construct(private readonly CrmAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Activity
    {
        $status = $data['status'] ?? 'planned';

        $activity = Activity::create([
            'type' => $data['type'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'activitable_type' => $data['activitable_type'] ?? null,
            'activitable_id' => $data['activitable_id'] ?? null,
            'party_id' => $data['party_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? $actor->id,
            'due_at' => $data['due_at'] ?? null,
            'completed_at' => $status === 'completed'
                ? ($data['completed_at'] ?? now())
                : null,
            'status' => $status,
            'created_by' => $actor->id,
        ]);

        $this->touchPartyActivity($activity->party_id);
        $this->audit->log($activity, 'created', null, $activity->only(['subject', 'type']), $activity->party_id);

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Activity $activity, array $data, User $actor): Activity
    {
        $old = $activity->only(['subject', 'type', 'status', 'description']);
        $status = $data['status'] ?? $activity->status;

        $payload = collect($data)->only([
            'type', 'subject', 'description', 'party_id', 'assigned_user_id', 'due_at', 'status', 'completed_at',
        ])->all();

        if ($status === 'completed') {
            $payload['completed_at'] = $data['completed_at']
                ?? $activity->completed_at
                ?? now();
        } else {
            $payload['completed_at'] = null;
        }

        $activity->update($payload);

        $this->touchPartyActivity($activity->party_id);
        $this->audit->log(
            $activity,
            'updated',
            $old,
            $activity->only(['subject', 'type', 'status', 'description']),
            $activity->party_id,
        );

        return $activity->fresh(['party', 'assignedUser']);
    }

    public function complete(Activity $activity, User $actor): Activity
    {
        $activity->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->touchPartyActivity($activity->party_id);
        $this->audit->log($activity, 'completed', null, ['status' => 'completed'], $activity->party_id);

        return $activity->fresh();
    }

    private function touchPartyActivity(?int $partyId): void
    {
        if (! $partyId) {
            return;
        }

        CustomerProfile::query()
            ->where('party_id', $partyId)
            ->update(['last_activity_at' => now()]);
    }
}
