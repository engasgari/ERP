<?php

namespace App\DTO\Hr;

use App\Core\Base\BaseDTO;

final readonly class EmploymentOrderSummaryDTO extends BaseDTO
{
    public function __construct(
        public float $totalBenefits,
        public float $insurableBenefits,
        public float $taxableBenefits,
        public float $totalDeductions,
        public float $grossSalary,
        public float $insurableWage,
        public float $taxableWage,
        public float $netSalary,
    ) {
    }

    public function toArray(): array
    {
        return [
            'total_benefits' => $this->totalBenefits,
            'insurable_benefits' => $this->insurableBenefits,
            'taxable_benefits' => $this->taxableBenefits,
            'total_deductions' => $this->totalDeductions,
            'gross_salary' => $this->grossSalary,
            'insurable_wage' => $this->insurableWage,
            'taxable_wage' => $this->taxableWage,
            'net_salary' => $this->netSalary,
        ];
    }
}
