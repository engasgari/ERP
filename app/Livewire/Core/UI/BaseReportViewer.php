<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseReportViewer extends Component
{
    public string $title = 'گزارش';
    public array $summary = [];
    public array $headers = [];
    public array $rows = [];
    public string $printUrl = '#';

    public function render()
    {
        return view('livewire.core.ui.base-report-viewer');
    }
}
