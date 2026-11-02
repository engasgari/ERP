<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ItemRepository
{
    public function paginateForPricing(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->pricingFilter(Item::query()->with('unit'), $filters)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, string>
     */
    public function categories(): Collection
    {
        return Item::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    private function pricingFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        } else {
            $query->where('is_active', true);
        }

        return $query;
    }
}
