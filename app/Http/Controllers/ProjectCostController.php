<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Services\ProjectCostingService;

class ProjectCostController extends Controller
{
    public function show(Project $project, ProjectCostingService $costing)
    {
        return view('projects.costing', $this->reportData($project, $costing));
    }

    public function print(Project $project, ProjectCostingService $costing)
    {
        return view('projects.print.costing', $this->reportData($project, $costing) + [
            'reportTitle' => 'گزارش بهای تمام‌شده پروژه',
            'backRoute' => route('projects.costing', $project),
        ]);
    }

    private function reportData(Project $project, ProjectCostingService $costing): array
    {
        $project->load([
            'party',
            'manager',
            'workLogs.employee',
        ]);

        return [
            'project' => $project,
            'summary' => $costing->summary($project),
            'saleInvoices' => Invoice::with('party')
                ->where('project_id', $project->id)
                ->where('direction', 'sale')
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->get(),
            'purchaseInvoices' => Invoice::with('party')
                ->where('project_id', $project->id)
                ->where('direction', 'purchase')
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->get(),
            'registeredExpenses' => $project->financialTransactions()
                ->with(['bankAccount', 'cashbox', 'chartAccount', 'detailAccount'])
                ->where('type', 'expense')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get(),
        ];
    }
}
