<?php

namespace App\Livewire\Attendance;

use App\Models\Employee;
use App\Models\WorkLog;
use App\Services\AttendanceDailySummaryService;
use App\Services\LeaveManagementService;
use App\Services\MissionManagementService;
use App\Services\WorkLogManualEntryService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DailySummary extends Component
{
    public ?int $employee_id = null;

    public int $year = 0;

    public int $month = 0;

    public bool $showLeaveModal = false;

    public bool $showMissionModal = false;

    public bool $showEditModal = false;

    public string $actionDate = '';

    public string $actionDateJalali = '';

    public string $leave_type = 'استحقاقی';

    public string $leave_request_type = 'hourly';

    public string $leave_start_time = '';

    public string $leave_end_time = '';

    public string $leave_reason = '';

    public string $mission_request_type = 'daily';

    public string $mission_hours = '';

    public string $mission_description = '';

    public ?int $edit_work_log_id = null;

    public string $edit_start_time = '';

    public string $edit_end_time = '';

    protected $queryString = [
        'employee_id' => ['except' => null],
        'year' => ['except' => 0],
        'month' => ['except' => 0],
    ];

    public function mount(): void
    {
        $this->year = (int) request()->integer('year', (int) getCurrentPersianYear());
        $this->month = (int) request()->integer('month', (int) getCurrentPersianMonth());
        $this->normalizeFilters();
    }

    public function updated($name): void
    {
        if (in_array($name, ['employee_id', 'year', 'month'], true)) {
            $this->normalizeFilters();
        }
    }

    public function openLeave(string $workDate, string $jalali): void
    {
        $this->actionDate = $workDate;
        $this->actionDateJalali = $jalali;
        $this->leave_type = 'استحقاقی';
        $this->leave_request_type = 'hourly';
        $this->leave_start_time = '';
        $this->leave_end_time = '';
        $this->leave_reason = '';
        $this->showLeaveModal = true;
    }

    public function openMission(string $workDate, string $jalali): void
    {
        $this->actionDate = $workDate;
        $this->actionDateJalali = $jalali;
        $this->mission_request_type = 'daily';
        $this->mission_hours = '';
        $this->mission_description = '';
        $this->showMissionModal = true;
    }

    public function openEdit(string $workDate, string $jalali, int $workLogId = 0, string $firstIn = '', string $lastOut = ''): void
    {
        $this->actionDate = $workDate;
        $this->actionDateJalali = $jalali;
        $this->edit_work_log_id = $workLogId > 0 ? $workLogId : null;
        $this->edit_start_time = $firstIn !== '-' ? $firstIn : '';
        $this->edit_end_time = $lastOut !== '-' ? $lastOut : '';
        $this->showEditModal = true;
    }

    public function closeModals(): void
    {
        $this->showLeaveModal = false;
        $this->showMissionModal = false;
        $this->showEditModal = false;
        $this->resetValidation();
    }

    public function saveLeave(): void
    {
        $this->normalizeFilters();

        $rules = [
            'employee_id' => ['required', 'exists:employees,id'],
            'actionDate' => ['required', 'date'],
            'leave_request_type' => ['required', 'in:hourly,daily'],
            'leave_type' => ['nullable', 'string', 'max:50'],
            'leave_reason' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->leave_request_type === 'hourly') {
            $rules['leave_start_time'] = ['required', 'date_format:H:i'];
            $rules['leave_end_time'] = ['required', 'date_format:H:i'];
        }

        $this->validate($rules, [], [
            'leave_start_time' => 'شروع مرخصی',
            'leave_end_time' => 'پایان مرخصی',
        ]);

        $hours = null;
        if ($this->leave_request_type === 'hourly') {
            $startAt = \Carbon\Carbon::createFromFormat('H:i', $this->leave_start_time);
            $endAt = \Carbon\Carbon::createFromFormat('H:i', $this->leave_end_time);
            if ($endAt->lessThanOrEqualTo($startAt)) {
                $endAt->addDay();
            }
            $hours = round($startAt->diffInMinutes($endAt) / 60, 2);
            if ($hours <= 0) {
                $this->addError('leave_end_time', 'پایان مرخصی باید بعد از شروع باشد.');
                return;
            }
        }

        app(LeaveManagementService::class)->request([
            'employee_id' => $this->employee_id,
            'request_type' => $this->leave_request_type,
            'start_date' => $this->actionDate,
            'end_date' => $this->actionDate,
            'leave_date' => $this->actionDate,
            'start_time' => $this->leave_request_type === 'hourly' ? $this->leave_start_time : null,
            'end_time' => $this->leave_request_type === 'hourly' ? $this->leave_end_time : null,
            'hours' => $hours,
            'total_days' => $this->leave_request_type === 'daily' ? 1 : null,
            'type' => $this->leave_type ?: 'استحقاقی',
            'reason' => $this->leave_reason ?: null,
            'status' => 'pending',
        ], auth()->id());

        $this->closeModals();
        session()->flash('success', 'درخواست مرخصی ثبت شد (در انتظار تایید).');
    }

    public function saveMission(): void
    {
        $this->normalizeFilters();
        $this->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'actionDate' => ['required', 'date'],
            'mission_request_type' => ['required', 'in:hourly,daily'],
            'mission_hours' => ['nullable', 'numeric', 'min:0'],
            'mission_description' => ['nullable', 'string', 'max:1000'],
        ]);

        app(MissionManagementService::class)->request([
            'employee_id' => $this->employee_id,
            'request_type' => $this->mission_request_type,
            'start_date' => $this->actionDate,
            'end_date' => $this->actionDate,
            'mission_date' => $this->actionDate,
            'hours' => $this->mission_request_type === 'hourly' ? ($this->mission_hours ?: null) : null,
            'total_days' => $this->mission_request_type === 'daily' ? 1 : null,
            'description' => $this->mission_description ?: null,
            'status' => 'pending',
        ], auth()->id());

        $this->closeModals();
        session()->flash('success', 'درخواست ماموریت ثبت شد (در انتظار تایید).');
    }

    public function saveEdit(): void
    {
        $this->normalizeFilters();
        $this->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'actionDate' => ['required', 'date'],
            'edit_start_time' => ['required', 'date_format:H:i'],
            'edit_end_time' => ['required', 'date_format:H:i'],
        ]);

        try {
            if ($this->edit_work_log_id) {
                $log = WorkLog::query()
                    ->whereKey($this->edit_work_log_id)
                    ->where('employee_id', $this->employee_id)
                    ->firstOrFail();

                $start = $this->edit_start_time;
                $end = $this->edit_end_time;
                $startAt = \Carbon\Carbon::createFromFormat('H:i', $start);
                $endAt = \Carbon\Carbon::createFromFormat('H:i', $end);
                if ($endAt->lessThan($startAt)) {
                    $endAt->addDay();
                }
                $hours = round($startAt->diffInMinutes($endAt) / 60, 2);

                $log->update([
                    'start_time' => $start,
                    'end_time' => $end,
                    'check_in_time' => $start,
                    'check_out_time' => $end,
                    'hours' => $hours,
                    'is_incomplete' => false,
                    'total_amount' => round($hours * (float) ($log->hourly_rate ?: 0), 2),
                ]);
            } else {
                app(WorkLogManualEntryService::class)->createMany([[
                    'employee_id' => $this->employee_id,
                    'work_date_fa' => $this->actionDateJalali,
                    'start_time' => $this->edit_start_time,
                    'end_time' => $this->edit_end_time,
                ]], auth()->user());
            }
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->closeModals();
        session()->flash('success', 'تردد روز ذخیره شد.');
    }

    public function render()
    {
        $this->normalizeFilters();

        $employees = Employee::query()
            ->where('is_active', true)
            ->with('party')
            ->orderBy('personnel_code')
            ->get();

        $days = [];
        $employee = null;

        if ($this->employee_id) {
            $employee = Employee::query()
                ->with(['party', 'workGroupAssignments.workGroup.shift', 'workGroupAssignments.workGroup.calendar'])
                ->find($this->employee_id);

            if ($employee) {
                $days = app(AttendanceDailySummaryService::class)
                    ->daysFor($employee, $this->year, $this->month);
            }
        }

        return view('livewire.attendance.daily-summary', compact('employees', 'days', 'employee'));
    }

    private function normalizeFilters(): void
    {
        if ($this->year < 1400 || $this->year > 1500) {
            $this->year = (int) getCurrentPersianYear();
        }

        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) getCurrentPersianMonth();
        }
    }
}
