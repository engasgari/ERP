<?php

namespace App\Http\Controllers;

use App\Models\WorkShift;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    public function index(Request $request)
    {
        return view('work-shifts.index');
    }

    public function create()
    {
        return view('work-shifts.form', ['shift' => new WorkShift()]);
    }

    public function store(Request $request)
    {
        WorkShift::create($this->validated($request));

        return redirect()->route('work-shifts.index')->with('success', 'شیفت کاری ثبت شد.');
    }

    public function edit(WorkShift $workShift)
    {
        return view('work-shifts.form', ['shift' => $workShift]);
    }

    public function update(Request $request, WorkShift $workShift)
    {
        $workShift->update($this->validated($request, $workShift->id));

        return redirect()->route('work-shifts.index')->with('success', 'شیفت کاری ویرایش شد.');
    }

    public function destroy(WorkShift $workShift)
    {
        if ($workShift->workGroups()->exists()) {
            return back()
                ->with('error', 'این شیفت قابل حذف نیست.')
                ->with('error_details', ['این شیفت به گروه کاری وصل است. ابتدا گروه‌های کاری مرتبط را اصلاح کنید.']);
        }

        $workShift->delete();

        return redirect()->route('work-shifts.index')->with('success', 'شیفت کاری حذف شد.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:work_shifts,code,' . $ignoreId,
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:1440',
            'daily_work_hours' => 'required|numeric|min:0|max:24',
            'overtime_multiplier' => 'required|numeric|min:1|max:5',
            'late_tolerance_minutes' => 'nullable|integer|min:0|max:1440',
            'early_leave_tolerance_minutes' => 'nullable|integer|min:0|max:1440',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
