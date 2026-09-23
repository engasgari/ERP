<?php

namespace App\Console\Commands;

use App\Models\AttendanceRawLog;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PayrollPeriod;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeEmployeeAttendanceYear extends Command
{
    protected $signature = 'attendance:purge-employee-year
        {first_name : نام}
        {last_name : نام خانوادگی}
        {year : سال شمسی، مثلاً 1405}
        {--dry-run : فقط گزارش، بدون حذف}';

    protected $description = 'حذف کارکرد، تردد و محاسبات کارکرد یک پرسنل برای یک سال شمسی';

    public function handle(): int
    {
        $firstName = (string) $this->argument('first_name');
        $lastName = (string) $this->argument('last_name');
        $year = (int) $this->argument('year');
        $dryRun = (bool) $this->option('dry-run');

        if ($year < 1300 || $year > 1500) {
            $this->error('سال شمسی نامعتبر است.');

            return self::FAILURE;
        }

        $employee = Employee::query()
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->orderBy('id')
            ->first();

        if (! $employee) {
            $this->error("پرسنل {$firstName} {$lastName} یافت نشد.");

            return self::FAILURE;
        }

        $start = Carbon::parse(jalaliToGregorianDateSafe($year, 1, 1))->startOfDay();
        $end = Carbon::parse(jalaliToGregorianDateSafe($year, 12, 29))->endOfDay();
        $periodIds = PayrollPeriod::query()->where('year', $year)->pluck('id');

        $workLogCount = WorkLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $rawLogCount = AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$start, $end])
            ->count();

        $monthlyCount = MonthlyAttendance::query()
            ->where('employee_id', $employee->id)
            ->when($periodIds->isNotEmpty(), fn ($query) => $query->whereIn('payroll_period_id', $periodIds))
            ->count();

        $summaryCount = AttendanceSummary::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->count();

        $this->info("پرسنل: {$employee->first_name} {$employee->last_name} (#{$employee->id})");
        $this->info("بازه: {$start->toDateString()} تا {$end->toDateString()} (سال {$year})");
        $this->line("work_logs: {$workLogCount}");
        $this->line("attendance_raw_logs: {$rawLogCount}");
        $this->line("monthly_attendances: {$monthlyCount}");
        $this->line("attendance_summaries: {$summaryCount}");

        if ($workLogCount + $rawLogCount + $monthlyCount + $summaryCount === 0) {
            $this->warn('رکوردی برای حذف یافت نشد.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('dry-run فعال است؛ داده‌ای حذف نشد.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($employee, $start, $end, $periodIds, $year): void {
            WorkLog::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->delete();

            AttendanceRawLog::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('logged_at', [$start, $end])
                ->delete();

            MonthlyAttendance::query()
                ->where('employee_id', $employee->id)
                ->when($periodIds->isNotEmpty(), fn ($query) => $query->whereIn('payroll_period_id', $periodIds))
                ->delete();

            AttendanceSummary::query()
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->delete();
        });

        $this->info('حذف انجام شد.');

        return self::SUCCESS;
    }
}
