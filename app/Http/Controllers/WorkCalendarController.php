<?php

namespace App\Http\Controllers;

use App\Models\WorkCalendar;
use App\Support\JalaliHolidayCalendar;
use App\Support\WorkCalendarDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkCalendarController extends Controller
{
    public function index(Request $request)
    {
        return view('work-calendars.index');
    }

    public function create()
    {
        $year = (int) (getCurrentPersianYear() ?: 1405);

        return view('work-calendars.form', $this->formViewData(new WorkCalendar([
            'jalali_year' => $year,
            'working_days' => WorkCalendarDefaults::workingDays(),
            'weekend_days' => WorkCalendarDefaults::weekendDays(),
            'holidays' => [],
            'is_active' => true,
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data = $this->normalizeCalendarFields($request, $data);
        $data = $this->applyDefaults($data, $request);
        WorkCalendar::create($data);

        return redirect()->route('work-calendars.index')->with('success', 'تقویم کاری ثبت شد.');
    }

    public function edit(WorkCalendar $workCalendar)
    {
        return view('work-calendars.form', $this->formViewData($workCalendar));
    }

    public function update(Request $request, WorkCalendar $workCalendar)
    {
        $data = $this->validated($request, $workCalendar->id);
        $data = $this->normalizeCalendarFields($request, $data);
        $data = $this->applyDefaults($data, $request);
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

    /**
     * @return array<string, mixed>
     */
    private function formViewData(WorkCalendar $calendar): array
    {
        $year = (int) (old('jalali_year', $calendar->jalali_year) ?: getCurrentPersianYear() ?: 1405);
        $selectedHolidays = old('holidays');
        if (is_string($selectedHolidays)) {
            $selectedHolidays = preg_split('/[\s,،;]+/u', $selectedHolidays) ?: [];
        }
        if (! is_array($selectedHolidays)) {
            $selectedHolidays = JalaliHolidayCalendar::toJalaliList($calendar->holidays ?: []);
        } else {
            $selectedHolidays = JalaliHolidayCalendar::toJalaliList($selectedHolidays);
        }

        return [
            'calendar' => $calendar,
            'dayLabels' => WorkCalendarDefaults::dayLabels(),
            'monthNames' => JalaliHolidayCalendar::monthNames(),
            'holidayYear' => $year,
            'selectedHolidays' => $selectedHolidays,
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:work_calendars,code,' . $ignoreId,
            'name' => 'required|string|max:255',
            'jalali_year' => 'nullable|integer|min:1300|max:1500',
            'working_days' => 'nullable|array',
            'working_days.*' => 'integer|between:0,6',
            'weekend_days' => 'nullable|array',
            'weekend_days.*' => 'integer|between:0,6',
            'holidays' => 'nullable|array',
            'holidays.*' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
        ]) + [
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function normalizeCalendarFields(Request $request, array $data): array
    {
        $data['working_days'] = WorkCalendarDefaults::normalizeDayList($request->input('working_days', []));
        $data['weekend_days'] = WorkCalendarDefaults::normalizeDayList($request->input('weekend_days', []));
        $data['holidays'] = $this->parseHolidays($request->input('holidays', []));

        return $data;
    }

    /**
     * @param  array<int, string>|string|null  $value
     * @return list<string> Gregorian Y-m-d dates
     */
    private function parseHolidays(array|string|null $value): array
    {
        $items = is_array($value)
            ? collect($value)
            : collect(preg_split('/[\s,،;]+/u', (string) $value) ?: []);

        $items = $items
            ->map(fn ($item) => trim((string) normalizePersianDigits((string) $item)))
            ->filter()
            ->values();

        $holidays = [];
        $invalid = [];

        foreach ($items as $item) {
            $normalized = str_replace(['.', '/'], '-', $item);

            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $matches) !== 1) {
                $invalid[] = $item;
                continue;
            }

            $year = (int) $matches[1];

            if ($year >= 1700) {
                $invalid[] = $item;
                continue;
            }

            $gregorian = jalaliToGregorianDate($item);
            if (! $gregorian) {
                $invalid[] = $item;
                continue;
            }

            $holidays[] = $gregorian;
        }

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'holidays' => 'تاریخ‌های نامعتبر (فقط شمسی مثل ۱۴۰۵/۰۶/۰۸): ' . implode('، ', $invalid),
            ]);
        }

        sort($holidays);

        return array_values(array_unique($holidays));
    }

    private function applyDefaults(array $data, Request $request): array
    {
        $year = (int) ($data['jalali_year'] ?? 0);

        if (! in_array($year, [1404, 1405], true)) {
            return $data;
        }

        $defaults = WorkCalendarDefaults::defaultCalendar($year);
        $data['working_days'] = $data['working_days'] ?: $defaults['working_days'];
        $data['weekend_days'] = $data['weekend_days'] ?: $defaults['weekend_days'];

        // تعطیلات فقط وقتی فرم انتخاب تعطیل نفرستاده (ایجاد خالی) از پیش‌فرض پر می‌شود.
        if (! $request->has('holidays_managed') && ($data['holidays'] ?? []) === []) {
            $data['holidays'] = $defaults['holidays'];
        }

        return $data;
    }
}
