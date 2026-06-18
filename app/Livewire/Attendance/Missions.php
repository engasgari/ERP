<?php

namespace App\Livewire\Attendance;

use App\Models\AttendanceMission;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Project;
use App\Services\MissionManagementService;
use Livewire\Component;
use Livewire\WithPagination;

class Missions extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $type = '';

    public ?int $employee_id = null;
    public ?int $project_id = null;
    public ?int $cost_center_id = null;
    public string $request_type = 'hourly';
    public string $destination = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $start_time = '';
    public string $end_time = '';
    public string $hours = '';
    public string $total_days = '';
    public string $description = '';
    public string $rejection_reason = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'type' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updated($name): void
    {
        if (in_array($name, ['search', 'status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'type']);
        $this->resetPage();
    }

    public function save(): void
    {
        $data = $this->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'request_type' => ['required', 'in:hourly,daily'],
            'destination' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'string'],
            'end_date' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], $this->attributes());

        $startDate = jalaliToGregorianDate($data['start_date']);
        $endDate = jalaliToGregorianDate($data['end_date'] ?: $data['start_date']);

        if (! $startDate || ! $endDate) {
            $this->addError('start_date', 'تاریخ وارد شده معتبر نیست.');
            return;
        }

        app(MissionManagementService::class)->request([
            'employee_id' => $data['employee_id'],
            'project_id' => $data['project_id'] ?: null,
            'cost_center_id' => $data['cost_center_id'] ?: null,
            'request_type' => $data['request_type'],
            'destination' => $data['destination'] ?: null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'mission_date' => $startDate,
            'start_time' => $data['request_type'] === 'hourly' ? ($data['start_time'] ?: null) : null,
            'end_time' => $data['request_type'] === 'hourly' ? ($data['end_time'] ?: null) : null,
            'hours' => $data['request_type'] === 'hourly' ? ($data['hours'] ?: null) : null,
            'total_days' => $data['request_type'] === 'daily' ? ($data['total_days'] ?: null) : null,
            'description' => $data['description'] ?: null,
            'status' => 'pending',
        ], auth()->id());

        $this->resetForm();
        session()->flash('success', 'درخواست ماموریت ثبت شد و در انتظار تایید قرار گرفت.');
    }

    public function approve(int $missionId): void
    {
        $mission = AttendanceMission::findOrFail($missionId);
        app(MissionManagementService::class)->approve($mission, auth()->id());

        session()->flash('success', 'درخواست ماموریت تایید شد.');
    }

    public function reject(int $missionId): void
    {
        $mission = AttendanceMission::findOrFail($missionId);
        app(MissionManagementService::class)->reject($mission, auth()->id(), $this->rejection_reason ?: null);
        $this->rejection_reason = '';

        session()->flash('success', 'درخواست ماموریت رد شد.');
    }

    public function cancel(int $missionId): void
    {
        $mission = AttendanceMission::findOrFail($missionId);

        if ($mission->status === 'approved') {
            session()->flash('error', 'درخواست تایید شده را فقط با رد/اصلاح مدیریتی تغییر دهید.');
            return;
        }

        $mission->update(['status' => 'cancelled']);
        session()->flash('success', 'درخواست ماموریت لغو شد.');
    }

    public function render()
    {
        $employees = Employee::query()->where('is_active', true)->with('party')->orderBy('employee_code')->get();
        $projects = Project::query()->orderBy('name')->get();
        $costCenters = CostCenter::query()->orderBy('name')->get();

        $missions = AttendanceMission::query()
            ->with(['employee.party', 'project', 'costCenter'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn ($query) => $query->where('request_type', $this->type))
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);
                $query->where(function ($q) use ($search): void {
                    $q->where('destination', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employee) use ($search): void {
                            $employee->where('employee_code', 'like', "%{$search}%")
                                ->orWhere('personnel_code', 'like', "%{$search}%")
                                ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.attendance.missions', compact('employees', 'projects', 'costCenters', 'missions'));
    }

    private function resetForm(): void
    {
        $this->reset([
            'employee_id',
            'project_id',
            'cost_center_id',
            'request_type',
            'destination',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'hours',
            'total_days',
            'description',
        ]);

        $this->request_type = 'hourly';
    }

    private function attributes(): array
    {
        return [
            'employee_id' => 'پرسنل',
            'project_id' => 'پروژه',
            'cost_center_id' => 'مرکز هزینه',
            'request_type' => 'نوع درخواست',
            'destination' => 'مقصد',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
            'start_time' => 'ساعت شروع',
            'end_time' => 'ساعت پایان',
            'hours' => 'مدت ساعت',
            'total_days' => 'تعداد روز',
            'description' => 'شرح',
        ];
    }
}
