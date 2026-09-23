<?php

namespace App\Services;

use App\Models\InsuranceLiability;
use App\Models\InsurancePayment;
use App\Models\InsurancePaymentLine;
use App\Models\PayrollAccountingSetting;
use App\Models\PayrollAudit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InsurancePaymentService
{
    public function __construct(
        private readonly InsuranceLiabilityService $liabilities,
        private readonly AccountingPostingService $accountingPosting,
        private readonly FiscalPeriodService $periods,
        private readonly NumberingService $numbering,
    ) {
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $header, array $lines, ?int $userId = null): InsurancePayment
    {
        return DB::transaction(function () use ($header, $lines, $userId) {
            $normalizedLines = $this->normalizeLines($lines);
            $paymentDate = (string) ($header['payment_date'] ?? now()->toDateString());
            $this->periods->ensureDateIsAllowed($paymentDate);

            $method = (string) ($header['method'] ?? 'bank');
            $bankAccountId = isset($header['bank_account_id']) && $header['bank_account_id'] !== ''
                ? (int) $header['bank_account_id']
                : null;
            $cashboxId = isset($header['cashbox_id']) && $header['cashbox_id'] !== ''
                ? (int) $header['cashbox_id']
                : null;

            if ($method === 'bank' && ! $bankAccountId) {
                throw new RuntimeException('برای پرداخت بانکی، انتخاب حساب بانکی الزامی است.');
            }

            if ($method === 'cash' && ! $cashboxId) {
                throw new RuntimeException('برای پرداخت نقدی، انتخاب صندوق الزامی است.');
            }

            $totals = $this->sumLines($normalizedLines);
            $fiscalYear = $this->periods->fiscalYearForDate($paymentDate);

            $payment = InsurancePayment::create([
                'number' => $header['number'] ?? $this->numbering->next('insurance_payment', 'IP-', $fiscalYear?->id),
                'payment_date' => $paymentDate,
                'method' => $method,
                'bank_account_id' => $method === 'bank' ? $bankAccountId : null,
                'cashbox_id' => $method === 'cash' ? $cashboxId : null,
                'total_amount' => $totals['total'],
                'principal_amount' => $totals['principal'],
                'penalty_amount' => $totals['penalty'],
                'other_amount' => $totals['other'],
                'reference_number' => $header['reference_number'] ?? null,
                'payment_identifier' => $header['payment_identifier'] ?? null,
                'receipt_number' => $header['receipt_number'] ?? null,
                'description' => $header['description'] ?? null,
                'created_by' => $userId,
                'status' => 'posted',
            ]);

            foreach ($normalizedLines as $line) {
                /** @var InsuranceLiability $liability */
                $liability = InsuranceLiability::query()
                    ->lockForUpdate()
                    ->with('period')
                    ->findOrFail($line['insurance_liability_id']);

                if ($liability->status === 'settled' && $line['total'] > 0.009) {
                    throw new RuntimeException('بدهی بیمه دوره «' . $liability->period->persian_title . '» قبلاً تسویه شده است.');
                }

                if ($line['total'] - $liability->remainingBalance() > 0.01) {
                    throw new RuntimeException('مبلغ پرداخت دوره «' . $liability->period->persian_title . '» بیشتر از مانده بدهی است.');
                }

                InsurancePaymentLine::create([
                    'insurance_payment_id' => $payment->id,
                    'insurance_period_id' => $liability->insurance_period_id,
                    'insurance_liability_id' => $liability->id,
                    'principal_amount' => $line['principal'],
                    'penalty_amount' => $line['penalty'],
                    'other_amount' => $line['other'],
                    'total_amount' => $line['total'],
                ]);

                $this->liabilities->applyPayment($liability, $line['total']);
            }

            $document = $this->accountingPosting->fromInsurancePayment($payment->refresh(), $userId);
            $payment->update(['accounting_document_id' => $document->id]);

            $this->audit($payment, 'payment', null, $payment->fresh(['lines.period'])->toArray(), $userId);

            return $payment->refresh(['lines.period', 'bankAccount', 'cashbox', 'accountingDocument', 'creator']);
        });
    }

    public function delete(InsurancePayment $payment, ?int $userId = null): void
    {
        DB::transaction(function () use ($payment, $userId): void {
            $payment->loadMissing('lines.liability');

            $this->accountingPosting->deleteSourceAccountingDocuments(
                InsurancePayment::class,
                $payment->id,
                $payment->accounting_document_id,
                $userId
            );

            foreach ($payment->lines as $line) {
                $this->liabilities->reversePayment($line->liability, (float) $line->total_amount);
            }

            $this->audit($payment, 'delete', $payment->toArray(), null, $userId);

            $payment->lines()->delete();
            $payment->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{insurance_liability_id:int,principal:float,penalty:float,other:float,total:float}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            if (empty($line['insurance_liability_id'])) {
                continue;
            }

            $principal = round(max(0, (float) ($line['principal_amount'] ?? 0)), 2);
            $penalty = round(max(0, (float) ($line['penalty_amount'] ?? 0)), 2);
            $other = round(max(0, (float) ($line['other_amount'] ?? 0)), 2);
            $total = round($principal + $penalty + $other, 2);

            if ($total <= 0) {
                continue;
            }

            $normalized[] = [
                'insurance_liability_id' => (int) $line['insurance_liability_id'],
                'principal' => $principal,
                'penalty' => $penalty,
                'other' => $other,
                'total' => $total,
            ];
        }

        if ($normalized === []) {
            throw new RuntimeException('حداقل یک دوره با مبلغ پرداخت معتبر انتخاب کنید.');
        }

        return $normalized;
    }

    /**
     * @param  list<array{principal:float,penalty:float,other:float,total:float}>  $lines
     * @return array{principal:float,penalty:float,other:float,total:float}
     */
    private function sumLines(array $lines): array
    {
        return [
            'principal' => round(collect($lines)->sum('principal'), 2),
            'penalty' => round(collect($lines)->sum('penalty'), 2),
            'other' => round(collect($lines)->sum('other'), 2),
            'total' => round(collect($lines)->sum('total'), 2),
        ];
    }

    public function accountCode(string $key, string $fallback): string
    {
        return PayrollAccountingSetting::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->value('account_code') ?: $fallback;
    }

    private function audit(InsurancePayment $payment, string $event, ?array $old, ?array $new, ?int $userId): void
    {
        PayrollAudit::create([
            'auditable_type' => InsurancePayment::class,
            'auditable_id' => $payment->id,
            'event' => $event,
            'user_id' => $userId,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
