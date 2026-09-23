<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CrmLeadRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $user)
            ->with(['source', 'assignedUser'])
            ->withCount('attachments')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForShow(int $id, User $user): ?Lead
    {
        $lead = Lead::query()
            ->with([
                'source',
                'assignedUser',
                'party',
                'convertedParty',
                'convertedOpportunity',
                'attachments' => fn ($q) => $q->latest('id'),
                'activities' => fn ($q) => $q->latest('id')->limit(20),
            ])
            ->find($id);

        if (! $lead) {
            return null;
        }

        if ($this->scope->canViewAll($user)) {
            return $lead;
        }

        if (! in_array($user->id, [$lead->assigned_user_id, $lead->created_by], true)) {
            return null;
        }

        return $lead;
    }

    public function baseQuery(array $filters, User $user): Builder
    {
        $query = Lead::query();
        $this->scope->applyOwnerScope($query, $user);

        $query->where('status', '!=', 'converted');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['source_id'])) {
            $query->where('source_id', $filters['source_id']);
        }

        return $query;
    }
}
