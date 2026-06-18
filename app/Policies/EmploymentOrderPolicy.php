<?php

namespace App\Policies;

use App\Models\User;

class EmploymentOrderPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('employment-orders.view'); }
    public function view(User $user): bool { return $user->hasPermission('employment-orders.view'); }
    public function create(User $user): bool { return $user->hasPermission('employment-orders.manage'); }
    public function update(User $user): bool { return $user->hasPermission('employment-orders.manage'); }
    public function delete(User $user): bool { return $user->hasPermission('employment-orders.manage'); }
    public function approve(User $user): bool { return $user->hasPermission('employment-orders.approve'); }
}
