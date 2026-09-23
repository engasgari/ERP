<?php

namespace App\Repositories;

use App\Models\FiscalYear;
use Illuminate\Support\Collection;

class FiscalYearRepository
{
    public function allWithPeriods(): Collection
    {
        return FiscalYear::with(['periods' => fn ($query) => $query->orderBy('period_number')])
            ->latest('jalali_year')
            ->get();
    }

    public function findForEdit(int $yearId): ?FiscalYear
    {
        return FiscalYear::with('periods')->find($yearId);
    }
}
