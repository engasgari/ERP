<?php

namespace App\Services\Crm;

use App\Models\Crm\Opportunity;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Collection;
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
     * Link an existing ERP sale proforma to a CRM opportunity that has no invoice yet.
     */
    public function attachExistingProforma(Opportunity $opportunity, Invoice $invoice, User $actor): Opportunity
    {
        if ($opportunity->invoice_id) {
            throw new InvalidArgumentException('برای این فرصت قبلاً پیش‌فاکتور/فاکتور متصل شده است.');
        }

        if ($invoice->document_type !== 'proforma' || $invoice->direction !== 'sale') {
            throw new InvalidArgumentException('فقط پیش‌فاکتور فروش قابل اتصال است.');
        }

        if ($invoice->status === 'cancelled') {
            throw new InvalidArgumentException('پیش‌فاکتور ابطال‌شده قابل اتصال نیست.');
        }

        if ($opportunity->party_id && $invoice->party_id && (int) $opportunity->party_id !== (int) $invoice->party_id) {
            throw new InvalidArgumentException('طرف‌حساب پیش‌فاکتور با مشتری این فرصت یکسان نیست.');
        }

        $this->linkToOpportunity($opportunity, $invoice, $actor);

        return $opportunity->fresh(['invoice.party', 'invoice']);
    }

    /**
     * Sale proformas for the opportunity party that can be attached from CRM.
     *
     * @return Collection<int, Invoice>
     */
    public function linkableProformasForOpportunity(Opportunity $opportunity, ?string $search = null): Collection
    {
        if (! $opportunity->party_id) {
            return collect();
        }

        $linkedInvoiceIds = Opportunity::query()
            ->whereNotNull('invoice_id')
            ->when($opportunity->id, fn ($query) => $query->where('id', '!=', $opportunity->id))
            ->pluck('invoice_id')
            ->all();

        $query = Invoice::query()
            ->with('party')
            ->where('direction', 'sale')
            ->where('document_type', 'proforma')
            ->where('status', '!=', 'cancelled')
            ->where('party_id', $opportunity->party_id)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->limit(75)->get()->map(function (Invoice $invoice) use ($linkedInvoiceIds) {
            $invoice->setAttribute('already_linked_elsewhere', in_array($invoice->id, $linkedInvoiceIds, true));

            return $invoice;
        });
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
