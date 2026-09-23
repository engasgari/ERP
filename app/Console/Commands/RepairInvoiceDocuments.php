<?php

namespace App\Console\Commands;

use App\Models\AccountingDocument;
use App\Models\Invoice;
use App\Services\AccountingDocumentService;
use App\Services\AccountingPostingService;
use Illuminate\Console\Command;
use RuntimeException;

class RepairInvoiceDocuments extends Command
{
    protected $signature = 'accounting:repair-invoice-documents {--invoice-id= : Invoice ID to repair}';

    protected $description = 'Void the current invoice accounting document and rebuild it using the current posting rules.';

    public function handle(AccountingDocumentService $documents, AccountingPostingService $posting): int
    {
        $invoiceId = (int) $this->option('invoice-id');

        if ($invoiceId <= 0) {
            throw new RuntimeException('The --invoice-id option is required.');
        }

        $invoice = Invoice::with('accountingDocument')->findOrFail($invoiceId);
        $document = $invoice->accountingDocument;

        if ($document && $document->status === 'posted' && ! $posting->isReversalDocument($document)) {
            $posting->reverse($document, $invoice->created_by);
        }

        AccountingDocument::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('status', 'posted')
            ->whereNull('voided_at')
            ->where('description', 'like', 'عطف سند%')
            ->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $invoice->created_by,
            ]);

        $invoice->update(['accounting_document_id' => null]);
        $rebuilt = $documents->postInvoice($invoice->refresh());

        $this->info('Invoice accounting document repaired: ' . $rebuilt->number);

        return self::SUCCESS;
    }
}
