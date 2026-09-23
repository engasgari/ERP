<?php

namespace App\Livewire\Concerns;

trait ResetsPaginationOnFilterChange
{
    public function updated(string $name): void
    {
        if ($name === 'page' || str_starts_with($name, 'form.')) {
            return;
        }

        $this->resetPage();
    }
}
