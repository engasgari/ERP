<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmploymentOrder;
use App\Models\InsuranceRecord;
use App\Models\MonthlyAttendance;
use App\Models\PayrollCalculation;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\TaxRecord;
use App\Models\WorkCalendar;
use App\Repositories\EmployeeRepository;
use App\Support\WorkCalendarDefaults;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Smart payroll: calendar-based full attendance (ignores punches) + agreed net payable.
 */
class SmartPayrollService
{
    public const TOPUP_CODE = 'other_non_insurable_benefits';

    public const TOPUP_TITLE = 'سایر مزایای غیرمشمول بیمه (محاسبه هوشمند)';

    public const HOURS_PER_DAY = 8.0;

    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly PayrollCalculationService $periods,
        private readonly NewPayrollEngineService $payrollEngine,
        private readonly PayslipSnapshotService $payslipSnapshot,
    ) {
    }

    /**
     * @return Collection<int, Employee>
     */
    public function listActiveEmployees(): Collection
    {
        return $this->employees->activeForPayroll();
    }

    /**
     * @param  array<int, float|int|string>  $employeeTargetNets  employee_id => target net (IRR)
     * @return Collection<int, array{employee_id:int,calculation_id:int,net_payable:float,insurance_employee:float,topup:float,status:string}>
     */
    public function run(
        int $year,
        int $month,
        array $employeeTargetNets,
        bool $useCalendarAttendance = true,
        ?int $userId = null,
    ): Collection {
        if ($employeeTargetNets === []) {
            throw ValidationException::withMessages([
                'employees' => 'حداقل یک کارمند را با مبلغ خالص هدف انتخاب کنید.',
            ]);
        }

        $period = $this->periods->createOrGetPeriod($year, $month, $userId);
        if (in_array($period->status, ['closed'], true)) {
            throw ValidationException::withMessages([
                'period' => 'دوره بسته‌شده قابل محاسبه هوشمند نیست.',
            ]);
        }

        $results = collect();

        DB::transaction(function () use ($period, $employeeTargetNets, $useCalendarAttendance, &$results): void {
            $this->ensureTopupItem();

            foreach ($employeeTargetNets as $employeeId => $targetNet) {
                $employeeId = (int) $employeeId;
                $targetNet = round((float) normalizeMoneyValue((string) $targetNet), 2);
                if ($targetNet <= 0) {
                    continue;
                }

                $employee = Employee::query()->with(['party', 'employmentOrders'])->findOrFail($employeeId);

                if ($useCalendarAttendance) {
                    $attendance = $this->seedCalendarAttendance($period, $employee);
                } else {
                    $attendance = MonthlyAttendance::query()
                        ->where('payroll_period_id', $period->id)
                        ->where('employee_id', $employee->id)
                        ->first();

                    if (! $attendance) {
                        throw ValidationException::withMessages([
                            'attendance' => 'برای «' . ($employee->full_name ?? $employee->id) . '» کارکرد ماهانه وجود ندارد. گزینه تقویم را فعال کنید.',
                        ]);
                    }
                }

                $calculation = $this->payrollEngine->calculateEmployee($period, $attendance->fresh(['employee.party', 'employee.employmentOrders']));

                if ($calculation->payments()->exists()) {
                    throw ValidationException::withMessages([
                        'payments' => 'برای «' . ($employee->full_name ?? $employee->id) . '» پرداخت ثبت شده و محاسبه هوشمند مجاز نیست.',
                    ]);
                }

                if ($calculation->status === 'failed') {
                    $results->push([
                        'employee_id' => $employeeId,
                        'calculation_id' => $calculation->id,
                        'net_payable' => 0.0,
                        'insurance_employee' => 0.0,
                        'topup' => 0.0,
                        'status' => 'failed',
                        'failure_reason' => $calculation->failure_reason,
                    ]);
                    continue;
                }

                $adjusted = $this->applyAgreedNetPayable($calculation->fresh([
                    'lines', 'employee.party', 'period', 'personnelDecree', 'payslip', 'attendance', 'accountingEntry', 'payments',
                ]), $targetNet);

                $this->payrollEngine->repostAccountingForCalculation($adjusted);
                $this->refreshAccountingEntryAmounts($adjusted->fresh());

                $topup = round((float) $adjusted->lines()->where('code', self::TOPUP_CODE)->value('amount'), 2);

                $results->push([
                    'employee_id' => $employeeId,
                    'calculation_id' => $adjusted->id,
                    'net_payable' => round((float) $adjusted->net_payable, 2),
                    'insurance_employee' => round((float) $adjusted->insurance_employee, 2),
                    'topup' => $topup,
                    'status' => (string) $adjusted->status,
                ]);
            }

            if ($period->status === 'draft') {
                $period->update([
                    'status' => 'calculated',
                    'calculated_at' => now(),
                ]);
            }
        });

        return $results;
    }

    public function seedCalendarAttendance(PayrollPeriod $period, Employee $employee): MonthlyAttendance
    {
        $order = $this->approvedOrderFor($employee, $period);
        $calendar = $this->calendarForYear((int) $period->year);
        $workDates = $this->workingDatesForPeriod($period, $calendar);
        $workingDays = count($workDates);
        $calendarDays = (int) Carbon::parse($period->starts_at)->diffInDays(Carbon::parse($period->ends_at)) + 1;
        $requiredHours = round($workingDays * self::HOURS_PER_DAY, 2);
        $requiredMinutes = (int) round($requiredHours * 60);

        $daily = [];
        foreach ($workDates as $date) {
            $daily[] = [
                'work_date' => $date,
                'is_working_day' => true,
                'worked_hours' => self::HOURS_PER_DAY,
                'planned_minutes' => (int) (self::HOURS_PER_DAY * 60),
                'worked_minutes' => (int) (self::HOURS_PER_DAY * 60),
                'break_minutes' => 0,
                'delay_minutes' => 0,
                'early_leave_minutes' => 0,
                'leave_hours' => 0,
                'mission_hours' => 0,
                'absence_hours' => 0,
                'overtime_hours' => 0,
                'holiday_hours' => 0,
                'friday_hours' => 0,
                'net_payable_hours' => self::HOURS_PER_DAY,
                'payable_hours' => self::HOURS_PER_DAY,
                'absence_day_fraction' => 0,
                'day_kind' => 'working',
                'source' => 'smart_calendar',
            ];
        }

        $summary = AttendanceSummary::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $period->year, 'month' => $period->month],
            [
                'payroll_period_id' => $period->id,
                'calendar_days' => $calendarDays,
                'working_days' => $workingDays,
                'required_days' => $workingDays,
                'required_hours' => $requiredHours,
                'required_minutes' => $requiredMinutes,
                'worked_days' => $workingDays,
                'absence_days' => 0,
                'worked_hours' => $requiredHours,
                'planned_minutes' => $requiredMinutes,
                'worked_minutes' => $requiredMinutes,
                'break_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_hours' => 0,
                'holiday_minutes' => 0,
                'delay_minutes' => 0,
                'early_leave_minutes' => 0,
                'absence_minutes' => 0,
                'absence_hours' => 0,
                'hourly_leave_minutes' => 0,
                'leave_hours' => 0,
                'daily_leave_days' => 0,
                'hourly_mission_minutes' => 0,
                'mission_hours' => 0,
                'daily_mission_days' => 0,
                'net_payable_hours' => $requiredHours,
                'status' => 'calculated',
                'failure_reason' => null,
                'meta' => [
                    'calculation_rule' => 'smart_calendar_full_month',
                    'ignores_punches' => true,
                    'decree_id' => $order?->id,
                    'daily' => $daily,
                ],
            ]
        );

        return MonthlyAttendance::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'calendar_days' => $calendarDays,
                'working_days' => $workingDays,
                'work_days' => $workingDays,
                'present_days' => $workingDays,
                'absence_days' => 0,
                'required_hours' => $requiredHours,
                'normal_hours' => $requiredHours,
                'worked_hours' => $requiredHours,
                'break_minutes' => 0,
                'delay_minutes' => 0,
                'delay_hours' => 0,
                'early_leave_minutes' => 0,
                'early_leave_hours' => 0,
                'absence_hours' => 0,
                'leave_hours' => 0,
                'mission_hours' => 0,
                'overtime_hours' => 0,
                'night_hours' => 0,
                'holiday_hours' => 0,
                'friday_hours' => 0,
                'payable_hours' => $requiredHours,
                'net_payable_hours' => $requiredHours,
                'status' => 'processed',
                'meta' => [
                    'attendance_summary_id' => $summary->id,
                    'calculation_rule' => 'smart_calendar_full_month',
                    'ignores_punches' => true,
                    'decree_id' => $order?->id,
                    'friday_hours' => 0,
                    'daily' => $daily,
                ],
            ]
        );
    }

    public function applyAgreedNetPayable(PayrollCalculation $calculation, float $targetNet): PayrollCalculation
    {
        $targetNet = round($targetNet, 2);
        if ($targetNet <= 0) {
            throw ValidationException::withMessages(['target_net' => 'مبلغ خالص هدف باید بزرگ‌تر از صفر باشد.']);
        }

        if ($calculation->payments()->exists()) {
            throw new \RuntimeException('حقوق پرداخت‌شده قابل محاسبه هوشمند نیست.');
        }

        $calculation->loadMissing(['lines', 'employee', 'period', 'personnelDecree', 'payslip']);

        $insPreserve = round((float) $calculation->insurance_employee, 2);
        $insEmployerPreserve = round((float) $calculation->insurance_employer, 2);
        $taxPreserve = round((float) $calculation->tax_amount, 2);

        $earn = 0.0;
        $otherDeduct = 0.0;
        foreach ($calculation->lines as $line) {
            $code = (string) $line->code;
            if (in_array($code, [self::TOPUP_CODE, 'bonus', 'insurance', 'tax', 'employee_insurance', 'employer_insurance', 'salary_tax'], true)) {
                continue;
            }
            if ($line->type === 'earning') {
                $earn += (float) $line->amount;
            } else {
                $otherDeduct += (float) $line->amount;
            }
        }
        $earn = round($earn, 2);
        $otherDeduct = round($otherDeduct, 2);

        $netWithoutTopup = round($earn - $insPreserve - $taxPreserve - $otherDeduct, 2);
        $topup = round($targetNet - $netWithoutTopup, 2);
        if ($topup < 0) {
            throw ValidationException::withMessages([
                'target_net' => 'خالص هدف از حقوق پایه محاسبه‌شده کمتر است؛ مبلغ را افزایش دهید یا کسورات را بررسی کنید.',
            ]);
        }

        $item = $this->ensureTopupItem();

        $calculation->lines()
            ->whereIn('code', [self::TOPUP_CODE, 'bonus', 'insurance', 'tax', 'employee_insurance', 'salary_tax'])
            ->delete();

        if ($topup > 0) {
            $calculation->lines()->create([
                'payroll_item_id' => $item->id,
                'code' => self::TOPUP_CODE,
                'title' => self::TOPUP_TITLE,
                'type' => 'earning',
                'hours' => 0,
                'rate' => 0,
                'amount' => $topup,
                'meta' => [
                    'is_insurable' => false,
                    'is_taxable' => true,
                    'smart_payroll' => true,
                    'target_net' => $targetNet,
                ],
            ]);
        }

        if ($insPreserve > 0) {
            $calculation->lines()->create([
                'payroll_item_id' => null,
                'code' => 'insurance',
                'title' => 'بیمه سهم کارمند',
                'type' => 'deduction',
                'hours' => 0,
                'rate' => 7,
                'amount' => $insPreserve,
                'meta' => null,
            ]);
        }

        if ($taxPreserve > 0) {
            $calculation->lines()->create([
                'payroll_item_id' => null,
                'code' => 'tax',
                'title' => 'مالیات حقوق',
                'type' => 'deduction',
                'hours' => 0,
                'rate' => 0,
                'amount' => $taxPreserve,
                'meta' => null,
            ]);
        }

        $gross = round($earn + max(0, $topup), 2);
        $totalDeduct = round($insPreserve + $taxPreserve + $otherDeduct, 2);
        $baseSalary = round((float) $calculation->lines()->where('code', 'base_salary')->value('amount'), 2);

        $calculation->update([
            'gross_salary' => $gross,
            'total_benefits' => max(0, round($gross - $baseSalary, 2)),
            'insurance_employee' => $insPreserve,
            'insurance_employer' => $insEmployerPreserve,
            'tax_amount' => $taxPreserve,
            'total_deductions' => $totalDeduct,
            'net_payable' => $targetNet,
            'status' => 'calculated',
            'failure_reason' => null,
            'calculated_at' => now(),
        ]);

        InsuranceRecord::query()->where('payroll_calculation_id', $calculation->id)->update([
            'employee_share' => $insPreserve,
            'employer_share' => $insEmployerPreserve,
        ]);

        $taxRecord = TaxRecord::query()->where('payroll_calculation_id', $calculation->id)->first();
        if ($taxRecord) {
            $taxRecord->update([
                'taxable_income' => round((float) $taxRecord->taxable_income + $topup, 2),
                'tax_amount' => $taxPreserve,
            ]);
        }

        $fresh = $calculation->fresh(['employee', 'period', 'personnelDecree', 'payslip', 'lines']);
        if ($fresh->payslip) {
            $snapshot = $this->payslipSnapshot->build(
                $fresh->employee,
                $fresh->period,
                $fresh->personnelDecree,
                [
                    'net_payable' => (float) $fresh->net_payable,
                    'gross_salary' => (float) $fresh->gross_salary,
                    'total_deductions' => (float) $fresh->total_deductions,
                ]
            );
            $fresh->payslip->update([
                'snapshot' => array_merge(is_array($fresh->payslip->snapshot) ? $fresh->payslip->snapshot : [], $snapshot, [
                    'smart_payroll' => true,
                    'adjusted_at' => now()->toDateTimeString(),
                ]),
            ]);
        }

        return $fresh;
    }

    private function ensureTopupItem(): PayrollItem
    {
        return PayrollItem::query()->updateOrCreate(
            ['code' => self::TOPUP_CODE],
            [
                'title' => self::TOPUP_TITLE,
                'type' => 'earning',
                'calculation_type' => 'manual',
                'default_amount' => 0,
                'default_rate' => 0,
                'taxable' => true,
                'insurable' => false,
                'is_statutory' => false,
                'is_active' => true,
                'sort_order' => 95,
                'source_title' => 'محاسبه هوشمند حقوق',
                'description' => 'مابه‌التفاوت تا خالص هدف؛ غیرمشمول بیمه.',
            ]
        );
    }

    private function refreshAccountingEntryAmounts(PayrollCalculation $calculation): void
    {
        DB::table('payroll_accounting_entries')
            ->where('payroll_calculation_id', $calculation->id)
            ->update([
                'salary_expense_debit' => (float) $calculation->gross_salary,
                'insurance_expense_debit' => (float) $calculation->insurance_employer,
                'salary_payable_credit' => (float) $calculation->net_payable,
                'insurance_payable_credit' => round((float) $calculation->insurance_employee + (float) $calculation->insurance_employer, 2),
                'tax_payable_credit' => (float) $calculation->tax_amount,
                'status' => 'posted',
            ]);
    }

    private function calendarForYear(int $jalaliYear): ?WorkCalendar
    {
        return WorkCalendar::query()
            ->where('jalali_year', $jalaliYear)
            ->where('is_default', true)
            ->first()
            ?? WorkCalendar::query()->where('jalali_year', $jalaliYear)->where('is_active', true)->first();
    }

    /**
     * @return list<string>
     */
    public function workingDatesForPeriod(PayrollPeriod $period, ?WorkCalendar $calendar = null): array
    {
        $calendar ??= $this->calendarForYear((int) $period->year);
        $defaults = WorkCalendarDefaults::calendar((int) $period->year);
        $workingDays = WorkCalendarDefaults::normalizeDayList($calendar?->working_days, $defaults['working_days']);
        $weekendDays = WorkCalendarDefaults::normalizeDayList($calendar?->weekend_days, $defaults['weekend_days']);
        $holidays = array_values(array_unique(array_merge(
            collect($calendar?->holidays ?: [])->map(fn ($h) => normalizePersianDigits(is_array($h) ? (string) ($h['date'] ?? '') : (string) $h))->filter()->all(),
            collect($defaults['holidays'] ?? [])->map(fn ($h) => normalizePersianDigits((string) $h))->all(),
        )));

        $start = Carbon::parse($period->starts_at)->startOfDay();
        $end = Carbon::parse($period->ends_at)->endOfDay();
        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $g = $d->toDateString();
            $j = normalizePersianDigits(formatJalaliDateSafe($d, ''));
            $dow = (int) $d->dayOfWeek;
            if (in_array($g, $holidays, true) || in_array($j, $holidays, true)) {
                continue;
            }
            if (in_array($dow, $weekendDays, true)) {
                continue;
            }
            if (! in_array($dow, $workingDays, true)) {
                continue;
            }
            $dates[] = $g;
        }

        return $dates;
    }

    private function approvedOrderFor(Employee $employee, PayrollPeriod $period): ?EmploymentOrder
    {
        $orders = $employee->relationLoaded('employmentOrders')
            ? $employee->employmentOrders
            : $employee->employmentOrders()->get();

        return $orders
            ->where('status', 'approved')
            ->filter(function (EmploymentOrder $order) use ($period): bool {
                if (! $order->effective_date) {
                    return true;
                }

                return $order->effective_date->lte($period->ends_at)
                    && (! $order->end_date || $order->end_date->gte($period->starts_at));
            })
            ->sortByDesc('id')
            ->first();
    }
}
