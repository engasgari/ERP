<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BasePrintLayout extends Component
{
    public string $title = 'چاپ';
    public string $backUrl = '#';

    public function render()
    {
        return view('livewire.core.ui.base-print-layout');
    }
}
