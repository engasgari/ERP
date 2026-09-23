<?php

namespace App\Livewire\Crm\Settings;

use App\Models\Crm\LeadSource;
use App\Models\Crm\Pipeline;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $sources = LeadSource::query()->orderBy('sort_order')->get();
        $pipelines = Pipeline::query()->with('stages')->orderBy('sort_order')->get();

        return view('livewire.crm.settings.index', compact('sources', 'pipelines'));
    }
}
