<?php

namespace App\Livewire\Payroll;

use App\Models\PayrollCalculation;
use App\Models\PayrollPeriod;
use App\Services\SmartPayrollService;
use Livewire\Component;

class SmartCalculation extends Component
{
    public int $year;

    public int $month;

    public bool $useCalendarAttendance = true;

    public string $defaultNet = '';

    public bool $headerSelectAll = false;

    /** @var array<int, array{employee_id:int,name:string,code:?string,selected:bool,target_net:string,current_net:?float}> */
    public array $rows = [];

    public function mount(SmartPayrollService $smartPayroll): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.calculate') || auth()->user()?->hasPermission('payroll.view'), 403);

        $this->year = (int) getCurrentPersianYear();
        $this->month = (int) getCurrentPersianMonth();
        $this->loadEmployees($smartPayroll);
    }

    public function updatedYear(): void
    {
        $this->refreshCurrentNets();
    }

    public function updatedMonth(): void
    {
        $this->refreshCurrentNets();
    }

    public function updatedHeaderSelectAll(bool $value): void
    {
        $this->setAllSelected($value);
    }

    public function updatedRows(): void
    {
        $this->syncHeaderSelectAll();
    }

    public function setAllSelected(bool $value = true): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['selected'] = $value;
        }
        $this->headerSelectAll = $value;
    }

    public function applyDefaultNet(): void
    {
        $normalized = normalizeMoneyValue($this->defaultNet);
        if ($normalized === null || $normalized <= 0) {
            session()->flash('error', 'مبلغ پیش‌فرض معتبر نیست.');

            return;
        }

        $formatted = moneyInputValue($normalized, 0);
        $this->defaultNet = $formatted;

        foreach ($this->rows as $index => $row) {
            if ($row['selected']) {
                $this->rows[$index]['target_net'] = $formatted;
            }
        }
    }

    public function selectedCount(): int
    {
        return collect($this->rows)->where('selected', true)->count();
    }

    public function run(SmartPayrollService $smartPayroll): void
    {
        abort_unless(auth()->user()?->hasPermission('payroll.calculate'), 403);

        $this->validate([
            'year' => ['required', 'integer', 'min:1400', 'max:1500'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.selected' => ['boolean'],
            'rows.*.target_net' => ['nullable', 'string'],
        ], [], [
            'year' => 'سال',
            'month' => 'ماه',
        ]);

        $targets = [];
        foreach ($this->rows as $row) {
            if (! ($row['selected'] ?? false)) {
                continue;
            }
            $net = normalizeMoneyValue($row['target_net'] ?? null);
            if ($net === null || $net <= 0) {
                session()->flash('error', 'برای «' . ($row['name'] ?? '') . '» مبلغ خالص هدف را وارد کنید.');

                return;
            }
            $targets[(int) $row['employee_id']] = $net;
        }

        if ($targets === []) {
            session()->flash('error', 'حداقل یک کارمند را انتخاب کنید.');

            return;
        }

        try {
            $results = $smartPayroll->run(
                $this->year,
                $this->month,
                $targets,
                $this->useCalendarAttendance,
                auth()->id(),
            );
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $ok = $results->where('status', '!=', 'failed')->count();
        $failed = $results->where('status', 'failed')->count();
        session()->flash(
            'success',
            'محاسبه هوشمند انجام شد: ' . formatMoney($ok) . ' نفر موفق' . ($failed ? '، ' . formatMoney($failed) . ' ناموفق' : '') . '.'
        );

        $this->refreshCurrentNets();
    }

    public function render()
    {
        $period = PayrollPeriod::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        $workdayCount = null;
        if ($period) {
            $workdayCount = count(app(SmartPayrollService::class)->workingDatesForPeriod($period));
        } else {
            $range = getPersianMonthRange($this->year, $this->month);
            $tmp = new PayrollPeriod([
                'year' => $this->year,
                'month' => $this->month,
                'starts_at' => $range['start']->toDateString(),
                'ends_at' => $range['end']->toDateString(),
            ]);
            $workdayCount = count(app(SmartPayrollService::class)->workingDatesForPeriod($tmp));
        }

        return view('livewire.payroll.smart-calculation', [
            'period' => $period,
            'workdayCount' => $workdayCount,
            'years' => range((int) getCurrentPersianYear() - 1, (int) getCurrentPersianYear() + 1),
            'months' => range(1, 12),
            'selectedCount' => $this->selectedCount(),
        ]);
    }

    private function loadEmployees(SmartPayrollService $smartPayroll): void
    {
        $employees = $smartPayroll->listActiveEmployees();
        $this->rows = $employees->map(function ($employee) {
            return [
                'employee_id' => (int) $employee->id,
                'name' => (string) ($employee->full_name ?? $employee->party?->full_name ?? $employee->party?->name ?? ('#' . $employee->id)),
                'code' => $employee->personnel_code ?? $employee->code ?? null,
                'selected' => false,
                'target_net' => '',
                'current_net' => null,
            ];
        })->values()->all();

        $this->headerSelectAll = false;
        $this->refreshCurrentNets();
    }

    private function refreshCurrentNets(): void
    {
        $period = PayrollPeriod::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        $nets = [];
        if ($period) {
            $nets = PayrollCalculation::query()
                ->where('payroll_period_id', $period->id)
                ->pluck('net_payable', 'employee_id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['current_net'] = $nets[$row['employee_id']] ?? null;
        }
    }

    private function syncHeaderSelectAll(): void
    {
        if ($this->rows === []) {
            $this->headerSelectAll = false;

            return;
        }

        $this->headerSelectAll = collect($this->rows)->every(fn (array $row): bool => (bool) ($row['selected'] ?? false));
    }
}
