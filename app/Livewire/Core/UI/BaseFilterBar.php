<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseFilterBar extends Component
{
    public array $filters = [];
    public string $resetLabel = 'حذف فیلترها';

    public function render()
    {
        return view('livewire.core.ui.base-filter-bar');
    }
}
