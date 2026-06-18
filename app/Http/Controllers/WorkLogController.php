<?php
namespace App\Http\Controllers;

use App\Models\WorkLog;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\Request;

class WorkLogController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkLog::with(['employee', 'project']);
        $this->scopeWorkLogQuery($query, $request->user(), 'worklogs.view');

        if ($request->filled('employee')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->employee . '%')
                    ->orWhere('last_name', 'like', '%' . $request->employee . '%')
                    ->orWhere('national_code', 'like', '%' . $request->employee . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->employee . '%']);
            });
        }

        $projectId = $request->input('project_id', $request->input('project'));
        if ($projectId !== null && $projectId !== '') {
            $query->where('project_id', $projectId);
        }

        $startInput = $request->input('start_date', $request->input('start_date_fa'));
        if ($startInput) {
            $startDate = jalaliToGregorianDate($startInput);
            if ($startDate) {
                $query->whereDate('work_date', '>=', $startDate);
            }
        }

        $endInput = $request->input('end_date', $request->input('end_date_fa'));
        if ($endInput) {
            $endDate = jalaliToGregorianDate($endInput);
            if ($endDate) {
                $query->whereDate('work_date', '<=', $endDate);
            }
        }

        $hoursMin = normalizePersianDigits($request->input('hours_min'));
        if ($hoursMin !== null && $hoursMin !== '' && is_numeric($hoursMin)) {
            $query->where('hours', '>=', $hoursMin);
        }

        $hoursMax = normalizePersianDigits($request->input('hours_max'));
        if ($hoursMax !== null && $hoursMax !== '' && is_numeric($hoursMax)) {
            $query->where('hours', '<=', $hoursMax);
        }

        $workLogs = $query->latest()->paginate(20)->withQueryString();

        $dateErrors = [
            'start_date' => $startInput && !jalaliToGregorianDate($startInput) ? 'تاریخ شروع معتبر نیست.' : null,
            'end_date' => $endInput && !jalaliToGregorianDate($endInput) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        $projects = Project::orderBy('name')->get();

        return view('work-logs.index', compact('workLogs', 'projects', 'dateErrors'));
    }

    public function create()
    {
        $employees = $this->employeeQueryForUser(request()->user(), 'worklogs.manage')
            ->where('is_active', true)
            ->get();
        $projects = Project::where('status', 'active')->get();
        return view('work-logs.create', compact('employees', 'projects'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'work_date' => jalaliToGregorianDate($request->input('work_date')),
        ]);

        $request->validate([
            'work_date' => 'required|date',
            'employees' => 'required|array',
            'employees.*.employee_id' => 'required|exists:employees,id',
            'employees.*.start_time' => 'nullable|date_format:H:i',
            'employees.*.end_time' => 'nullable|date_format:H:i|after:employees.*.start_time',
            'employees.*.description' => 'nullable|string|max:500',
            'employees.*.project_id' => 'nullable|exists:projects,id',
        ], [
            'employees.*.employee_id.required' => 'شناسه پرسنل الزامی است',
            'employees.*.employee_id.exists' => 'پرسنل انتخاب شده معتبر نیست',
            'employees.*.start_time.date_format' => 'فرمت ساعت شروع معتبر نیست',
            'employees.*.end_time.date_format' => 'فرمت ساعت پایان معتبر نیست',
            'employees.*.end_time.after' => 'ساعت پایان باید بعد از ساعت شروع باشد',
            'employees.*.project_id.exists' => 'پروژه انتخاب شده معتبر نیست',
        ]);

        $workDate = $request->work_date;
        $createdCount = 0;

        foreach ($request->employees as $employeeData) {
            // فقط اگر هر دو زمان ورود و خروج پر شده باشند
            if (!empty($employeeData['employee_id']) &&
                !empty($employeeData['start_time']) &&
                !empty($employeeData['end_time'])) {

                // دریافت پرسنل
                $employee = Employee::find($employeeData['employee_id']);

                if (!$employee) {
                    continue;
                }

                abort_unless($request->user()->canAccessEmployee((int) $employee->id, 'worklogs.manage'), 403);

                // محاسبه ساعت کار
                $start = \Carbon\Carbon::parse($employeeData['start_time']);
                $end = \Carbon\Carbon::parse($employeeData['end_time']);

                if ($end->lessThan($start)) {
                    $end->addDay();
                }

                $hours = $start->diffInMinutes($end) / 60;
                $hourlyRate = $employee->hourly_rate;
                $totalAmount = $hours * $hourlyRate;

                $this->createWorkLog([
                    'employee_id' => $employeeData['employee_id'],
                    'project_id' => $employeeData['project_id'] ?? null,
                    'work_date' => $workDate,
                    'start_time' => $employeeData['start_time'],
                    'end_time' => $employeeData['end_time'],
                    'hours' => $hours,
                    'description' => $employeeData['description'] ?? null,
                    'hourly_rate' => $hourlyRate,
                    'total_amount' => $totalAmount
                ]);

                $createdCount++;
            }
        }

        $message = $createdCount > 0
            ? "حضور و غیاب برای {$createdCount} پرسنل با موفقیت ثبت شد!"
            : "هیچ داده‌ای برای ثبت وجود ندارد!";

        return redirect()->route('work-logs.index')->with('success', $message);
    }

    public function show(WorkLog $workLog)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $workLog->employee_id, 'worklogs.view'), 403);
        $workLog->load(['employee', 'project']);

        return view('work-logs.show', compact('workLog'));
    }

    private function createWorkLog(array $attributes): WorkLog
    {
        return WorkLog::create($attributes);
    }

    public function edit(WorkLog $workLog)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $workLog->employee_id, 'worklogs.manage'), 403);
        $employees = $this->employeeQueryForUser(request()->user(), 'worklogs.manage')
            ->where('is_active', true)
            ->get();
        $projects = Project::where('status', 'active')->get();
        // تبدیل فیلدهای تاریخ و زمان به Carbon object برای نمایش در فرم
        $workLog->work_date = \Carbon\Carbon::parse($workLog->work_date);
        $workLog->start_time = \Carbon\Carbon::parse($workLog->start_time);
        $workLog->end_time = \Carbon\Carbon::parse($workLog->end_time);

        return view('work-logs.edit', compact('workLog', 'employees', 'projects'));
    }


    public function update(Request $request, WorkLog $workLog)
    {
        abort_unless($request->user()->canAccessEmployee((int) $workLog->employee_id, 'worklogs.manage'), 403);
        abort_unless($request->user()->canAccessEmployee((int) $request->input('employee_id'), 'worklogs.manage'), 403);

        $request->merge([
            'work_date' => jalaliToGregorianDate($request->input('work_date')),
        ]);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'project_id' => 'nullable|exists:projects,id',
            'work_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'description' => 'nullable|string|max:500',
        ], [
            'end_time.after' => 'ساعت پایان باید بعد از ساعت شروع باشد',
        ]);

        // محاسبه ساعت کار
        $start = \Carbon\Carbon::parse($request->start_time);
        $end = \Carbon\Carbon::parse($request->end_time);

        // بررسی اگر زمان پایان کوچکتر از زمان شروع باشد (عبور از نیمه شب)
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $hours = $start->diffInMinutes($end) / 60;

        // دریافت نرخ ساعتی پرسنل
        $employee = Employee::find($request->employee_id);
        $hourlyRate = $employee->hourly_rate;

        // محاسبه مبلغ کل
        $totalAmount = $hours * $hourlyRate;

        // بروزرسانی رکورد
        $workLog->update([
            'employee_id' => $request->employee_id,
            'project_id' => $request->project_id,
            'work_date' => $request->work_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'hours' => $hours,
            'description' => $request->description,
            'hourly_rate' => $hourlyRate,
            'total_amount' => $totalAmount
        ]);

        return redirect()->route('work-logs.index')
            ->with('success', 'کارکرد با موفقیت بروزرسانی شد!');
    }

    public function destroy(WorkLog $workLog)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $workLog->employee_id, 'worklogs.manage'), 403);
        $workLog->delete();

        return redirect()->route('work-logs.index')
            ->with('success', 'کارکرد با موفقیت حذف شد!');
    }

    // گزارش کارکرد پرسنل
    public function employeeReport($employeeId)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $employeeId, 'worklogs.view'), 403);
        $employee = Employee::with('workLogs.project')->findOrFail($employeeId);

        $workLogs = $employee->workLogs()
            ->with('project')
            ->latest()
            ->get();

        $monthlySummary = $employee->workLogs()
            ->selectRaw('YEAR(work_date) as year, MONTH(work_date) as month, SUM(hours) as total_hours, SUM(total_amount) as total_amount')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return view('work-logs.employee-report', compact('employee', 'workLogs', 'monthlySummary'));
    }

    // گزارش کارکرد پروژه
    public function projectReport($projectId)
    {
        $project = Project::with('workLogs.employee')->findOrFail($projectId);

        $workLogs = $project->workLogs()
            ->with('employee')
            ->whereIn('employee_id', request()->user()->accessibleEmployeeIds('worklogs.view'))
            ->latest()
            ->get();

        $employeeSummary = $project->workLogs()
            ->selectRaw('employee_id, SUM(hours) as total_hours, SUM(total_amount) as total_amount')
            ->with('employee')
            ->whereIn('employee_id', request()->user()->accessibleEmployeeIds('worklogs.view'))
            ->groupBy('employee_id')
            ->get();

        return view('work-logs.project-report', compact('project', 'workLogs', 'employeeSummary'));
    }

    private function scopeWorkLogQuery($query, $user, string $scope): void
    {
        if (!$user->isAdmin()) {
            $query->whereIn('employee_id', $user->accessibleEmployeeIds($scope));
        }
    }

    private function employeeQueryForUser($user, string $scope)
    {
        $query = Employee::query();

        if (!$user->isAdmin()) {
            $query->whereIn('id', $user->accessibleEmployeeIds($scope));
        }

        return $query;
    }
}
