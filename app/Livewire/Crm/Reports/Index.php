<?php

namespace App\Livewire\Crm\Reports;

use App\Models\Crm\CrmModel;
use App\Services\Crm\CrmReportService;
use Livewire\Component;

class Index extends Component
{
    public function render(CrmReportService $reports)
    {
        $user = auth()->user();
        $summary = $reports->summary($user);
        $leadsByStatus = $reports->leadsByStatus($user);
        $opportunitiesByStage = $reports->opportunitiesByStage($user);
        $statusLabels = CrmModel::STATUSES_LEAD;

        return view('livewire.crm.reports.index', compact('summary', 'leadsByStatus', 'opportunitiesByStage', 'statusLabels'));
    }
}
