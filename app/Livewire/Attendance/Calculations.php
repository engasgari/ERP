<?php

namespace App\Livewire\Attendance;

use App\Exceptions\AttendancePrerequisiteException;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PayrollPeriod;
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
        $todayParts = explode('/', formatJalaliDateSafe(now()));
        $this->year = (int) request()->integer('year', (int) ($todayParts[0] ?? 1405));
        $this->month = (int) request()->integer('month', (int) ($todayParts[1] ?? 1));
    }

    public function updated($name): void
    {
        if (in_array($name, ['year', 'month', 'search', 'salaryType', 'scope', 'employee_id'], true)) {
            $this->resetPage();
        }
    }

    public function calculate(): void
    {
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

        session()->flash('success', 'محاسبه کارکرد برای ' . number_format($rows->count()) . ' نفر انجام شد.');
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
