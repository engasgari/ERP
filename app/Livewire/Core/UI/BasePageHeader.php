<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BasePageHeader extends Component
{
    public string $title = '';
    public string $description = '';
    public array $actions = [];

    public function render()
    {
        return view('livewire.core.ui.base-page-header');
    }
}
