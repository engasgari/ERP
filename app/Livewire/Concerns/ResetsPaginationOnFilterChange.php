<?php

namespace App\Livewire\Concerns;

trait ResetsPaginationOnFilterChange
{
    public function updated(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }
}
