<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\PayrollCalculation;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\WorkLog;
use App\Services\NewPayrollEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignActiveEmployeeWorkLogsByYear extends Command
{
    protected $signature = 'project:assign-active-worklogs-by-year
        {project1404 : شناسه یا نام پروژه سال 1404}
        {project1405 : شناسه یا نام پروژه سال 1405}
        {--repost-payroll : بازنشر سند حقوق دوره‌های متاثر}
        {--dry-run : فقط گزارش}';

    protected $description = 'تخصیص کارکرد پرسنل فعال به پروژه 1404/1405 بر اساس سال شمسی';

    public function handle(NewPayrollEngineService $payrollEngine): int
    {
        $project1404 = $this->resolveProject((string) $this->argument('project1404'));
        $project1405 = $this->resolveProject((string) $this->argument('project1405'));

        if (! $project1404 || ! $project1405) {
            $this->error('یکی از پروژه‌ها یافت نشد.');

            return self::FAILURE;
        }

        if ($project1404->id === $project1405->id) {
            $this->error('پروژه 1404 و 1405 نباید یکسان باشند.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $repostPayroll = (bool) $this->option('repost-payroll');
        $activeEmployeeIds = Employee::query()->where('is_active', true)->pluck('id');

        if ($activeEmployeeIds->isEmpty()) {
            $this->warn('پرسنل فعالی یافت نشد.');

            return self::SUCCESS;
        }

        $yearMap = [
            1404 => $project1404->id,
            1405 => $project1405->id,
        ];

        $this->info('پرسنل فعال: ' . $activeEmployeeIds->count());
        $this->info("1404 -> {$project1404->name} (#{$project1404->id})");
        $this->info("1405 -> {$project1405->name} (#{$project1405->id})");

        $totalUpdated = 0;
        $affectedYears = [];

        foreach ($yearMap as $year => $targetProjectId) {
            $start = jalaliToGregorianDateSafe($year, 1, 1);
            $end = jalaliToGregorianDateSafe($year, 12, 29);

            $query = WorkLog::query()
                ->whereIn('employee_id', $activeEmployeeIds)
                ->whereBetween('work_date', [$start, $end])
                ->where(function ($builder) use ($targetProjectId) {
                    $builder->whereNull('project_id')
                        ->orWhere('project_id', '!=', $targetProjectId);
                });

            $count = (clone $query)->count();
            $this->line("سال {$year}: {$count} کارکرد نیاز به بروزرسانی دارد.");

            if ($count === 0) {
                continue;
            }

            $affectedYears[] = $year;

            if (! $dryRun) {
                $updated = $query->update(['project_id' => $targetProjectId]);
                $totalUpdated += $updated;
            } else {
                $totalUpdated += $count;
            }
        }

        if ($dryRun) {
            $this->info("[dry-run] {$totalUpdated} کارکرد بروزرسانی می‌شود.");

            return self::SUCCESS;
        }

        $this->info("{$totalUpdated} کارکرد بروزرسانی شد.");

        if ($repostPayroll && $affectedYears !== []) {
            $this->repostPayrollForYears($payrollEngine, $activeEmployeeIds, $affectedYears);
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $employeeIds
     * @param  list<int>  $years
     */
    private function repostPayrollForYears(NewPayrollEngineService $payrollEngine, $employeeIds, array $years): void
    {
        $periodIds = PayrollPeriod::query()
            ->whereIn('year', $years)
            ->pluck('id');

        $calculations = PayrollCalculation::query()
            ->with('period')
            ->whereIn('payroll_period_id', $periodIds)
            ->whereIn('employee_id', $employeeIds)
            ->get();

        $this->info('بازنشر سند حقوق: ' . $calculations->count() . ' محاسبه');

        foreach ($calculations as $calculation) {
            try {
                $payrollEngine->repostAccountingForCalculation($calculation);
                $this->line("  reposted calc #{$calculation->id} | {$calculation->period->year}/{$calculation->period->month}");
            } catch (\Throwable $exception) {
                $this->warn("  failed calc #{$calculation->id}: {$exception->getMessage()}");
            }
        }
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
}
