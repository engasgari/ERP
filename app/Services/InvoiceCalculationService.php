<?php

namespace App\Services;

/**
 * Single source of truth for invoice line/header money math (IRR, 2 decimal places).
 *
 * Semantics:
 * - base = qty × unit_price
 * - taxable = max(base − discount, 0)
 * - tax = taxable × (tax_rate / 100)
 * - line_total = taxable + tax (includes VAT)
 * - header total = Σ base − Σ discount + Σ tax
 */
class InvoiceCalculationService
{
    public function round(float|int|string $amount): float
    {
        return round((float) $amount, 2);
    }

    /**
     * @param  array{
     *     quantity?: float|int|string,
     *     unit_price?: float|int|string,
     *     discount_amount?: float|int|string,
     *     tax_rate?: float|int|string,
     *     item_id?: int|string|null,
     *     description?: string|null
     * }  $line
     * @return array{
     *     item_id: int,
     *     description: string|null,
     *     quantity: float,
     *     unit_price: float,
     *     discount_amount: float,
     *     tax_rate: float,
     *     tax_amount: float,
     *     line_total: float,
     *     base_amount: float,
     *     taxable_amount: float
     * }
     */
    public function calculateLine(array $line): array
    {
        $quantity = $this->round($line['quantity'] ?? 0);
        $unitPrice = $this->round($line['unit_price'] ?? 0);
        $discountAmount = $this->round($line['discount_amount'] ?? 0);
        $taxRate = (float) ($line['tax_rate'] ?? 0);
        $baseAmount = $this->round($quantity * $unitPrice);
        $taxableAmount = $this->round(max($baseAmount - $discountAmount, 0));
        $taxAmount = $this->round($taxableAmount * ($taxRate / 100));

        return [
            'item_id' => (int) ($line['item_id'] ?? 0),
            'description' => $line['description'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $this->round($taxableAmount + $taxAmount),
            'base_amount' => $baseAmount,
            'taxable_amount' => $taxableAmount,
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $lines
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     subtotal: float,
     *     discount_amount: float,
     *     tax_amount: float,
     *     total_amount: float,
     *     net_sales: float
     * }
     */
    public function calculateDocument(iterable $lines): array
    {
        $calculated = [];
        foreach ($lines as $line) {
            if (empty($line['item_id'])) {
                continue;
            }
            $calculated[] = $this->calculateLine($line);
        }

        $subtotal = $this->round(array_sum(array_column($calculated, 'base_amount')));
        $discountAmount = $this->round(array_sum(array_column($calculated, 'discount_amount')));
        $taxAmount = $this->round(array_sum(array_column($calculated, 'tax_amount')));
        $netSales = $this->round($subtotal - $discountAmount);

        return [
            'lines' => $calculated,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $this->round($netSales + $taxAmount),
            'net_sales' => $netSales,
        ];
    }

    public function netSalesFromHeader(float $subtotal, float $discountAmount): float
    {
        return $this->round($subtotal - $discountAmount);
    }

    public function lineNetExVat(float $lineTotal, float $taxAmount): float
    {
        return $this->round($lineTotal - $taxAmount);
    }
}
