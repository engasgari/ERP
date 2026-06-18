<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseActionMenu extends Component
{
    public array $actions = [];

    public function render()
    {
        return view('livewire.core.ui.base-action-menu');
    }
}
