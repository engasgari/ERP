<?php

namespace App\Services\Crm;

use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Crm\CrmCustomerRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CrmCustomerInvoiceAccessService
{
    public function __construct(
        private readonly CrmCustomerRepository $customers,
    ) {}

    public function canAccess(User $user, Invoice $invoice): bool
    {
        if ($invoice->direction !== 'sale' || ! $invoice->party_id) {
            return false;
        }

        if ($user->isAdmin() || $user->hasPermission('commerce.view')) {
            return true;
        }

        if (! $user->hasPermission('crm.customers.view')) {
            return false;
        }

        return $this->customers->findForShow((int) $invoice->party_id, $user) !== null;
    }

    public function authorize(User $user, Invoice $invoice): void
    {
        if (! $this->canAccess($user, $invoice)) {
            throw new AccessDeniedHttpException('شما به این فاکتور دسترسی ندارید.');
        }
    }
}
