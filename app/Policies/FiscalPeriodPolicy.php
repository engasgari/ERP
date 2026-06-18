<?php

namespace App\Policies;

use App\Models\FiscalPeriod;
use App\Models\User;

class FiscalPeriodPolicy
{
    public function close(User $user, FiscalPeriod $period): bool
    {
        return $user->hasPermission('fiscal-years.manage');
    }

    public function reopen(User $user, FiscalPeriod $period): bool
    {
        return $user->hasPermission('fiscal-periods.reopen');
    }
}
