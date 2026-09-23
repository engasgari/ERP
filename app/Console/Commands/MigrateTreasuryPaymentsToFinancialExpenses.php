<?php

namespace App\Console\Commands;

use App\Models\ChartAccount;
use App\Models\FinancialTransaction;
use App\Models\Project;
use App\Models\ProjectCostSnapshot;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\AccountingPostingService;
use App\Services\ProjectCostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MigrateTreasuryPaymentsToFinancialExpenses extends Command
{
    protected $signature = 'treasury:migrate-payments-to-financial-expenses
        {--numbers=TR-00046,TR-00037 : Comma-separated treasury transaction numbers}
        {--project-id=539 : Target project ID}
        {--category=کمیسیون بازاریابی : Expense category label}
        {--expense-detail-code=520110 : Detail expense account code}
        {--dry-run : Report only, without saving}';

    protected $description = 'Remove treasury bank payments and recreate them as financial expense transactions on the target project (works on closed fiscal years).';

    public function handle(AccountingPostingService $posting, ProjectCostingService $costing): int
    {
        $project = Project::query()->findOrFail((int) $this->option('project-id'));
        $category = (string) $this->option('category');
        $generalExpense = ChartAccount::query()->where('code', '5201')->firstOrFail();
        $detailExpense = ChartAccount::query()
            ->where('code', (string) $this->option('expense-detail-code'))
            ->where('parent_id', $generalExpense->id)
            ->firstOrFail();
        $numbers = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('numbers')))));
        $dryRun = (bool) $this->option('dry-run');
        $admin = User::query()->where('email', 'admin@aale.ir')->first()
            ?? User::query()->orderBy('id')->firstOrFail();

        if ($numbers === []) {
            throw new RuntimeException('At least one treasury transaction number is required.');
        }

        $this->info(sprintf(
            'Migrating treasury payments to financial expenses for %s (%s) / %s%s',
            $project->name,
            $project->project_number,
            $detailExpense->code . ' - ' . $detailExpense->title,
            $dryRun ? ' [dry-run]' : '',
        ));

        $created = [];

        DB::transaction(function () use ($numbers, $project, $category, $generalExpense, $detailExpense, $posting, $admin, $dryRun, &$created): void {
            foreach ($numbers as $number) {
                $treasury = TreasuryTransaction::query()
                    ->with(['accountingDocument.lines.account', 'party'])
                    ->where('number', $number)
                    ->first();

                if (! $treasury) {
                    throw new RuntimeException("Treasury transaction {$number} was not found.");
                }

                if (! in_array($treasury->type, ['bank_payment', 'cash_payment', 'withdrawal'], true)) {
                    throw new RuntimeException("{$number} is not a payment transaction.");
                }

                if ($treasury->from_treasury_type !== \App\Models\BankAccount::class) {
                    throw new RuntimeException("{$number} is not a bank payment.");
                }

                $amount = (float) $treasury->amount;
                $partyName = $treasury->party?->name ?: 'طرف حساب';
                $description = "کمیسیون بازاریابی پروژه {$project->name} - پرداخت به {$partyName}";
                $documentDescription = 'سند هزینه مالی - ' . $detailExpense->title;

                $this->line(sprintf(
                    '%s | %s | %s | treasury_doc=%s',
                    $number,
                    $treasury->transaction_date?->toDateString(),
                    number_format($amount, 0, '.', ','),
                    $treasury->accountingDocument?->number,
                ));

                if ($dryRun) {
                    $created[] = ['number' => $number, 'amount' => $amount];

                    continue;
                }

                if ($treasury->accountingDocument && $treasury->accountingDocument->status === 'posted') {
                    $posting->voidMaintenanceDocument($treasury->accountingDocument, $admin->id);
                }

                $treasury->delete();

                $financial = FinancialTransaction::create([
                    'project_id' => $project->id,
                    'bank_account_id' => (int) $treasury->from_treasury_id,
                    'chart_account_id' => $generalExpense->id,
                    'detail_account_id' => $detailExpense->id,
                    'type' => 'expense',
                    'category' => $category,
                    'amount' => $amount,
                    'transaction_date' => $treasury->transaction_date,
                    'description' => $description,
                    'reference_number' => $number,
                ]);

                $bankDetailAccountId = \App\Models\BankAccount::query()
                    ->whereKey($treasury->from_treasury_id)
                    ->value('detail_account_id');

                $lines = [
                    $posting->line(
                        $detailExpense->code,
                        $amount,
                        0,
                        $documentDescription,
                        projectId: $project->id,
                        accountId: $detailExpense->id,
                    ),
                    $posting->line(
                        '1202',
                        0,
                        $amount,
                        $documentDescription,
                        bankAccountId: (int) $treasury->from_treasury_id,
                        detailAccountId: $bankDetailAccountId ? (int) $bankDetailAccountId : null,
                    ),
                ];

                $document = $posting->createPostedMaintenance([
                    'document_date' => $treasury->transaction_date->toDateString(),
                    'type' => 'manual',
                    'description' => $documentDescription . ' - ' . $description,
                ], $lines, $financial, $admin->id);

                $financial->update(['accounting_document_id' => $document->id]);

                $created[] = [
                    'number' => $number,
                    'amount' => $amount,
                    'financial_id' => $financial->id,
                    'document' => $document->number,
                ];
            }
        });

        if (! $dryRun) {
            $summary = $costing->summary($project->fresh());
            ProjectCostSnapshot::updateOrCreate(
                ['project_id' => $project->id],
                [
                    'material_cost' => $summary['material_cost'],
                    'labor_cost' => $summary['labor_cost'],
                    'service_cost' => $summary['service_cost'],
                    'overhead_cost' => $summary['overhead_cost'] + $summary['registered_expense_cost'] + $summary['ledger_expense_cost'],
                    'total_cost' => $summary['total_cost'],
                    'revenue' => $summary['revenue'],
                    'gross_profit' => $summary['gross_profit'],
                    'profit_margin' => $summary['profit_margin'],
                    'calculated_at' => now(),
                ]
            );
        }

        $this->newLine();
        foreach ($created as $row) {
            if ($dryRun) {
                $this->line("Would migrate {$row['number']} amount " . number_format($row['amount'], 0, '.', ','));
            } else {
                $this->info("Migrated {$row['number']} -> financial #{$row['financial_id']} / {$row['document']}");
            }
        }

        if (! $dryRun) {
            $summary = $costing->summary($project->fresh());
            $this->line('Registered project expenses: ' . number_format($summary['registered_expense_cost'], 0, '.', ','));
        }

        return self::SUCCESS;
    }
}
