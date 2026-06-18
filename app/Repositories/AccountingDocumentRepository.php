<?php

namespace App\Repositories;

use App\Models\AccountingDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountingDocumentRepository
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return AccountingDocument::query()
            ->with(['lines.account', 'creator'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $search = trim((string) $search);
                $query->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('lines.account', fn ($account) => $account->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('document_date', '<=', $date))
            ->latest('document_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
