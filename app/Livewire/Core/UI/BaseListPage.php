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

    public function mount(): void
    {
        foreach (['date_from', 'date_to'] as $property) {
            if (property_exists($this, $property) && is_string($this->{$property})) {
                $this->{$property} = normalizeJalaliFilterDate($this->{$property});
            }
        }
    }

    public function updated(string $name): void
    {
        if ($name !== 'selectedRows' && $name !== 'detailsId') {
            $this->resetPage();
        }
    }

    public function updatedDateFrom(string $value): void
    {
        if (property_exists($this, 'date_from')) {
            $this->date_from = normalizeJalaliFilterDate($value);
        }
    }

    public function updatedDateTo(string $value): void
    {
        if (property_exists($this, 'date_to')) {
            $this->date_to = normalizeJalaliFilterDate($value);
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
