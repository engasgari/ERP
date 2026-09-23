<?php

namespace App\Repositories;

use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContractorServicePurchaseRepository
{
    public function contractorParties(): Collection
    {
        return Party::query()
            ->where('is_active', true)
            ->whereHas('types', fn (Builder $q) => $q->whereIn('name', ['contractor', 'vendor']))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function projectsForFilter(): Collection
    {
        return Project::query()->orderBy('name')->get(['id', 'name']);
    }

    public function fiscalYearsForFilter(): Collection
    {
        return FiscalYear::query()
            ->where('status', 'open')
            ->orderByDesc('start_date')
            ->get(['id', 'title', 'jalali_year']);
    }

    /**
     * @param  array{search?: string|null, project_id?: int|null, fiscal_year_id?: int|null, contractor_party_id?: string|null, include_purchased?: bool}  $filters
     */
    public function paginateServiceSaleInvoices(array $filters, User $user, int $perPage = 25): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->where('direction', 'sale')
            ->where('document_type', 'invoice')
            ->where('status', 'confirmed')
            ->whereHas('fiscalYear', fn (Builder $fy) => $fy->where('status', 'open'))
            ->whereHas('lines.item', fn (Builder $q) => $q->where('type', 'service'))
            ->with([
                'party:id,name,code',
                'project:id,name',
                'contractorAllocation.contractor:id,name',
                'contractorAllocation.purchaseInvoice:id,number,status',
            ])
            ->latest('invoice_date')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('party', fn (Builder $p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', (int) $filters['fiscal_year_id']);
        }

        if (! empty($filters['contractor_party_id'])) {
            if ($filters['contractor_party_id'] === 'none') {
                $query->whereDoesntHave('contractorAllocation');
            } else {
                $query->whereHas(
                    'contractorAllocation',
                    fn (Builder $a) => $a->where('contractor_party_id', (int) $filters['contractor_party_id']),
                );
            }
        }

        if (empty($filters['include_purchased'])) {
            $query->where(function (Builder $q) {
                $q->whereDoesntHave('contractorAllocation')
                    ->orWhereHas('contractorAllocation', fn (Builder $a) => $a->whereNull('purchase_invoice_id'));
            });
        }

        return $query->paginate($perPage);
    }
}
