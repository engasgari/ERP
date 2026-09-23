<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceRepository
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filter(Invoice::query(), $filters)
            ->with(['party', 'project', 'lines.item', 'accountingDocument', 'settledBy'])
            ->latest()
            ->paginate($perPage);
    }

    public function findForDetails(int $invoiceId): ?Invoice
    {
        return Invoice::with(['party', 'project', 'lines.item', 'accountingDocument', 'settledBy'])
            ->find($invoiceId);
    }

    private function filter(Builder $query, array $filters): Builder
    {
        foreach (['direction', 'document_type', 'status'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(fn (Builder $builder) => $builder
                ->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('party', fn (Builder $partyQuery) => $partyQuery->where('name', 'like', "%{$search}%"))
                ->orWhereHas('lines.item', fn (Builder $lineQuery) => $lineQuery->where('name', 'like', "%{$search}%")));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        return $query;
    }
}
