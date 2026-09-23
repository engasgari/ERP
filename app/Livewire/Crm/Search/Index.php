<?php

namespace App\Livewire\Crm\Search;

use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Party;
use App\Services\Crm\CrmScopeService;
use Livewire\Component;

class Index extends Component
{
    public string $query = '';

    protected $queryString = [
        'query' => ['except' => ''],
    ];

    public function render(CrmScopeService $scope)
    {
        $user = auth()->user();
        $results = [
            'customers' => collect(),
            'leads' => collect(),
            'opportunities' => collect(),
        ];

        if (strlen(trim($this->query)) >= 2) {
            $search = trim($this->query);

            $customers = Party::query()->customers()->with('crmProfile');
            if (! $scope->canViewAll($user)) {
                $customers->whereHas('crmProfile', fn ($q) => $q->where('assigned_user_id', $user->id));
            }
            $results['customers'] = $customers
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get();

            $leads = Lead::query();
            $scope->applyOwnerScope($leads, $user);
            $results['leads'] = $leads
                ->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get();

            $opportunities = Opportunity::query()->with('party');
            $scope->applyOwnerScope($opportunities, $user);
            $results['opportunities'] = $opportunities
                ->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get();
        }

        return view('livewire.crm.search.index', compact('results'));
    }
}
