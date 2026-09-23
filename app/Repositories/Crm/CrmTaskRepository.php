<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Task;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmTaskRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $user)
            ->with(['party', 'assignedUser'])
            ->orderByRaw('COALESCE(completed_at, created_at) DESC')
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
        $query = Task::query();
        $this->scope->applyOwnerScope($query, $user);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }

        return $query;
    }
}
