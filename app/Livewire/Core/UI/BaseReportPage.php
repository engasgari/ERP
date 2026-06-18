<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseReportPage extends Component
{
    use WithPagination;

    public int $perPage = 50;

    public function updated($name): void
    {
        if ($name !== 'perPage') {
            $this->resetPage();
        }
    }

    abstract public function clearFilters(): void;
}
