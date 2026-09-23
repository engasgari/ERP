<?php

namespace App\Console\Commands;

use App\Models\Crm\Opportunity;
use App\Models\User;
use App\Services\Crm\CrmTaskService;
use Illuminate\Console\Command;

class CompleteWonOpportunityTasks extends Command
{
    protected $signature = 'crm:complete-won-opportunity-tasks
        {--dry-run : فقط گزارش، بدون تغییر}';

    protected $description = 'تکمیل وظایف باز فرصت‌های برنده‌شده (اصلاح داده‌های قبلی)';

    public function handle(CrmTaskService $tasks): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $actor = User::query()->orderBy('id')->first();

        if (! $actor) {
            $this->error('هیچ کاربری در سیستم یافت نشد.');

            return self::FAILURE;
        }

        $wonOpportunities = Opportunity::query()
            ->where('status', 'won')
            ->orderBy('id')
            ->get();

        if ($wonOpportunities->isEmpty()) {
            $this->info('فرصت برنده‌شده‌ای یافت نشد.');

            return self::SUCCESS;
        }

        $totalCompleted = 0;

        foreach ($wonOpportunities as $opportunity) {
            $openCount = $opportunity->tasks()
                ->whereIn('status', ['pending', 'in_progress'])
                ->count();

            if ($openCount === 0) {
                continue;
            }

            $this->line("فرصت {$opportunity->number} ({$opportunity->title}): {$openCount} وظیفه باز");

            if (! $dryRun) {
                $totalCompleted += $tasks->completeOpenTasksForOpportunity($opportunity, $actor);
            } else {
                $totalCompleted += $openCount;
            }
        }

        if ($totalCompleted === 0) {
            $this->info('وظیفه بازی برای فرصت‌های برنده یافت نشد.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}{$totalCompleted} وظیفه تکمیل شد.");

        return self::SUCCESS;
    }
}
