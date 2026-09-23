<?php

namespace App\Console\Commands;

use App\Models\AccountingDocumentLine;
use App\Models\AttendanceCalculation;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\PayrollAccountingEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\ProjectCostSnapshot;
use App\Models\WorkLog;
use App\Services\ProjectCostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReassignProjectFiscalYearCosts extends Command
{
    protected $signature = 'projects:reassign-fiscal-year-costs
        {--source-project=490 : Source project ID}
        {--target-project=539 : Target project ID}
        {--fiscal-year=1404 : Jalali fiscal year}
        {--employee-ids= : Comma-separated employee IDs (defaults to employees on source project)}
        {--skip-employee-default : Do not update employee default_project_id}
        {--dry-run : Report only, without saving}';

    protected $description = 'Reassign project costs and accounting lines from one project to another for a closed fiscal year without reopening it.';

    public function handle(ProjectCostingService $costing): int
    {
        $sourceProject = Project::query()->findOrFail((int) $this->option('source-project'));
        $targetProject = Project::query()->findOrFail((int) $this->option('target-project'));
        $fiscalYear = FiscalYear::query()
            ->where('jalali_year', (int) $this->option('fiscal-year'))
            ->firstOrFail();

        if ($sourceProject->id === $targetProject->id) {
            throw new RuntimeException('Source and target projects must be different.');
        }

        $employeeIds = $this->resolveEmployeeIds($sourceProject);
        $periodIds = PayrollPeriod::query()
            ->where('year', $fiscalYear->jalali_year)
            ->pluck('id');

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Reassigning FY %d costs: %s (%d) -> %s (%d)%s',
            $fiscalYear->jalali_year,
            $sourceProject->name,
            $sourceProject->id,
            $targetProject->name,
            $targetProject->id,
            $dryRun ? ' [dry-run]' : '',
        ));

        $stats = [
            'accounting_lines' => 0,
            'work_logs' => 0,
            'attendance_calculations' => 0,
            'payroll_accounting_entries' => 0,
            'employees_default_project' => 0,
        ];

        DB::transaction(function () use (
            $sourceProject,
            $targetProject,
            $fiscalYear,
            $employeeIds,
            $periodIds,
            $dryRun,
            $costing,
            &$stats,
        ): void {
            $stats['accounting_lines'] = AccountingDocumentLine::query()
                ->where('project_id', $sourceProject->id)
                ->whereHas('document', fn ($query) => $query
                    ->where('fiscal_year_id', $fiscalYear->id))
                ->count();

            $stats['work_logs'] = WorkLog::query()
                ->where('project_id', $sourceProject->id)
                ->whereDate('work_date', '>=', $fiscalYear->start_date->toDateString())
                ->whereDate('work_date', '<=', $fiscalYear->end_date->toDateString())
                ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count();

            $stats['attendance_calculations'] = AttendanceCalculation::query()
                ->where('project_id', $sourceProject->id)
                ->when($periodIds->isNotEmpty(), fn ($query) => $query->whereIn('payroll_period_id', $periodIds))
                ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count();

            $stats['payroll_accounting_entries'] = $this->countPayrollAccountingEntriesToRewrite(
                $sourceProject->id,
                $employeeIds,
                $periodIds,
            );

            if (! $this->option('skip-employee-default')) {
                $stats['employees_default_project'] = Employee::query()
                    ->where('default_project_id', $sourceProject->id)
                    ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $employeeIds))
                    ->count();
            }

            if ($dryRun) {
                return;
            }

            AccountingDocumentLine::query()
                ->where('project_id', $sourceProject->id)
                ->whereHas('document', fn ($query) => $query
                    ->where('fiscal_year_id', $fiscalYear->id))
                ->update(['project_id' => $targetProject->id]);

            WorkLog::query()
                ->where('project_id', $sourceProject->id)
                ->whereDate('work_date', '>=', $fiscalYear->start_date->toDateString())
                ->whereDate('work_date', '<=', $fiscalYear->end_date->toDateString())
                ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->update(['project_id' => $targetProject->id]);

            AttendanceCalculation::query()
                ->where('project_id', $sourceProject->id)
                ->when($periodIds->isNotEmpty(), fn ($query) => $query->whereIn('payroll_period_id', $periodIds))
                ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->update(['project_id' => $targetProject->id]);

            $this->rewritePayrollAccountingEntries($sourceProject->id, $targetProject->id, $employeeIds, $periodIds);

            if (! $this->option('skip-employee-default')) {
                Employee::query()
                    ->where('default_project_id', $sourceProject->id)
                    ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $employeeIds))
                    ->update(['default_project_id' => $targetProject->id]);
            }

            foreach ([$sourceProject, $targetProject] as $project) {
                $summary = $costing->summary($project->fresh());
                ProjectCostSnapshot::updateOrCreate(
                    ['project_id' => $project->id],
                    [
                        'material_cost' => $summary['material_cost'],
                        'labor_cost' => $summary['labor_cost'],
                        'service_cost' => $summary['service_cost'],
                        'overhead_cost' => $summary['overhead_cost'],
                        'total_cost' => $summary['total_cost'],
                        'revenue' => $summary['revenue'],
                        'gross_profit' => $summary['gross_profit'],
                        'profit_margin' => $summary['profit_margin'],
                        'calculated_at' => now(),
                    ],
                );
            }
        });

        $this->table(['Item', 'Count'], collect($stats)->map(fn ($count, $key) => [$key, $count])->values()->all());

        if ($dryRun) {
            $this->warn('Dry-run only. Re-run without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        $laborTarget = $costing->directLaborCost($targetProject->fresh());
        $this->info('Updated direct labor on target project: ' . number_format($laborTarget, 0, '.', ',') . ' Rials');

        return self::SUCCESS;
    }

    private function resolveEmployeeIds(Project $sourceProject)
    {
        $raw = trim((string) $this->option('employee-ids'));

        if ($raw !== '') {
            return collect(explode(',', $raw))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->values();
        }

        return Employee::query()
            ->where('default_project_id', $sourceProject->id)
            ->pluck('id');
    }

    private function countPayrollAccountingEntriesToRewrite(int $sourceProjectId, $employeeIds, $periodIds): int
    {
        return $this->payrollAccountingEntryQuery($sourceProjectId, $employeeIds, $periodIds)->count();
    }

    private function rewritePayrollAccountingEntries(int $sourceProjectId, int $targetProjectId, $employeeIds, $periodIds): void
    {
        $this->payrollAccountingEntryQuery($sourceProjectId, $employeeIds, $periodIds)
            ->get()
            ->each(function (PayrollAccountingEntry $entry) use ($sourceProjectId, $targetProjectId): void {
                $lines = collect($entry->lines ?? [])
                    ->map(function (array $line) use ($sourceProjectId, $targetProjectId) {
                        if ((int) ($line['project_id'] ?? 0) === $sourceProjectId) {
                            $line['project_id'] = $targetProjectId;
                        }

                        return $line;
                    })
                    ->all();

                $entry->update(['lines' => $lines]);
            });
    }

    private function payrollAccountingEntryQuery(int $sourceProjectId, $employeeIds, $periodIds)
    {
        return PayrollAccountingEntry::query()
            ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->when($periodIds->isNotEmpty(), fn ($query) => $query->whereIn('payroll_period_id', $periodIds))
            ->where(function ($query) use ($sourceProjectId) {
                $query->where('lines', 'like', '%"project_id":' . $sourceProjectId . '%')
                    ->orWhere('lines', 'like', '%"project_id": ' . $sourceProjectId . '%');
            });
    }
}
