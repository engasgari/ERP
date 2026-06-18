<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseApprovalActions extends Component
{
    public array $actions = [];

    public function render()
    {
        return view('livewire.core.ui.base-approval-actions');
    }
}
