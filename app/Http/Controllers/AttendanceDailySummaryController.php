<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Services\AttendanceDailySummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceDailySummaryController extends Controller
{
    public function print(Request $request, AttendanceDailySummaryService $summaries): View
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'year' => ['required', 'integer', 'min:1400', 'max:1500'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $employee = Employee::query()
            ->with(['party', 'organizationUnit', 'positionRecord'])
            ->findOrFail((int) $validated['employee_id']);

        $report = $summaries->buildPrintReport(
            $employee,
            (int) $validated['year'],
            (int) $validated['month'],
        );

        return view('attendance.daily-summary-print', [
            'employee' => $employee,
            'company' => CompanySetting::query()->first(),
            'days' => $report['days'],
            'totals' => $report['totals'],
            'year' => $report['year'],
            'month' => $report['month'],
            'monthLabel' => $report['month_label'],
            'backUrl' => route('attendance.daily-summary', [
                'employee_id' => $employee->id,
                'year' => $report['year'],
                'month' => $report['month'],
            ]),
        ]);
    }
}
