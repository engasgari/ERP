<?php

namespace App\Services;

use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryPostingService
{
    public function __construct(private NumberingService $numbering)
    {
    }

    public function availableQuantity(int $itemId, int $warehouseId, ?int $ignoreDocumentId = null): float
    {
        $receipt = $this->lineSum($itemId, $warehouseId, 'receipt', 'warehouse_id', $ignoreDocumentId);
        $issue = $this->lineSum($itemId, $warehouseId, 'issue', 'warehouse_id', $ignoreDocumentId);
        $consumption = $this->lineSum($itemId, $warehouseId, 'consumption', 'warehouse_id', $ignoreDocumentId);
        $transferIn = $this->lineSum($itemId, $warehouseId, 'transfer', 'target_warehouse_id', $ignoreDocumentId);
        $transferOut = $this->lineSum($itemId, $warehouseId, 'transfer', 'warehouse_id', $ignoreDocumentId);

        return $receipt + $transferIn - $issue - $consumption - $transferOut;
    }

    public function postInitialStock(Item $item, float $quantity, ?float $unitPrice, ?int $warehouseId, ?int $userId = null): ?InventoryDocument
    {
        if ($item->type !== 'product' || $quantity <= 0) {
            return null;
        }

        $warehouseId = $warehouseId ?: $this->defaultWarehouse()?->id;

        if (!$warehouseId) {
            throw new RuntimeException('برای ثبت موجودی اولیه، ابتدا یک انبار تعریف کنید.');
        }

        return $this->createDocument(
            type: 'receipt',
            warehouseId: $warehouseId,
            source: $item,
            description: 'موجودی اولیه کالا ' . $item->name,
            lines: collect([[
                'item_id' => $item->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice ?? 0,
                'description' => 'موجودی اولیه',
            ]]),
            date: now()->toDateString(),
            userId: $userId
        );
    }

    public function postInvoice(Invoice $invoice): ?InventoryDocument
    {
        $invoice->loadMissing('lines.item');

        if ($invoice->document_type !== 'invoice') {
            return null;
        }

        if (InventoryDocument::where('source_type', Invoice::class)->where('source_id', $invoice->id)->exists()) {
            return null;
        }

        $productLines = $invoice->lines
            ->filter(fn ($line) => $line->item && $line->item->type === 'product')
            ->values();

        if ($productLines->isEmpty()) {
            return null;
        }

        if (!$invoice->warehouse_id) {
            throw new RuntimeException('برای تایید فاکتور دارای کالا، انتخاب انبار الزامی است.');
        }

        if ($invoice->direction === 'sale') {
            $this->assertEnoughStock($productLines, (int) $invoice->warehouse_id);
        }

        return $this->createDocument(
            type: $invoice->direction === 'purchase' ? 'receipt' : 'issue',
            warehouseId: (int) $invoice->warehouse_id,
            source: $invoice,
            description: ($invoice->direction === 'purchase' ? 'رسید خرید فاکتور ' : 'حواله فروش فاکتور ') . $invoice->number,
            lines: $productLines->map(fn ($line) => [
                'item_id' => $line->item_id,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'description' => $line->description,
            ]),
            date: $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
            userId: $invoice->created_by
        );
    }

    private function assertEnoughStock(Collection $lines, int $warehouseId): void
    {
        $required = $lines
            ->groupBy('item_id')
            ->map(fn ($group) => $group->sum(fn ($line) => (float) $line->quantity));

        foreach ($required as $itemId => $quantity) {
            $available = $this->availableQuantity((int) $itemId, $warehouseId);

            if ($available + 0.0001 < $quantity) {
                $itemName = optional($lines->firstWhere('item_id', $itemId)?->item)->name ?? 'کالا';
                throw new RuntimeException("موجودی {$itemName} کافی نیست. موجودی فعلی: {$available}، مقدار فاکتور: {$quantity}");
            }
        }
    }

    private function createDocument(string $type, int $warehouseId, object $source, string $description, Collection $lines, string $date, ?int $userId = null): InventoryDocument
    {
        return DB::transaction(function () use ($type, $warehouseId, $source, $description, $lines, $date, $userId) {
            $document = InventoryDocument::create([
                'number' => $this->numbering->next($this->numberingKey($type), $this->numberingPrefix($type)),
                'type' => $type,
                'document_date' => $date,
                'document_time' => now()->format('H:i:s'),
                'warehouse_id' => $warehouseId,
                'source_type' => get_class($source),
                'source_id' => $source->id,
                'entry_mode' => get_class($source) === Item::class ? 'manual' : 'automatic',
                'status' => 'confirmed',
                'description' => $description,
                'created_by' => $userId,
                'confirmed_by' => auth()->id() ?: $userId,
                'confirmed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) ($line['unit_price'] ?? 0);

                $document->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $document;
        });
    }

    private function lineSum(int $itemId, int $warehouseId, string $type, string $warehouseColumn, ?int $ignoreDocumentId = null): float
    {
        $query = DB::table('inventory_document_lines')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_lines.inventory_document_id')
            ->where('inventory_documents.status', 'confirmed')
            ->where('inventory_documents.type', $type)
            ->where("inventory_documents.{$warehouseColumn}", $warehouseId)
            ->where('inventory_document_lines.item_id', $itemId);

        if ($ignoreDocumentId) {
            $query->where('inventory_documents.id', '!=', $ignoreDocumentId);
        }

        return (float) $query->sum('inventory_document_lines.quantity');
    }

    private function defaultWarehouse(): ?Warehouse
    {
        return Warehouse::where('is_active', true)->orderBy('id')->first();
    }

    private function numberingKey(string $type): string
    {
        return match ($type) {
            'receipt' => 'inventory_receipt',
            'transfer' => 'inventory_transfer',
            'consumption' => 'inventory_consumption',
            default => 'inventory_issue',
        };
    }

    private function numberingPrefix(string $type): string
    {
        return match ($type) {
            'receipt' => 'IR-',
            'transfer' => 'IT-',
            'consumption' => 'IC-',
            default => 'II-',
        };
    }
}
