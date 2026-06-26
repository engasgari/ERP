<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\FinancialTransaction;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\ProductionOrder;
use App\Models\Project;
use App\Models\ProjectCostSnapshot;
use App\Models\TreasuryTransaction;
use App\Models\WorkLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectDeletionService
{
    public function __construct(
        private RelatedDocumentDeletionService $relatedDocuments,
        private AccountingPostingService $accountingPosting
    ) {
    }

    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $projectId = $project->id;

            $this->deleteAccountingDocumentsForProjectLines($projectId);
            $this->deleteOverheadAllocationAccountingDocuments($projectId);
            $this->deleteInvoices($projectId);
            $this->deleteInventoryDocuments($projectId);
            $this->deleteFinancialTransactions($projectId);
            $this->deleteTreasuryTransactions($projectId);
            $this->deleteProductionOrders($projectId);

            WorkLog::where('project_id', $projectId)->delete();
            DB::table('project_overhead_allocations')->where('project_id', $projectId)->delete();
            ProjectCostSnapshot::where('project_id', $projectId)->delete();

            $project->delete();
        });
    }

    private function deleteAccountingDocumentsForProjectLines(int $projectId): void
    {
        AccountingDocumentLine::where('project_id', $projectId)
            ->select('accounting_document_id')
            ->distinct()
            ->pluck('accounting_document_id')
            ->filter()
            ->unique()
            ->values()
            ->each(function (int $accountingId): void {
                $document = AccountingDocument::withTrashed()->find($accountingId);

                if (! $document) {
                    return;
                }

                if ($document->status === 'posted') {
                    throw ValidationException::withMessages([
                        'accounting_document_id' => 'سند حسابداری ثبت‌شده قابل حذف نیست.',
                    ]);
                }

                $document->lines()->delete();
                $document->forceDelete();
            });
    }

    private function deleteOverheadAllocationAccountingDocuments(int $projectId): void
    {
        DB::table('project_overhead_allocations')
            ->where('project_id', $projectId)
            ->select('accounting_document_id')
            ->get()
            ->pluck('accounting_document_id')
            ->filter()
            ->unique()
            ->values()
            ->each(function ($accountingId): void {
                $document = AccountingDocument::withTrashed()->find($accountingId);

                if (! $document) {
                    return;
                }

                $document->lines()->delete();
                $document->forceDelete();
            });
    }

    private function deleteInvoices(int $projectId): void
    {
        Invoice::where('project_id', $projectId)
            ->orderBy('id')
            ->get()
            ->each(function (Invoice $invoice): void {
                $this->deleteInvoiceWithRelatedDocuments($invoice);
            });
    }

    private function deleteInvoiceWithRelatedDocuments(Invoice $invoice): void
    {
        $invoice->loadMissing('inventoryDocuments');

        $inventoryDocuments = $invoice->inventoryDocuments()->with('lines')->get();
        $accountingIds = collect([$invoice->accounting_document_id])
            ->merge($inventoryDocuments->pluck('accounting_document_id'))
            ->filter()
            ->unique()
            ->values();

        foreach ($inventoryDocuments as $inventoryDocument) {
            $this->relatedDocuments->deleteInventoryDocumentWithRelated($inventoryDocument);
        }

        foreach ($accountingIds as $accountingId) {
            $document = AccountingDocument::withTrashed()->find($accountingId);

            if (! $document) {
                continue;
            }

            $document->lines()->delete();
            $document->forceDelete();
        }

        $invoice->lines()->delete();
        $invoice->delete();
    }

    private function deleteInventoryDocuments(int $projectId): void
    {
        InventoryDocument::where('project_id', $projectId)
            ->orderBy('id')
            ->get()
            ->each(fn (InventoryDocument $document) => $this->relatedDocuments->deleteInventoryDocumentWithRelated($document));
    }

    private function deleteFinancialTransactions(int $projectId): void
    {
        FinancialTransaction::where('project_id', $projectId)
            ->orderBy('id')
            ->get()
            ->each(function (FinancialTransaction $transaction): void {
                $this->accountingPosting->deleteFinancialTransactionDocument($transaction);
                $transaction->delete();
            });
    }

    private function deleteTreasuryTransactions(int $projectId): void
    {
        TreasuryTransaction::withTrashed()
            ->where('project_id', $projectId)
            ->orderBy('id')
            ->get()
            ->each(function (TreasuryTransaction $transaction): void {
                $accountingIds = collect([$transaction->accounting_document_id])
                    ->merge(AccountingDocument::withTrashed()
                        ->where('source_type', TreasuryTransaction::class)
                        ->where('source_id', $transaction->id)
                        ->pluck('id'))
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($accountingIds as $accountingId) {
                    $document = AccountingDocument::withTrashed()->find($accountingId);

                    if (! $document) {
                        continue;
                    }

                    $document->lines()->delete();
                    $document->forceDelete();
                }

                if (method_exists($transaction, 'forceDelete')) {
                    $transaction->forceDelete();
                    return;
                }

                $transaction->delete();
            });
    }

    private function deleteProductionOrders(int $projectId): void
    {
        ProductionOrder::where('project_id', $projectId)
            ->orderBy('id')
            ->get()
            ->each(function (ProductionOrder $order): void {
                InventoryDocument::where('source_type', ProductionOrder::class)
                    ->where('source_id', $order->id)
                    ->orderBy('id')
                    ->get()
                    ->each(fn (InventoryDocument $document) => $this->relatedDocuments->deleteInventoryDocumentWithRelated($document));

                $order->delete();
            });
    }
}
