<?php

namespace App\Policies;

use App\Models\TreasuryTransaction;
use App\Models\User;

class TreasuryTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('treasury.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('treasury.manage');
    }

    public function view(User $user, TreasuryTransaction $transaction): bool
    {
        return $user->hasPermission('treasury.manage');
    }
}
