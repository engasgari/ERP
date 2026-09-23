<?php

namespace App\Livewire\FiscalPeriods;

use App\Livewire\Core\UI\BaseListPage;
use App\Repositories\FiscalYearRepository;
use App\Services\FiscalPeriodService;

class Index extends BaseListPage
{
    public string $edit = '';

    protected array $queryString = [
        'edit' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->edit = '';
    }

    public function render(FiscalYearRepository $years, FiscalPeriodService $fiscalPeriodService)
    {
        $items = $years->allWithPeriods();
        $editingYear = null;
        $isCreating = $this->edit === 'new';

        if ($this->edit !== '' && $this->edit !== 'new' && ctype_digit($this->edit)) {
            $editingYear = $years->findForEdit((int) $this->edit);
        }

        return view('livewire.fiscal-periods.index', compact('items', 'editingYear', 'isCreating', 'fiscalPeriodService'));
    }
}
