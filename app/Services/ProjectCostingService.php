<?php

namespace App\Services;

use App\Models\BomVersion;
use App\Models\FinancialTransaction;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\PayrollCalculation;
use App\Models\Project;
use App\Models\ProductionOrder;
use App\Models\PayrollAccountingSetting;
use Illuminate\Support\Facades\DB;

class ProjectCostingService
{
    public function summary(Project $project): array
    {
        $invoiceSales = $this->invoiceSaleTotals($project);
        $invoicePurchases = $this->invoicePurchaseTotals($project);
        $financialRevenue = $this->financialRevenue($project);
        $revenue = $financialRevenue + $invoiceSales['net'];
        $grossRevenue = $financialRevenue + $invoiceSales['gross'];
        $materialCost = $this->materialCost($project);
        $laborCost = $this->directLaborCost($project);
        $serviceCostGross = $invoicePurchases['gross'];
        $serviceCostNet = $invoicePurchases['net'];
        $registeredExpenseCost = $this->registeredExpenseCost($project);
        $ledgerExpenseCost = $this->ledgerProjectExpenseCost($project);
        $overheadCost = 0.0;
        $totalCostGross = $materialCost + $laborCost + $serviceCostGross + $registeredExpenseCost + $ledgerExpenseCost + $overheadCost;
        $totalCostNet = $materialCost + $laborCost + $serviceCostNet + $registeredExpenseCost + $ledgerExpenseCost + $overheadCost;
        $profitNet = $revenue - $totalCostNet;
        $profitGross = $grossRevenue - $totalCostGross;
        $margin = $revenue > 0 ? ($profitNet / $revenue) * 100 : 0;
        $budget = (float) $project->budget;

        return [
            'revenue' => $revenue,
            'gross_revenue' => $grossRevenue,
            'financial_revenue' => $financialRevenue,
            'invoice_gross_revenue' => $invoiceSales['gross'],
            'vat_collected' => $invoiceSales['vat'],
            'invoice_gross_purchase' => $invoicePurchases['gross'],
            'vat_paid' => $invoicePurchases['vat'],
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'service_cost' => $serviceCostGross,
            'service_cost_net' => $serviceCostNet,
            'registered_expense_cost' => $registeredExpenseCost,
            'ledger_expense_cost' => $ledgerExpenseCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $totalCostGross,
            'total_cost_net' => $totalCostNet,
            'gross_profit' => $profitNet,
            'profit_net' => $profitNet,
            'profit_gross' => $profitGross,
            'profit_margin' => $margin,
            'budget' => $budget,
            'budget_variance' => $budget - $totalCostNet,
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
            ->whereIn('docs.source_type', [PayrollCalculation::class])
            ->where('accounts.code', $salaryExpenseCode)
            ->sum('lines.debit');
    }

    public function projectRevenue(Project $project): float
    {
        return $this->financialRevenue($project) + $this->invoiceSaleTotals($project)['net'];
    }

    public function financialRevenue(Project $project): float
    {
        return (float) $project->financialTransactions()
            ->where('type', 'income')
            ->sum('amount');
    }

    /**
     * @return array{gross: float, vat: float, net: float}
     */
    public function invoiceSaleTotals(Project $project): array
    {
        $row = DB::table('invoices')
            ->where('project_id', $project->id)
            ->where('direction', 'sale')
            ->where(function ($query) {
                $query->where('status', 'confirmed')
                    ->orWhereNotNull('accounting_document_id');
            })
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as vat')
            ->selectRaw('COALESCE(SUM(total_amount - tax_amount), 0) as net')
            ->first();

        return [
            'gross' => (float) ($row->gross ?? 0),
            'vat' => (float) ($row->vat ?? 0),
            'net' => (float) ($row->net ?? 0),
        ];
    }

    /**
     * @return array{gross: float, vat: float, net: float}
     */
    public function invoicePurchaseTotals(Project $project): array
    {
        $row = DB::table('invoices')
            ->where('project_id', $project->id)
            ->where('direction', 'purchase')
            ->where('status', 'confirmed')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as vat')
            ->selectRaw('COALESCE(SUM(total_amount - tax_amount), 0) as net')
            ->first();

        return [
            'gross' => (float) ($row->gross ?? 0),
            'vat' => (float) ($row->vat ?? 0),
            'net' => (float) ($row->net ?? 0),
        ];
    }

    public function serviceCost(Project $project): float
    {
        return $this->invoicePurchaseTotals($project)['gross'];
    }

    public function registeredExpenseCost(Project $project): float
    {
        return (float) $project->financialTransactions()
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function ledgerProjectExpenseCost(Project $project): float
    {
        return (float) DB::table('accounting_document_lines as lines')
            ->join('accounting_documents as docs', 'docs.id', '=', 'lines.accounting_document_id')
            ->join('chart_accounts as accounts', 'accounts.id', '=', 'lines.chart_account_id')
            ->where('lines.project_id', $project->id)
            ->where('docs.status', 'posted')
            ->where('accounts.nature', 'debit')
            ->where('accounts.code', 'like', '5%')
            ->where(function ($query): void {
                $query->whereNull('docs.source_type')
                    ->orWhereNotIn('docs.source_type', [
                        PayrollCalculation::class,
                        FinancialTransaction::class,
                        Invoice::class,
                        InventoryDocument::class,
                    ]);
            })
            ->sum('lines.debit');
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
