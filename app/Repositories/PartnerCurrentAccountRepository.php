<?php

namespace App\Repositories;

use App\Models\AccountingDocument;
use App\Models\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartnerCurrentAccountRepository
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return AccountingDocument::query()
            ->where('type', AccountingDocument::TYPE_PARTNER_CURRENT)
            ->where('source_type', Party::class)
            ->with([
                'source',
                'lines.account',
                'lines.bankAccount',
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $search = trim((string) $search);
                $query->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHasMorph('source', [Party::class], fn ($party) => $party->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['party_id'] ?? null, fn ($query, $partyId) => $query->where('source_id', $partyId))
            ->when($filters['direction'] ?? null, function ($query, $direction) {
                $pattern = $direction === 'deposit' ? 'برداشت از حساب جاری%' : 'واریز به حساب جاری%';
                $query->where('description', 'like', $pattern);
            })
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('document_date', '<=', $date))
            ->latest('document_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function shareholderParties()
    {
        return Party::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('types', fn ($types) => $types->where('name', 'shareholder'))
                    ->orWhere('detail_code', 'like', '32%');
            })
            ->orderBy('name')
            ->get();
    }
}
