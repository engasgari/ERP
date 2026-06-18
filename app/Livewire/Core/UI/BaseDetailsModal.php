<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseDetailsModal extends Component
{
    public string $title = 'جزئیات';
    public array $fields = [];
    public array $actions = [];
    public bool $open = false;

    public function render()
    {
        return view('livewire.core.ui.base-details-modal');
    }
}
