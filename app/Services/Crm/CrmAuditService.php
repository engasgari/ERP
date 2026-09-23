<?php

namespace App\Services\Crm;

use App\Models\Crm\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class CrmAuditService
{
    public function log(Model $model, string $event, ?array $old = null, ?array $new = null, ?int $partyId = null): Audit
    {
        return Audit::create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'party_id' => $partyId ?? $this->resolvePartyId($model),
            'event' => $event,
            'user_id' => auth()->id(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
        ]);
    }

    private function resolvePartyId(Model $model): ?int
    {
        if (isset($model->party_id)) {
            return (int) $model->party_id;
        }

        if ($model instanceof \App\Models\Party) {
            return (int) $model->id;
        }

        return null;
    }
}
