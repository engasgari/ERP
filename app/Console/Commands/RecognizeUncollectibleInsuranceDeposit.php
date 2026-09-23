<?php

namespace App\Console\Commands;

use App\Models\ChartAccount;
use App\Models\FiscalYear;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountingPostingService;
use App\Services\FiscalPeriodService;
use Illuminate\Console\Command;
use RuntimeException;

class RecognizeUncollectibleInsuranceDeposit extends Command
{
    private const EXPENSE_ACCOUNT_CODE = '520115';

    private const EXPENSE_ACCOUNT_TITLE = 'هزینه کسور بیمه غیرقابل وصول پروژه‌ها';

    private const DEPOSIT_ACCOUNT_CODE = '110801';

    private const DEFAULT_AMOUNT = 2479366672;

    protected $signature = 'accounting:recognize-uncollectible-insurance-deposit
        {--fiscal-year=1405 : Jalali fiscal year}
        {--project-id=539 : Domino CCTV project ID}
        {--project-name= : Project name (overrides --project-id when set)}
        {--amount=2479366672 : Amount in IRR}
        {--document-date= : Document date (defaults to fiscal year end date)}
        {--expense-account-code=520115 : Expense account code}
        {--dry-run : Report only, without saving}';

    protected $description = 'Create a draft accounting document to recognize an uncollectible Social Security insurance deposit as project expense.';

    public function handle(AccountingPostingService $posting, FiscalPeriodService $periods): int
    {
        $fiscalYear = FiscalYear::query()
            ->where('jalali_year', (int) $this->option('fiscal-year'))
            ->firstOrFail();

        $project = $this->resolveProject();
        $amount = (float) $this->option('amount');
        $documentDate = $this->option('document-date') ?: $fiscalYear->end_date->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $expenseAccountCode = (string) $this->option('expense-account-code');

        $this->info('=== شناسایی سپرده بیمه غیرقابل وصول ===');
        $this->line("سال مالی: {$fiscalYear->jalali_year} (id={$fiscalYear->id}, status={$fiscalYear->status})");
        $this->line("پروژه: {$project->name} (id={$project->id})");
        $this->line("تاریخ سند: {$documentDate}");
        $this->line('مبلغ: ' . number_format($amount, 0, '.', ',') . ' ریال');

        $expenseAccountResult = $this->ensureExpenseAccount($expenseAccountCode, $dryRun);
        $depositAccount = ChartAccount::query()->where('code', self::DEPOSIT_ACCOUNT_CODE)->first();

        if (! $depositAccount) {
            $this->error('حساب ' . self::DEPOSIT_ACCOUNT_CODE . ' (سپرده بیمه) یافت نشد.');

            return self::FAILURE;
        }

        $this->reportAccountStatus($expenseAccountResult, $depositAccount);

        if ($fiscalYear->status === 'closed') {
            $this->newLine();
            $this->error('سال مالی ' . $fiscalYear->jalali_year . ' بسته است. سند حسابداری ایجاد نمی‌شود.');
            $this->warn('برای ثبت این سند، ابتدا سال مالی را باز کنید یا سند را در سال مالی باز ثبت کنید.');

            return self::FAILURE;
        }

        $period = $periods->periodForDate($documentDate);

        if (! $period || $period->status !== 'open') {
            $this->newLine();
            $this->error("بازه مالی باز برای تاریخ {$documentDate} وجود ندارد. سند ایجاد نمی‌شود.");

            return self::FAILURE;
        }

        if (! $periods->isDateAllowed($documentDate)) {
            $this->newLine();
            $this->error("تاریخ {$documentDate} در بازه مالی باز مجاز نیست. سند ایجاد نمی‌شود.");

            return self::FAILURE;
        }

        if ($expenseAccountResult['blocked']) {
            $this->newLine();
            $this->error('به‌دلیل تداخل کد حساب هزینه، سند ایجاد نمی‌شود.');

            return self::FAILURE;
        }

        $expenseAccount = $expenseAccountResult['account'];

        if (! $expenseAccount) {
            $this->error('حساب هزینه آماده نیست.');

            return self::FAILURE;
        }

        $lines = [
            $posting->line(
                $expenseAccount->code,
                $amount,
                0,
                'شناسایی هزینه سپرده بیمه غیرقابل وصول پروژه دوربین مداربسته دومینو',
                projectId: $project->id,
                accountId: $expenseAccount->id,
            ),
            $posting->line(
                self::DEPOSIT_ACCOUNT_CODE,
                0,
                $amount,
                'خروج سپرده بیمه غیرقابل وصول پروژه دوربین مداربسته دومینو',
                projectId: $project->id,
            ),
        ];

        $debit = collect($lines)->sum(fn (array $line) => (float) $line['debit']);
        $credit = collect($lines)->sum(fn (array $line) => (float) $line['credit']);

        $this->newLine();
        $this->info('تراز سند: بدهکار ' . number_format($debit, 0, '.', ',') . ' = بستانکار ' . number_format($credit, 0, '.', ','));

        if ($dryRun) {
            $this->warn('[dry-run] سند ایجاد نشد.');

            return self::SUCCESS;
        }

        $admin = User::query()->where('email', 'admin@aale.ir')->first()
            ?? User::query()->orderBy('id')->firstOrFail();

        $document = $posting->createManual([
            'document_date' => $documentDate,
            'type' => 'manual',
            'status' => 'draft',
            'description' => 'شناسایی هزینه سپرده بیمه غیرقابل وصول پروژه دوربین مداربسته دومینو',
        ], $lines, $admin->id);

        $this->newLine();
        $this->info("سند پیش‌نویس ایجاد شد: {$document->number} (id={$document->id})");
        $this->line("وضعیت: {$document->status}");
        $this->line("بازه مالی: {$period->title} (id={$period->id})");

        return self::SUCCESS;
    }

    private function resolveProject(): Project
    {
        $name = trim((string) $this->option('project-name'));

        if ($name !== '') {
            $project = Project::query()->where('name', $name)->first();

            if (! $project) {
                throw new RuntimeException("پروژه با نام «{$name}» یافت نشد.");
            }

            return $project;
        }

        return Project::query()->findOrFail((int) $this->option('project-id'));
    }

    /**
     * @return array{account: ?ChartAccount, status: string, blocked: bool}
     */
    private function ensureExpenseAccount(string $code, bool $dryRun): array
    {
        if ($code === self::EXPENSE_ACCOUNT_CODE) {
            $existing = ChartAccount::query()->where('code', self::EXPENSE_ACCOUNT_CODE)->first();

            if ($existing) {
                if ($existing->title === self::EXPENSE_ACCOUNT_TITLE) {
                    return [
                        'account' => $existing,
                        'status' => 'confirmed_existing',
                        'blocked' => false,
                    ];
                }

                return [
                    'account' => null,
                    'status' => 'code_conflict',
                    'blocked' => true,
                    'existing_title' => $existing->title,
                ];
            }
        }

        $existing = ChartAccount::query()->where('code', $code)->first();

        if ($existing) {
            return [
                'account' => $existing,
                'status' => 'confirmed_existing',
                'blocked' => false,
            ];
        }

        if ($dryRun) {
            return [
                'account' => null,
                'status' => 'would_create',
                'blocked' => false,
                'create_code' => $code,
                'create_title' => self::EXPENSE_ACCOUNT_TITLE,
            ];
        }

        $parent = ChartAccount::query()->where('code', '5201')->firstOrFail();

        $account = ChartAccount::create([
            'parent_id' => $parent->id,
            'level' => 'detail',
            'code' => $code,
            'title' => self::EXPENSE_ACCOUNT_TITLE,
            'nature' => 'debit',
            'is_active' => true,
            'is_system' => false,
        ]);

        return [
            'account' => $account,
            'status' => 'created',
            'blocked' => false,
        ];
    }

    private function reportAccountStatus(array $expenseResult, ChartAccount $depositAccount): void
    {
        $this->newLine();
        $this->info('وضعیت حساب‌ها:');

        match ($expenseResult['status']) {
            'confirmed_existing' => $this->line(
                'حساب هزینه: ' . $expenseResult['account']->code . ' - ' . $expenseResult['account']->title . ' (موجود)'
            ),
            'created' => $this->line(
                'حساب هزینه: ' . $expenseResult['account']->code . ' - ' . $expenseResult['account']->title . ' (ایجاد شد)'
            ),
            'would_create' => $this->line(
                'حساب هزینه: ' . $expenseResult['create_code'] . ' - ' . $expenseResult['create_title'] . ' (در اجرای واقعی ایجاد می‌شود)'
            ),
            'code_conflict' => $this->warn(
                'حساب ' . self::EXPENSE_ACCOUNT_CODE . ' از قبل وجود دارد: «' . ($expenseResult['existing_title'] ?? '') . '»'
                . ' — با عنوان درخواستی «' . self::EXPENSE_ACCOUNT_TITLE . '» متفاوت است.'
                . ' حساب تکراری ایجاد نمی‌شود. از --expense-account-code با کد آزاد دیگر استفاده کنید.'
            ),
            default => null,
        };

        $this->line('حساب سپرده: ' . $depositAccount->code . ' - ' . $depositAccount->title . ' (موجود)');
    }
}
