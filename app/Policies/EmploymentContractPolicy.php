<?php

namespace App\Policies;

use App\Models\User;

class EmploymentContractPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('contracts.view'); }
    public function view(User $user): bool { return $user->hasPermission('contracts.view'); }
    public function create(User $user): bool { return $user->hasPermission('contracts.manage'); }
    public function update(User $user): bool { return $user->hasPermission('contracts.manage'); }
    public function delete(User $user): bool { return $user->hasPermission('contracts.manage'); }
}
