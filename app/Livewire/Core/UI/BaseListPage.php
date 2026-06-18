<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseListPage extends Component
{
    use WithPagination;

    public array $selectedRows = [];
    public ?int $detailsId = null;
    public int $perPage = 20;

    protected array $queryString = [
        'page' => ['except' => 1],
    ];

    public function updated(string $name): void
    {
        if ($name !== 'selectedRows' && $name !== 'detailsId') {
            $this->resetPage();
        }
    }

    public function openDetails(int $id): void
    {
        $this->detailsId = $id;
    }

    public function closeDetails(): void
    {
        $this->detailsId = null;
    }

    public function clearSelection(): void
    {
        $this->selectedRows = [];
    }

    abstract public function clearFilters(): void;
}
