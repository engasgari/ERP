<?php

namespace App\Livewire\Attendance;

use App\Exceptions\AttendancePrerequisiteException;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\NewAttendanceEngineService;
use Livewire\Component;
use Livewire\WithPagination;

class Summaries extends Component
{
    use WithPagination;

    public int $year = 0;
    public int $month = 0;
    public string $search = '';

    protected $queryString = [
        'year' => ['except' => 0],
        'month' => ['except' => 0],
        'search' => ['except' => ''],
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
        if (in_array($name, ['year', 'month', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function rebuild(): void
    {
        $this->validate([
            'year' => ['required', 'integer', 'min:1400', 'max:1500'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $period = PayrollPeriod::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (! $period) {
            session()->flash('error', 'برای این ماه هنوز دوره حقوق و دستمزد ساخته نشده است.');
            return;
        }

        try {
            $summaries = app(NewAttendanceEngineService::class)->processPeriod($period);
        } catch (AttendancePrerequisiteException $exception) {
            session()->flash('error', $exception->getMessage());
            return;
        }

        session()->flash('success', 'خلاصه کارکرد برای ' . number_format($summaries->count()) . ' نفر بازسازی شد.');
    }

    public function render()
    {
        $summaries = AttendanceSummary::query()
            ->with(['employee.party', 'period'])
            ->where('year', $this->year)
            ->where('month', $this->month)
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

        $employeesWithoutSummary = Employee::query()
            ->where('is_active', true)
            ->whereDoesntHave('workGroupAssignments')
            ->count();

        return view('livewire.attendance.summaries', compact('summaries', 'employeesWithoutSummary'));
    }
}
