<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Invoice;
use App\Models\User;

class CrmProformaInvoiceService
{
    public function linkToOpportunity(Opportunity $opportunity, Invoice $invoice, User $actor): void
    {
        if ($opportunity->invoice_id) {
            return;
        }

        $opportunity->update([
            'invoice_id' => $invoice->id,
            'updated_by' => $actor->id,
        ]);

        app(CrmAuditService::class)->log($opportunity, 'proforma_linked', null, [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
        ], $opportunity->party_id);
    }
}
