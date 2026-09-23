<?php

namespace App\Console\Commands;

use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\EmploymentOrder;
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

class SeedActiveOrderWorkLogs1405FarMordad extends Command
{
    protected $signature = 'attendance:seed-active-orders-1405-far-mordad
        {--project-id=489 : شناسه پروژه پیش‌فرض در صورت خالی بودن پروژه پرسنل}
        {--from-month=1 : ماه شروع شمسی (پیش‌فرض: فروردین)}
        {--to-month=5 : ماه پایان شمسی (پیش‌فرض: مرداد)}
        {--year=1405 : سال شمسی}
        {--overwrite-seeded : کارکردهای قبلی با همین batch را بازنویسی کند}
        {--dry-run : فقط گزارش، بدون ذخیره}';

    protected $description = 'ثبت کارکرد روزانه اداری برای همه پرسنل دارای حکم فعال تا پایان مرداد 1405';

    private const IMPORT_BATCH = 'WORKLOG-SEED-1405-FAR-MORDAD-ACTIVE';

    private const ATTENDANCE_SOURCE = 'worklog-seed-1405-far-mordad-active';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwriteSeeded = (bool) $this->option('overwrite-seeded');
        $fallbackProjectId = (int) $this->option('project-id');
        $year = (int) $this->option('year');
        $fromMonth = max(1, min(12, (int) $this->option('from-month')));
        $toMonth = max($fromMonth, min(12, (int) $this->option('to-month')));

        $start = Carbon::parse(jalaliToGregorianDateSafe($year, $fromMonth, 1))->startOfDay();
        $end = Carbon::parse(jalaliToGregorianDateSafe($year, $toMonth, $this->lastDayOfJalaliMonth($year, $toMonth)))->endOfDay();

        $defaults = WorkCalendarDefaults::defaultCalendar($year);

        $calendar = WorkCalendar::updateOrCreate(
            ['code' => 'CAL-' . $year],
            [
                'name' => 'تقویم کاری ' . $year,
                'jalali_year' => $year,
                'working_days' => $defaults['working_days'],
                'weekend_days' => $defaults['weekend_days'],
                'holidays' => $defaults['holidays'],
                'is_default' => true,
                'is_active' => true,
                'description' => 'تقویم کاری استاندارد ' . $year,
            ]
        );

        $shift = WorkShift::updateOrCreate(
            ['code' => 'SHIFT-' . $year . '-ADMIN-08-17'],
            [
                'name' => 'شیفت اداری ' . $year . ' (08 تا 17)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'daily_work_hours' => 8,
                'overtime_multiplier' => 1.4,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'is_active' => true,
                'description' => 'شیفت اداری استاندارد بر اساس تقویم ' . $year,
            ]
        );

        $group = WorkGroup::updateOrCreate(
            ['code' => 'WG-' . $year . '-ADMIN'],
            [
                'name' => 'گروه کاری اداری ' . $year,
                'work_shift_id' => $shift->id,
                'work_calendar_id' => $calendar->id,
                'payroll_rules' => [
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'break_minutes' => 60,
                    'daily_work_hours' => 8,
                ],
                'is_active' => true,
                'description' => 'گروه اداری برای کارکرد ' . $year,
            ]
        );

        $employees = $this->resolveEmployeesWithActiveOrders($start, $end);

        if ($employees->isEmpty()) {
            $this->warn('هیچ پرسنلی با حکم فعال در این بازه یافت نشد.');

            return self::SUCCESS;
        }

        $workDays = $this->workingDates($start, $end, $calendar);
        $hours = (float) $shift->daily_work_hours;

        $this->info('بازه: ' . formatJalaliDateSafe($start) . ' تا ' . formatJalaliDateSafe($end));
        $this->info('پرسنل دارای حکم فعال: ' . $employees->count());
        $this->info('روز کاری غیرتعطیل: ' . $workDays->count());
        $this->info('شیفت: ' . $shift->name . ' | ساعت مفید روزانه: ' . $hours);

        if ($dryRun) {
            foreach ($employees as $employee) {
                $eligibleDays = $this->eligibleWorkDays($employee, $workDays);
                $existingDays = $this->existingWorkLogDates($employee, $eligibleDays);
                $missingDays = $eligibleDays->count() - $existingDays->count();

                $this->line(sprintf(
                    '[dry-run] %s %s: %d روز واجد شرایط | %d موجود | %d جدید',
                    $employee->first_name,
                    $employee->last_name,
                    $eligibleDays->count(),
                    $existingDays->count(),
                    max(0, $missingDays)
                ));
            }
            $this->warn('dry-run فعال است؛ داده‌ای ذخیره نشد.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($employees, $group, $shift, $workDays, $start, $end, $hours, $fallbackProjectId, $overwriteSeeded, $year): void {
            $totalCreated = 0;
            $totalSkipped = 0;

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

                if ($overwriteSeeded) {
                    $this->clearSeededAttendance($employee, $start, $end);
                }

                $eligibleDays = $this->eligibleWorkDays($employee, $workDays);
                $existingDates = $this->existingWorkLogDates($employee, $eligibleDays)
                    ->map(fn (Carbon $date) => $date->toDateString())
                    ->flip();

                $hourlyRate = (float) $employee->hourly_rate;
                if ($hourlyRate <= 0 && (float) $employee->base_salary > 0) {
                    $hourlyRate = round((float) $employee->base_salary / 220, 2);
                }

                $projectId = $employee->default_project_id ?: $fallbackProjectId;
                $created = 0;
                $skipped = 0;

                foreach ($eligibleDays as $date) {
                    $dateKey = $date->toDateString();

                    if ($existingDates->has($dateKey)) {
                        $skipped++;
                        continue;
                    }

                    $checkIn = Carbon::parse($dateKey . ' 08:00:00');
                    $checkOut = Carbon::parse($dateKey . ' 17:00:00');

                    WorkLog::create([
                        'employee_id' => $employee->id,
                        'project_id' => $projectId,
                        'cost_center' => $employee->default_cost_center,
                        'work_date' => $dateKey,
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
                        'device_code' => 'ADMIN-ATT-' . $year,
                        'source' => self::ATTENDANCE_SOURCE,
                        'payload' => [
                            'work_date' => $dateKey,
                            'shift_code' => $shift->code,
                            'event' => 'check_in',
                        ],
                    ]);

                    AttendanceRawLog::create([
                        'employee_id' => $employee->id,
                        'attendance_card_number' => $employee->attendance_card_number ?: $employee->personnel_code,
                        'logged_at' => $checkOut,
                        'direction' => 'out',
                        'device_code' => 'ADMIN-ATT-' . $year,
                        'source' => self::ATTENDANCE_SOURCE,
                        'payload' => [
                            'work_date' => $dateKey,
                            'shift_code' => $shift->code,
                            'event' => 'check_out',
                        ],
                    ]);

                    $created++;
                }

                $totalCreated += $created;
                $totalSkipped += $skipped;

                $this->line(sprintf(
                    '%s %s: %d روز ثبت شد | %d روز از قبل موجود | پروژه: %d',
                    $employee->first_name,
                    $employee->last_name,
                    $created,
                    $skipped,
                    $projectId
                ));
            }

            $this->info("جمع ثبت‌شده: {$totalCreated} | ردشده (موجود): {$totalSkipped}");
        });

        $this->info('ثبت کارکرد پرسنل دارای حکم فعال تا پایان مرداد ' . $year . ' انجام شد.');

        return self::SUCCESS;
    }

    private function resolveEmployeesWithActiveOrders(Carbon $start, Carbon $end): Collection
    {
        $employeeIds = EmploymentOrder::query()
            ->where('status', 'approved')
            ->where('order_type', '!=', 'termination')
            ->where('effective_date', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $start->toDateString());
            })
            ->pluck('employee_id')
            ->unique()
            ->values();

        return Employee::query()
            ->whereIn('id', $employeeIds)
            ->with(['employmentOrders' => fn ($query) => $query->where('status', 'approved')])
            ->orderBy('id')
            ->get()
            ->filter(fn (Employee $employee): bool => $this->hasActiveOrderInPeriod($employee, $start, $end))
            ->values();
    }

    private function hasActiveOrderInPeriod(Employee $employee, Carbon $start, Carbon $end): bool
    {
        if ($this->activeOrderForDate($employee, $end) === null) {
            return false;
        }

        foreach (CarbonPeriod::create($start->copy(), '1 day', $end->copy()) as $date) {
            if ($this->activeOrderForDate($employee, Carbon::parse($date)) !== null) {
                return true;
            }
        }

        return false;
    }

    private function activeOrderForDate(Employee $employee, Carbon $date): ?EmploymentOrder
    {
        $order = $employee->employmentOrders
            ->filter(fn (EmploymentOrder $item): bool => (bool) $item->effective_date && $item->effective_date->lte($date))
            ->filter(fn (EmploymentOrder $item): bool => ! $item->end_date || $item->end_date->gte($date))
            ->sortByDesc(fn (EmploymentOrder $item) => $item->effective_date?->timestamp ?? 0)
            ->sortByDesc('id')
            ->first();

        if (! $order || $order->order_type === 'termination') {
            return null;
        }

        return $order;
    }

    /**
     * @param  Collection<int, Carbon>  $workDays
     * @return Collection<int, Carbon>
     */
    private function eligibleWorkDays(Employee $employee, Collection $workDays): Collection
    {
        return $workDays->filter(fn (Carbon $date): bool => $this->activeOrderForDate($employee, $date) !== null)->values();
    }

    /**
     * @param  Collection<int, Carbon>  $workDays
     * @return Collection<int, Carbon>
     */
    private function existingWorkLogDates(Employee $employee, Collection $workDays): Collection
    {
        if ($workDays->isEmpty()) {
            return collect();
        }

        $dates = WorkLog::query()
            ->where('employee_id', $employee->id)
            ->whereIn('work_date', $workDays->map(fn (Carbon $date) => $date->toDateString())->all())
            ->pluck('work_date')
            ->map(fn (string $date) => Carbon::parse($date)->startOfDay());

        return $dates->values();
    }

    private function clearSeededAttendance(Employee $employee, Carbon $start, Carbon $end): void
    {
        WorkLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('import_batch', self::IMPORT_BATCH)
            ->delete();

        AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where('source', self::ATTENDANCE_SOURCE)
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

    private function lastDayOfJalaliMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return (($year + 2346) % 128) < 30 ? 30 : 29;
    }
}
