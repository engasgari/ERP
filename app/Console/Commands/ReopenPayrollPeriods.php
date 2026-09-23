<?php

namespace App\Console\Commands;

use App\Services\PayrollCalculationService;
use Illuminate\Console\Command;

class ReopenPayrollPeriods extends Command
{
    protected $signature = 'payroll:reopen-periods
        {year : سال شمسی، مثلاً 1405}
        {--dry-run : فقط گزارش، بدون تغییر}';

    protected $description = 'بازگشایی دوره‌های بسته‌شده حقوق برای یک سال شمسی';

    public function handle(PayrollCalculationService $service): int
    {
        $year = (int) $this->argument('year');
        $dryRun = (bool) $this->option('dry-run');

        if ($year < 1300 || $year > 1500) {
            $this->error('سال شمسی نامعتبر است.');

            return self::FAILURE;
        }

        $count = \App\Models\PayrollPeriod::query()
            ->where('year', $year)
            ->where('status', 'closed')
            ->count();

        if ($count === 0) {
            $this->info("هیچ دوره بسته‌شده‌ای برای سال {$year} یافت نشد.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[dry-run] {$count} دوره بسته‌شده برای سال {$year} بازگشایی می‌شود.");

            return self::SUCCESS;
        }

        $updated = $service->reopenClosedPeriodsForYear($year);

        $this->info("{$updated} دوره حقوق سال {$year} باز شد.");

        return self::SUCCESS;
    }
}
