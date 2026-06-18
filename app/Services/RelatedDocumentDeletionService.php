<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\InventoryDocument;
use App\Models\ProductionMaterialConsumption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelatedDocumentDeletionService
{
    public function deleteInventoryDocumentWithRelated(InventoryDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $accountingIds = collect([$document->accounting_document_id])
                ->filter()
                ->merge(AccountingDocument::withTrashed()
                    ->where('source_type', InventoryDocument::class)
                    ->where('source_id', $document->id)
                    ->pluck('id'))
                ->unique()
                ->values();

            if (Schema::hasTable('production_material_consumptions')) {
                ProductionMaterialConsumption::where('inventory_document_id', $document->id)
                    ->update(['inventory_document_id' => null]);
            }

            foreach ($accountingIds as $accountingId) {
                $accountingDocument = AccountingDocument::withTrashed()->find($accountingId);

                if (! $accountingDocument) {
                    continue;
                }

                $accountingDocument->lines()->delete();
                $accountingDocument->forceDelete();
            }

            $document->lines()->delete();
            $document->delete();
        });
    }
}
