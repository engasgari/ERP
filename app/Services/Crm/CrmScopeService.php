<?php

namespace App\Services\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CrmScopeService
{
    public function canViewAll(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('crm.records.view_all');
    }

    public function applyOwnerScope(Builder $query, User $user, string $assignedColumn = 'assigned_user_id'): Builder
    {
        if ($this->canViewAll($user)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user, $assignedColumn) {
            $builder->where($assignedColumn, $user->id)
                ->orWhere('created_by', $user->id);
        });
    }
}
