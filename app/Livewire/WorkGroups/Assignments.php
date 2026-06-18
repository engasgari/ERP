<?php

namespace App\Livewire\WorkGroups;

use App\Models\Employee;
use App\Models\WorkGroup;
use App\Models\WorkGroupEmployee;
use App\Services\WorkGroupAssignmentService;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class Assignments extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $employee_id = null;
    public ?int $work_group_id = null;
    public string $start_date = '';
    public string $end_date = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updated($name): void
    {
        if ($name === 'search') {
            $this->resetPage();
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_group_id' => ['required', 'exists:work_groups,id'],
            'start_date' => ['required', 'string'],
            'end_date' => ['nullable', 'string'],
        ], [], [
            'employee_id' => 'پرسنل',
            'work_group_id' => 'گروه کاری',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
        ]);

        $startDate = jalaliToGregorianDate($data['start_date']);
        $endDate = jalaliToGregorianDate($data['end_date'] ?: null);

        if (! $startDate) {
            $this->addError('start_date', 'تاریخ شروع معتبر نیست.');
            return;
        }

        if ($data['end_date'] !== '' && ! $endDate) {
            $this->addError('end_date', 'تاریخ پایان معتبر نیست.');
            return;
        }

        try {
            app(WorkGroupAssignmentService::class)->assign(
                Employee::findOrFail($data['employee_id']),
                WorkGroup::findOrFail($data['work_group_id']),
                $startDate,
                $endDate
            );
        } catch (InvalidArgumentException $exception) {
            session()->flash('error', $exception->getMessage());
            return;
        }

        $this->reset(['employee_id', 'work_group_id', 'start_date', 'end_date']);
        session()->flash('success', 'تخصیص گروه کاری ثبت شد.');
    }

    public function closeAssignment(int $assignmentId): void
    {
        $assignment = WorkGroupEmployee::findOrFail($assignmentId);
        $assignment->update(['end_date' => now()->toDateString()]);

        session()->flash('success', 'تخصیص فعال بسته شد.');
    }

    public function delete(int $assignmentId): void
    {
        WorkGroupEmployee::findOrFail($assignmentId)->delete();
        session()->flash('success', 'تخصیص گروه کاری حذف شد.');
    }

    public function render()
    {
        $employees = Employee::query()->where('is_active', true)->with('party')->orderBy('employee_code')->get();
        $workGroups = WorkGroup::query()->where('is_active', true)->orderBy('name')->get();

        $assignments = WorkGroupEmployee::query()
            ->with(['employee.party', 'workGroup.shift', 'workGroup.calendar'])
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);
                $query->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('personnel_code', 'like', "%{$search}%")
                        ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
                })->orWhereHas('workGroup', fn ($group) => $group->where('name', 'like', "%{$search}%"));
            })
            ->orderByRaw('end_date is null desc')
            ->latest('start_date')
            ->paginate(20);

        return view('livewire.work-groups.assignments', compact('employees', 'workGroups', 'assignments'));
    }
}
