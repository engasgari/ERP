<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseStatusBadge extends Component
{
    public string $label = '';
    public string $tone = 'neutral';

    public function render()
    {
        return view('livewire.core.ui.base-status-badge');
    }
}
