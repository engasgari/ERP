<?php

namespace App\Repositories;

use App\Core\Ledger\PartyStatementGuard;
use App\Models\AccountingAudit;
use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PayrollAccountingSetting;
use App\Models\TreasuryTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FinancialReportRepository
{
    public function postedLineQuery(array $filters = [], bool $excludeReversalDocuments = false): Builder
    {
        $query = AccountingDocumentLine::query()
            ->select('accounting_document_lines.*')
            ->join('accounting_documents', 'accounting_documents.id', '=', 'accounting_document_lines.accounting_document_id')
            ->where('accounting_documents.status', 'posted')
            ->with(['account', 'detailAccount', 'document', 'party', 'project', 'bankAccount', 'cashbox']);

        if (Schema::hasColumn('accounting_documents', 'deleted_at')) {
            $query->whereNull('accounting_documents.deleted_at');
        }

        if (Schema::hasColumn('accounting_documents', 'voided_at')) {
            $query->whereNull('accounting_documents.voided_at');
        }

        if ($excludeReversalDocuments) {
            $this->excludeReversalDocuments($query);
        }

        $this->applyDocumentFilters($query, $filters);
        $this->applyLineFilters($query, $filters);

        return $query;
    }

    public function partyStatementRows(?int $accountId, ?int $partyId, array $filters = []): \Illuminate\Support\Collection
    {
        $query = $this->postedLineQuery($filters, excludeReversalDocuments: true);

        $allowedAccountIds = $accountId
            ? $this->accountAndDescendantIds($accountId)
            : $this->partyStatementAllowedAccountIds();

        if (! $allowedAccountIds) {
            return collect();
        }

        $query->whereIn('accounting_document_lines.chart_account_id', $allowedAccountIds);

        if ($partyId) {
            $query->where('accounting_document_lines.party_id', $partyId);
        }

        $rows = $query
            ->with(['document.source'])
            ->orderBy('accounting_documents.document_date')
            ->orderBy('accounting_document_lines.id')
            ->get();

        return $this->filterPartyStatementRows($rows);
    }

    private function filterPartyStatementRows(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        $guard = new PartyStatementGuard();

        return $rows
            ->filter(function ($line) use ($guard) {
                $description = $line->description ?? '';
                $debit = (float) $line->debit;
                $credit = (float) $line->credit;

                return ! $guard->isPayrollCompanyLiabilityLine($description, $debit, $credit);
            })
            ->values();
    }

    public function documentQuery(array $filters = []): Builder
    {
        $query = AccountingDocument::query()
            ->with(['lines.account', 'lines.party', 'creator', 'fiscalYear', 'fiscalPeriod']);

        $this->applyDocumentFilters($query, $filters);

        return $query;
    }

    public function invoiceQuery(array $filters = []): Builder
    {
        $query = Invoice::query()->with(['party', 'project', 'accountingDocument', 'lines.item']);

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function treasuryQuery(array $filters = []): Builder
    {
        $query = TreasuryTransaction::query()
            ->with(['party', 'accountingDocument'])
            ->when(! empty($filters['date_from']), fn (Builder $builder) => $builder->whereDate('transaction_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $builder) => $builder->whereDate('transaction_date', '<=', $filters['date_to']))
            ->when(! empty($filters['project_id']), fn (Builder $builder) => $builder->where('project_id', $filters['project_id']))
            ->when(! empty($filters['party_id']), fn (Builder $builder) => $builder->where('party_id', $filters['party_id']))
            ->when(! empty($filters['search']), function (Builder $builder) use ($filters) {
                $search = trim((string) $filters['search']);
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'like', "%{$search}%"));
                });
            });

        return $query;
    }

    public function auditQuery(array $filters = []): Builder
    {
        $query = AccountingAudit::query()
            ->with(['auditable']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('event', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function excludeReversalDocuments(Builder $query): void
    {
        $query->where(function (Builder $scope) {
            $scope->whereNull('accounting_documents.description')
                ->orWhere('accounting_documents.description', 'not like', 'عطف سند%');
        });
    }

    private function applyDocumentFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('accounting_documents.document_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('accounting_documents.document_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('accounting_documents.fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (! empty($filters['branch_id']) && Schema::hasColumn('accounting_documents', 'branch_id')) {
            $query->where('accounting_documents.branch_id', $filters['branch_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $isPostedLineQuery = $query->getModel() instanceof AccountingDocumentLine;

            $query->where(function (Builder $builder) use ($search, $isPostedLineQuery) {
                if ($isPostedLineQuery) {
                    $builder->where('accounting_documents.number', 'like', "%{$search}%")
                        ->orWhere('accounting_documents.description', 'like', "%{$search}%")
                        ->orWhere('accounting_document_lines.description', 'like', "%{$search}%")
                        ->orWhereHas('account', fn (Builder $account) => $account->where('title', 'like', "%{$search}%"));

                    return;
                }

                $builder->where('number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('lines.account', fn (Builder $account) => $account->where('title', 'like', "%{$search}%"));
            });
        }
    }

    private function applyLineFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['account_id'])) {
            $accountIds = $this->accountAndDescendantIds((int) $filters['account_id']);

            $query->where(function (Builder $scope) use ($accountIds) {
                $scope->whereIn('accounting_document_lines.chart_account_id', $accountIds)
                    ->orWhereIn('accounting_document_lines.detail_account_id', $accountIds);
            });
        }

        if (! empty($filters['bank_account_id'])) {
            $this->applyBankAccountFilter($query, (int) $filters['bank_account_id']);
        }

        if (! empty($filters['cashbox_id'])) {
            $this->applyCashboxFilter($query, (int) $filters['cashbox_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('accounting_document_lines.project_id', $filters['project_id']);
        }

        if (! empty($filters['party_id'])) {
            $query->where('accounting_document_lines.party_id', $filters['party_id']);
        }

        if (! empty($filters['cost_center'])) {
            $query->where('accounting_document_lines.cost_center', 'like', '%' . $filters['cost_center'] . '%');
        }
    }

    private function applyBankAccountFilter(Builder $query, int $bankAccountId): void
    {
        $bank = BankAccount::query()->find($bankAccountId);

        if (! $bank) {
            $query->whereRaw('1 = 0');

            return;
        }

        $detailAccountId = (int) ($bank->detail_account_id ?: 0);
        $ledgerAccountId = (int) ($bank->chart_account_id ?: 0);

        $query->where(function (Builder $scope) use ($bankAccountId, $detailAccountId, $ledgerAccountId) {
            $scope->where('accounting_document_lines.bank_account_id', $bankAccountId);

            if ($detailAccountId > 0) {
                $scope->orWhere('accounting_document_lines.detail_account_id', $detailAccountId)
                    ->orWhere('accounting_document_lines.chart_account_id', $detailAccountId);
            }

            if ($ledgerAccountId > 0 && $detailAccountId > 0) {
                $scope->orWhere(function (Builder $nested) use ($ledgerAccountId, $detailAccountId) {
                    $nested->where('accounting_document_lines.chart_account_id', $ledgerAccountId)
                        ->where('accounting_document_lines.detail_account_id', $detailAccountId);
                });
            }
        });
    }

    private function applyCashboxFilter(Builder $query, int $cashboxId): void
    {
        $cashbox = Cashbox::query()->find($cashboxId);

        if (! $cashbox) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('accounting_document_lines.cashbox_id', $cashboxId);
    }

    private function accountAndDescendantIds(int $accountId): array
    {
        $ids = [$accountId];
        $frontier = [$accountId];

        while ($frontier) {
            $children = ChartAccount::whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));

            if (! $children) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function partyStatementAllowedAccountIds(): array
    {
        $guard = new PartyStatementGuard();
        $rootCodes = $this->unifiedPartyStatementRoots($guard);
        $rootCodes = array_values(array_unique(array_filter($rootCodes, fn ($code) => $code !== null && $code !== '')));

        if ($rootCodes === []) {
            return [];
        }

        return collect($rootCodes)
            ->flatMap(function ($rootCode) {
                $code = (string) $rootCode;
                $rootAccountId = ChartAccount::where('code', $code)->value('id')
                    ?: ChartAccount::where('code', 'like', $code . '%')->orderBy('code')->value('id');

                return $rootAccountId ? $this->accountAndDescendantIds((int) $rootAccountId) : [];
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int|string>
     */
    private function unifiedPartyStatementRoots(PartyStatementGuard $guard): array
    {
        return array_merge(
            $guard->allowedAccounts('customer'),
            $guard->allowedAccounts('supplier'),
            $this->salaryPayableStatementRoots($guard),
        );
    }

    /**
     * @return array<int, int|string>
     */
    private function salaryPayableStatementRoots(PartyStatementGuard $guard): array
    {
        $roots = $guard->allowedAccounts('employee');
        $configured = PayrollAccountingSetting::query()
            ->where('key', 'salary_payable')
            ->where('is_active', true)
            ->value('account_code');

        if (is_string($configured) && trim($configured) !== '') {
            $roots[] = trim($configured);
        }

        return $roots;
    }
}
