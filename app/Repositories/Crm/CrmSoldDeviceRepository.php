<?php

namespace App\Repositories\Crm;

use App\Models\Crm\SoldDevice;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CrmSoldDeviceRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $user)
            ->with(['party', 'item', 'assignedUser'])
            ->latest('sold_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function baseQuery(array $filters, User $user): Builder
    {
        $query = SoldDevice::query()->where('is_active', true);
        $this->scope->applyOwnerScope($query, $user);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($item) => $item
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }

        if (($filters['warranty_status'] ?? '') === 'active') {
            $query->whereDate('warranty_ends_at', '>=', now()->toDateString());
        } elseif (($filters['warranty_status'] ?? '') === 'expired') {
            $query->whereDate('warranty_ends_at', '<', now()->toDateString());
        }

        return $query;
    }
}
