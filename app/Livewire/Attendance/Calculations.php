<?php

namespace App\Livewire\Attendance;

use App\Exceptions\AttendancePrerequisiteException;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PayrollPeriod;
use App\Services\AttendanceAdjustmentService;
use App\Services\NewAttendanceEngineService;
use App\Services\PayrollCalculationService;
use Livewire\Component;
use Livewire\WithPagination;

class Calculations extends Component
{
    use WithPagination;

    public int $year = 0;

    public int $month = 0;

    public string $search = '';

    public string $salaryType = '';

    public string $scope = 'with_data';

    public ?int $employee_id = null;

    public ?int $editingId = null;

    /** @var array<string, float|int|string> */
    public array $editForm = [];

    protected $queryString = [
        'year' => ['except' => 0],
        'month' => ['except' => 0],
        'search' => ['except' => ''],
        'salaryType' => ['except' => ''],
        'scope' => ['except' => 'with_data'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->year = (int) request()->integer('year', (int) getCurrentPersianYear());
        $this->month = (int) request()->integer('month', (int) getCurrentPersianMonth());
        $this->normalizePeriodFilters();
    }

    public function calculate(): void
    {
        $this->normalizePeriodFilters();

        $this->validate([
            'year' => ['required', 'integer', 'min:1400', 'max:1500'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'scope' => ['required', 'in:with_data,all,single'],
            'employee_id' => ['nullable', 'required_if:scope,single', 'exists:employees,id'],
        ]);

        $period = app(PayrollCalculationService::class)->createOrGetPeriod($this->year, $this->month, auth()->id());

        try {
            $rows = app(NewAttendanceEngineService::class)->processPeriod(
                $period,
                $this->scope === 'single' ? $this->employee_id : null,
                $this->scope === 'all'
            );
        } catch (AttendancePrerequisiteException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->cancelEdit();
        session()->flash('success', 'محاسبه کارکرد برای ' . formatMoney($rows->count()) . ' نفر انجام شد. می‌توانید اعداد هر نفر را دستی ویرایش کنید.');
    }

    public function updated($name): void
    {
        if (in_array($name, ['year', 'month', 'search', 'salaryType', 'scope', 'employee_id'], true)) {
            $this->resetPage();
            $this->cancelEdit();
        }
    }

    private function normalizePeriodFilters(): void
    {
        if ($this->year < 1400 || $this->year > 1500) {
            $this->year = (int) getCurrentPersianYear();
        }

        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) getCurrentPersianMonth();
        }
    }

    public function startEdit(int $attendanceId): void
    {
        $attendance = MonthlyAttendance::query()->findOrFail($attendanceId);

        $this->editingId = $attendance->id;
        $this->editForm = [
            'work_days' => (float) ($attendance->work_days ?? $attendance->present_days ?? 0),
            'absence_days' => (float) ($attendance->absence_days ?? 0),
            'normal_hours' => (float) $attendance->normal_hours,
            'overtime_hours' => (float) $attendance->overtime_hours,
            'delay_hours' => (float) $attendance->delay_hours,
            'early_leave_hours' => (float) $attendance->early_leave_hours,
            'absence_hours' => (float) $attendance->absence_hours,
            'leave_hours' => (float) $attendance->leave_hours,
            'mission_hours' => (float) $attendance->mission_hours,
            'night_hours' => (float) $attendance->night_hours,
            'holiday_hours' => (float) $attendance->holiday_hours,
            'payable_hours' => (float) $attendance->payable_hours,
            'net_payable_hours' => (float) $attendance->net_payable_hours,
            'required_hours' => (float) $attendance->required_hours,
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editForm = [];
        $this->resetValidation();
    }

    public function saveEdit(bool $recalculatePayable = false): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'editForm.work_days' => ['required', 'numeric', 'min:0'],
            'editForm.absence_days' => ['required', 'numeric', 'min:0'],
            'editForm.normal_hours' => ['required', 'numeric', 'min:0'],
            'editForm.overtime_hours' => ['required', 'numeric', 'min:0'],
            'editForm.delay_hours' => ['required', 'numeric', 'min:0'],
            'editForm.early_leave_hours' => ['required', 'numeric', 'min:0'],
            'editForm.absence_hours' => ['required', 'numeric', 'min:0'],
            'editForm.leave_hours' => ['required', 'numeric', 'min:0'],
            'editForm.mission_hours' => ['required', 'numeric', 'min:0'],
            'editForm.night_hours' => ['required', 'numeric', 'min:0'],
            'editForm.holiday_hours' => ['required', 'numeric', 'min:0'],
            'editForm.payable_hours' => ['required', 'numeric', 'min:0'],
            'editForm.net_payable_hours' => ['required', 'numeric', 'min:0'],
        ], [], [
            'editForm.work_days' => 'روز کارکرد',
            'editForm.absence_days' => 'روز غیبت',
            'editForm.normal_hours' => 'کارکرد عادی',
            'editForm.overtime_hours' => 'اضافه‌کاری',
            'editForm.delay_hours' => 'تأخیر',
            'editForm.early_leave_hours' => 'تعجیل',
            'editForm.absence_hours' => 'غیبت',
            'editForm.leave_hours' => 'مرخصی',
            'editForm.mission_hours' => 'ماموریت',
            'editForm.night_hours' => 'شب‌کاری',
            'editForm.holiday_hours' => 'تعطیل‌کاری',
            'editForm.payable_hours' => 'قابل پرداخت',
            'editForm.net_payable_hours' => 'خالص قابل پرداخت',
        ]);

        $attendance = MonthlyAttendance::query()->findOrFail($this->editingId);

        app(AttendanceAdjustmentService::class)->update(
            $attendance,
            $this->editForm + ['recalculate_payable' => $recalculatePayable],
            auth()->id()
        );

        $this->cancelEdit();
        session()->flash('success', 'اعداد کارکرد با موفقیت ذخیره شد.');
    }

    public function render()
    {
        $period = PayrollPeriod::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        $calculations = MonthlyAttendance::query()
            ->with(['employee.party', 'period'])
            ->when($period, fn ($query) => $query->where('payroll_period_id', $period->id))
            ->when(! $period, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->salaryType !== '', fn ($query) => $query->where('meta->employee_salary_type', $this->salaryType))
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);
                $query->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('personnel_code', 'like', "%{$search}%")
                        ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('employee_id')
            ->paginate(20);

        $employees = Employee::query()
            ->where(function ($query): void {
                $query->where('is_active', true)->orWhere('status', 'active');
            })
            ->with('party')
            ->orderBy('employee_code')
            ->get();

        return view('livewire.attendance.calculations', compact('period', 'calculations', 'employees'));
    }
}
