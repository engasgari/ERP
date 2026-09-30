<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Crm\CrmCustomerRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CrmCustomerInvoiceAccessService
{
    public function __construct(
        private readonly CrmCustomerRepository $customers,
        private readonly CrmProformaAccessService $proformaAccess,
    ) {}

    public function canAccess(User $user, Invoice $invoice): bool
    {
        if ($invoice->direction !== 'sale' || ! $invoice->party_id) {
            return false;
        }

        if ($user->isAdmin() || $user->hasPermission('commerce.view')) {
            return true;
        }

        // فاکتور/پیش‌فاکتور متصل به فرصت: دسترسی مثل خود فرصت
        if ($user->hasPermission('crm.opportunities.view')) {
            $opportunity = Opportunity::query()->where('invoice_id', $invoice->id)->first();

            if ($opportunity && $this->proformaAccess->canAccessOpportunity($user, $opportunity)) {
                return true;
            }
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
