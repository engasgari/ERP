<?php

namespace App\Policies;

use App\Models\User;

class OrganizationUnitPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('hr.view'); }
    public function view(User $user): bool { return $user->hasPermission('hr.view'); }
    public function create(User $user): bool { return $user->hasPermission('hr.manage'); }
    public function update(User $user): bool { return $user->hasPermission('hr.manage'); }
    public function delete(User $user): bool { return $user->hasPermission('hr.manage'); }
}
