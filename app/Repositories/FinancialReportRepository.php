<?php

namespace App\Repositories;

use App\Models\AccountingAudit;
use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\Invoice;
use App\Models\TreasuryTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FinancialReportRepository
{
    public function postedLineQuery(array $filters = []): Builder
    {
        $query = AccountingDocumentLine::query()
            ->select('accounting_document_lines.*')
            ->join('accounting_documents', 'accounting_documents.id', '=', 'accounting_document_lines.accounting_document_id')
            ->where('accounting_documents.status', 'posted')
            ->with(['account', 'detailAccount', 'document', 'party', 'project', 'bankAccount', 'cashbox']);

        $this->applyDocumentFilters($query, $filters);
        $this->applyLineFilters($query, $filters);

        return $query;
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
        $query = Invoice::query()->with(['party', 'project', 'accountingDocument']);

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
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('accounting_documents.number', 'like', "%{$search}%")
                    ->orWhere('accounting_documents.description', 'like', "%{$search}%")
                    ->orWhereHas('lines.account', fn (Builder $account) => $account->where('title', 'like', "%{$search}%"));
            });
        }
    }

    private function applyLineFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['account_id'])) {
            $query->where('accounting_document_lines.chart_account_id', $filters['account_id']);
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
}
