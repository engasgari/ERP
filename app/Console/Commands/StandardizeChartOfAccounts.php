<?php

namespace App\Console\Commands;

use App\Services\ChartOfAccountsStandardizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class StandardizeChartOfAccounts extends Command
{
    protected $signature = 'accounting:standardize-chart
        {--execute : Apply the plan (default is a dry run)}
        {--confirm-database= : Required with --execute; must equal the connected database name}
        {--no-backup : Skip creating backup tables before executing}';

    protected $description = 'Clean up duplicate chart of accounts entries without touching posted accounting documents.';

    public function handle(ChartOfAccountsStandardizationService $service): int
    {
        $database = DB::connection()->getDatabaseName();
        $this->info("پایگاه داده: {$database}");

        $plan = $service->plan();
        $this->renderPlan($plan);

        $issues = $service->structuralIssues();
        foreach ($issues['blocking'] as $issue) {
            $this->error($issue);
        }
        foreach ($issues['warnings'] as $warning) {
            $this->warn($warning);
        }

        if (! $this->option('execute')) {
            $this->newLine();
            $this->comment('اجرای آزمایشی بود؛ هیچ داده‌ای تغییر نکرد. برای اجرا: --execute --confirm-database='.$database);

            return self::SUCCESS;
        }

        if ($this->option('confirm-database') !== $database) {
            $this->error('برای اجرا باید --confirm-database دقیقاً برابر نام پایگاه داده متصل باشد.');

            return self::FAILURE;
        }

        if (collect($plan)->contains('status', 'blocked')) {
            $this->error('برخی عملیات مسدود هستند؛ اجرا متوقف شد.');

            return self::FAILURE;
        }

        try {
            $result = $service->execute(null, ! $this->option('no-backup'));
        } catch (Throwable $exception) {
            $this->error('اجرا ناموفق بود و همه تغییرات برگشت داده شد: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('اصلاح کدینگ با موفقیت انجام شد.');
        if ($result['backup']) {
            $this->line('جدول پشتیبان: '.$result['backup']);
        }
        $this->line('ردیف‌های سند: '.$result['validation']['line_count']);
        $this->line('جمع بدهکار: '.$result['validation']['debit_total'].' | جمع بستانکار: '.$result['validation']['credit_total']);
        $this->line('مانده و گردش همه حساب‌ها قبل و بعد یکسان است.');
        foreach ($result['validation']['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     */
    private function renderPlan(array $plan): void
    {
        $this->table(
            ['Action', 'Code', 'Target / New', 'Posted lines', 'Balance', 'Status', 'Note'],
            array_map(fn (array $row) => [
                $row['action'],
                $row['code'],
                $row['new_code'] ?? $row['target'] ?? $row['title'] ?? $row['parent'] ?? '',
                $row['usage']['posted_lines'],
                number_format($row['usage']['balance']),
                $row['status'],
                $row['note'] ?? '',
            ], $plan)
        );
    }
}
