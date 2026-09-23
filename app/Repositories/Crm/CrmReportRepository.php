<?php

namespace App\Repositories\Crm;

use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Party;
use App\Models\User;
use App\Services\Crm\CrmScopeService;
use Illuminate\Support\Facades\DB;

class CrmReportRepository
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function summary(User $user): array
    {
        $leads = Lead::query();
        $this->scope->applyOwnerScope($leads, $user);

        $opportunities = Opportunity::query();
        $this->scope->applyOwnerScope($opportunities, $user);

        $customers = Party::query()->customers();
        if (! $this->scope->canViewAll($user)) {
            $customers->whereHas('crmProfile', fn ($q) => $q->where('assigned_user_id', $user->id));
        }

        $totalLeads = (clone $leads)->count();
        $convertedLeads = (clone $leads)->where('status', 'converted')->count();

        return [
            'total_customers' => (clone $customers)->count(),
            'total_leads' => $totalLeads,
            'converted_leads' => $convertedLeads,
            'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0,
            'open_opportunities' => (clone $opportunities)->where('status', 'open')->count(),
            'won_opportunities' => (clone $opportunities)->where('status', 'won')->count(),
            'lost_opportunities' => (clone $opportunities)->where('status', 'lost')->count(),
            'pipeline_value' => (float) (clone $opportunities)->where('status', 'open')->sum('amount'),
            'weighted_pipeline' => (float) (clone $opportunities)->where('status', 'open')->sum('expected_amount'),
        ];
    }

    public function leadsByStatus(User $user): array
    {
        $query = Lead::query()->select('status', DB::raw('count(*) as total'));
        $this->scope->applyOwnerScope($query, $user);

        return $query->groupBy('status')->pluck('total', 'status')->all();
    }

    public function opportunitiesByStage(User $user): array
    {
        $query = Opportunity::query()
            ->where('status', 'open')
            ->select('stage_id', DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->groupBy('stage_id')
            ->with('stage');

        $this->scope->applyOwnerScope($query, $user);

        return $query->get()->map(fn ($row) => [
            'stage' => $row->stage?->name ?? '-',
            'total' => (int) $row->total,
            'amount' => (float) $row->amount,
        ])->all();
    }
}
