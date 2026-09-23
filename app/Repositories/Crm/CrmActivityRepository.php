<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Activity;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmActivityRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $user)
            ->with(['party', 'assignedUser'])
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('completed_at')
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function forCalendar(User $user, string $from, string $to): Collection
    {
        return $this->baseQuery([], $user)
            ->with(['party', 'assignedUser'])
            ->whereBetween('due_at', [$from, $to])
            ->orderBy('due_at')
            ->get();
    }

    public function baseQuery(array $filters, User $user): Builder
    {
        $query = Activity::query();
        $this->scope->applyOwnerScope($query, $user);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }

        return $query;
    }
}
