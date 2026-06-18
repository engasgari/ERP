<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\AccountingDocument;
use App\Models\EmployeeTransaction;
use App\Models\InsuranceRecord;
use App\Models\MonthlyAttendance;
use App\Models\PayrollAccountingEntry;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\PayrollAudit;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payment;
use App\Models\PayrollPayment;
use App\Models\Payslip;
use App\Models\TaxRecord;
use App\Models\Salary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    public function __construct(private readonly AttendanceCalculationService $attendanceService)
    {
    }

    public function createOrGetPeriod(int $year, int $month, ?int $userId = null): PayrollPeriod
    {
        $range = jalaliMonthRangeGregorianSafe($year, $month);

        return PayrollPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'title' => getPersianMonthName($month) . ' ' . $year,
                'starts_at' => $range['start']->toDateString(),
                'ends_at' => $range['end']->toDateString(),
                'status' => 'draft',
                'created_by' => $userId,
            ]
        );
    }

    public function calculate(PayrollPeriod $period, ?int $userId = null): Collection
    {
        $attendanceRows = $this->attendanceService->calculatePeriod($period)->keyBy('employee_id');
        $activeItems = PayrollItem::where('is_active', true)->orderBy('sort_order')->get();

        return DB::transaction(function () use ($period, $attendanceRows, $activeItems, $userId) {
            $salaries = Employee::query()
                ->where(function ($query) {
                    $query->where('is_active', true)->orWhere('status', 'active');
                })
                ->with(['party', 'employmentOrders'])
                ->get()
                ->map(function (Employee $employee) use ($period, $attendanceRows, $activeItems) {
                    $attendance = $attendanceRows->get($employee->id);
                    $order = $this->approvedOrderFor($employee, $period);
                    $isHourly = $this->isHourlyEmployee($employee, $order);

                    $hourlyRate = (float) ($attendance?->hourly_rate ?: $order?->hourly_rate ?: $employee->hourly_rate);
                    $baseSalary = $this->baseSalaryFor($employee, $order, $attendance, $isHourly);
                    $overtimeHours = (float) ($attendance?->overtime_hours ?? 0);
                    $overtimeRate = (float) ($attendance?->meta['overtime_rate'] ?? ($hourlyRate * 1.4));
                    $overtimeSalary = $overtimeHours * $overtimeRate;
                    $missionHours = (float) ($attendance?->mission_hours ?? 0);
                    $missionSalary = $missionHours * $hourlyRate;
                    $attendanceDeduction = (
                        (float) ($attendance?->delay_hours ?? 0)
                        + (float) ($attendance?->early_leave_hours ?? 0)
                        + (float) ($attendance?->absence_hours ?? 0)
                    ) * $hourlyRate;

                    [$earningLines, $deductionLines] = $this->buildLines($employee, $order, $activeItems, $baseSalary + $overtimeSalary + $missionSalary, $baseSalary, $overtimeSalary, $missionSalary, $attendanceDeduction);
                    $benefits = collect($earningLines)->whereNotIn('code', ['base_salary', 'overtime', 'mission'])->sum('amount');
                    $grossSalary = $baseSalary + $overtimeSalary + $missionSalary + $benefits;
                    $insuranceAmount = collect($deductionLines)->where('code', 'employee_insurance')->sum('amount');
                    $taxAmount = collect($deductionLines)->where('code', 'salary_tax')->sum('amount');
                    $loanAmount = collect($deductionLines)->where('code', 'loan')->sum('amount');
                    $deduction = collect($deductionLines)->whereNotIn('code', ['employee_insurance', 'salary_tax', 'loan'])->sum('amount');
                    $totalDeductions = collect($deductionLines)->sum('amount');

                    $salary = Salary::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'year' => $period->year,
                            'month' => $period->month,
                        ],
                        [
                            'payroll_period_id' => $period->id,
                            'total_hours' => (float) ($attendance?->payable_hours ?? 0),
                            'hourly_rate' => $hourlyRate,
                            'base_salary' => $baseSalary,
                            'overtime_hours' => $overtimeHours,
                            'overtime_rate' => $overtimeRate,
                            'overtime_salary' => $overtimeSalary,
                            'bonus' => collect($earningLines)->where('code', 'bonus')->sum('amount'),
                            'benefits' => $benefits,
                            'gross_salary' => $grossSalary,
                            'deduction' => $deduction,
                            'insurance_amount' => $insuranceAmount,
                            'tax_amount' => $taxAmount,
                            'loan_amount' => $loanAmount,
                            'penalty_amount' => collect($deductionLines)->where('code', 'penalty')->sum('amount'),
                            'total_deductions' => $totalDeductions,
                            'net_salary' => $baseSalary + $overtimeSalary + $missionSalary,
                            'advance_payment' => collect($deductionLines)->where('code', 'advance')->sum('amount'),
                            'final_salary' => $grossSalary - $totalDeductions,
                            'status' => 'calculated',
                            'notes' => 'محاسبه شده با موتور استاندارد حقوق و دستمزد',
                        ]
                    );

                    $salary->lines()->delete();
                    foreach (array_merge($earningLines, $deductionLines) as $line) {
                        $salary->lines()->create($line);
                    }

                    return $salary->refresh();
                });

            $period->update([
                'status' => 'calculated',
                'calculated_at' => now(),
            ]);

            $period->salaries()->each(function (Salary $salary) use ($userId): void {
                $salary->audits()->create([
                    'event' => 'payroll_calculated',
                    'user_id' => $userId,
                    'new_values' => ['salary_id' => $salary->id],
                ]);
            });

            return $salaries;
        });
    }

    public function approve(PayrollPeriod $period, int $userId): void
    {
        DB::transaction(function () use ($period, $userId) {
            $period->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

            $period->salaries()->update([
                'approved_at' => now(),
            ]);
        });
    }

    public function deletePeriod(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period): void {
            $calculationIds = PayrollCalculation::where('payroll_period_id', $period->id)->pluck('id');
            $salaryIds = Salary::where('payroll_period_id', $period->id)->pluck('id');

            if ($calculationIds->isNotEmpty()) {
                $paymentIds = PayrollPayment::whereIn('payroll_calculation_id', $calculationIds)->pluck('id');

                PayrollCalculationLine::whereIn('payroll_calculation_id', $calculationIds)->delete();
                PayrollAccountingEntry::whereIn('payroll_calculation_id', $calculationIds)->delete();
                InsuranceRecord::whereIn('payroll_calculation_id', $calculationIds)->delete();
                TaxRecord::whereIn('payroll_calculation_id', $calculationIds)->delete();
                Payslip::whereIn('payroll_calculation_id', $calculationIds)->delete();
                PayrollPayment::whereIn('payroll_calculation_id', $calculationIds)->delete();

                AccountingDocument::query()
                    ->where('source_type', PayrollCalculation::class)
                    ->whereIn('source_id', $calculationIds)
                    ->get()
                    ->each(function (AccountingDocument $document): void {
                        $document->lines()->delete();
                        $document->forceDelete();
                    });

                if ($paymentIds->isNotEmpty()) {
                    AccountingDocument::query()
                        ->where('source_type', PayrollPayment::class)
                        ->whereIn('source_id', $paymentIds)
                        ->get()
                        ->each(function (AccountingDocument $document): void {
                            $document->lines()->delete();
                            $document->forceDelete();
                        });
                }

                PayrollAudit::where('auditable_type', PayrollCalculation::class)
                    ->whereIn('auditable_id', $calculationIds)
                    ->delete();

                MonthlyAttendance::where('payroll_period_id', $period->id)->delete();
                PayrollCalculation::where('payroll_period_id', $period->id)->delete();
            }

            if ($salaryIds->isNotEmpty()) {
                $paymentIds = Payment::whereIn('salary_id', $salaryIds)->pluck('id');

                if ($paymentIds->isNotEmpty()) {
                    EmployeeTransaction::where('reference_type', 'payment')
                        ->whereIn('reference_id', $paymentIds)
                        ->delete();
                }

                EmployeeTransaction::whereIn('reference_type', ['salary', 'bonus', 'deduction', 'advance'])
                    ->whereIn('reference_id', $salaryIds)
                    ->delete();

                PayrollAudit::where('auditable_type', Salary::class)
                    ->whereIn('auditable_id', $salaryIds)
                    ->delete();

                AccountingDocument::query()
                    ->where('source_type', Salary::class)
                    ->whereIn('source_id', $salaryIds)
                    ->get()
                    ->each(function (AccountingDocument $document): void {
                        $document->lines()->delete();
                        $document->forceDelete();
                    });

                Salary::whereIn('id', $salaryIds)->delete();
            }

            $period->delete();
        });
    }

    private function approvedOrderFor(Employee $employee, PayrollPeriod $period): ?EmploymentOrder
    {
        return $employee->employmentOrders()
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', $period->ends_at)
            ->where(function ($query) use ($period) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->starts_at);
            })
            ->latest('effective_date')
            ->first();
    }

    private function baseSalaryFor(Employee $employee, ?EmploymentOrder $order, $attendance, bool $isHourly): float
    {
        if ($isHourly) {
            return (float) ($attendance?->normal_hours ?? 0) * (float) ($attendance?->hourly_rate ?? $employee->hourly_rate);
        }

        return (float) ($order?->base_salary ?: $employee->base_salary ?: $employee->salary ?: 0);
    }

    private function isHourlyEmployee(Employee $employee, ?EmploymentOrder $order = null): bool
    {
        return $employee->salary_type === 'hourly'
            || $employee->employment_type === 'hourly'
            || $order?->employment_type === 'hourly_contract';
    }

    private function buildLines(Employee $employee, ?EmploymentOrder $order, Collection $items, float $taxBase, float $baseSalary, float $overtimeSalary, float $missionSalary, float $attendanceDeduction): array
    {
        $earnings = [
            ['code' => 'base_salary', 'title' => 'حقوق پایه', 'type' => 'earning', 'amount' => $baseSalary],
            ['code' => 'overtime', 'title' => 'اضافه کاری', 'type' => 'earning', 'amount' => $overtimeSalary],
            ['code' => 'mission', 'title' => 'ماموریت', 'type' => 'earning', 'amount' => $missionSalary],
        ];
        $deductions = $attendanceDeduction > 0
            ? [['code' => 'attendance_deduction', 'title' => 'کسر کارکرد، تاخیر و تعجیل', 'type' => 'deduction', 'amount' => $attendanceDeduction]]
            : [];

        foreach ($items as $item) {
            $amount = $this->amountForItem($item, $taxBase, $order);

            if ($amount <= 0) {
                continue;
            }

            $line = [
                'payroll_item_id' => $item->id,
                'code' => $item->code,
                'title' => $item->title,
                'type' => $item->type,
                'amount' => $amount,
                'meta' => [
                    'calculation_type' => $item->calculation_type,
                    'source_title' => $item->source_title,
                ],
            ];

            if ($item->type === 'earning') {
                $earnings[] = $line;
            } else {
                $deductions[] = $line;
            }
        }

        foreach ((array) ($order?->fixed_benefits ?? []) as $title => $amount) {
            if ((float) $amount > 0) {
                $earnings[] = ['code' => 'order_benefit', 'title' => (string) $title, 'type' => 'earning', 'amount' => (float) $amount];
            }
        }

        foreach ((array) ($order?->fixed_deductions ?? []) as $title => $amount) {
            if ((float) $amount > 0) {
                $deductions[] = ['code' => 'order_deduction', 'title' => (string) $title, 'type' => 'deduction', 'amount' => (float) $amount];
            }
        }

        return [$earnings, $deductions];
    }

    private function amountForItem(PayrollItem $item, float $taxBase, ?EmploymentOrder $order): float
    {
        $orderMap = [
            'housing_allowance' => (float) ($order?->housing_allowance ?? 0),
            'food_allowance' => (float) ($order?->food_allowance ?? 0),
            'child_allowance' => (float) ($order?->child_allowance ?? 0),
            'transportation_allowance' => (float) ($order?->transportation_allowance ?? 0),
        ];

        if (array_key_exists($item->code, $orderMap) && $orderMap[$item->code] > 0) {
            return $orderMap[$item->code];
        }

        if ($item->calculation_type === 'percentage') {
            return round($taxBase * ((float) $item->default_rate / 100), 2);
        }

        if (in_array($item->calculation_type, ['manual', 'reference'], true)) {
            return 0.0;
        }

        return (float) $item->default_amount;
    }
}
