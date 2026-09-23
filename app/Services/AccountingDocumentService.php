<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class AccountingDocumentService
{
    public function __construct(
        private InventoryPostingService $inventory,
        private AccountingPostingService $posting
    ) {
    }

    public function postInvoice(Invoice $invoice): AccountingDocument
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->load('lines.item', 'party', 'accountingDocument');

            $document = $invoice->accountingDocument;
            if ($document && $this->posting->isActiveSourceAccountingDocument($document)) {
                return $document;
            }

            if ($invoice->accounting_document_id) {
                $invoice->update(['accounting_document_id' => null]);
                $invoice->refresh();
            }

            $this->inventory->postInvoice($invoice);
            $document = $this->posting->fromInvoice($invoice, $invoice->created_by);

            $invoice->update([
                'accounting_document_id' => $document->id,
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            return $document;
        });
    }
}
