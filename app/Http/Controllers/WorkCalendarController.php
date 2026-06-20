<?php

namespace App\Http\Controllers;

use App\Models\WorkCalendar;
use App\Support\WorkCalendarDefaults;
use Illuminate\Http\Request;

class WorkCalendarController extends Controller
{
    public function index(Request $request)
    {
        return view('work-calendars.index');
    }

    public function create()
    {
        return view('work-calendars.form', ['calendar' => new WorkCalendar()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['working_days'] = $this->csv($request->input('working_days'));
        $data['weekend_days'] = $this->csv($request->input('weekend_days'));
        $data['holidays'] = $this->csv($request->input('holidays'));
        $data = $this->applyDefaults($data);
        WorkCalendar::create($data);

        return redirect()->route('work-calendars.index')->with('success', 'تقویم کاری ثبت شد.');
    }

    public function edit(WorkCalendar $workCalendar)
    {
        return view('work-calendars.form', ['calendar' => $workCalendar]);
    }

    public function update(Request $request, WorkCalendar $workCalendar)
    {
        $data = $this->validated($request, $workCalendar->id);
        $data['working_days'] = $this->csv($request->input('working_days'));
        $data['weekend_days'] = $this->csv($request->input('weekend_days'));
        $data['holidays'] = $this->csv($request->input('holidays'));
        $data = $this->applyDefaults($data);
        $workCalendar->update($data);

        return redirect()->route('work-calendars.index')->with('success', 'تقویم کاری ویرایش شد.');
    }

    public function destroy(WorkCalendar $workCalendar)
    {
        if ($workCalendar->workGroups()->exists()) {
            return back()
                ->with('error', 'این تقویم قابل حذف نیست.')
                ->with('error_details', ['این تقویم به گروه کاری وصل است. ابتدا گروه‌های کاری مرتبط را اصلاح کنید.']);
        }

        $workCalendar->delete();

        return redirect()->route('work-calendars.index')->with('success', 'تقویم کاری حذف شد.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:work_calendars,code,' . $ignoreId,
            'name' => 'required|string|max:255',
            'jalali_year' => 'nullable|integer|min:1300|max:1500',
            'working_days' => 'nullable|string|max:1000',
            'weekend_days' => 'nullable|string|max:1000',
            'holidays' => 'nullable|string|max:4000',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]) + [
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function csv(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function applyDefaults(array $data): array
    {
        $year = (int) ($data['jalali_year'] ?? 0);

        if (in_array($year, [1404, 1405], true)) {
            $defaults = WorkCalendarDefaults::defaultCalendar($year);

            $data['working_days'] = $data['working_days'] ?: $defaults['working_days'];
            $data['weekend_days'] = $data['weekend_days'] ?: $defaults['weekend_days'];
            $data['holidays'] = $data['holidays'] ?: $defaults['holidays'];
        }

        return $data;
    }
}
