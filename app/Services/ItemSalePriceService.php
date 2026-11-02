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
        if ($invoice->direction !== 'sale' || $invoice->document_type !== 'invoice') {
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

    public function updateCatalogSalePrice(Item|int $item, float|string|null $salePrice): Item
    {
        $model = $item instanceof Item ? $item : Item::query()->findOrFail($item);
        $amount = $this->normalizeMoney($salePrice);

        if ($amount < 0) {
            throw new \InvalidArgumentException('قیمت فروش نمی‌تواند منفی باشد.');
        }

        $model->update(['sale_price' => $amount]);

        return $model->fresh(['unit']);
    }

    private function normalizeMoney(float|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }

        $digitMap = [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];

        $normalized = strtr((string) $value, $digitMap);
        $normalized = str_replace(['٬', ',', ' ', '٫'], ['', '', '', '.'], $normalized);

        return round((float) $normalized, 2);
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
