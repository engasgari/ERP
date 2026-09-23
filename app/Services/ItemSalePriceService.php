<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use Illuminate\Support\Collection;

class ItemSalePriceService
{
    /**
     * @param  iterable<int>  $itemIds
     * @return array<int, float>
     */
    public function lastSaleUnitPricesForItems(iterable $itemIds, ?int $excludeInvoiceId = null): array
    {
        $ids = collect($itemIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return InvoiceLine::query()
            ->select(['invoice_lines.item_id', 'invoice_lines.unit_price'])
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.direction', 'sale')
            ->whereIn('invoice_lines.item_id', $ids)
            ->when($excludeInvoiceId, fn ($query) => $query->where('invoices.id', '!=', $excludeInvoiceId))
            ->orderByDesc('invoices.invoice_date')
            ->orderByDesc('invoices.id')
            ->orderByDesc('invoice_lines.id')
            ->get()
            ->unique('item_id')
            ->mapWithKeys(fn (InvoiceLine $line) => [(int) $line->item_id => (float) $line->unit_price])
            ->all();
    }

    public function syncItemSalePricesFromInvoice(Invoice $invoice): void
    {
        if ($invoice->direction !== 'sale') {
            return;
        }

        $invoice->loadMissing('lines');

        foreach ($invoice->lines as $line) {
            if (! $line->item_id) {
                continue;
            }

            Item::query()
                ->whereKey($line->item_id)
                ->update(['sale_price' => $line->unit_price]);
        }
    }

    /**
     * @param  array<int, float>  $lastSaleUnitPrices
     */
    public function resolveSaleUnitPrice(Item $item, array $lastSaleUnitPrices): float
    {
        $itemId = (int) $item->id;

        if (array_key_exists($itemId, $lastSaleUnitPrices)) {
            return (float) $lastSaleUnitPrices[$itemId];
        }

        return (float) ($item->sale_price ?? 0);
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return array<int, float>
     */
    public function saleUnitPricesForCatalog(Collection $items, ?int $excludeInvoiceId = null): array
    {
        return $this->lastSaleUnitPricesForItems($items->pluck('id'), $excludeInvoiceId);
    }
}
