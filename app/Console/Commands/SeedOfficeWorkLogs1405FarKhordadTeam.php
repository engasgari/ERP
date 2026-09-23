<?php

namespace App\Console\Commands;

use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\WorkCalendar;
use App\Models\WorkGroup;
use App\Models\WorkLog;
use App\Models\WorkShift;
use App\Support\WorkCalendarDefaults;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeedOfficeWorkLogs1405FarKhordadTeam extends Command
{
    protected $signature = 'attendance:seed-office-1405-far-khordad-team
        {--project-id=489 : شناسه پروژه پیش‌فرض در صورت خالی بودن پروژه پرسنل}
        {--dry-run : فقط گزارش تعداد روزها، بدون ذخیره}';

    protected $description = 'ثبت کارکرد روزانه اداری ۵ نفر (میلاد رحیمی، محسن جانی، شیما حسنی، محسن اخلاصی، حمید علیزاده) از فروردین تا خرداد 1405';

    private const IMPORT_BATCH = 'WORKLOG-SEED-1405-FAR-KHORDAD-TEAM';

    private const ATTENDANCE_SOURCE = 'worklog-seed-1405-far-khordad-team';

    /** @var list<array{first_name: string, last_name: string}> */
    private const EMPLOYEE_NAMES = [
        ['first_name' => 'میلاد', 'last_name' => 'رحیمی'],
        ['first_name' => 'محسن', 'last_name' => 'جانی'],
        ['first_name' => 'شیما', 'last_name' => 'حسنی'],
        ['first_name' => 'محسن', 'last_name' => 'اخلاصی'],
        ['first_name' => 'حمید', 'last_name' => 'علیزاده'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fallbackProjectId = (int) $this->option('project-id');
        $defaults = WorkCalendarDefaults::defaultCalendar(1405);
        $start = Carbon::parse(jalaliToGregorianDateSafe(1405, 1, 1))->startOfDay();
        $end = Carbon::parse(jalaliToGregorianDateSafe(1405, 3, 31))->endOfDay();

        $calendar = WorkCalendar::updateOrCreate(
            ['code' => 'CAL-1405'],
            [
                'name' => 'تقویم کاری ۱۴۰۵',
                'jalali_year' => 1405,
                'working_days' => $defaults['working_days'],
                'weekend_days' => $defaults['weekend_days'],
                'holidays' => $defaults['holidays'],
                'is_default' => true,
                'is_active' => true,
                'description' => 'تقویم کاری استاندارد 1405',
            ]
        );

        $shift = WorkShift::updateOrCreate(
            ['code' => 'SHIFT-1405-ADMIN-08-17'],
            [
                'name' => 'شیفت اداری ۱۴۰۵ (08 تا 17)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'daily_work_hours' => 8,
                'overtime_multiplier' => 1.4,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'is_active' => true,
                'description' => 'شیفت اداری استاندارد بر اساس تقویم 1405',
            ]
        );

        $group = WorkGroup::updateOrCreate(
            ['code' => 'WG-1405-ADMIN'],
            [
                'name' => 'گروه کاری اداری ۱۴۰۵',
                'work_shift_id' => $shift->id,
                'work_calendar_id' => $calendar->id,
                'payroll_rules' => [
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'break_minutes' => 60,
                    'daily_work_hours' => 8,
                ],
                'is_active' => true,
                'description' => 'گروه اداری برای کارکرد فروردین تا خرداد 1405',
            ]
        );

        $employees = $this->resolveEmployees();

        if ($employees->count() !== count(self::EMPLOYEE_NAMES)) {
            $found = $employees->map(fn (Employee $e) => $e->first_name . ' ' . $e->last_name)->implode(', ');
            $missing = collect(self::EMPLOYEE_NAMES)
                ->filter(fn (array $name) => ! $employees->contains(
                    fn (Employee $e) => $e->first_name === $name['first_name'] && $e->last_name === $name['last_name']
                ))
                ->map(fn (array $name) => $name['first_name'] . ' ' . $name['last_name'])
                ->implode(', ');

            $this->error('تعداد پرسنل یافت‌شده: ' . $employees->count() . ' از ' . count(self::EMPLOYEE_NAMES));
            $this->error('یافت‌شده: ' . ($found !== '' ? $found : 'هیچ'));
            $this->error('یافت‌نشده: ' . $missing);

            return self::FAILURE;
        }

        $workDays = $this->workingDates($start, $end, $calendar);
        $hours = (float) $shift->daily_work_hours;

        $this->info('بازه: ' . formatJalaliDateSafe($start) . ' تا ' . formatJalaliDateSafe($end));
        $this->info('تعداد روز کاری غیرتعطیل: ' . $workDays->count());
        $this->info('شیفت: ' . $shift->name . ' | ساعت مفید روزانه: ' . $hours);
        $this->info('تقویم: ' . $calendar->name);
        $this->info('بدون ماموریت / اضافه‌کاری / تأخیر / تعجیل');

        if ($dryRun) {
            foreach ($employees as $employee) {
                $this->line(sprintf(
                    '[dry-run] %s %s: %d روز × %s ساعت',
                    $employee->first_name,
                    $employee->last_name,
                    $workDays->count(),
                    number_format($hours, 2)
                ));
            }
            $this->warn('dry-run فعال است؛ داده‌ای ذخیره نشد.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($employees, $group, $shift, $workDays, $start, $end, $hours, $fallbackProjectId): void {
            foreach ($employees as $employee) {
                DB::table('work_group_employee')->updateOrInsert(
                    [
                        'employee_id' => $employee->id,
                        'work_group_id' => $group->id,
                    ],
                    [
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->clearSeededAttendance($employee, $start, $end);

                $hourlyRate = (float) $employee->hourly_rate;
                if ($hourlyRate <= 0 && (float) $employee->base_salary > 0) {
                    $hourlyRate = round((float) $employee->base_salary / 220, 2);
                }

                $projectId = $employee->default_project_id ?: $fallbackProjectId;
                $created = 0;

                foreach ($workDays as $date) {
                    $checkIn = Carbon::parse($date->toDateString() . ' 08:00:00');
                    $checkOut = Carbon::parse($date->toDateString() . ' 17:00:00');

                    WorkLog::create([
                        'employee_id' => $employee->id,
                        'project_id' => $projectId,
                        'cost_center' => $employee->default_cost_center,
                        'work_date' => $date->toDateString(),
                        'check_in_time' => '08:00:00',
                        'check_out_time' => '17:00:00',
                        'start_time' => '08:00:00',
                        'end_time' => '17:00:00',
                        'hours' => $hours,
                        'overtime_hours' => 0,
                        'delay_hours' => 0,
                        'early_leave_hours' => 0,
                        'mission_hours' => 0,
                        'leave_hours' => 0,
                        'absence_hours' => 0,
                        'description' => 'کارکرد اداری شیفت 08-17 - ' . formatJalaliDateSafe($date),
                        'hourly_rate' => $hourlyRate,
                        'total_amount' => round($hours * $hourlyRate, 2),
                        'attendance_source' => self::ATTENDANCE_SOURCE,
                        'import_batch' => self::IMPORT_BATCH,
                    ]);

                    AttendanceRawLog::create([
                        'employee_id' => $employee->id,
                        'attendance_card_number' => $employee->attendance_card_number ?: $employee->personnel_code,
                        'logged_at' => $checkIn,
                        'direction' => 'in',
                        'device_code' => 'ADMIN-ATT-1405',
                        'source' => self::ATTENDANCE_SOURCE,
                        'payload' => [
                            'work_date' => $date->toDateString(),
                            'shift_code' => $shift->code,
                            'event' => 'check_in',
                        ],
                    ]);

                    AttendanceRawLog::create([
                        'employee_id' => $employee->id,
                        'attendance_card_number' => $employee->attendance_card_number ?: $employee->personnel_code,
                        'logged_at' => $checkOut,
                        'direction' => 'out',
                        'device_code' => 'ADMIN-ATT-1405',
                        'source' => self::ATTENDANCE_SOURCE,
                        'payload' => [
                            'work_date' => $date->toDateString(),
                            'shift_code' => $shift->code,
                            'event' => 'check_out',
                        ],
                    ]);

                    $created++;
                }

                $this->line(sprintf(
                    '%s %s: %d روز کاری ثبت شد (جمع ساعات: %s | پروژه: %d)',
                    $employee->first_name,
                    $employee->last_name,
                    $created,
                    number_format($created * $hours, 2),
                    $projectId
                ));
            }
        });

        $this->info('ثبت کارکرد فروردین تا خرداد 1405 برای ۵ نفر انجام شد.');

        return self::SUCCESS;
    }

    private function resolveEmployees(): Collection
    {
        $employees = collect();

        foreach (self::EMPLOYEE_NAMES as $name) {
            $employee = Employee::query()
                ->where('first_name', $name['first_name'])
                ->where('last_name', $name['last_name'])
                ->orderBy('id')
                ->first();

            if ($employee) {
                $employees->push($employee);
            }
        }

        return $employees->unique('id')->values();
    }

    private function clearSeededAttendance(Employee $employee, Carbon $start, Carbon $end): void
    {
        WorkLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query): void {
                $query->where('import_batch', self::IMPORT_BATCH)
                    ->orWhere('import_batch', 'WORKLOG-SEED-1405-FAR-ORDIB')
                    ->orWhere('attendance_source', 'like', 'worklog-seed-1405%');
            })
            ->delete();

        AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where(function ($query): void {
                $query->where('source', self::ATTENDANCE_SOURCE)
                    ->orWhere('source', 'worklog-seed-1405-far-ordib')
                    ->orWhere('source', 'like', 'worklog-seed-1405%');
            })
            ->delete();
    }

    private function workingDates(Carbon $start, Carbon $end, WorkCalendar $calendar): Collection
    {
        $workingDays = collect($calendar->working_days ?? WorkCalendarDefaults::workingDays());
        $holidays = collect($calendar->holidays ?? []);
        $period = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->endOfDay());
        $dates = collect();

        foreach ($period as $date) {
            if ($holidays->contains($date->toDateString()) || $holidays->contains(formatJalaliDateSafe($date))) {
                continue;
            }

            if (! $workingDays->contains((int) $date->dayOfWeek)) {
                continue;
            }

            $dates->push($date->copy());
        }

        return $dates;
    }
}
