<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    /**
     * When a CRM opportunity is lost, void the linked proforma in ERP but keep the FK for CRM history.
     */
    public function cancelForOpportunity(Opportunity $opportunity, User $actor, ?string $reason = null): ?Invoice
    {
        $invoice = $opportunity->invoice ?? ($opportunity->invoice_id
            ? Invoice::query()->find($opportunity->invoice_id)
            : null);

        if (! $invoice || $invoice->document_type !== 'proforma' || $invoice->status === 'cancelled') {
            return null;
        }

        $old = $invoice->only(['status', 'number']);
        $invoice->update(['status' => 'cancelled']);

        app(CrmAuditService::class)->log($opportunity, 'proforma_cancelled', $old, [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'status' => 'cancelled',
            'reason' => $reason ?: 'فرصت از دست رفت',
        ], $opportunity->party_id);

        return $invoice->fresh();
    }

    /**
     * After converting a proforma to an invoice: point CRM opportunities at the new invoice, then delete the proforma.
     */
    public function finalizeConversion(Invoice $proforma, Invoice $invoice, ?User $actor = null): void
    {
        if ($proforma->document_type !== 'proforma') {
            throw new InvalidArgumentException('فقط پیش‌فاکتور قابل نهایی‌سازی تبدیل است.');
        }

        DB::transaction(function () use ($proforma, $invoice, $actor) {
            $opportunities = Opportunity::query()
                ->where('invoice_id', $proforma->id)
                ->get();

            foreach ($opportunities as $opportunity) {
                $opportunity->update([
                    'invoice_id' => $invoice->id,
                    'updated_by' => $actor?->id ?? $opportunity->updated_by,
                ]);

                app(CrmAuditService::class)->log($opportunity, 'proforma_converted', [
                    'invoice_id' => $proforma->id,
                    'invoice_number' => $proforma->number,
                ], [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                ], $opportunity->party_id);
            }

            $proforma->lines()->delete();
            $proforma->delete();
        });
    }
}
