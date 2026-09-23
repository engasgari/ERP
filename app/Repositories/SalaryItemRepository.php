<?php

namespace App\Repositories;

use App\Core\Base\BaseRepository;
use App\Models\SalaryItem;
use Illuminate\Support\Collection;

class SalaryItemRepository extends BaseRepository
{
    public function __construct()
    {
        $this->model = new SalaryItem();
    }

    public function decreeCatalog(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->where('appears_on_decree', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function earningCatalogForDecree(): Collection
    {
        return $this->decreeCatalog()->where('type', 'earning')->values();
    }

    public function deductionCatalogForDecree(): Collection
    {
        return $this->decreeCatalog()->where('type', 'deduction')->values();
    }

    public function findByCode(string $code): ?SalaryItem
    {
        return $this->query()->where('code', $code)->first();
    }

    public function findActive(int $id): ?SalaryItem
    {
        return $this->query()->whereKey($id)->where('is_active', true)->first();
    }
}
