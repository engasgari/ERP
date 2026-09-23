<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Opportunity;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmOpportunityRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function findAccessible(int $id, User $user): ?Opportunity
    {
        return $this->baseQuery([], $user)->whereKey($id)->first();
    }

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $user)
            ->with(['party', 'stage', 'assignedUser', 'pipeline'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function kanbanCollection(array $filters, User $user): Collection
    {
        return $this->baseQuery($filters, $user)
            ->with(['party', 'assignedUser', 'stage', 'invoice'])
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['pending', 'in_progress'])])
            ->where('status', 'open')
            ->orderBy('expected_close_date')
            ->get()
            ->groupBy(fn (Opportunity $opportunity) => (int) $opportunity->stage_id);
    }

    public function baseQuery(array $filters, User $user): Builder
    {
        $query = Opportunity::query();
        $this->scope->applyOwnerScope($query, $user);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($party) use ($search) {
                        $party->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['pipeline_id'])) {
            $query->where('pipeline_id', $filters['pipeline_id']);
        }

        return $query;
    }
}
