<?php

namespace App\Policies;

use App\Support\FinancialReportContext;
use App\Models\User;

class FinancialReportPolicy
{
    public function view(User $user, FinancialReportContext $context): bool
    {
        return $user->hasPermission('financial.reports.view')
            || $user->hasPermission('reports.view');
    }

    public function export(User $user, FinancialReportContext $context): bool
    {
        return $this->view($user, $context)
            && (
                $user->hasPermission('financial.reports.export')
                || $user->hasPermission('financial.reports.view')
            );
    }
}
