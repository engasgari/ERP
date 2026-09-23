<?php

namespace App\Services;

use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollAdjustmentService
{
    /** @var list<string> */
    private const LOCKED_CODES = [
        'insurance',
        'tax',
        'employee_insurance',
        'employer_insurance',
        'salary_tax',
    ];

    public function __construct(
        private readonly NewPayrollEngineService $payrollEngine,
        private readonly PayslipSnapshotService $payslipSnapshot,
    ) {
    }

    /**
     * @param  array<int, array{id?: int|null, code?: string|null, title?: string|null, type?: string|null, amount: float|int|string}>  $lines
     */
    public function update(PayrollCalculation $calculation, array $lines, ?int $userId = null): PayrollCalculation
    {
        return DB::transaction(function () use ($calculation, $lines, $userId) {
            $calculation->loadMissing(['period', 'employee', 'lines', 'payments', 'payslip', 'personnelDecree']);

            $this->assertEditable($calculation);

            $normalized = $this->normalizeLines($calculation, $lines);
            $this->persistLines($calculation, $normalized);

            $fresh = $this->payrollEngine->recalculateAfterManualLineChanges(
                $calculation->fresh(['employee', 'period', 'personnelDecree', 'payslip', 'lines', 'attendance', 'accountingEntry'])
            );

            if ((float) $fresh->net_payable < 0) {
                throw ValidationException::withMessages([
                    'lines' => 'خالص حقوق نمی‌تواند منفی باشد. مبلغ کسورات را کاهش دهید.',
                ]);
            }

            $fresh->update([
                'status' => $calculation->status === 'failed' ? 'failed' : 'calculated',
                'failure_reason' => null,
            ]);

            $this->syncPayslip($fresh->fresh(['employee', 'period', 'personnelDecree', 'payslip']));
            $this->payrollEngine->repostAccountingForCalculation($fresh);

            return $fresh->fresh(['employee.party', 'period', 'lines', 'payslip', 'accountingEntry', 'accountingDocument', 'payments']);
        });
    }

    private function assertEditable(PayrollCalculation $calculation): void
    {
        if (in_array($calculation->period?->status, ['closed'], true)) {
            throw new \RuntimeException('دوره بسته‌شده قابل اصلاح دستی نیست.');
        }

        if ($calculation->payments()->exists()) {
            throw new \RuntimeException('برای حقوقی که پرداخت ثبت شده، اصلاح دستی مجاز نیست.');
        }

        if (in_array($calculation->status, ['paid', 'partial'], true)) {
            throw new \RuntimeException('حقوق پرداخت‌شده قابل اصلاح نیست.');
        }

        if ($calculation->status === 'failed') {
            throw new \RuntimeException('محاسبه ناموفق قابل اصلاح نیست؛ ابتدا محاسبه را اصلاح کنید.');
        }
    }

    /**
     * @param  array<int, array{id?: int|null, code?: string|null, title?: string|null, type?: string|null, amount: float|int|string}>  $inputLines
     * @return list<array{id: ?int, code: string, title: string, type: string, hours: float, rate: float, amount: float, payroll_item_id: ?int, meta: array<string, mixed>}>
     */
    private function normalizeLines(PayrollCalculation $calculation, array $inputLines): array
    {
        $existingById = $calculation->lines->keyBy('id');
        $result = [];

        foreach ($inputLines as $index => $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount < 0) {
                throw ValidationException::withMessages([
                    "lines.$index.amount" => 'مبالغ حقوق نمی‌توانند منفی باشند.',
                ]);
            }

            $lineId = isset($row['id']) ? (int) $row['id'] : null;
            $existing = $lineId ? $existingById->get($lineId) : null;

            if ($existing) {
                $code = (string) $existing->code;
                $locked = in_array($code, self::LOCKED_CODES, true);
                $result[] = [
                    'id' => $existing->id,
                    'code' => $code,
                    'title' => (string) $existing->title,
                    'type' => (string) $existing->type,
                    'hours' => (float) $existing->hours,
                    'rate' => (float) $existing->rate,
                    'amount' => $locked ? (float) $existing->amount : $amount,
                    'payroll_item_id' => $existing->payroll_item_id,
                    'meta' => array_merge($existing->meta ?? [], [
                        'manually_adjusted' => ! $locked && abs($amount - (float) $existing->amount) > 0.009,
                    ]),
                ];

                continue;
            }

            $code = trim((string) ($row['code'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $type = (string) ($row['type'] ?? 'earning');

            if ($amount <= 0) {
                continue;
            }

            if ($code === '' || $title === '' || ! in_array($type, ['earning', 'deduction'], true)) {
                throw ValidationException::withMessages([
                    "lines.$index" => 'برای ردیف جدید، کد، عنوان و نوع الزامی است.',
                ]);
            }

            if (in_array($code, self::LOCKED_CODES, true)) {
                throw ValidationException::withMessages([
                    "lines.$index.code" => 'امکان افزودن ردیف سیستم‌محور (بیمه/مالیات) وجود ندارد.',
                ]);
            }

            $item = PayrollItem::query()->where('code', $code)->first();

            $result[] = [
                'id' => null,
                'code' => $code,
                'title' => $title,
                'type' => $type,
                'hours' => 0.0,
                'rate' => 0.0,
                'amount' => $amount,
                'payroll_item_id' => $item?->id,
                'meta' => ['manually_adjusted' => true, 'manual_line' => true],
            ];
        }

        // Keep locked system lines that were omitted from payload.
        foreach ($calculation->lines as $existing) {
            if (! in_array((string) $existing->code, self::LOCKED_CODES, true)) {
                continue;
            }

            $already = collect($result)->contains(fn (array $line): bool => (int) ($line['id'] ?? 0) === (int) $existing->id);
            if ($already) {
                continue;
            }

            $result[] = [
                'id' => $existing->id,
                'code' => (string) $existing->code,
                'title' => (string) $existing->title,
                'type' => (string) $existing->type,
                'hours' => (float) $existing->hours,
                'rate' => (float) $existing->rate,
                'amount' => (float) $existing->amount,
                'payroll_item_id' => $existing->payroll_item_id,
                'meta' => $existing->meta ?? [],
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{id: ?int, code: string, title: string, type: string, hours: float, rate: float, amount: float, payroll_item_id: ?int, meta: array<string, mixed>}>  $lines
     */
    private function persistLines(PayrollCalculation $calculation, array $lines): void
    {
        $keptIds = [];

        foreach ($lines as $line) {
            if ($line['id']) {
                PayrollCalculationLine::query()
                    ->where('payroll_calculation_id', $calculation->id)
                    ->whereKey($line['id'])
                    ->update([
                        'amount' => $line['amount'],
                        'meta' => $line['meta'],
                    ]);
                $keptIds[] = (int) $line['id'];

                continue;
            }

            $created = $calculation->lines()->create([
                'payroll_item_id' => $line['payroll_item_id'],
                'code' => $line['code'],
                'title' => $line['title'],
                'type' => $line['type'],
                'hours' => $line['hours'],
                'rate' => $line['rate'],
                'amount' => $line['amount'],
                'meta' => $line['meta'],
            ]);
            $keptIds[] = (int) $created->id;
        }

        $calculation->lines()
            ->whereNotIn('id', $keptIds)
            ->whereNotIn('code', self::LOCKED_CODES)
            ->delete();
    }

    private function syncPayslip(PayrollCalculation $calculation): void
    {
        $payslip = $calculation->payslip;
        if (! $payslip) {
            return;
        }

        $snapshot = $this->payslipSnapshot->build(
            $calculation->employee,
            $calculation->period,
            $calculation->personnelDecree,
            [
                'net_payable' => (float) $calculation->net_payable,
                'gross_salary' => (float) $calculation->gross_salary,
                'total_deductions' => (float) $calculation->total_deductions,
            ]
        );

        $payslip->update([
            'snapshot' => array_merge(is_array($payslip->snapshot) ? $payslip->snapshot : [], $snapshot, [
                'manually_adjusted' => true,
                'adjusted_at' => now()->toDateTimeString(),
            ]),
        ]);
    }
}
