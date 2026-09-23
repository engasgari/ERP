<?php

namespace App\Services\Crm;

use App\Models\Crm\CustomerProfile;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrmCustomerService
{
    public function __construct(
        private readonly CrmAuditService $audit,
        private readonly CrmCustomerCascadeService $cascade,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function ensureProfile(Party $party, User $actor, array $attributes = []): CustomerProfile
    {
        $profile = CustomerProfile::query()->firstOrCreate(
            ['party_id' => $party->id],
            [
                'assigned_user_id' => $attributes['assigned_user_id'] ?? $actor->id,
                'status' => $attributes['status'] ?? 'active',
                'source_id' => $attributes['source_id'] ?? null,
                'created_by' => $actor->id,
            ],
        );

        if (! empty($attributes)) {
            $profile->fill(collect($attributes)->only([
                'assigned_user_id', 'status', 'industry', 'city', 'website',
                'source_id', 'customer_score', 'customer_segment', 'crm_notes',
            ])->all())->save();
        }

        return $profile;
    }

    public function assignOwner(CustomerProfile $profile, int $userId, User $actor): CustomerProfile
    {
        $old = $profile->assigned_user_id;
        $profile->update(['assigned_user_id' => $userId, 'updated_by' => $actor->id]);
        $this->audit->log($profile, 'assigned', ['assigned_user_id' => $old], ['assigned_user_id' => $userId], $profile->party_id);

        return $profile->fresh();
    }

    public function deactivate(Party $party, User $actor): CustomerProfile
    {
        return DB::transaction(function () use ($party, $actor) {
            $profile = $this->ensureProfile($party, $actor);

            $this->cascade->onCustomerDeactivated($party, $actor);

            $party->update(['is_active' => false]);
            $profile->update(['status' => 'inactive', 'updated_by' => $actor->id]);

            $this->audit->log($profile, 'deactivated', null, ['status' => 'inactive'], $party->id);

            return $profile->fresh();
        });
    }

    public function reactivate(Party $party, User $actor): CustomerProfile
    {
        $profile = $this->ensureProfile($party, $actor);

        $party->update(['is_active' => true]);
        $profile->update(['status' => 'active', 'updated_by' => $actor->id]);

        $this->audit->log($profile, 'reactivated', null, ['status' => 'active'], $party->id);

        return $profile->fresh();
    }
}
