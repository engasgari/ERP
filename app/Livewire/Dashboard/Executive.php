<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardShortcutService;
use App\Services\ExecutiveDashboardService;
use App\Support\ExecutiveDashboardPeriod;
use Livewire\Component;

class Executive extends Component
{
    public string $periodPreset = 'this_month';

    public string $customDateFrom = '';

    public string $customDateTo = '';

    protected $queryString = [
        'periodPreset' => ['except' => 'this_month', 'as' => 'period'],
        'customDateFrom' => ['except' => '', 'as' => 'from'],
        'customDateTo' => ['except' => '', 'as' => 'to'],
    ];

    public function updatedPeriodPreset(): void
    {
        if ($this->periodPreset !== 'custom') {
            $this->customDateFrom = '';
            $this->customDateTo = '';
        }
    }

    public function render(ExecutiveDashboardService $dashboard, DashboardShortcutService $shortcuts)
    {
        $user = auth()->user();

        $data = $dashboard->build(
            $user,
            $this->periodPreset,
            $this->customDateFrom !== '' ? $this->customDateFrom : null,
            $this->customDateTo !== '' ? $this->customDateTo : null,
        );

        return view('livewire.dashboard.executive', [
            'dashboard' => $data,
            'presets' => ExecutiveDashboardPeriod::PRESETS,
            'shortcuts' => $shortcuts->forUser($user),
            'shortcutStorageKey' => 'erp.dashboard.shortcuts.'.$user->id,
        ]);
    }
}
