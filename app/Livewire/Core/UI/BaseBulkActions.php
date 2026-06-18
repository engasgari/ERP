<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseBulkActions extends Component
{
    public int $selectedCount = 0;
    public array $actions = [];

    public function render()
    {
        return view('livewire.core.ui.base-bulk-actions');
    }
}
