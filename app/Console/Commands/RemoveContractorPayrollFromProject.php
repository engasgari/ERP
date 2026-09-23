<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\PayrollCalculation;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\WorkLog;
use App\Services\PayrollCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveContractorPayrollFromProject extends Command
{
    protected $signature = 'project:remove-contractor-payroll
        {project : شناسه یا بخشی از نام پروژه}
        {employees* : نام پرسنل، مثلاً "حمید علیزاده" "محسن اخلاصی"}
        {--year= : سال شمسی دوره حقوق (مثلاً 1405)}
        {--from-month=1 : از ماه}
        {--to-month=12 : تا ماه}
        {--dry-run : فقط گزارش}';

    protected $description = 'حذف حقوق ماهانه پرسنل پیمانکاری از پروژه (کارکرد + محاسبات حقوق + سند)';

    public function handle(PayrollCalculationService $payrollService): int
    {
        $project = $this->resolveProject((string) $this->argument('project'));
        if (! $project) {
            $this->error('پروژه یافت نشد.');

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?: 0);
        if ($year < 1300) {
            $this->error('سال شمسی را با --year مشخص کنید.');

            return self::FAILURE;
        }

        $fromMonth = max(1, min(12, (int) $this->option('from-month')));
        $toMonth = max($fromMonth, min(12, (int) $this->option('to-month')));
        $dryRun = (bool) $this->option('dry-run');

        $employees = collect($this->argument('employees'))
            ->map(fn (string $name) => $this->resolveEmployee($name))
            ->filter();

        if ($employees->isEmpty()) {
            $this->error('هیچ پرسنلی یافت نشد.');

            return self::FAILURE;
        }

        $periodIds = PayrollPeriod::query()
            ->where('year', $year)
            ->whereBetween('month', [$fromMonth, $toMonth])
            ->pluck('id');

        if ($periodIds->isEmpty()) {
            $this->error("دوره حقوقی برای سال {$year} یافت نشد.");

            return self::FAILURE;
        }

        $employeeIds = $employees->pluck('id');

        $workLogCount = WorkLog::query()
            ->where('project_id', $project->id)
            ->whereIn('employee_id', $employeeIds)
            ->count();

        $calculations = PayrollCalculation::query()
            ->with(['employee', 'period'])
            ->whereIn('payroll_period_id', $periodIds)
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('payroll_period_id')
            ->get();

        $attendanceCount = DB::table('monthly_attendances')
            ->whereIn('payroll_period_id', $periodIds)
            ->whereIn('employee_id', $employeeIds)
            ->count();

        $this->info("پروژه: {$project->name} (#{$project->id})");
        $this->info("سال {$year} | ماه {$fromMonth} تا {$toMonth}");
        $this->line('پرسنل: ' . $employees->map(fn ($e) => $e->full_name)->implode('، '));
        $this->line("work_logs حذف‌شونده: {$workLogCount}");
        $this->line("payroll_calculations حذف‌شونده: {$calculations->count()}");
        $this->line("monthly_attendances حذف‌شونده: {$attendanceCount}");

        foreach ($calculations as $calculation) {
            $this->line("  - calc #{$calculation->id} | {$calculation->period->year}/{$calculation->period->month} | {$calculation->employee->full_name} | gross={$calculation->gross_salary}");
        }

        if ($calculations->isEmpty() && $workLogCount === 0) {
            $this->warn('موردی برای حذف نیست.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[dry-run] تغییری اعمال نشد.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($project, $employeeIds, $periodIds, $calculations, $payrollService): void {
            WorkLog::query()
                ->where('project_id', $project->id)
                ->whereIn('employee_id', $employeeIds)
                ->delete();

            DB::table('monthly_attendances')
                ->whereIn('payroll_period_id', $periodIds)
                ->whereIn('employee_id', $employeeIds)
                ->delete();

            $payrollService->deleteCalculations($calculations->pluck('id'));
        });

        $this->info('حقوق ماهانه پرسنل پیمانکاری از پروژه حذف شد.');

        return self::SUCCESS;
    }

    private function resolveProject(string $needle): ?Project
    {
        if (ctype_digit($needle)) {
            return Project::query()->find((int) $needle);
        }

        return Project::query()
            ->where('name', 'like', '%' . $needle . '%')
            ->orderBy('id')
            ->first();
    }

    private function resolveEmployee(string $fullName): ?Employee
    {
        $parts = preg_split('/\s+/u', trim($fullName), 2);

        if (! $parts || count($parts) < 2) {
            $this->warn("نام نامعتبر: {$fullName}");

            return null;
        }

        [$firstName, $lastName] = $parts;

        $employee = Employee::query()
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->orderBy('id')
            ->first();

        if (! $employee) {
            $this->warn("پرسنل یافت نشد: {$fullName}");
        }

        return $employee;
    }
}
