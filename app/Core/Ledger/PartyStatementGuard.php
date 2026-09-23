<?php

namespace App\Core\Ledger;

use RuntimeException;

class PartyStatementGuard
{
    /**
     * @return array<int, int|string>
     */
    public function allowedAccounts(string $type): array
    {
        return match (strtolower(trim($type))) {
            'customer' => [1101],
            'supplier', 'vendor' => [2101],
            'employee', 'colleague', 'personnel' => [2104],
            default => [],
        };
    }

    /**
     * Payroll insurance/tax liabilities and other non-net salary credits are
     * company obligations and must never appear on any party statement.
     */
    public function isPayrollCompanyLiabilityLine(
        ?string $description,
        float $debit = 0,
        float $credit = 0,
    ): bool {
        $description = trim((string) $description);

        foreach ([
            'بیمه پرداختنی',
            'مالیات پرداختنی',
            'هزینه بیمه',
            'سایر کسورات حقوق',
        ] as $prefix) {
            if ($description !== '' && str_starts_with($description, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Supplier AP credits on 2101 are excluded from personnel view; treasury
     * payments (2101 debits) remain.
     */
    public function isExcludedFromEmployeePartyStatement(
        ?string $description,
        ?string $accountCode = null,
        float $debit = 0,
        float $credit = 0,
    ): bool {
        if ($this->isPayrollCompanyLiabilityLine($description, $debit, $credit)) {
            return true;
        }

        $accountCode = trim((string) $accountCode);

        if ($accountCode !== '' && str_starts_with($accountCode, '2101') && (float) $credit > 0 && (float) $debit <= 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, int|string>  $accountIds
     * @param  array<int, int|string>  $allowed
     *
     * @throws RuntimeException
     */
    public function assertValidAccounts(array $accountIds, array $allowed): void
    {
        $allowedPrefixes = array_map(
            static fn ($accountId) => (string) $accountId,
            array_values($allowed)
        );

        foreach ($accountIds as $accountId) {
            $accountId = (string) $accountId;
            $valid = false;

            foreach ($allowedPrefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($accountId, $prefix)) {
                    $valid = true;
                    break;
                }
            }

            if (! $valid) {
                throw new RuntimeException('Invalid account detected for party statement scope.');
            }
        }
    }
}
