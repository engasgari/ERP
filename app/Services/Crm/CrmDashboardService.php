<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Task;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class CrmDashboardService
{
    public function __construct(private readonly CrmScopeService $scope) {}

    public function metrics(User $user): array
    {
        $customers = $this->customersQuery($user);
        $leads = $this->activeLeadsQuery($user);
        $opportunities = Opportunity::query()->where('status', 'open');
        $this->scope->applyOwnerScope($opportunities, $user);

        $pipelineValue = (clone $opportunities)->sum('amount');
        $weightedPipeline = (clone $opportunities)->sum('expected_amount');

        $scopedOpportunities = Opportunity::query();
        $this->scope->applyOwnerScope($scopedOpportunities, $user);

        $scopedTasks = Task::query();
        $this->scope->applyOwnerScope($scopedTasks, $user);

        $scopedActivities = Activity::query();
        $this->scope->applyOwnerScope($scopedActivities, $user);

        return [
            'total_customers' => (clone $customers)->count(),
            'active_leads' => (clone $leads)->count(),
            'new_leads' => (clone $leads)->where('status', 'new')->count(),
            'qualified_leads' => (clone $leads)->where('status', 'qualified')->count(),
            'open_opportunities' => (clone $opportunities)->count(),
            'pipeline_value' => (float) $pipelineValue,
            'weighted_pipeline' => (float) $weightedPipeline,
            'won_this_month' => (clone $scopedOpportunities)
                ->where('status', 'won')
                ->whereMonth('won_at', now()->month)
                ->count(),
            'lost_this_month' => (clone $scopedOpportunities)
                ->where('status', 'lost')
                ->whereMonth('lost_at', now()->month)
                ->count(),
            'tasks_due_today' => (clone $scopedTasks)
                ->whereDate('due_at', today())
                ->where('status', '!=', 'completed')
                ->count(),
            'overdue_tasks' => (clone $scopedTasks)
                ->where('due_at', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
            'today_activities' => (clone $scopedActivities)
                ->where(function (Builder $query) {
                    $query->whereDate('due_at', today())
                        ->orWhereDate('completed_at', today());
                })
                ->count(),
        ];
    }

    public function charts(User $user): array
    {
        $leadsBySource = $this->activeLeadsQuery($user)
            ->select('source_id', DB::raw('count(*) as total'))
            ->groupBy('source_id')
            ->with('source')
            ->get();

        $oppsByStage = Opportunity::query()
            ->where('status', 'open');
        $this->scope->applyOwnerScope($oppsByStage, $user);
        $oppsByStage = $oppsByStage
            ->select('stage_id', DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->groupBy('stage_id')
            ->with('stage')
            ->get();

        return [
            'leads_by_source' => $leadsBySource,
            'opportunities_by_stage' => $oppsByStage,
        ];
    }

    /**
     * @return list<array{label: string, url: string, tone: string, icon: string}>
     */
    public function shortcuts(User $user): array
    {
        $shortcuts = [];

        if ($user->hasPermission('crm.leads.create')) {
            $shortcuts[] = [
                'label' => 'سرنخ جدید',
                'url' => route('crm.leads.index', ['create' => 1]),
                'tone' => 'peach',
                'icon' => 'lead',
            ];
        }

        if ($user->hasPermission('crm.opportunities.create')) {
            $shortcuts[] = [
                'label' => 'فرصت جدید',
                'url' => route('crm.opportunities.index', ['create' => 1]),
                'tone' => 'sky',
                'icon' => 'opportunity',
            ];
        }

        if ($user->hasPermission('crm.sold_devices.create')) {
            $shortcuts[] = [
                'label' => 'گارانتی',
                'url' => route('crm.sold-devices.index', ['create' => 1]),
                'tone' => 'mint',
                'icon' => 'warranty',
            ];
        }

        return $shortcuts;
    }

    /**
     * @return list<array{label: string, value: string, pastel: string, href: string|null}>
     */
    public function statCards(User $user, array $metrics): array
    {
        $definitions = [
            [
                'label' => 'مشتریان',
                'value' => number_format($metrics['total_customers']),
                'pastel' => 'lavender',
                'route' => 'crm.customers.index',
                'permission' => 'crm.customers.view',
            ],
            [
                'label' => 'سرنخ فعال',
                'value' => number_format($metrics['active_leads']),
                'pastel' => 'peach',
                'route' => 'crm.leads.index',
                'permission' => 'crm.leads.view',
            ],
            [
                'label' => 'سرنخ تازه',
                'value' => number_format($metrics['new_leads']),
                'pastel' => 'butter',
                'route' => 'crm.leads.index',
                'params' => ['status' => 'new'],
                'permission' => 'crm.leads.view',
            ],
            [
                'label' => 'سرنخ واجد شرایط',
                'value' => number_format($metrics['qualified_leads']),
                'pastel' => 'mint',
                'route' => 'crm.leads.index',
                'params' => ['status' => 'qualified'],
                'permission' => 'crm.leads.view',
            ],
            [
                'label' => 'فرصت باز',
                'value' => number_format($metrics['open_opportunities']),
                'pastel' => 'sky',
                'route' => 'crm.opportunities.index',
                'params' => ['status' => 'open'],
                'permission' => 'crm.opportunities.view',
            ],
            [
                'label' => 'ارزش پایپ‌لاین',
                'value' => formatMoney($metrics['pipeline_value']),
                'pastel' => 'blue',
                'route' => 'crm.pipeline.index',
                'permission' => 'crm.opportunities.view',
            ],
            [
                'label' => 'پایپ‌لاین وزنی',
                'value' => formatMoney($metrics['weighted_pipeline']),
                'pastel' => 'indigo',
                'route' => 'crm.pipeline.index',
                'permission' => 'crm.opportunities.view',
            ],
            [
                'label' => 'برنده این ماه',
                'value' => number_format($metrics['won_this_month']),
                'pastel' => 'sage',
                'route' => 'crm.opportunities.index',
                'params' => ['status' => 'won'],
                'permission' => 'crm.opportunities.view',
            ],
            [
                'label' => 'از دست رفته این ماه',
                'value' => number_format($metrics['lost_this_month']),
                'pastel' => 'rose',
                'route' => 'crm.opportunities.index',
                'params' => ['status' => 'lost'],
                'permission' => 'crm.opportunities.view',
            ],
            [
                'label' => 'وظایف امروز',
                'value' => number_format($metrics['tasks_due_today']),
                'pastel' => 'butter',
                'route' => 'crm.tasks.index',
                'permission' => 'crm.tasks.view',
            ],
            [
                'label' => 'وظایف معوق',
                'value' => number_format($metrics['overdue_tasks']),
                'pastel' => 'coral',
                'route' => 'crm.tasks.index',
                'permission' => 'crm.tasks.view',
            ],
            [
                'label' => 'فعالیت امروز',
                'value' => number_format($metrics['today_activities']),
                'pastel' => 'aqua',
                'route' => 'crm.activities.index',
                'permission' => 'crm.activities.view',
            ],
        ];

        return collect($definitions)
            ->map(function (array $card) use ($user) {
                $permission = $card['permission'] ?? null;
                $href = null;

                if (($permission === null || $user->hasPermission($permission)) && Route::has($card['route'])) {
                    $href = route($card['route'], $card['params'] ?? []);
                }

                return [
                    'label' => $card['label'],
                    'value' => $card['value'],
                    'pastel' => $card['pastel'],
                    'href' => $href,
                ];
            })
            ->all();
    }

    private function activeLeadsQuery(User $user): Builder
    {
        $query = Lead::query()->where('status', '!=', 'converted');
        $this->scope->applyOwnerScope($query, $user);

        return $query;
    }

    private function customersQuery(User $user): Builder
    {
        $query = Party::query()->customers();

        if (! $this->scope->canViewAll($user)) {
            $query->whereHas('crmProfile', fn (Builder $profile) => $profile->where('assigned_user_id', $user->id));
        }

        return $query;
    }
}
