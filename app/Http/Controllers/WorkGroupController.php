<?php

namespace App\Http\Controllers;

use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkShift;
use Illuminate\Http\Request;

class WorkGroupController extends Controller
{
    public function index(Request $request)
    {
        return view('work-groups.index');
    }

    public function create()
    {
        return view('work-groups.form', $this->formData(new WorkGroup()));
    }

    public function store(Request $request)
    {
        WorkGroup::create($this->validated($request));

        return redirect()->route('work-groups.index')->with('success', 'گروه کاری ثبت شد.');
    }

    public function edit(WorkGroup $workGroup)
    {
        return view('work-groups.form', $this->formData($workGroup));
    }

    public function update(Request $request, WorkGroup $workGroup)
    {
        $workGroup->update($this->validated($request, $workGroup->id));

        return redirect()->route('work-groups.index')->with('success', 'گروه کاری ویرایش شد.');
    }

    public function destroy(WorkGroup $workGroup)
    {
        if ($workGroup->employees()->exists()) {
            return back()
                ->with('error', 'این گروه کاری قابل حذف نیست.')
                ->with('error_details', ['پرسنل به این گروه کاری وصل هستند. ابتدا عضویت پرسنل را اصلاح کنید.']);
        }

        $workGroup->delete();

        return redirect()->route('work-groups.index')->with('success', 'گروه کاری حذف شد.');
    }

    private function formData(WorkGroup $group): array
    {
        return [
            'group' => $group,
            'shifts' => WorkShift::where('is_active', true)->orderBy('name')->get(),
            'calendars' => WorkCalendar::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:work_groups,code,' . $ignoreId,
            'name' => 'required|string|max:255',
            'work_shift_id' => 'nullable|exists:work_shifts,id',
            'work_calendar_id' => 'nullable|exists:work_calendars,id',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
