<?php

namespace App\Livewire\Attendance;

use App\Models\AttendanceLeave;
use App\Models\Employee;
use App\Services\LeaveManagementService;
use Livewire\Component;
use Livewire\WithPagination;

class Leaves extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $type = '';

    public ?int $employee_id = null;
    public string $request_type = 'hourly';
    public string $start_date = '';
    public string $end_date = '';
    public string $start_time = '';
    public string $end_time = '';
    public string $hours = '';
    public string $total_days = '';
    public string $leave_type = 'استحقاقی';
    public string $reason = '';
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
            'request_type' => ['required', 'in:hourly,daily'],
            'start_date' => ['required', 'string'],
            'end_date' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'leave_type' => ['nullable', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], $this->attributes());

        $startDate = jalaliToGregorianDate($data['start_date']);
        $endDate = jalaliToGregorianDate($data['end_date'] ?: $data['start_date']);

        if (! $startDate || ! $endDate) {
            $this->addError('start_date', 'تاریخ وارد شده معتبر نیست.');
            return;
        }

        app(LeaveManagementService::class)->request([
            'employee_id' => $data['employee_id'],
            'request_type' => $data['request_type'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'leave_date' => $startDate,
            'start_time' => $data['request_type'] === 'hourly' ? ($data['start_time'] ?: null) : null,
            'end_time' => $data['request_type'] === 'hourly' ? ($data['end_time'] ?: null) : null,
            'hours' => $data['request_type'] === 'hourly' ? ($data['hours'] ?: null) : null,
            'total_days' => $data['request_type'] === 'daily' ? ($data['total_days'] ?: null) : null,
            'type' => $data['leave_type'] ?: 'استحقاقی',
            'reason' => $data['reason'] ?: null,
            'status' => 'pending',
        ], auth()->id());

        $this->resetForm();
        session()->flash('success', 'درخواست مرخصی ثبت شد و در انتظار تایید قرار گرفت.');
    }

    public function approve(int $leaveId): void
    {
        $leave = AttendanceLeave::findOrFail($leaveId);
        app(LeaveManagementService::class)->approve($leave, auth()->id());

        session()->flash('success', 'درخواست مرخصی تایید شد.');
    }

    public function reject(int $leaveId): void
    {
        $leave = AttendanceLeave::findOrFail($leaveId);
        app(LeaveManagementService::class)->reject($leave, auth()->id(), $this->rejection_reason ?: null);
        $this->rejection_reason = '';

        session()->flash('success', 'درخواست مرخصی رد شد.');
    }

    public function cancel(int $leaveId): void
    {
        $leave = AttendanceLeave::findOrFail($leaveId);

        if ($leave->status === 'approved') {
            session()->flash('error', 'درخواست تایید شده را فقط با رد/اصلاح مدیریتی تغییر دهید.');
            return;
        }

        $leave->update(['status' => 'cancelled']);
        session()->flash('success', 'درخواست مرخصی لغو شد.');
    }

    public function render()
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->with('party')
            ->orderBy('employee_code')
            ->get();

        $leaves = AttendanceLeave::query()
            ->with('employee.party')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn ($query) => $query->where('request_type', $this->type))
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);
                $query->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('personnel_code', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.attendance.leaves', compact('employees', 'leaves'));
    }

    private function resetForm(): void
    {
        $this->reset([
            'employee_id',
            'request_type',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'hours',
            'total_days',
            'leave_type',
            'reason',
        ]);

        $this->request_type = 'hourly';
        $this->leave_type = 'استحقاقی';
    }

    private function attributes(): array
    {
        return [
            'employee_id' => 'پرسنل',
            'request_type' => 'نوع درخواست',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
            'start_time' => 'ساعت شروع',
            'end_time' => 'ساعت پایان',
            'hours' => 'مدت ساعت',
            'total_days' => 'تعداد روز',
            'leave_type' => 'نوع مرخصی',
            'reason' => 'علت',
        ];
    }
}
