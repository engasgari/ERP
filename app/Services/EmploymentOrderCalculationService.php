<?php

namespace App\Services;

use App\Core\Base\BaseService;
use App\DTO\Hr\EmploymentOrderLineDTO;
use App\DTO\Hr\EmploymentOrderSummaryDTO;
use App\Models\SalaryItem;
use App\Repositories\SalaryItemRepository;
use Illuminate\Support\Collection;

class EmploymentOrderCalculationService extends BaseService
{
    public function __construct(
        private readonly SalaryItemRepository $salaryItems,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>|Collection<int, EmploymentOrderLineDTO|array>  $lines
     */
    public function summarize(array|Collection $lines): EmploymentOrderSummaryDTO
    {
        $normalized = $this->normalizeLines($lines);

        $earnings = $normalized->where('type', 'earning');
        $deductions = $normalized->where('type', 'deduction');

        $totalBenefits = round((float) $earnings->sum(fn (EmploymentOrderLineDTO $line) => $line->amount), 2);
        $insurableBenefits = round((float) $earnings
            ->filter(fn (EmploymentOrderLineDTO $line) => $line->isInsurable)
            ->sum(fn (EmploymentOrderLineDTO $line) => $line->amount), 2);
        $taxableBenefits = round((float) $earnings
            ->filter(fn (EmploymentOrderLineDTO $line) => $line->isTaxable)
            ->sum(fn (EmploymentOrderLineDTO $line) => $line->amount), 2);
        $totalDeductions = round((float) $deductions->sum(fn (EmploymentOrderLineDTO $line) => $line->amount), 2);

        $grossSalary = $totalBenefits;
        $insurableWage = $insurableBenefits;
        $taxableWage = $taxableBenefits;
        $netSalary = round($grossSalary - $totalDeductions, 2);

        return new EmploymentOrderSummaryDTO(
            totalBenefits: $totalBenefits,
            insurableBenefits: $insurableBenefits,
            taxableBenefits: $taxableBenefits,
            totalDeductions: $totalDeductions,
            grossSalary: $grossSalary,
            insurableWage: $insurableWage,
            taxableWage: $taxableWage,
            netSalary: $netSalary,
        );
    }

    public function lineFromSalaryItem(SalaryItem $item, float|int|string|null $amount = null): EmploymentOrderLineDTO
    {
        return new EmploymentOrderLineDTO(
            id: null,
            salaryItemId: $item->id,
            code: $item->code,
            title: $item->title,
            type: $item->type,
            amount: $amount === null || $amount === '' ? (float) $item->default_amount : (float) $amount,
            isInsurable: (bool) $item->is_insurable,
            isTaxable: (bool) $item->is_taxable,
            isEditable: (bool) $item->is_editable,
            isRemovable: (bool) $item->is_removable,
            sortOrder: (int) $item->sort_order,
        );
    }

    public function defaultDecreeLines(): array
    {
        return $this->salaryItems
            ->earningCatalogForDecree()
            ->filter(fn (SalaryItem $item) => in_array($item->code, ['base_salary'], true) || (float) $item->default_amount > 0)
            ->map(fn (SalaryItem $item) => $this->lineFromSalaryItem($item)->toArray())
            ->values()
            ->all();
    }

    public function availableItemsForAdd(array $existingCodes, string $type = 'earning'): Collection
    {
        $catalog = $type === 'deduction'
            ? $this->salaryItems->deductionCatalogForDecree()
            : $this->salaryItems->earningCatalogForDecree();

        return $catalog
            ->reject(fn (SalaryItem $item) => in_array($item->code, $existingCodes, true))
            ->values();
    }

    /**
     * Sync legacy flat columns used by older payroll paths.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function legacyColumnMap(array $lines): array
    {
        $byCode = collect($lines)->keyBy('code');

        return [
            'base_salary' => (float) ($byCode->get('base_salary')['amount'] ?? 0),
            'seniority_pay' => (float) ($byCode->get('seniority_monthly')['amount'] ?? 0),
            'housing_allowance' => (float) ($byCode->get('housing_allowance')['amount'] ?? 0),
            'food_allowance' => (float) ($byCode->get('food_allowance')['amount'] ?? 0),
            'child_allowance' => (float) ($byCode->get('child_allowance')['amount'] ?? 0),
            'children_allowance' => (float) ($byCode->get('child_allowance')['amount'] ?? 0),
            'marriage_allowance' => (float) ($byCode->get('marriage_allowance')['amount'] ?? 0),
            'transportation_allowance' => (float) ($byCode->get('transportation_allowance')['amount'] ?? 0),
            'job_allowance' => (float) ($byCode->get('job_allowance')['amount'] ?? 0),
            'hardship_allowance' => (float) ($byCode->get('hardship_allowance')['amount'] ?? 0),
            'shift_allowance' => (float) ($byCode->get('shift_allowance')['amount'] ?? 0),
            'other_insurable_benefits' => (float) ($byCode->get('other_insurable_benefits')['amount'] ?? 0),
            'other_non_insurable_benefits' => (float) ($byCode->get('other_non_insurable_benefits')['amount'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|Collection  $lines
     * @return Collection<int, EmploymentOrderLineDTO>
     */
    private function normalizeLines(array|Collection $lines): Collection
    {
        return collect($lines)
            ->map(function ($line): EmploymentOrderLineDTO {
                if ($line instanceof EmploymentOrderLineDTO) {
                    return $line;
                }

                return EmploymentOrderLineDTO::fromArray((array) $line);
            })
            ->values();
    }
}
