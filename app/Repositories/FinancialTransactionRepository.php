<?php

namespace App\Repositories;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\FinancialTransaction;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialTransactionRepository
{
    /**
     * Source types already represented elsewhere (or duplicated via financial_transactions posting).
     *
     * @var list<string>
     */
    private const EXCLUDED_LEDGER_SOURCE_TYPES = [
        FinancialTransaction::class,
        Invoice::class,
        InventoryDocument::class,
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $rows = $this->unifiedRows($filters);
        $page = Paginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();

        return new Paginator($items, $rows->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function summarize(array $filters = []): array
    {
        $incomeFilters = $filters;
        $incomeFilters['type'] = 'income';
        $expenseFilters = $filters;
        $expenseFilters['type'] = 'expense';

        $totalIncome = (float) $this->financialTransactionQuery($incomeFilters)->sum('amount');
        $registeredExpense = (float) $this->financialTransactionQuery($expenseFilters)->sum('amount');
        $ledgerExpense = (float) $this->ledgerExpenseAmount($expenseFilters);
        $totalExpense = $registeredExpense + $ledgerExpense;

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'registered_expense' => $registeredExpense,
            'ledger_expense' => $ledgerExpense,
            'profit_loss' => $totalIncome - $totalExpense,
        ];
    }

    public function distinctCategories(): Collection
    {
        $ftCategories = FinancialTransaction::query()
            ->select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $ledgerCategories = DB::table('accounting_document_lines as lines')
            ->join('accounting_documents as docs', 'docs.id', '=', 'lines.accounting_document_id')
            ->join('chart_accounts as accounts', 'accounts.id', '=', 'lines.chart_account_id')
            ->where('docs.status', 'posted')
            ->where('lines.debit', '>', 0)
            ->where('accounts.code', 'like', '5%')
            ->where(function ($query): void {
                $query->whereNull('docs.source_type')
                    ->orWhereNotIn('docs.source_type', self::EXCLUDED_LEDGER_SOURCE_TYPES);
            })
            ->distinct()
            ->orderBy('accounts.title')
            ->pluck('accounts.title');

        return $ftCategories
            ->merge($ledgerCategories)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function filterOptions(): array
    {
        return [
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::orderBy('bank_name')->orderBy('code')->get(),
            'cashboxes' => Cashbox::orderBy('name')->orderBy('code')->get(),
            'categories' => $this->distinctCategories(),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function unifiedRows(array $filters = []): Collection
    {
        $type = $filters['type'] ?? null;
        $rows = collect();

        $rows = $rows->merge($this->mapFinancialTransactions(
            $this->financialTransactionQuery($filters)->with([
                'project',
                'bankAccount',
                'cashbox',
                'chartAccount',
                'detailAccount',
                'accountingDocument',
            ])->get()
        ));

        if ($type !== 'income') {
            $rows = $rows->merge($this->mapLedgerExpenseRows($this->ledgerExpenseQuery($filters)->get()));
        }

        return $rows
            ->sortByDesc(fn (object $row) => sprintf('%s-%s', $row->transaction_date, $row->row_key))
            ->values();
    }

    private function financialTransactionQuery(array $filters): Builder
    {
        $query = FinancialTransaction::query();

        if (! empty($filters['project_id'])) {
            if ($filters['project_id'] === 'null') {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', $filters['project_id']);
            }
        }

        if (! empty($filters['bank_account_id'])) {
            $query->where('bank_account_id', $filters['bank_account_id']);
        }

        if (! empty($filters['cashbox_id'])) {
            $query->where('cashbox_id', $filters['cashbox_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', 'like', '%' . $filters['category'] . '%');
        }

        if (! empty($filters['reference_number'])) {
            $query->where('reference_number', 'like', '%' . $filters['reference_number'] . '%');
        }

        if (! empty($filters['description'])) {
            $query->where('description', 'like', '%' . $filters['description'] . '%');
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        $amountMin = $filters['amount_min'] ?? null;
        if ($amountMin !== null && $amountMin !== '' && is_numeric($amountMin)) {
            $query->where('amount', '>=', $amountMin);
        }

        $amountMax = $filters['amount_max'] ?? null;
        if ($amountMax !== null && $amountMax !== '' && is_numeric($amountMax)) {
            $query->where('amount', '<=', $amountMax);
        }

        return $query;
    }

    private function ledgerExpenseQuery(array $filters)
    {
        $query = DB::table('accounting_document_lines as lines')
            ->join('accounting_documents as docs', 'docs.id', '=', 'lines.accounting_document_id')
            ->join('chart_accounts as accounts', 'accounts.id', '=', 'lines.chart_account_id')
            ->leftJoin('projects', 'projects.id', '=', 'lines.project_id')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'lines.bank_account_id')
            ->leftJoin('cashboxes', 'cashboxes.id', '=', 'lines.cashbox_id')
            ->where('docs.status', 'posted')
            ->where('lines.debit', '>', 0)
            ->where('accounts.code', 'like', '5%')
            ->where(function ($builder): void {
                $builder->whereNull('docs.source_type')
                    ->orWhereNotIn('docs.source_type', self::EXCLUDED_LEDGER_SOURCE_TYPES);
            })
            ->select([
                'lines.id as line_id',
                'lines.accounting_document_id',
                'lines.project_id',
                'lines.bank_account_id',
                'lines.cashbox_id',
                'lines.debit as amount',
                'lines.description as line_description',
                'docs.document_date as transaction_date',
                'docs.number as document_number',
                'docs.description as document_description',
                'docs.source_type',
                'accounts.code as account_code',
                'accounts.title as account_title',
                'projects.name as project_name',
                'bank_accounts.bank_name',
                'bank_accounts.code as bank_code',
                'cashboxes.name as cashbox_name',
                'cashboxes.code as cashbox_code',
            ]);

        if (! empty($filters['project_id'])) {
            if ($filters['project_id'] === 'null') {
                $query->whereNull('lines.project_id');
            } else {
                $query->where('lines.project_id', $filters['project_id']);
            }
        }

        if (! empty($filters['bank_account_id'])) {
            $query->where('lines.bank_account_id', $filters['bank_account_id']);
        }

        if (! empty($filters['cashbox_id'])) {
            $query->where('lines.cashbox_id', $filters['cashbox_id']);
        }

        if (! empty($filters['category'])) {
            $query->where(function ($builder) use ($filters): void {
                $builder->where('accounts.title', 'like', '%' . $filters['category'] . '%')
                    ->orWhere('accounts.code', 'like', '%' . $filters['category'] . '%');
            });
        }

        if (! empty($filters['description'])) {
            $query->where(function ($builder) use ($filters): void {
                $builder->where('lines.description', 'like', '%' . $filters['description'] . '%')
                    ->orWhere('docs.description', 'like', '%' . $filters['description'] . '%')
                    ->orWhere('docs.number', 'like', '%' . $filters['description'] . '%');
            });
        }

        if (! empty($filters['reference_number'])) {
            $query->where('docs.number', 'like', '%' . $filters['reference_number'] . '%');
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('docs.document_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('docs.document_date', '<=', $filters['end_date']);
        }

        $amountMin = $filters['amount_min'] ?? null;
        if ($amountMin !== null && $amountMin !== '' && is_numeric($amountMin)) {
            $query->where('lines.debit', '>=', $amountMin);
        }

        $amountMax = $filters['amount_max'] ?? null;
        if ($amountMax !== null && $amountMax !== '' && is_numeric($amountMax)) {
            $query->where('lines.debit', '<=', $amountMax);
        }

        return $query;
    }

    private function ledgerExpenseAmount(array $filters): float
    {
        return (float) $this->ledgerExpenseQuery($filters)->sum('lines.debit');
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return Collection<int, object>
     */
    private function mapFinancialTransactions(Collection $transactions): Collection
    {
        return $transactions->map(function (FinancialTransaction $transaction) {
            return (object) [
                'row_key' => 'ft-' . $transaction->id,
                'row_source' => 'financial_transaction',
                'id' => $transaction->id,
                'transaction_date' => optional($transaction->transaction_date)?->format('Y-m-d') ?? (string) $transaction->transaction_date,
                'project_id' => $transaction->project_id,
                'project' => $transaction->project,
                'project_name' => $transaction->project?->name,
                'type' => $transaction->type,
                'type_label' => $transaction->type_label,
                'category' => $transaction->category ?: ($transaction->detailAccount?->title ?: $transaction->chartAccount?->title),
                'amount' => (float) $transaction->amount,
                'description' => $transaction->description,
                'reference_number' => $transaction->reference_number,
                'source_label' => $transaction->source_label,
                'accounting_document_id' => $transaction->accounting_document_id,
                'is_editable' => true,
                'model' => $transaction,
            ];
        });
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return Collection<int, object>
     */
    private function mapLedgerExpenseRows(Collection $lines): Collection
    {
        return $lines->map(function (object $line) {
            $sourceLabel = '-';
            if (! empty($line->bank_name)) {
                $sourceLabel = 'بانک: ' . $line->bank_name . ' - ' . $line->bank_code;
            } elseif (! empty($line->cashbox_name)) {
                $sourceLabel = 'صندوق: ' . $line->cashbox_name . ' - ' . $line->cashbox_code;
            } elseif (! empty($line->document_number)) {
                $sourceLabel = 'سند: ' . $line->document_number;
            }

            $description = trim((string) ($line->line_description ?: $line->document_description ?: ''));
            if ($description === '' && ! empty($line->document_number)) {
                $description = 'سند حسابداری ' . $line->document_number;
            }

            return (object) [
                'row_key' => 'ledger-' . $line->line_id,
                'row_source' => 'ledger_expense',
                'id' => $line->line_id,
                'transaction_date' => (string) $line->transaction_date,
                'project_id' => $line->project_id,
                'project' => null,
                'project_name' => $line->project_name,
                'type' => 'expense',
                'type_label' => 'هزینه',
                'category' => trim(($line->account_code ? $line->account_code . ' - ' : '') . ($line->account_title ?? '')),
                'amount' => (float) $line->amount,
                'description' => $description !== '' ? $description : '-',
                'reference_number' => $line->document_number,
                'source_label' => $sourceLabel,
                'accounting_document_id' => $line->accounting_document_id,
                'is_editable' => false,
                'model' => null,
            ];
        });
    }
}
