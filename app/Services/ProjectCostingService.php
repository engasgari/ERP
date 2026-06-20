<?php

namespace App\Services;

use App\Models\BomVersion;
use App\Models\PayrollCalculation;
use App\Models\Project;
use App\Models\Salary;
use App\Models\ProductionOrder;
use App\Models\PayrollAccountingSetting;
use Illuminate\Support\Facades\DB;

class ProjectCostingService
{
    public function summary(Project $project): array
    {
        $revenue = $this->projectRevenue($project);
        $materialCost = $this->materialCost($project);
        $laborCost = $this->directLaborCost($project);
        $serviceCost = $this->serviceCost($project);
        $overheadCost = 0.0;
        $totalCost = $materialCost + $laborCost + $serviceCost;
        $profit = $revenue - $totalCost;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        $budget = (float) $project->budget;

        return [
            'revenue' => $revenue,
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'service_cost' => $serviceCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $totalCost,
            'gross_profit' => $profit,
            'profit_margin' => $margin,
            'budget' => $budget,
            'budget_variance' => $budget - $totalCost,
            'material_variance' => $this->materialVariance($project),
            'work_hours' => (float) $project->workLogs()->sum('hours'),
        ];
    }

    public function productionSummary(ProductionOrder $order): array
    {
        $order->loadMissing('materialConsumptions', 'project.workLogs');

        $plannedMaterial = $order->materialConsumptions
            ->sum(fn ($line) => (float) $line->planned_quantity * (float) $line->unit_cost);
        $actualMaterial = $order->materialConsumptions
            ->sum(fn ($line) => (float) $line->actual_quantity * (float) $line->unit_cost);

        $projectSummary = $this->summary($order->project);
        $quantity = (float) $order->quantity;
        $unitCost = $quantity > 0 ? $actualMaterial / $quantity : 0;

        return [
            'planned_material_cost' => $plannedMaterial,
            'actual_material_cost' => $actualMaterial,
            'material_variance' => $actualMaterial - $plannedMaterial,
            'project_total_cost' => $projectSummary['total_cost'],
            'unit_material_cost' => $unitCost,
        ];
    }

    public function bomPlannedLines(BomVersion $bom, float $orderQuantity): array
    {
        $bom->loadMissing('lines.component');

        return $bom->lines->map(fn ($line) => [
            'item_id' => $line->component_item_id,
            'item_name' => $line->component?->name,
            'quantity' => $line->net_quantity * $orderQuantity,
            'unit_cost' => (float) ($line->component?->purchase_price ?? 0),
        ])->all();
    }

    public function directLaborCost(Project $project): float
    {
        $salaryExpenseCode = $this->salaryExpenseAccountCode();

        return (float) DB::table('accounting_document_lines as lines')
            ->join('accounting_documents as docs', 'docs.id', '=', 'lines.accounting_document_id')
            ->join('chart_accounts as accounts', 'accounts.id', '=', 'lines.chart_account_id')
            ->where('lines.project_id', $project->id)
            ->where('docs.status', 'posted')
            ->whereIn('docs.source_type', [PayrollCalculation::class, Salary::class])
            ->where('accounts.code', $salaryExpenseCode)
            ->sum('lines.debit');
    }

    private function projectRevenue(Project $project): float
    {
        $financialRevenue = (float) $project->financialTransactions()
            ->where('type', 'income')
            ->sum('amount');

        $invoiceRevenue = (float) DB::table('invoices')
            ->where('project_id', $project->id)
            ->where('direction', 'sale')
            ->where('status', 'confirmed')
            ->sum('total_amount');

        return $financialRevenue + $invoiceRevenue;
    }

    public function serviceCost(Project $project): float
    {
        return (float) DB::table('invoices')
            ->where('project_id', $project->id)
            ->where('direction', 'purchase')
            ->where('status', 'confirmed')
            ->sum('total_amount');
    }

    private function salaryExpenseAccountCode(): string
    {
        return PayrollAccountingSetting::query()
            ->where('key', 'salary_expense')
            ->where('is_active', true)
            ->value('account_code') ?: '5202';
    }

    private function materialCost(Project $project): float
    {
        return (float) DB::table('inventory_document_lines as lines')
            ->join('inventory_documents as docs', 'docs.id', '=', 'lines.inventory_document_id')
            ->where('docs.project_id', $project->id)
            ->where('docs.status', 'confirmed')
            ->whereIn('docs.type', ['issue', 'consumption'])
            ->sum('lines.line_total');
    }

    private function materialVariance(Project $project): float
    {
        return (float) DB::table('production_material_consumptions as lines')
            ->join('production_orders as orders', 'orders.id', '=', 'lines.production_order_id')
            ->where('orders.project_id', $project->id)
            ->sum(DB::raw('(lines.actual_quantity - lines.planned_quantity) * lines.unit_cost'));
    }
}
