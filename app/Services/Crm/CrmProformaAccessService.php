<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Invoice;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CrmProformaAccessService
{
    public function canAccess(User $user, Invoice $invoice): bool
    {
        if ($invoice->document_type !== 'proforma' || $invoice->direction !== 'sale') {
            return false;
        }

        if ($user->isAdmin() || $user->hasPermission('commerce.view')) {
            return true;
        }

        if (! $user->hasPermission('crm.opportunities.view')) {
            return false;
        }

        $opportunity = Opportunity::query()->where('invoice_id', $invoice->id)->first();

        if (! $opportunity) {
            return false;
        }

        if ($user->hasPermission('crm.records.view_all')) {
            return true;
        }

        return (int) $opportunity->assigned_user_id === $user->id
            || (int) $opportunity->created_by === $user->id;
    }

    public function authorize(User $user, Invoice $invoice): void
    {
        if (! $this->canAccess($user, $invoice)) {
            throw new AccessDeniedHttpException('شما به این پیش‌فاکتور دسترسی ندارید.');
        }
    }

    public function canAccessOpportunity(User $user, Opportunity $opportunity): bool
    {
        if ($user->isAdmin() || $user->hasPermission('crm.records.view_all')) {
            return true;
        }

        if (! $user->hasPermission('crm.opportunities.view')) {
            return false;
        }

        return (int) $opportunity->assigned_user_id === $user->id
            || (int) $opportunity->created_by === $user->id;
    }

    public function authorizeOpportunity(User $user, Opportunity $opportunity): void
    {
        if (! $this->canAccessOpportunity($user, $opportunity)) {
            throw new AccessDeniedHttpException('شما به این فرصت دسترسی ندارید.');
        }
    }
}
