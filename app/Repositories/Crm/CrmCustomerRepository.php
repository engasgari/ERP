<?php

namespace App\Repositories\Crm;

use App\Models\Party;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CrmCustomerRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        $query = Party::query()
            ->customers()
            ->with(['crmProfile.assignedUser', 'crmProfile.source', 'types']);

        if (! $this->scope->canViewAll($user)) {
            $query->whereHas('crmProfile', fn ($q) => $q->where('assigned_user_id', $user->id));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->whereHas('crmProfile', fn ($q) => $q->where('status', $filters['status']));
        }

        return $query->latest('id')->paginate($perPage);
    }

    public function findForShow(int $partyId, User $user): ?Party
    {
        $party = Party::query()
            ->customers()
            ->with([
                'crmProfile.assignedUser',
                'crmContacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('first_name'),
                'crmLeads',
                'crmOpportunities.stage',
                'crmActivities' => fn ($q) => $q->with('assignedUser')->latest('id')->limit(30),
                'crmTasks' => fn ($q) => $q->with('assignedUser')->whereIn('status', ['pending', 'in_progress'])->orderBy('due_at')->limit(30),
                'invoices' => fn ($q) => $q->where('direction', 'sale')->latest('invoice_date')->limit(10),
                'projects' => fn ($q) => $q->latest('id')->limit(10),
            ])
            ->find($partyId);

        if (! $party) {
            return null;
        }

        if (! $this->scope->canViewAll($user)) {
            $ownerId = $party->crmProfile?->assigned_user_id;
            if ($ownerId && $ownerId !== $user->id) {
                return null;
            }
        }

        return $party;
    }
}
