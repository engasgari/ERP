<?php

namespace App\Livewire\Employees;

use App\Models\AttendanceLeave;
use App\Models\AttendanceMission;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\PayrollCalculation;
use App\Services\LeaveManagementService;
use App\Services\MissionManagementService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SelfService extends Component
{
    public string $leave_request_type = 'hourly';
    public string $leave_start_date = '';
    public string $leave_end_date = '';
    public string $leave_start_time = '';
    public string $leave_end_time = '';
    public string $leave_hours = '';
    public string $leave_total_days = '';
    public string $leave_reason = '';

    public string $mission_request_type = 'hourly';
    public string $mission_destination = '';
    public string $mission_start_date = '';
    public string $mission_end_date = '';
    public string $mission_start_time = '';
    public string $mission_end_time = '';
    public string $mission_hours = '';
    public string $mission_total_days = '';
    public string $mission_description = '';

    public function requestLeave(): void
    {
        $employee = $this->employee();

        if (! $employee) {
            session()->flash('error', 'برای کاربر فعلی پرسنل متصل پیدا نشد.');
            return;
        }

        $this->validate([
            'leave_request_type' => ['required', 'in:hourly,daily'],
            'leave_start_date' => ['required', 'string'],
            'leave_end_date' => ['nullable', 'string'],
            'leave_start_time' => ['nullable', 'date_format:H:i'],
            'leave_end_time' => ['nullable', 'date_format:H:i'],
            'leave_hours' => ['nullable', 'numeric', 'min:0'],
            'leave_total_days' => ['nullable', 'numeric', 'min:0'],
            'leave_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $startDate = jalaliToGregorianDate($this->leave_start_date);
        $endDate = jalaliToGregorianDate($this->leave_end_date ?: $this->leave_start_date);

        if (! $startDate || ! $endDate) {
            $this->addError('leave_start_date', 'تاریخ مرخصی معتبر نیست.');
            return;
        }

        app(LeaveManagementService::class)->request([
            'employee_id' => $employee->id,
            'request_type' => $this->leave_request_type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'leave_date' => $startDate,
            'start_time' => $this->leave_request_type === 'hourly' ? ($this->leave_start_time ?: null) : null,
            'end_time' => $this->leave_request_type === 'hourly' ? ($this->leave_end_time ?: null) : null,
            'hours' => $this->leave_request_type === 'hourly' ? ($this->leave_hours ?: null) : null,
            'total_days' => $this->leave_request_type === 'daily' ? ($this->leave_total_days ?: null) : null,
            'type' => 'استحقاقی',
            'reason' => $this->leave_reason ?: null,
        ], auth()->id());

        $this->reset(['leave_start_date', 'leave_end_date', 'leave_start_time', 'leave_end_time', 'leave_hours', 'leave_total_days', 'leave_reason']);
        session()->flash('success', 'درخواست مرخصی شما ثبت شد.');
    }

    public function requestMission(): void
    {
        $employee = $this->employee();

        if (! $employee) {
            session()->flash('error', 'برای کاربر فعلی پرسنل متصل پیدا نشد.');
            return;
        }

        $this->validate([
            'mission_request_type' => ['required', 'in:hourly,daily'],
            'mission_destination' => ['nullable', 'string', 'max:255'],
            'mission_start_date' => ['required', 'string'],
            'mission_end_date' => ['nullable', 'string'],
            'mission_start_time' => ['nullable', 'date_format:H:i'],
            'mission_end_time' => ['nullable', 'date_format:H:i'],
            'mission_hours' => ['nullable', 'numeric', 'min:0'],
            'mission_total_days' => ['nullable', 'numeric', 'min:0'],
            'mission_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $startDate = jalaliToGregorianDate($this->mission_start_date);
        $endDate = jalaliToGregorianDate($this->mission_end_date ?: $this->mission_start_date);

        if (! $startDate || ! $endDate) {
            $this->addError('mission_start_date', 'تاریخ ماموریت معتبر نیست.');
            return;
        }

        app(MissionManagementService::class)->request([
            'employee_id' => $employee->id,
            'request_type' => $this->mission_request_type,
            'destination' => $this->mission_destination ?: null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'mission_date' => $startDate,
            'start_time' => $this->mission_request_type === 'hourly' ? ($this->mission_start_time ?: null) : null,
            'end_time' => $this->mission_request_type === 'hourly' ? ($this->mission_end_time ?: null) : null,
            'hours' => $this->mission_request_type === 'hourly' ? ($this->mission_hours ?: null) : null,
            'total_days' => $this->mission_request_type === 'daily' ? ($this->mission_total_days ?: null) : null,
            'description' => $this->mission_description ?: null,
        ], auth()->id());

        $this->reset(['mission_destination', 'mission_start_date', 'mission_end_date', 'mission_start_time', 'mission_end_time', 'mission_hours', 'mission_total_days', 'mission_description']);
        session()->flash('success', 'درخواست ماموریت شما ثبت شد.');
    }

    public function render()
    {
        $employee = $this->employee();

        $leaves = collect();
        $missions = collect();
        $summaries = collect();
        $payslips = collect();
        $balances = collect();

        if ($employee) {
            $leaves = AttendanceLeave::query()->where('employee_id', $employee->id)->latest()->take(8)->get();
            $missions = AttendanceMission::query()->where('employee_id', $employee->id)->latest()->take(8)->get();
            $summaries = AttendanceSummary::query()->where('employee_id', $employee->id)->orderByDesc('year')->orderByDesc('month')->take(6)->get();
            $payslips = PayrollCalculation::query()->with(['period', 'payslip'])->where('employee_id', $employee->id)->latest()->take(6)->get();
            $balances = LeaveBalance::query()->where('employee_id', $employee->id)->orderByDesc('year')->take(3)->get();
        }

        return view('livewire.employees.self-service', compact('employee', 'leaves', 'missions', 'summaries', 'payslips', 'balances'));
    }

    private function employee(): ?Employee
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $accessible = $user->accessibleEmployees()->with('party')->first();
        if ($accessible) {
            return $accessible;
        }

        return Employee::query()
            ->with('party')
            ->where(function ($query) use ($user): void {
                if (Schema::hasColumn('employees', 'user_id')) {
                    $query->where('user_id', $user->id)
                        ->orWhere('email', $user->email)
                        ->orWhereHas('party', fn ($party) => $party->where('email', $user->email));

                    return;
                }

                $query->where('email', $user->email)
                    ->orWhereHas('party', fn ($party) => $party->where('email', $user->email));
            })
            ->first();
    }
}
