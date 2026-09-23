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
use Illuminate\Support\Facades\DB;

class SeedOfficeWorkLogs1404MehrEsfand extends Command
{
    protected $signature = 'attendance:seed-office-1404-mehr-esfand
        {--project-id=489 : شناسه پروژه پیش‌فرض در صورت خالی بودن پروژه پرسنل}
        {--dry-run : فقط گزارش تعداد روزها، بدون ذخیره}';

    protected $description = 'ثبت تردد عادی اداری مهدی عسگری و میلاد رحیمی از مهر تا اسفند 1404 بر اساس تقویم (بدون ماموریت/اضافه‌کار/تأخیر)';

    private const IMPORT_BATCH = 'WORKLOG-SEED-1404-MEHR-ESFAND';

    private const ATTENDANCE_SOURCE = 'worklog-seed-1404-mehr-esfand';

    private const EMPLOYEE_CODES = ['EMP-001', 'EMP-20260718172529'];

    private const EMPLOYEE_NAMES = [
        ['first_name' => 'مهدی', 'last_name' => 'عسگری'],
        ['first_name' => 'میلاد', 'last_name' => 'رحیمی'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fallbackProjectId = (int) $this->option('project-id');
        $defaults = WorkCalendarDefaults::defaultCalendar(1404);
        $start = Carbon::parse(jalaliToGregorianDateSafe(1404, 7, 1))->startOfDay();
        $end = Carbon::parse(jalaliToGregorianDateSafe(1404, 12, 29))->endOfDay();

        $calendar = WorkCalendar::updateOrCreate(
            ['code' => 'CAL-1404'],
            [
                'name' => 'تقویم کاری ۱۴۰۴',
                'jalali_year' => 1404,
                'working_days' => $defaults['working_days'],
                'weekend_days' => $defaults['weekend_days'],
                'holidays' => $defaults['holidays'],
                'is_default' => false,
                'is_active' => true,
                'description' => 'تقویم کاری استاندارد 1404',
            ]
        );

        $shift = WorkShift::updateOrCreate(
            ['code' => 'SHIFT-1404-ADMIN-08-17'],
            [
                'name' => 'شیفت اداری ۱۴۰۴ (08 تا 17)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'daily_work_hours' => 8,
                'overtime_multiplier' => 1.4,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'is_active' => true,
                'description' => 'شیفت اداری استاندارد بر اساس تقویم 1404',
            ]
        );

        $group = WorkGroup::updateOrCreate(
            ['code' => 'WG-1404-ADMIN'],
            [
                'name' => 'گروه کاری اداری ۱۴۰۴',
                'work_shift_id' => $shift->id,
                'work_calendar_id' => $calendar->id,
                'payroll_rules' => [
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'break_minutes' => 60,
                    'daily_work_hours' => 8,
                ],
                'is_active' => true,
                'description' => 'گروه اداری برای کارکرد مهر تا اسفند 1404',
            ]
        );

        $employees = $this->resolveEmployees();

        if ($employees->count() !== 2) {
            $found = $employees->map(fn (Employee $e) => $e->personnel_code . ' ' . $e->first_name . ' ' . $e->last_name)->implode(', ');
            $this->error('پرسنل مهدی عسگری / میلاد رحیمی یافت نشدند. یافت‌شده: ' . ($found !== '' ? $found : 'هیچ'));

            return self::FAILURE;
        }

        $workDays = $this->workingDates($start, $end, $calendar);
        $hours = (float) $shift->daily_work_hours;

        $this->info('بازه: ' . formatJalaliDateSafe($start) . ' تا ' . formatJalaliDateSafe($end));
        $this->info('تعداد روز کاری غیرتعطیل: ' . $workDays->count());
        $this->info('شیفت: ' . $shift->name . ' | ساعت مفید روزانه: ' . $hours);
        $this->info('بدون ماموریت / اضافه‌کاری / تأخیر / تعجیل');

        if ($dryRun) {
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

                WorkLog::query()
                    ->where('employee_id', $employee->id)
                    ->where('import_batch', self::IMPORT_BATCH)
                    ->delete();

                AttendanceRawLog::query()
                    ->where('employee_id', $employee->id)
                    ->where('source', self::ATTENDANCE_SOURCE)
                    ->delete();

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
                        'description' => 'تردد عادی اداری شیفت 08-17 - ' . formatJalaliDateSafe($date),
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
                        'device_code' => 'ADMIN-ATT-1404',
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
                        'device_code' => 'ADMIN-ATT-1404',
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

        $this->info('ثبت تردد مهر تا اسفند 1404 انجام شد.');

        return self::SUCCESS;
    }

    private function resolveEmployees()
    {
        $byCode = Employee::query()
            ->whereIn('personnel_code', self::EMPLOYEE_CODES)
            ->orderBy('id')
            ->get();

        if ($byCode->count() === 2) {
            return $byCode;
        }

        $ids = [];
        foreach (self::EMPLOYEE_NAMES as $name) {
            $employee = Employee::query()
                ->where('first_name', $name['first_name'])
                ->where('last_name', $name['last_name'])
                ->orderBy('id')
                ->first();

            if ($employee) {
                $ids[] = $employee->id;
            }
        }

        return Employee::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    private function workingDates(Carbon $start, Carbon $end, WorkCalendar $calendar)
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
