<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseDataTable extends Component
{
    public array $headers = [];
    public array $rows = [];
    public string $emptyMessage = 'رکوردی یافت نشد.';

    public function render()
    {
        return view('livewire.core.ui.base-data-table');
    }
}
