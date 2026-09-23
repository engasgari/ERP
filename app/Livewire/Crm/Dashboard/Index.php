<?php

namespace App\Livewire\Crm\Dashboard;

use App\Services\Crm\CrmDashboardService;
use Livewire\Component;

class Index extends Component
{
    public function render(CrmDashboardService $dashboard)
    {
        $user = auth()->user();
        $metrics = $dashboard->metrics($user);
        $charts = $dashboard->charts($user);
        $shortcuts = $dashboard->shortcuts($user);
        $statCards = $dashboard->statCards($user, $metrics);

        return view('livewire.crm.dashboard.index', compact('metrics', 'charts', 'shortcuts', 'statCards'));
    }
}
