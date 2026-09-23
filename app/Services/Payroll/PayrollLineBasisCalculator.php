<?php

namespace App\Services\Payroll;

use App\Models\PayrollItem;
use App\Support\Hr\IranLaborEmploymentOrderCatalog;
use Illuminate\Support\Collection;

/**
 * Determines insurance/tax bases from payroll line items (PayrollItem flags + line overrides).
 */
class PayrollLineBasisCalculator
{
    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{
     *   gross: float,
     *   insurance_base: float,
     *   tax_base: float,
     *   non_insurance_earnings: float,
     *   non_tax_earnings: float
     * }
     */
    public function summarize(Collection $lines): array
    {
        $codes = $lines->pluck('code')->unique()->filter()->values();
        $itemsByCode = PayrollItem::whereIn('code', $codes)->get()->keyBy('code');
        $catalog = IranLaborEmploymentOrderCatalog::wageComponents();

        $earnings = $lines->filter(fn (array $line): bool => ($line['type'] ?? '') === 'earning');

        $gross = round((float) $earnings->sum(fn (array $line): float => (float) ($line['amount'] ?? 0)), 2);

        $insuranceBase = round((float) $earnings
            ->filter(fn (array $line): bool => $this->lineIsInsurable($line, $itemsByCode, $catalog))
            ->sum(fn (array $line): float => (float) ($line['amount'] ?? 0)), 2);

        $taxBase = round((float) $earnings
            ->filter(fn (array $line): bool => $this->lineIsTaxable($line, $itemsByCode, $catalog))
            ->sum(fn (array $line): float => (float) ($line['amount'] ?? 0)), 2);

        return [
            'gross' => $gross,
            'insurance_base' => $insuranceBase,
            'tax_base' => $taxBase,
            'non_insurance_earnings' => round(max(0, $gross - $insuranceBase), 2),
            'non_tax_earnings' => round(max(0, $gross - $taxBase), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<string, PayrollItem>  $itemsByCode
     * @param  array<string, array{title:string,insurable:bool,taxable:bool,field:string}>  $catalog
     */
    public function lineIsInsurable(array $line, Collection $itemsByCode, array $catalog): bool
    {
        if (array_key_exists('is_insurable', $line)) {
            return (bool) $line['is_insurable'];
        }

        return $this->resolveFlag($line, $itemsByCode, $catalog, 'insurable');
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<string, PayrollItem>  $itemsByCode
     * @param  array<string, array{title:string,insurable:bool,taxable:bool,field:string}>  $catalog
     */
    public function lineIsTaxable(array $line, Collection $itemsByCode, array $catalog): bool
    {
        if (array_key_exists('is_taxable', $line)) {
            return (bool) $line['is_taxable'];
        }

        return $this->resolveFlag($line, $itemsByCode, $catalog, 'taxable');
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<string, PayrollItem>  $itemsByCode
     * @param  array<string, array{title:string,insurable:bool,taxable:bool,field:string}>  $catalog
     */
    private function resolveFlag(array $line, Collection $itemsByCode, array $catalog, string $flag): bool
    {
        $code = (string) ($line['code'] ?? '');

        if ($code !== '' && isset($catalog[$code])) {
            return (bool) $catalog[$code][$flag];
        }

        $item = $itemsByCode->get($code);
        if ($item !== null) {
            return (bool) $item->{$flag};
        }

        return false;
    }
}
