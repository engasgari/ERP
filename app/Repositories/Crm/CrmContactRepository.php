<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Contact;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CrmContactRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $user)
            ->with(['party', 'assignedUser'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id, User $user): ?Contact
    {
        $contact = Contact::query()->with(['party', 'assignedUser'])->find($id);

        if (! $contact || ! $this->canAccess($contact, $user)) {
            return null;
        }

        return $contact;
    }

    public function baseQuery(array $filters, User $user): Builder
    {
        $query = Contact::query();
        $this->scope->applyOwnerScope($query, $user);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('is_active', true);
        }

        return $query;
    }

    private function canAccess(Contact $contact, User $user): bool
    {
        if ($this->scope->canViewAll($user)) {
            return true;
        }

        return in_array($user->id, [$contact->assigned_user_id, $contact->created_by], true);
    }
}
