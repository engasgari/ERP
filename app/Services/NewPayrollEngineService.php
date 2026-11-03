<?php

namespace App\Services;

use App\Exceptions\PayrollPrerequisiteException;
use App\Models\AccountingDocument;
use App\Models\AttendanceSummary;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\InsuranceRecord;
use App\Models\MonthlyAttendance;
use App\Models\PayrollAccountingEntry;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollItem;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollAccountingSetting;
use App\Models\Payslip;
use App\Models\Project;
use App\Models\ProjectCostSnapshot;
use App\Models\TaxRecord;
use App\Models\WorkLog;
use App\Services\Payroll\PayrollLegalParameterResolver;
use App\Services\Payroll\PayrollLineBasisCalculator;
use App\Services\Payroll\SalaryTaxBracketCalculator;
use App\Support\Hr\InsuranceExemptionResolver;
use App\Support\Hr\IranLaborEmploymentOrderCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NewPayrollEngineService
{
    public function __construct(
        private readonly NewAttendanceEngineService $attendanceEngine,
        private readonly AccountingPostingService $accountingPosting,
        private readonly InsuranceLiabilityService $insuranceLiabilities,
        private readonly ProjectCostingService $projectCosting,
        private readonly EmploymentContractResolver $contractResolver,
        private readonly PayslipSnapshotService $payslipSnapshot,
        private readonly PayrollLineBasisCalculator $lineBasis,
        private readonly PayrollLegalParameterResolver $legalParams,
        private readonly SalaryTaxBracketCalculator $taxBrackets,
    ) {
    }

    public function runFullLifecycle(PayrollPeriod $period): Collection
    {
        $this->assertAttendanceCalculated($period);

        return DB::transaction(function () use ($period) {
            $calculations = MonthlyAttendance::with(['employee.party', 'employee.employmentOrders'])
                ->where('payroll_period_id', $period->id)
                ->orderBy('employee_id')
                ->get()
                ->map(fn (MonthlyAttendance $attendance) => $this->calculateEmployee($period, $attendance));

            $this->postAccountingDocuments($period, $calculations);
            $this->insuranceLiabilities->syncFromPayrollPeriod($period);
            $this->syncProjectCostSnapshots($period);

            $period->update([
                'status' => 'calculated',
                'calculated_at' => now(),
            ]);

            return $calculations;
        });
    }

    public function calculateEmployee(PayrollPeriod $period, MonthlyAttendance $attendance): PayrollCalculation
    {
        $employee = $attendance->employee;
        $order = $this->approvedOrderFor($employee, $period);
        $attendanceSummaryId = $attendance->meta['attendance_summary_id'] ?? null;
        $contractType = $this->contractTypeFor($employee, $order);

        if (in_array($contractType, ['hourly', 'project'], true)) {
            return $this->calculateWorkBasedEmployee($period, $attendance, $order, $attendanceSummaryId, $contractType);
        }

        $failureReason = $this->payrollFailureReason($employee, $period, $attendance, $order, $attendanceSummaryId);

        if ($failureReason !== null) {
            return PayrollCalculation::updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                [
                    'personnel_decree_id' => $order?->id,
                    'monthly_attendance_id' => $attendance->id,
                    'attendance_summary_id' => $attendanceSummaryId,
                    'gross_salary' => 0,
                    'total_benefits' => 0,
                    'insurance_employee' => 0,
                    'insurance_employer' => 0,
                    'tax_amount' => 0,
                    'total_deductions' => 0,
                    'net_payable' => 0,
                    'status' => 'failed',
                    'failure_reason' => $failureReason,
                    'calculated_at' => now(),
                ]
            );
        }

        $legalSet = $this->legalParams->forPeriod($period);
        $premiums = $this->legalParams->statutoryPremiumDefaults();
        if ($legalSet) {
            $premiums['overtime_multiplier'] = (float) $legalSet->parameter('overtime_multiplier', $premiums['overtime_multiplier']);
            $premiums['night_premium_rate'] = (float) $legalSet->parameter('night_premium_rate', $premiums['night_premium_rate']);
            $premiums['holiday_work_multiplier'] = (float) $legalSet->parameter('holiday_work_multiplier', $premiums['holiday_work_multiplier']);
            $premiums['friday_work_multiplier'] = (float) $legalSet->parameter('friday_work_multiplier', $premiums['friday_work_multiplier']);
        }

        $hourlyRate = $this->hourlyRate($employee, $order, $period);
        $isHourly = $this->isHourlyEmployee($employee, $order);
        $insuranceEnabled = $this->insuranceEnabled($order);
        $overtimeRate = $hourlyRate * (float) $premiums['overtime_multiplier'];
        $nightRate = $hourlyRate * (float) $premiums['night_premium_rate'];
        $holidayRate = $hourlyRate * (float) $premiums['holiday_work_multiplier'];
        $fridayRate = $hourlyRate * (float) $premiums['friday_work_multiplier'];
        $fridayHours = (float) ($attendance->friday_hours ?? data_get($attendance->meta, 'friday_hours', 0));
        $netPayableHours = (float) ($attendance->net_payable_hours ?? $attendance->payable_hours ?? $attendance->normal_hours ?? 0);
        $baseSalary = $isHourly
            ? $netPayableHours * $hourlyRate
            : (float) ($order?->base_salary ?: $employee->base_salary ?: 0);

        // Explicit insurance/tax flags: never rely only on payroll_items rows (may be unseeded).
        // Mission: SSI practice — non-insurable expense-like; still taxable unless law says otherwise.
        $lines = collect([
            [
                'code' => 'base_salary',
                'title' => 'حقوق پایه',
                'type' => 'earning',
                'hours' => $isHourly ? $netPayableHours : 0,
                'rate' => $isHourly ? $hourlyRate : 0,
                'amount' => $baseSalary,
                'is_insurable' => true,
                'is_taxable' => true,
            ],
            ['code' => 'overtime', 'title' => 'اضافه‌کاری', 'type' => 'earning', 'hours' => (float) $attendance->overtime_hours, 'rate' => $overtimeRate, 'amount' => (float) $attendance->overtime_hours * $overtimeRate, 'is_insurable' => true, 'is_taxable' => true],
            ['code' => 'mission', 'title' => 'حق مأموریت', 'type' => 'earning', 'hours' => (float) $attendance->mission_hours, 'rate' => $hourlyRate, 'amount' => (float) $attendance->mission_hours * $hourlyRate, 'is_insurable' => false, 'is_taxable' => true],
            ['code' => 'night_shift', 'title' => 'شب‌کاری', 'type' => 'earning', 'hours' => (float) $attendance->night_hours, 'rate' => $nightRate, 'amount' => (float) $attendance->night_hours * $nightRate, 'is_insurable' => true, 'is_taxable' => true],
            ['code' => 'holiday_work', 'title' => 'تعطیل‌کاری رسمی', 'type' => 'earning', 'hours' => (float) $attendance->holiday_hours, 'rate' => $holidayRate, 'amount' => (float) $attendance->holiday_hours * $holidayRate, 'is_insurable' => true, 'is_taxable' => true],
            ['code' => 'friday_work', 'title' => 'جمعه‌کاری / استراحت هفتگی', 'type' => 'earning', 'hours' => $fridayHours, 'rate' => $fridayRate, 'amount' => $fridayHours * $fridayRate, 'is_insurable' => true, 'is_taxable' => true],
            ['code' => 'attendance_deduction', 'title' => 'کسر کارکرد', 'type' => 'deduction', 'hours' => $this->attendanceDeductionHours($attendance), 'rate' => $hourlyRate, 'amount' => $this->attendanceDeductionHours($attendance) * $hourlyRate],
        ]);

        if ($isHourly) {
            foreach ((array) ($order?->fixed_benefits ?? []) as $title => $amount) {
                if ((float) $amount > 0) {
                    $lines->push(['code' => 'custom_benefit', 'title' => (string) $title, 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) $amount]);
                }
            }

            foreach ((array) ($order?->fixed_deductions ?? []) as $title => $amount) {
                if ((float) $amount > 0) {
                    $lines->push(['code' => 'custom_deduction', 'title' => (string) $title, 'type' => 'deduction', 'hours' => 0, 'rate' => 0, 'amount' => (float) $amount]);
                }
            }

            $lines = $lines->filter(fn ($line) => (float) $line['amount'] > 0)->values();
            $finalized = $this->finalizePayrollLines($lines, $employee, $order, $attendance, $insuranceEnabled, $baseSalary, $period, $legalSet);
            $calculation = $this->persistFinalizedCalculation(
                $period,
                $employee,
                $attendance,
                $order,
                $attendanceSummaryId,
                $finalized,
                $baseSalary,
            );

            return $calculation;
        }

        if ($order) {
            $order->loadMissing('lines');
        }

        if ($order && $order->lines->isNotEmpty()) {
            foreach ($order->lines as $orderLine) {
                if ($orderLine->type === 'earning' && $orderLine->code === 'base_salary') {
                    continue;
                }

                if ((float) $orderLine->amount <= 0) {
                    continue;
                }

                $lines->push([
                    'code' => (string) ($orderLine->code ?: 'custom_'.$orderLine->type),
                    'title' => (string) $orderLine->title,
                    'type' => $orderLine->type === 'deduction' ? 'deduction' : 'earning',
                    'hours' => 0,
                    'rate' => 0,
                    'amount' => (float) $orderLine->amount,
                    'is_insurable' => (bool) $orderLine->is_insurable,
                    'is_taxable' => (bool) $orderLine->is_taxable,
                ]);
            }
        } else {
            $catalog = IranLaborEmploymentOrderCatalog::wageComponents();
            $stamp = static function (array $line) use ($catalog): array {
                $code = (string) ($line['code'] ?? '');
                $mapped = $code === 'seniority_monthly' ? 'seniority_pay'
                    : ($code === 'children_allowance' ? 'child_allowance' : $code);
                if (isset($catalog[$mapped])) {
                    $line['is_insurable'] = (bool) $catalog[$mapped]['insurable'];
                    $line['is_taxable'] = (bool) $catalog[$mapped]['taxable'];
                }

                return $line;
            };

            $lines->push(
                $stamp(['code' => 'housing_allowance', 'title' => 'حق مسکن', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => $this->allowanceAmount($order?->housing_allowance, $legalSet, 'housing_allowance_monthly', 'housing_allowance')]),
                $stamp(['code' => 'food_allowance', 'title' => 'بن کارگری', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => $this->allowanceAmount($order?->food_allowance, $legalSet, 'food_allowance_monthly', 'food_allowance')]),
                $stamp(['code' => 'child_allowance', 'title' => 'حق اولاد', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->child_allowance ?: 0)]),
                $stamp(['code' => 'transportation_allowance', 'title' => 'ایاب و ذهاب', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->transportation_allowance ?: 0)]),
            );

            collect([
                ['code' => 'children_allowance', 'title' => 'حق اولاد حکم', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => max(0, (float) ($order?->children_allowance ?? 0) - (float) ($order?->child_allowance ?? 0))],
                ['code' => 'seniority_monthly', 'title' => 'پایه سنوات', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->seniority_pay ?: 0)],
                ['code' => 'marriage_allowance', 'title' => 'حق تاهل', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->marriage_allowance ?: 0)],
                ['code' => 'job_allowance', 'title' => 'فوق‌العاده شغل', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->job_allowance ?: 0)],
                ['code' => 'hardship_allowance', 'title' => 'فوق‌العاده سختی کار', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->hardship_allowance ?: 0)],
                ['code' => 'shift_allowance', 'title' => 'فوق‌العاده نوبت‌کاری', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->shift_allowance ?: 0)],
                ['code' => 'other_insurable_benefits', 'title' => 'سایر مزایای مشمول بیمه', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->other_insurable_benefits ?: 0)],
                ['code' => 'other_non_insurable_benefits', 'title' => 'سایر مزایای غیرمشمول بیمه', 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) ($order?->other_non_insurable_benefits ?: 0)],
            ])->filter(fn ($line) => (float) $line['amount'] > 0)
                ->map($stamp)
                ->each(fn ($line) => $lines->push($line));

            foreach ((array) ($order?->fixed_benefits ?? []) as $title => $amount) {
                if ((float) $amount > 0) {
                    $lines->push(['code' => 'custom_benefit', 'title' => (string) $title, 'type' => 'earning', 'hours' => 0, 'rate' => 0, 'amount' => (float) $amount]);
                }
            }

            foreach ((array) ($order?->fixed_deductions ?? []) as $title => $amount) {
                if ((float) $amount > 0) {
                    $lines->push(['code' => 'custom_deduction', 'title' => (string) $title, 'type' => 'deduction', 'hours' => 0, 'rate' => 0, 'amount' => (float) $amount]);
                }
            }
        }

        $lines = $lines->filter(fn ($line) => (float) $line['amount'] > 0)->values();

        $finalized = $this->finalizePayrollLines($lines, $employee, $order, $attendance, $insuranceEnabled, $baseSalary, $period, $legalSet);

        return $this->persistFinalizedCalculation(
            $period,
            $employee,
            $attendance,
            $order,
            $attendanceSummaryId,
            $finalized,
            $baseSalary,
        );
    }

    private function calculateWorkBasedEmployee(
        PayrollPeriod $period,
        MonthlyAttendance $attendance,
        ?EmploymentOrder $order,
        mixed $attendanceSummaryId,
        string $contractType,
    ): PayrollCalculation {
        $employee = $attendance->employee;
        $hourlyRate = $this->hourlyRate($employee, $order, $period);
        $workedHours = (float) (
            $attendance->net_payable_hours
            ?? $attendance->payable_hours
            ?? $attendance->worked_hours
            ?? $attendance->normal_hours
            ?? 0
        );
        $gross = round($workedHours * $hourlyRate, 2);

        $calculation = PayrollCalculation::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'personnel_decree_id' => $order?->id,
                'monthly_attendance_id' => $attendance->id,
                'attendance_summary_id' => $attendanceSummaryId,
                'gross_salary' => $gross,
                'total_benefits' => 0,
                'insurance_employee' => 0,
                'insurance_employer' => 0,
                'tax_amount' => 0,
                'total_deductions' => 0,
                'net_payable' => $gross,
                'status' => 'calculated',
                'failure_reason' => null,
                'calculated_at' => now(),
            ]
        );

        $calculation->lines()->delete();
        $baseSalaryItem = PayrollItem::where('code', 'base_salary')->first();
        $calculation->lines()->create([
            'payroll_item_id' => $baseSalaryItem?->id,
            'code' => 'base_salary',
            'title' => 'حقوق پایه',
            'type' => 'earning',
            'hours' => $workedHours,
            'rate' => $hourlyRate,
            'amount' => $gross,
        ]);

        InsuranceRecord::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'insurance_days' => 0,
                'insurance_wage' => $gross,
                'employee_share' => 0,
                'employer_share' => 0,
                'unemployment_share' => 0,
            ]
        );

        TaxRecord::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'taxable_income' => $gross,
                'exemption_amount' => 0,
                'tax_amount' => 0,
                'brackets' => [],
            ]
        );

        $this->issuePayslip($calculation, $employee, $period, $order, [
            'net_payable' => $gross,
            'gross_salary' => $gross,
            'total_deductions' => 0,
        ]);

        PayrollAccountingEntry::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'entry_number' => 'PA-' . $period->year . '-' . str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
                'salary_expense_debit' => $gross,
                'insurance_expense_debit' => 0,
                'salary_payable_credit' => $gross,
                'insurance_payable_credit' => 0,
                'tax_payable_credit' => 0,
                'status' => $gross > 0 ? 'generated' : 'skipped',
                'lines' => [],
            ]
        );

        return $calculation->refresh();
    }

    /**
     * @param  array{net_payable: float|int, gross_salary?: float|int|null, total_deductions?: float|int|null}  $totals
     */
    private function issuePayslip(
        PayrollCalculation $calculation,
        Employee $employee,
        PayrollPeriod $period,
        ?EmploymentOrder $order,
        array $totals,
    ): Payslip {
        return Payslip::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'number' => 'PS-' . $period->year . '-' . str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
                'issued_at' => now(),
                'status' => 'issued',
                'snapshot' => $this->payslipSnapshot->build($employee, $period, $order, $totals),
            ]
        );
    }

    public function pay(PayrollCalculation $calculation, ?string $reference = null): PayrollPayment
    {
        $bankAccountId = BankAccount::query()->where('is_active', true)->value('id');
        $cashboxId = Cashbox::query()->where('is_active', true)->value('id');

        return $this->payWithDetails($calculation, [
            'payment_date' => now()->toDateString(),
            'amount' => $calculation->net_payable,
            'method' => $bankAccountId ? 'bank' : 'cash',
            'bank_account_id' => $bankAccountId,
            'cashbox_id' => $bankAccountId ? null : $cashboxId,
            'reference_number' => $reference ?: 'PAY-' . $calculation->id,
            'description' => 'پرداخت حقوق از موتور جدید',
        ]);
    }

    public function payWithDetails(PayrollCalculation $calculation, array $data, ?int $userId = null): PayrollPayment
    {
        $calculation->loadMissing('period', 'employee.party', 'payments');

        if (! in_array($calculation->period?->status, ['approved', 'closed'], true)) {
            throw new \RuntimeException('فقط حقوق دوره تایید شده یا بسته شده قابل پرداخت است.');
        }

        $alreadyPaid = (float) $calculation->payments->sum('amount');
        $remaining = round((float) $calculation->net_payable - $alreadyPaid, 2);
        $amount = round((float) ($data['amount'] ?? $remaining), 2);
        $method = (string) ($data['method'] ?? 'bank');
        $bankAccountId = isset($data['bank_account_id']) && $data['bank_account_id'] !== ''
            ? (int) $data['bank_account_id']
            : null;
        $cashboxId = isset($data['cashbox_id']) && $data['cashbox_id'] !== ''
            ? (int) $data['cashbox_id']
            : null;

        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ پرداخت باید بیشتر از صفر باشد.');
        }

        if ($amount - $remaining > 0.01) {
            throw new \RuntimeException('مبلغ پرداخت نمی‌تواند بیشتر از مانده حقوق باشد.');
        }

        if ($method === 'bank' && ! $bankAccountId) {
            throw new \RuntimeException('برای پرداخت بانکی، انتخاب حساب بانکی الزامی است.');
        }

        if ($method === 'cash' && ! $cashboxId) {
            throw new \RuntimeException('برای پرداخت نقدی، انتخاب صندوق الزامی است.');
        }

        $paymentDate = (string) ($data['payment_date'] ?? '');
        if ($paymentDate === '') {
            throw new \RuntimeException('تاریخ پرداخت الزامی است.');
        }

        $payment = PayrollPayment::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $calculation->employee_id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'method' => $method,
                'bank_account_id' => $method === 'bank' ? $bankAccountId : null,
                'cashbox_id' => $method === 'cash' ? $cashboxId : null,
                'reference_number' => $data['reference_number'] ?? ('PAY-' . $calculation->id),
                'status' => 'paid',
                'description' => $data['description'] ?? 'پرداخت حقوق از موتور جدید',
                'accounting_document_id' => null,
            ]
        );

        $payment = $payment->refresh();
        $document = $this->accountingPosting->fromPayrollPayment($payment, $userId);

        $payment->update(['accounting_document_id' => $document->id]);

        $remainingAfterPayment = round($remaining - $amount, 2);
        $calculation->update([
            'status' => $remainingAfterPayment <= 0.01 ? 'paid' : 'partial',
        ]);

        return $payment->refresh();
    }

    public function reversePayment(PayrollCalculation $calculation, ?int $userId = null): void
    {
        DB::transaction(function () use ($calculation, $userId): void {
            $calculation->loadMissing('period', 'payments.accountingDocument');

            if ($calculation->period?->status === 'closed') {
                throw new \RuntimeException('دوره حقوق بسته شده است. ابتدا دوره را باز کنید.');
            }

            $payment = $calculation->payments()->first();

            if (! $payment) {
                throw new \RuntimeException('پرداختی برای برگشت وجود ندارد.');
            }

            $this->accountingPosting->deleteSourceAccountingDocuments(
                PayrollPayment::class,
                $payment->id,
                $payment->accounting_document_id,
                $userId
            );

            $payment->delete();

            $remainingPaid = round((float) $calculation->payments()->sum('amount'), 2);
            $netPayable = round((float) $calculation->net_payable, 2);

            if ($remainingPaid <= 0.01) {
                $calculation->update(['status' => 'calculated']);
            } elseif ($remainingPaid + 0.01 < $netPayable) {
                $calculation->update(['status' => 'partial']);
            } else {
                $calculation->update(['status' => 'paid']);
            }
        });
    }

    public function repostAccountingForCalculation(PayrollCalculation $calculation): void
    {
        $calculation->loadMissing('period', 'employee');

        if (! $calculation->period) {
            return;
        }

        $this->postAccountingDocuments($calculation->period, collect([$calculation]));
    }

    private function postAccountingDocuments(PayrollPeriod $period, Collection $calculations): void
    {
        $calculations->each(function (PayrollCalculation $calculation) use ($period): void {
            if ((float) $calculation->gross_salary <= 0 && (float) $calculation->net_payable <= 0) {
                return;
            }

            $employee = $calculation->employee()->with('defaultProject', 'party')->first();
            if (! $employee) {
                return;
            }

            if (! $employee->party_id) {
                throw new \RuntimeException('برای ثبت سند حقوق، پرسنل «' . $employee->full_name . '» باید طرف حساب (Party) داشته باشد.');
            }

            $userId = auth()->id() ?? $calculation->approved_by ?? $period->created_by;

            $workLogs = WorkLog::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
                ->get();

            $projectAmounts = $this->projectAmountsFor($employee, $period, $calculation, $workLogs);
            $salaryExpenseCode = $this->accountCode('salary_expense', '5202');
            $salaryPayableCode = $this->accountCode('salary_payable', '2104');
            $insurancePayableCode = $this->accountCode('insurance_payable', '2104');
            $taxPayableCode = $this->accountCode('tax_payable', '2104');
            $insuranceExpenseCode = $this->accountCode('insurance_expense', '5202');

            $salaryExpenseTotal = (float) $calculation->gross_salary;
            $insuranceEmployer = (float) $calculation->insurance_employer;
            $insuranceEmployee = (float) $calculation->insurance_employee;
            $tax = (float) $calculation->tax_amount;
            $otherDeductions = max(0, (float) $calculation->total_deductions - $insuranceEmployee - $tax);
            $netPayable = (float) $calculation->net_payable;

            $lines = [];

            foreach ($projectAmounts as $projectId => $amount) {
                if ((float) $amount <= 0) {
                    continue;
                }

                $lines[] = $this->accountingPosting->line(
                    $salaryExpenseCode,
                    (float) $amount,
                    0,
                    'هزینه حقوق و دستمزد ' . $employee->full_name . ' - ' . $period->persian_title,
                    projectId: $projectId ?: null
                );
            }

            if ($insuranceEmployer > 0) {
                $lines[] = $this->accountingPosting->line(
                    $insuranceExpenseCode,
                    $insuranceEmployer,
                    0,
                    'هزینه بیمه سهم کارفرما ' . $employee->full_name . ' - ' . $period->persian_title,
                    projectId: $employee->default_project_id
                );
            }

            $lines[] = $this->accountingPosting->line(
                $salaryPayableCode,
                0,
                $netPayable,
                'حقوق پرداختنی ' . $employee->full_name . ' - ' . $period->persian_title,
                partyId: $employee->party_id,
                projectId: $employee->default_project_id
            );

            if ($insuranceEmployee + $insuranceEmployer > 0) {
                $lines[] = $this->accountingPosting->line(
                    $insurancePayableCode,
                    0,
                    $insuranceEmployee + $insuranceEmployer,
                    'بیمه پرداختنی ' . $employee->full_name . ' - ' . $period->persian_title,
                    projectId: $employee->default_project_id
                );
            }

            if ($tax > 0) {
                $lines[] = $this->accountingPosting->line(
                    $taxPayableCode,
                    0,
                    $tax,
                    'مالیات پرداختنی ' . $employee->full_name . ' - ' . $period->persian_title,
                    projectId: $employee->defaultProject?->id ?: $employee->default_project_id
                );
            }

            if ($otherDeductions > 0) {
                $lines[] = $this->accountingPosting->line(
                    $salaryPayableCode,
                    0,
                    $otherDeductions,
                    'سایر کسورات حقوق ' . $employee->full_name . ' - ' . $period->persian_title,
                    projectId: $employee->default_project_id
                );
            }

            $this->balanceAccountingLines($lines);

            $documentData = [
                'document_date' => $period->ends_at->toDateString(),
                'type' => AccountingDocument::TYPE_PAYROLL,
                'description' => 'ثبت حقوق ' . $employee->full_name . ' - ' . $period->persian_title,
                'number' => 'PRL-' . $period->year . '-' . str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT),
            ];

            $document = AccountingDocument::query()
                ->where('source_type', PayrollCalculation::class)
                ->where('source_id', $calculation->id)
                ->first();

            if ($document) {
                $document = $this->accountingPosting->updateManual($document, $documentData, $lines, $userId);
                $document->update([
                    'type' => AccountingDocument::TYPE_PAYROLL,
                    'status' => 'posted',
                    'source_type' => PayrollCalculation::class,
                    'source_id' => $calculation->id,
                    'posted_at' => $document->posted_at ?: now(),
                    'posted_by' => $document->posted_by ?: $userId,
                ]);
            } else {
                $document = $this->accountingPosting->createPostedMaintenance($documentData, $lines, $calculation, $userId);
                $document->update(['type' => AccountingDocument::TYPE_PAYROLL]);
            }

            PayrollAccountingEntry::where('payroll_calculation_id', $calculation->id)->update([
                'accounting_document_id' => $document->id,
                'status' => 'posted',
                'lines' => $document->lines->map(fn ($line) => [
                    'account' => $line->account?->code ?: null,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'project_id' => $line->project_id,
                    'description' => $line->description,
                ])->values()->all(),
            ]);
        });
    }

    private function contractTypeFor(Employee $employee, ?EmploymentOrder $order = null): string
    {
        return $this->contractResolver->resolve($employee, $order);
    }

    private function syncProjectCostSnapshots(PayrollPeriod $period): void
    {
        $projectIds = WorkLog::query()
            ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id');

        $defaultProjectIds = PayrollCalculation::with('employee.defaultProject')
            ->where('payroll_period_id', $period->id)
            ->get()
            ->map(fn (PayrollCalculation $calculation) => $calculation->employee?->default_project_id)
            ->filter()
            ->unique();

        $allProjectIds = $projectIds->merge($defaultProjectIds)->filter()->unique();

        Project::whereIn('id', $allProjectIds)->get()->each(function (Project $project): void {
            $summary = $this->projectCosting->summary($project);

            ProjectCostSnapshot::updateOrCreate(
                ['project_id' => $project->id],
                [
                    'material_cost' => $summary['material_cost'],
                    'labor_cost' => $summary['labor_cost'],
                    'service_cost' => $summary['service_cost'],
                    'overhead_cost' => $summary['overhead_cost'],
                    'total_cost' => $summary['total_cost'],
                    'revenue' => $summary['revenue'],
                    'gross_profit' => $summary['gross_profit'],
                    'profit_margin' => $summary['profit_margin'],
                    'calculated_at' => now(),
                ]
            );
        });
    }

    private function projectAmountsFor(Employee $employee, PayrollPeriod $period, PayrollCalculation $calculation, Collection $workLogs): Collection
    {
        $projectAmounts = $workLogs
            ->groupBy(fn (WorkLog $log) => (int) ($log->project_id ?: $employee->default_project_id ?: 0))
            ->map(function (Collection $logs) use ($employee): float {
                return (float) $logs->sum(function (WorkLog $log) use ($employee): float {
                    $rate = (float) ($log->hourly_rate ?: $employee->hourly_rate ?: 0);
                    $amount = (float) $log->total_amount;

                    if ($amount > 0) {
                        return $amount;
                    }

                    return round(((float) $log->hours) * $rate, 2);
                });
            })
            ->filter(fn (float $amount) => $amount > 0);

        if ($projectAmounts->isEmpty()) {
            return collect([(int) ($employee->default_project_id ?: 0) => (float) $calculation->gross_salary]);
        }

        $baseTotal = (float) $projectAmounts->sum();
        if ($baseTotal <= 0) {
            return collect([(int) ($employee->default_project_id ?: 0) => (float) $calculation->gross_salary]);
        }

        $targetTotal = (float) $calculation->gross_salary;
        $scaled = $projectAmounts->map(fn (float $amount) => round($targetTotal * ($amount / $baseTotal), 2));
        $difference = round($targetTotal - (float) $scaled->sum(), 2);
        $firstKey = $scaled->keys()->first();

        if ($firstKey !== null && abs($difference) >= 0.01) {
            $scaled[$firstKey] = round((float) $scaled[$firstKey] + $difference, 2);
        }

        return $scaled;
    }

    private function approvedOrderFor(Employee $employee, PayrollPeriod $period): ?EmploymentOrder
    {
        $orders = $employee->relationLoaded('employmentOrders')
            ? $employee->employmentOrders
            : $employee->employmentOrders()->get();

        return $orders
            ->where('status', 'approved')
            ->filter(function (EmploymentOrder $order) use ($period): bool {
                return $order->effective_date
                    && $order->effective_date->lte($period->ends_at)
                    && (! $order->end_date || $order->end_date->gte($period->starts_at));
            })
            ->sortByDesc('effective_date')
            ->sortByDesc('id')
            ->first();
    }

    private function assertAttendanceCalculated(PayrollPeriod $period): void
    {
        $count = AttendanceSummary::query()
            ->where('payroll_period_id', $period->id)
            ->whereIn('status', ['calculated', 'adjusted'])
            ->count();

        if ($count === 0) {
            throw new PayrollPrerequisiteException('ابتدا محاسبه کارکرد این دوره را انجام دهید؛ محاسبه حقوق بدون خلاصه کارکرد مجاز نیست.');
        }
    }

    private function payrollFailureReason(Employee $employee, PayrollPeriod $period, MonthlyAttendance $attendance, ?EmploymentOrder $order, mixed $attendanceSummaryId): ?string
    {
        if (! $attendanceSummaryId || ! AttendanceSummary::whereKey($attendanceSummaryId)->whereIn('status', ['calculated', 'adjusted'])->exists()) {
            return 'خلاصه کارکرد تایید/محاسبه شده برای پرسنل وجود ندارد.';
        }

        if (! $order) {
            return 'حکم کارگزینی فعال برای دوره حقوق پیدا نشد.';
        }

        if (! $order->employment_type && ! $employee->employment_type && ! $employee->salary_type) {
            return 'نوع استخدام/حقوق پرسنل مشخص نیست.';
        }

        $isHourly = $this->isHourlyEmployee($employee, $order);
        if ($isHourly && (float) ($order->hourly_rate ?: $employee->hourly_rate) <= 0) {
            return 'نرخ ساعتی در حکم یا پرونده پرسنل ثبت نشده است.';
        }

        if (! $isHourly && (float) ($order->base_salary ?: $employee->base_salary) <= 0) {
            return 'حقوق پایه ماهانه در حکم یا پرونده پرسنل ثبت نشده است.';
        }

        return null;
    }

    private function hourlyRate(Employee $employee, ?EmploymentOrder $order, ?PayrollPeriod $period = null): float
    {
        if ((float) ($order?->hourly_rate ?? 0) > 0) {
            return (float) $order->hourly_rate;
        }

        if ((float) $employee->hourly_rate > 0) {
            return (float) $employee->hourly_rate;
        }

        if ($period) {
            $periodWorkLogs = WorkLog::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$period->starts_at, $period->ends_at])
                ->get();

            $loggedRates = $periodWorkLogs
                ->pluck('hourly_rate')
                ->filter(fn ($rate) => (float) $rate > 0);

            if ($loggedRates->isNotEmpty()) {
                return round((float) $loggedRates->avg(), 2);
            }

            $totalHours = (float) $periodWorkLogs->sum('hours');
            $totalAmount = (float) $periodWorkLogs->sum('total_amount');

            if ($totalHours > 0 && $totalAmount > 0) {
                return round($totalAmount / $totalHours, 2);
            }
        }

        return round(((float) ($order?->base_salary ?: $employee->base_salary ?: 0)) / 220, 2);
    }

    private function isHourlyEmployee(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        if ($order) {
            return in_array($order->employment_type, ['hourly', 'hourly_contract'], true)
                || ((float) ($order->hourly_rate ?? 0) > 0 && (float) ($order->base_salary ?? 0) <= 0);
        }

        return $employee->salary_type === 'hourly'
            || $employee->employment_type === 'hourly';
    }

    private function itemAmount(string $code): float
    {
        return (float) PayrollItem::where('code', $code)->value('default_amount');
    }

    /**
     * Order amount → year legal parameter → payroll_items catalog fallback.
     */
    private function allowanceAmount(
        mixed $orderAmount,
        ?\App\Models\PayrollLegalParameterSet $legalSet,
        string $legalKey,
        string $itemCode,
    ): float {
        $fromOrder = (float) ($orderAmount ?: 0);
        if ($fromOrder > 0) {
            return $fromOrder;
        }

        $fromLegal = $legalSet ? (float) $legalSet->parameter($legalKey, 0) : 0.0;
        if ($fromLegal > 0) {
            return $fromLegal;
        }

        return $this->itemAmount($itemCode);
    }

    private function itemRate(string $code): float
    {
        return (float) PayrollItem::where('code', $code)->value('default_rate');
    }

    private function insuranceEnabled(?EmploymentOrder $order): bool
    {
        $status = strtolower((string) ($order?->insurance_status ?: 'insured'));

        return ! in_array($status, ['not_insured', 'exempt'], true);
    }

    private function unemploymentInsuranceEnabled(Employee $employee, ?EmploymentOrder $order): bool
    {
        if (! $this->insuranceEnabled($order)) {
            return false;
        }

        return ! InsuranceExemptionResolver::isExemptFromUnemployment($employee, $order);
    }

    public function recalculateAfterManualLineChanges(PayrollCalculation $calculation): PayrollCalculation
    {
        $calculation->loadMissing(['lines', 'employee', 'personnelDecree', 'attendance', 'period']);

        $lockedCodes = ['insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'];
        $lines = $calculation->lines
            ->reject(fn (PayrollCalculationLine $line): bool => in_array((string) $line->code, $lockedCodes, true))
            ->map(fn (PayrollCalculationLine $line): array => [
                'code' => (string) $line->code,
                'title' => (string) $line->title,
                'type' => (string) $line->type,
                'hours' => (float) $line->hours,
                'rate' => (float) $line->rate,
                'amount' => (float) $line->amount,
                'payroll_item_id' => $line->payroll_item_id,
                'meta' => $line->meta ?? [],
            ])
            ->values();

        $order = $calculation->personnelDecree;
        $employee = $calculation->employee;
        $attendance = $calculation->attendance;
        $insuranceEnabled = $this->insuranceEnabled($order);
        $baseSalary = round((float) $lines->where('code', 'base_salary')->sum('amount'), 2);

        $period = $calculation->period;
        $legalSet = $period ? $this->legalParams->forPeriod($period) : null;

        $finalized = $this->finalizePayrollLines(
            $lines,
            $employee,
            $order,
            $attendance ?? new MonthlyAttendance(),
            $insuranceEnabled,
            $baseSalary,
            $period,
            $legalSet,
        );

        return $this->persistFinalizedCalculation(
            $period,
            $employee,
            $attendance ?? new MonthlyAttendance(),
            $order,
            $calculation->attendance_summary_id,
            $finalized,
            $baseSalary,
            $calculation,
        )->fresh(['lines', 'payslip', 'accountingEntry']);
    }

    /**
     * @param  array<string, mixed>  $finalized
     */
    private function persistFinalizedCalculation(
        PayrollPeriod $period,
        Employee $employee,
        MonthlyAttendance $attendance,
        ?EmploymentOrder $order,
        mixed $attendanceSummaryId,
        array $finalized,
        float $baseSalary,
        ?PayrollCalculation $existing = null,
    ): PayrollCalculation {
        $insuranceEnabled = $this->insuranceEnabled($order);
        $lines = $finalized['lines'];

        $calculation = PayrollCalculation::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'personnel_decree_id' => $order?->id,
                'monthly_attendance_id' => $attendance->id ?: $existing?->monthly_attendance_id,
                'attendance_summary_id' => $attendanceSummaryId,
                'gross_salary' => $finalized['gross'],
                'total_benefits' => max(0, $finalized['gross'] - $baseSalary),
                'insurance_employee' => $finalized['insurance_employee'],
                'insurance_employer' => $finalized['insurance_employer'],
                'tax_amount' => $finalized['tax_amount'],
                'total_deductions' => $finalized['total_deductions'],
                'net_payable' => $finalized['net_payable'],
                'status' => 'calculated',
                'failure_reason' => null,
                'calculated_at' => now(),
            ]
        );

        $calculation->lines()->delete();
        $itemsByCode = PayrollItem::whereIn('code', $lines->pluck('code'))->get()->keyBy('code');
        $lines->each(function (array $line) use ($calculation, $itemsByCode): void {
            $calculation->lines()->create($line + [
                'payroll_item_id' => $line['payroll_item_id'] ?? $itemsByCode->get($line['code'])?->id,
            ]);
        });

        InsuranceRecord::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'insurance_days' => $insuranceEnabled
                    ? min(30, (int) $attendance->present_days + (int) floor((float) $attendance->leave_hours / 8))
                    : 0,
                'insurance_wage' => $insuranceEnabled ? $finalized['insurance_base'] : 0,
                'employee_share' => $finalized['insurance_employee'],
                'employer_share' => $finalized['employer_insurance_share'],
                'unemployment_share' => $finalized['unemployment_share'],
            ]
        );

        TaxRecord::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'taxable_income' => $finalized['tax_base'],
                'exemption_amount' => $finalized['tax_exemption'] ?? 0,
                'tax_amount' => $finalized['tax_amount'],
                'brackets' => $finalized['tax_brackets'] ?? [],
            ]
        );

        $this->issuePayslip($calculation, $employee, $period, $order, $this->payslipTotalsPayload($finalized));

        PayrollAccountingEntry::updateOrCreate(
            ['payroll_calculation_id' => $calculation->id],
            [
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'entry_number' => $calculation->accountingEntry?->entry_number
                    ?: ('PA-' . $period->year . '-' . str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT)),
                'salary_expense_debit' => $finalized['gross'],
                'insurance_expense_debit' => $finalized['insurance_employer'],
                'salary_payable_credit' => $finalized['net_payable'],
                'insurance_payable_credit' => $finalized['insurance_employee'] + $finalized['insurance_employer'],
                'tax_payable_credit' => $finalized['tax_amount'],
                'status' => $finalized['gross'] > 0 ? 'generated' : 'skipped',
                'lines' => [],
            ]
        );

        return $calculation->refresh();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{
     *   lines: Collection<int, array<string, mixed>>,
     *   gross: float,
     *   insurance_base: float,
     *   tax_base: float,
     *   non_insurance_earnings: float,
     *   non_tax_earnings: float,
     *   insurance_employee: float,
     *   insurance_employer: float,
     *   employer_insurance_share: float,
     *   unemployment_share: float,
     *   tax_amount: float,
     *   total_deductions: float,
     *   net_payable: float
     * }
     */
    private function finalizePayrollLines(
        Collection $lines,
        Employee $employee,
        ?EmploymentOrder $order,
        MonthlyAttendance $attendance,
        bool $insuranceEnabled,
        float $baseSalary,
        ?PayrollPeriod $period = null,
        ?\App\Models\PayrollLegalParameterSet $legalSet = null,
    ): array {
        $basis = $this->lineBasis->summarize($lines);
        $gross = $basis['gross'];
        $insurableWage = $basis['insurance_base'];
        $taxableWage = $basis['tax_base'];

        $daysInMonth = 30;
        if ($period?->starts_at && $period?->ends_at) {
            $daysInMonth = max(1, $period->starts_at->diffInDays($period->ends_at) + 1);
        }

        $ceiling = $this->legalParams->insuranceCeilingMonthly($legalSet, $daysInMonth);
        if ($ceiling !== null && $insurableWage > $ceiling) {
            $insurableWage = $ceiling;
        }

        $employeeRate = $legalSet
            ? (float) $legalSet->parameter('employee_insurance_rate', $this->itemRate('employee_insurance'))
            : $this->itemRate('employee_insurance');
        $employerInsuranceRate = $legalSet
            ? (float) $legalSet->parameter('employer_insurance_rate', $this->itemRate('employer_insurance'))
            : $this->itemRate('employer_insurance');
        $unemploymentRate = $this->unemploymentInsuranceEnabled($employee, $order)
            ? ($legalSet
                ? (float) $legalSet->parameter('unemployment_insurance_rate', $this->itemRate('unemployment_insurance'))
                : $this->itemRate('unemployment_insurance'))
            : 0.0;

        $insuranceEmployee = $insuranceEnabled
            ? round($insurableWage * ($employeeRate / 100), 2)
            : 0.0;
        $employerShare = $insuranceEnabled
            ? round($insurableWage * ($employerInsuranceRate / 100), 2)
            : 0.0;
        $unemploymentShare = $insuranceEnabled && $unemploymentRate > 0
            ? round($insurableWage * ($unemploymentRate / 100), 2)
            : 0.0;
        $insuranceEmployer = round($employerShare + $unemploymentShare, 2);

        $taxResult = $this->resolveTax($taxableWage, $legalSet);
        $tax = $taxResult['tax_amount'];

        $lines = $lines->values();
        if ($insuranceEnabled && $insuranceEmployee > 0) {
            $lines->push(['code' => 'insurance', 'title' => 'بیمه سهم کارمند', 'type' => 'deduction', 'hours' => 0, 'rate' => $employeeRate, 'amount' => $insuranceEmployee]);
        }
        if ($tax > 0 || ($taxResult['mode'] ?? '') === 'progressive') {
            $lines->push(['code' => 'tax', 'title' => 'مالیات حقوق', 'type' => 'deduction', 'hours' => 0, 'rate' => 0, 'amount' => $tax]);
        }

        $totalDeductions = (float) $lines->where('type', 'deduction')->sum('amount');
        if ($totalDeductions > $gross) {
            $excess = $totalDeductions - $gross;
            $lines = $lines->map(function (array $line) use (&$excess): array {
                if ($excess <= 0 || $line['code'] !== 'attendance_deduction') {
                    return $line;
                }

                $reduction = min((float) $line['amount'], $excess);
                $line['amount'] = round((float) $line['amount'] - $reduction, 2);
                $excess -= $reduction;

                return $line;
            });
            $totalDeductions = (float) $lines->where('type', 'deduction')->sum('amount');
        }

        $netPayable = round($gross - $totalDeductions, 2);

        return [
            'lines' => $lines,
            'gross' => $gross,
            'insurance_base' => $insurableWage,
            'tax_base' => $taxableWage,
            'non_insurance_earnings' => $basis['non_insurance_earnings'],
            'non_tax_earnings' => $basis['non_tax_earnings'],
            'insurance_employee' => $insuranceEmployee,
            'insurance_employer' => $insuranceEmployer,
            'employer_insurance_share' => $employerShare,
            'unemployment_share' => $unemploymentShare,
            'tax_amount' => $tax,
            'tax_exemption' => $taxResult['exemption_amount'] ?? 0,
            'tax_brackets' => $taxResult['brackets_applied'] ?? [],
            'tax_mode' => $taxResult['mode'] ?? 'legacy_flat',
            'total_deductions' => $totalDeductions,
            'net_payable' => $netPayable,
        ];
    }

    /**
     * @return array{
     *   tax_amount: float,
     *   exemption_amount: float,
     *   brackets_applied: list<array<string, mixed>>,
     *   mode: string
     * }
     */
    private function resolveTax(float $taxableBase, ?\App\Models\PayrollLegalParameterSet $legalSet): array
    {
        $brackets = $legalSet?->parameter('tax_brackets_monthly');

        if (is_array($brackets) && $brackets !== []) {
            $result = $this->taxBrackets->calculateMonthly($taxableBase, $brackets);

            return $result + [
                'mode' => 'progressive',
                'source_ref' => $legalSet?->source_ref,
                'jalali_year' => $legalSet?->jalali_year,
            ];
        }

        // No versioned brackets for this year: keep legacy flat rate from payroll_items
        // and mark mode so finance can see it is provisional (do not invent 1405 rates).
        $flatRate = $this->itemRate('salary_tax');
        $tax = round(max(0, $taxableBase) * ($flatRate / 100), 2);

        return [
            'tax_amount' => $tax,
            'exemption_amount' => 0,
            'brackets_applied' => [[
                'from' => 0,
                'to' => null,
                'rate' => $flatRate,
                'taxable_slice' => max(0, $taxableBase),
                'tax' => $tax,
                'note' => 'legacy_flat_rate_pending_legal_parameter_set',
            ]],
            'mode' => 'legacy_flat',
        ];
    }

    /**
     * @param  array<string, mixed>  $finalized
     * @return array<string, mixed>
     */
    private function payslipTotalsPayload(array $finalized): array
    {
        return [
            'net_payable' => $finalized['net_payable'],
            'gross_salary' => $finalized['gross'],
            'total_deductions' => $finalized['total_deductions'],
            'insurance_base' => $finalized['insurance_base'],
            'tax_base' => $finalized['tax_base'],
            'non_insurance_earnings' => $finalized['non_insurance_earnings'],
            'non_tax_earnings' => $finalized['non_tax_earnings'],
            'insurance_employee' => $finalized['insurance_employee'],
            'insurance_employer' => $finalized['insurance_employer'],
        ];
    }

    private function accountCode(string $key, string $fallback): string
    {
        return PayrollAccountingSetting::where('key', $key)
            ->where('is_active', true)
            ->value('account_code') ?: $fallback;
    }

    private function attendanceDeductionHours(MonthlyAttendance $attendance): float
    {
        $plannedHours = max(0, (float) ($attendance->required_hours ?? 0));
        $latenessHours = max(
            0,
            (float) ($attendance->delay_hours ?? 0) + (float) ($attendance->early_leave_hours ?? 0)
        );
        $absenceHours = max(0, (float) ($attendance->absence_hours ?? 0));

        return round(min($plannedHours > 0 ? $plannedHours : max($latenessHours, $absenceHours), max($latenessHours, $absenceHours)), 2);
    }

    /**
     * Keep the generated journal balanced after rounding the allocation lines.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function balanceAccountingLines(array &$lines): void
    {
        $debit = collect($lines)->sum(fn (array $line) => (float) ($line['debit'] ?? 0));
        $credit = collect($lines)->sum(fn (array $line) => (float) ($line['credit'] ?? 0));
        $difference = round($debit - $credit, 2);

        if (abs($difference) < 0.01) {
            return;
        }

        if ($difference > 0) {
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if ((float) ($lines[$i]['credit'] ?? 0) > 0) {
                    $lines[$i]['credit'] = round((float) $lines[$i]['credit'] + $difference, 2);
                    return;
                }
            }
        }

        $difference = abs($difference);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if ((float) ($lines[$i]['debit'] ?? 0) > 0) {
                $lines[$i]['debit'] = round((float) $lines[$i]['debit'] + $difference, 2);
                return;
            }
        }
    }
}
