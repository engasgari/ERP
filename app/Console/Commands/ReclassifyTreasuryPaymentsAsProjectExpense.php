<?php

namespace App\Console\Commands;

use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\ChartAccount;
use App\Models\Project;
use App\Models\ProjectCostSnapshot;
use App\Models\TreasuryTransaction;
use App\Services\ProjectCostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReclassifyTreasuryPaymentsAsProjectExpense extends Command
{
    protected $signature = 'treasury:reclassify-payments-as-project-expense
        {--numbers=TR-00046,TR-00037 : Comma-separated treasury transaction numbers}
        {--project-id=539 : Target project ID}
        {--expense-account-code=520110 : Expense account code (marketing commission)}
        {--dry-run : Report only, without saving}';

    protected $description = 'Reclassify posted treasury bank payments from payable to project expense on existing accounting documents (works on closed fiscal years).';

    public function handle(ProjectCostingService $costing): int
    {
        $project = Project::query()->findOrFail((int) $this->option('project-id'));
        $expenseAccount = ChartAccount::query()
            ->where('code', (string) $this->option('expense-account-code'))
            ->where('is_active', true)
            ->firstOrFail();
        $numbers = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('numbers')))));
        $dryRun = (bool) $this->option('dry-run');

        if ($numbers === []) {
            throw new RuntimeException('At least one treasury transaction number is required.');
        }

        $this->info(sprintf(
            'Reclassifying treasury payments to project %s (%s) on account %s - %s%s',
            $project->name,
            $project->project_number,
            $expenseAccount->code,
            $expenseAccount->title,
            $dryRun ? ' [dry-run]' : '',
        ));

        $stats = [
            'transactions' => 0,
            'documents' => 0,
            'lines' => 0,
            'amount' => 0.0,
        ];

        DB::transaction(function () use ($numbers, $project, $expenseAccount, $dryRun, &$stats): void {
            foreach ($numbers as $number) {
                $transaction = TreasuryTransaction::query()
                    ->with(['accountingDocument.lines.account', 'party'])
                    ->where('number', $number)
                    ->first();

                if (! $transaction) {
                    throw new RuntimeException("Treasury transaction {$number} was not found.");
                }

                if (! in_array($transaction->type, ['bank_payment', 'cash_payment', 'withdrawal'], true)) {
                    throw new RuntimeException("{$number} is not a payment transaction (type={$transaction->type}).");
                }

                $document = $transaction->accountingDocument;
                if (! $document) {
                    throw new RuntimeException("{$number} has no accounting document.");
                }

                if ($document->status !== 'posted') {
                    throw new RuntimeException("{$number} accounting document {$document->number} is not posted.");
                }

                $debitLine = $document->lines->first(fn (AccountingDocumentLine $line) => (float) $line->debit > 0);
                if (! $debitLine) {
                    throw new RuntimeException("{$number} accounting document has no debit line.");
                }

                $amount = (float) $debitLine->debit;
                $partyName = $transaction->party?->name ?: 'طرف حساب';
                $lineDescription = "کمیسیون بازاریابی پروژه {$project->name} - پرداخت به {$partyName}";
                $documentDescription = "تراکنش خزانه {$number} - کمیسیون بازاریابی پروژه {$project->name}";
                $treasuryDescription = "کمیسیون بازاریابی پروژه {$project->name} - {$partyName}";

                $this->line(sprintf(
                    '%s | %s | amount=%s | doc=%s | old_account=%s',
                    $number,
                    $document->document_date?->toDateString(),
                    number_format($amount, 0, '.', ','),
                    $document->number,
                    $debitLine->account?->code,
                ));

                if ($dryRun) {
                    $stats['transactions']++;
                    $stats['documents']++;
                    $stats['lines']++;
                    $stats['amount'] += $amount;

                    continue;
                }

                $transaction->update([
                    'project_id' => $project->id,
                    'expense_account_id' => $expenseAccount->id,
                    'description' => $treasuryDescription,
                ]);

                $debitLine->update([
                    'chart_account_id' => $expenseAccount->id,
                    'project_id' => $project->id,
                    'description' => $lineDescription,
                ]);

                $document->update([
                    'description' => $documentDescription,
                ]);

                $stats['transactions']++;
                $stats['documents']++;
                $stats['lines']++;
                $stats['amount'] += $amount;
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
        $this->info(sprintf(
            'Done: %d transactions, %d documents, %d lines, total amount %s',
            $stats['transactions'],
            $stats['documents'],
            $stats['lines'],
            number_format($stats['amount'], 0, '.', ','),
        ));

        if (! $dryRun) {
            $ledger = $costing->ledgerProjectExpenseCost($project->fresh());
            $this->line('Project ledger expense cost now: ' . number_format($ledger, 0, '.', ','));
        }

        return self::SUCCESS;
    }
}
