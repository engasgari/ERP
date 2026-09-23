<?php

namespace App\Repositories;

use App\Models\AccountingDocument;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Project;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExecutiveDashboardRepository
{
    public function __construct(
        private readonly SalesReportRepository $salesReports,
        private readonly FinancialReportRepository $financialReports,
    ) {}

    public function purchaseTotals(array $filters): array
    {
        $query = Invoice::query()
            ->where('direction', 'purchase')
            ->where('document_type', 'invoice')
            ->where('status', '!=', 'cancelled');

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        $row = $query
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN settled_at IS NOT NULL THEN total_amount ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN settled_at IS NULL THEN total_amount ELSE 0 END), 0) as outstanding_amount')
            ->first();

        return [
            'invoice_count' => (int) ($row->invoice_count ?? 0),
            'total_amount' => (float) ($row->total_amount ?? 0),
            'paid_amount' => (float) ($row->paid_amount ?? 0),
            'outstanding_amount' => (float) ($row->outstanding_amount ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{party_name: string, outstanding_amount: float, invoice_count: int}>
     */
    public function topDebtorCustomers(int $limit = 5): Collection
    {
        return $this->salesReports->aggregateByCustomer([])
            ->map(function ($row) {
                $row->outstanding_amount = abs((float) $row->outstanding_amount);

                return $row;
            })
            ->filter(fn ($row) => (float) $row->outstanding_amount > 0.00001)
            ->sortByDesc('outstanding_amount')
            ->take($limit)
            ->map(fn ($row) => [
                'party_name' => (string) $row->party_name,
                'outstanding_amount' => (float) $row->outstanding_amount,
                'invoice_count' => (int) $row->invoice_count,
            ])
            ->values();
    }

    public function activeCustomerCount(): int
    {
        return (int) Party::query()->customers()->where('is_active', true)->count();
    }

    public function newCustomersInPeriod(array $filters): int
    {
        $query = Party::query()->customers();

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return (int) $query->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function importantProjects(int $limit = 8): array
    {
        $activeStatuses = ['planning', 'active', 'procurement', 'manufacturing', 'testing'];

        return Project::query()
            ->with(['party:id,name', 'manager:id,name'])
            ->whereIn('status', $activeStatuses)
            ->orderByRaw("FIELD(status, 'manufacturing', 'procurement', 'testing', 'active', 'planning')")
            ->limit($limit)
            ->get()
            ->map(function (Project $project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'number' => $project->project_number,
                    'party_name' => $project->party?->name,
                    'status' => $project->status,
                    'status_label' => Project::STATUSES[$project->status] ?? $project->status,
                    'budget' => (float) $project->budget,
                    'manager_name' => $project->manager?->name,
                    'end_date' => $project->end_date?->toDateString(),
                    'is_overdue' => $project->end_date && $project->end_date->isPast(),
                    'url' => route('projects.show', $project->id),
                ];
            })
            ->all();
    }

    public function projectCounts(): array
    {
        $activeStatuses = ['planning', 'active', 'procurement', 'manufacturing', 'testing'];
        $today = Carbon::today();

        return [
            'total' => Project::query()->count(),
            'active' => Project::query()->whereIn('status', $activeStatuses)->count(),
            'overdue' => Project::query()
                ->whereIn('status', $activeStatuses)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', $today)
                ->count(),
            'near_end' => Project::query()
                ->whereIn('status', $activeStatuses)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today, $today->copy()->addDays(30)])
                ->count(),
        ];
    }

    public function openPurchaseOrdersCount(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('purchase_orders')) {
            return 0;
        }

        return (int) DB::table('purchase_orders')
            ->whereIn('status', ['draft', 'approved', 'sent', 'partial'])
            ->count();
    }

    public function draftInvoicesCount(): int
    {
        return (int) Invoice::query()->where('status', 'draft')->count();
    }

    /**
     * @return array{payroll: array<string, float>, other: array<string, float>}
     */
    public function aggregateOperatingExpensesByJalaliMonth(array $filters): array
    {
        $payroll = [];
        $other = [];

        $lines = $this->financialReports->postedLineQuery($filters)
            ->with(['document:id,document_date,type', 'account:id,code'])
            ->whereHas('account', fn ($query) => $query->where('code', 'like', '52%'))
            ->get();

        foreach ($lines as $line) {
            $documentDate = $line->document?->document_date;
            if (! $documentDate) {
                continue;
            }

            $period = Verta::instance($documentDate)->format('Y-m');
            $amount = max((float) $line->debit - (float) $line->credit, 0.0);
            if ($amount <= 0) {
                continue;
            }

            if ($line->document?->type === AccountingDocument::TYPE_PAYROLL) {
                $payroll[$period] = ($payroll[$period] ?? 0.0) + $amount;
            } else {
                $other[$period] = ($other[$period] ?? 0.0) + $amount;
            }
        }

        return [
            'payroll' => $payroll,
            'other' => $other,
        ];
    }
}
