<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectOverheadAllocation;
use App\Services\ProjectCostingService;
use Illuminate\Http\Request;

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
            'inventoryDocuments.warehouse',
            'inventoryDocuments.lines.item',
            'productionOrders.item',
            'overheadAllocations',
        ]);

        return [
            'project' => $project,
            'summary' => $costing->summary($project),
        ];
    }

    public function storeOverhead(Request $request, Project $project)
    {
        $data = $request->validate([
            'method' => 'required|in:labor_hours,material_cost,project_value,fixed_percentage,manual',
            'base_amount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'allocated_date' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $data['allocated_date'] = jalaliToGregorianDate($request->input('allocated_date')) ?: now()->toDateString();
        $data['created_by'] = $request->user()?->id;

        $project->overheadAllocations()->create($data);

        return redirect()
            ->route('projects.costing', $project)
            ->with('success', 'سربار پروژه ثبت شد.');
    }
}
