<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canonical sales gross-profit layer (IRR).
 *
 * Definitions (confirmed for Sales Intelligence phase 1):
 * - Gross sales (incl. VAT) = invoice.total_amount
 * - Net sales (ex VAT) = subtotal − discount_amount  [= taxable base]
 * - VAT is never profit or cost
 * - COGS (products only) = qty × unit_cost
 * - unit_cost priority:
 *   1) weighted average of confirmed purchase invoice lines on the same project
 *   2) weighted average of all confirmed purchase invoice lines for the item
 *   3) items.purchase_price (master)
 * - Services: COGS = 0 in this layer (project costing remains separate)
 * - Gross profit = net sales − COGS
 * - Margin % = gross profit / net sales × 100
 *
 * Audit note (Kosar SI-00064): master purchase_price understated COGS vs project purchases.
 */
class SalesProfitCalculationService
{
    /** @var array<string, float|null> */
    private array $unitCostCache = [];

    public function __construct(
        private readonly InvoiceCalculationService $invoices,
    ) {}

    /**
     * @return array{
     *     invoice_id: int,
     *     number: string|null,
     *     gross_sales: float,
     *     net_sales: float,
     *     discount_amount: float,
     *     vat_amount: float,
     *     cogs_amount: float,
     *     cogs_source: string,
     *     gross_profit: float,
     *     margin_percent: float,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines.item']);

        $netSales = $this->invoices->netSalesFromHeader(
            (float) $invoice->subtotal,
            (float) $invoice->discount_amount,
        );
        $vat = $this->invoices->round((float) $invoice->tax_amount);
        $grossSales = $this->invoices->round((float) $invoice->total_amount);

        $lineRows = [];
        $cogs = 0.0;
        $sources = [];

        foreach ($invoice->lines as $line) {
            $breakdown = $this->forLine($line, $invoice->project_id ? (int) $invoice->project_id : null);
            $lineRows[] = $breakdown;
            $cogs = $this->invoices->round($cogs + $breakdown['cogs_amount']);
            $sources[$breakdown['cogs_source']] = true;
        }

        $grossProfit = $this->invoices->round($netSales - $cogs);

        return [
            'invoice_id' => (int) $invoice->id,
            'number' => $invoice->number,
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'discount_amount' => $this->invoices->round((float) $invoice->discount_amount),
            'vat_amount' => $vat,
            'cogs_amount' => $cogs,
            'cogs_source' => $this->resolveAggregateSource(array_keys($sources)),
            'gross_profit' => $grossProfit,
            'margin_percent' => $this->marginPercent($grossProfit, $netSales),
            'lines' => $lineRows,
        ];
    }

    /**
     * @return array{
     *     line_id: int|null,
     *     item_id: int|null,
     *     item_name: string|null,
     *     item_type: string|null,
     *     quantity: float,
     *     net_amount: float,
     *     unit_cost: float,
     *     cogs_amount: float,
     *     cogs_source: string,
     *     gross_profit: float
     * }
     */
    public function forLine(InvoiceLine $line, ?int $projectId = null): array
    {
        $line->loadMissing('item');
        $item = $line->item;
        $quantity = $this->invoices->round((float) $line->quantity);
        $netAmount = $this->invoices->lineNetExVat((float) $line->line_total, (float) $line->tax_amount);

        if (! $item || $item->type !== 'product') {
            return [
                'line_id' => $line->id ? (int) $line->id : null,
                'item_id' => $item?->id,
                'item_name' => $item?->name,
                'item_type' => $item?->type,
                'quantity' => $quantity,
                'net_amount' => $netAmount,
                'unit_cost' => 0.0,
                'cogs_amount' => 0.0,
                'cogs_source' => 'none',
                'gross_profit' => $netAmount,
            ];
        }

        [$unitCost, $source] = $this->resolveUnitCost((int) $item->id, $projectId, (float) ($item->purchase_price ?? 0));
        $cogs = $this->invoices->round($quantity * $unitCost);

        return [
            'line_id' => $line->id ? (int) $line->id : null,
            'item_id' => (int) $item->id,
            'item_name' => $item->name,
            'item_type' => $item->type,
            'quantity' => $quantity,
            'net_amount' => $netAmount,
            'unit_cost' => $unitCost,
            'cogs_amount' => $cogs,
            'cogs_source' => $source,
            'gross_profit' => $this->invoices->round($netAmount - $cogs),
        ];
    }

    public function invoiceCogs(Invoice $invoice): float
    {
        return $this->forInvoice($invoice)['cogs_amount'];
    }

    /**
     * @param  Collection<int, Invoice>|iterable<int, Invoice>  $invoices
     * @return array{net_sales: float, cogs_amount: float, gross_profit: float, margin_percent: float, invoice_count: int}
     */
    public function aggregate(iterable $invoices): array
    {
        $net = 0.0;
        $cogs = 0.0;
        $count = 0;

        foreach ($invoices as $invoice) {
            $row = $this->forInvoice($invoice);
            $net = $this->invoices->round($net + $row['net_sales']);
            $cogs = $this->invoices->round($cogs + $row['cogs_amount']);
            $count++;
        }

        $profit = $this->invoices->round($net - $cogs);

        return [
            'net_sales' => $net,
            'cogs_amount' => $cogs,
            'gross_profit' => $profit,
            'margin_percent' => $this->marginPercent($profit, $net),
            'invoice_count' => $count,
        ];
    }

    public function marginPercent(float $profit, float $netSales): float
    {
        if ($netSales <= 0.009) {
            return 0.0;
        }

        return round(($profit / $netSales) * 100, 1);
    }

    /**
     * @return array{0: float, 1: string}
     */
    public function resolveUnitCost(int $itemId, ?int $projectId, float $masterPurchasePrice): array
    {
        if ($projectId) {
            $projectAvg = $this->weightedPurchaseUnitCost($itemId, $projectId);
            if ($projectAvg !== null) {
                return [$projectAvg, 'project_purchase_avg'];
            }
        }

        $globalAvg = $this->weightedPurchaseUnitCost($itemId, null);
        if ($globalAvg !== null) {
            return [$globalAvg, 'global_purchase_avg'];
        }

        return [$this->invoices->round(max(0, $masterPurchasePrice)), 'master_purchase_price'];
    }

    public function weightedPurchaseUnitCost(int $itemId, ?int $projectId): ?float
    {
        $cacheKey = $itemId.'.'.($projectId ?? 'g');
        if (array_key_exists($cacheKey, $this->unitCostCache)) {
            return $this->unitCostCache[$cacheKey];
        }

        $query = DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoice_lines.item_id', $itemId)
            ->where('invoices.direction', 'purchase')
            ->where('invoices.document_type', 'invoice')
            ->where('invoices.status', 'confirmed');

        if ($projectId !== null) {
            $query->where('invoices.project_id', $projectId);
        }

        $row = $query
            ->selectRaw('COALESCE(SUM(invoice_lines.quantity), 0) as qty')
            ->selectRaw('COALESCE(SUM(invoice_lines.quantity * invoice_lines.unit_price), 0) as amount')
            ->first();

        $qty = (float) ($row->qty ?? 0);
        if ($qty <= 0.0001) {
            return $this->unitCostCache[$cacheKey] = null;
        }

        return $this->unitCostCache[$cacheKey] = $this->invoices->round(((float) $row->amount) / $qty);
    }

    /**
     * @param  list<string>  $sources
     */
    private function resolveAggregateSource(array $sources): string
    {
        $sources = array_values(array_filter($sources, fn ($s) => $s !== 'none'));
        if ($sources === []) {
            return 'none';
        }
        if (count($sources) === 1) {
            return $sources[0];
        }

        return 'mixed';
    }
}
