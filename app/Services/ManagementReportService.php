<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Project;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagementReportService
{
    public function warehouseInventoryData(Request $request, array $baseData = []): array
    {
        $aggregates = $this->filteredInventoryDocumentLines($request, false)
            ->selectRaw("
                d.warehouse_id,
                d.project_id,
                i.name as item_name,
                i.category,
                SUM(CASE WHEN d.type = 'receipt' THEN l.quantity ELSE 0 END) as quantity_in,
                SUM(CASE WHEN d.type IN ('issue', 'consumption') THEN l.quantity ELSE 0 END) as quantity_out,
                SUM(CASE WHEN d.type = 'receipt' THEN l.line_total ELSE 0 END) as value_in,
                SUM(CASE WHEN d.type IN ('issue', 'consumption') THEN l.line_total ELSE 0 END) as value_out
            ")
            ->groupBy('d.warehouse_id', 'd.project_id', 'i.name', 'i.category')
            ->orderBy('d.warehouse_id')
            ->orderBy('i.name')
            ->get();

        $warehousesById = Warehouse::whereIn('id', $aggregates->pluck('warehouse_id')->filter()->unique())->get()->keyBy('id');
        $projectsById = Project::whereIn('id', $aggregates->pluck('project_id')->filter()->unique())->get()->keyBy('id');

        $rows = $aggregates->map(function ($row) use ($warehousesById, $projectsById) {
            $quantityIn = (float) $row->quantity_in;
            $quantityOut = (float) $row->quantity_out;
            $valueIn = (float) $row->value_in;
            $valueOut = (float) $row->value_out;
            $balanceQuantity = $quantityIn - $quantityOut;
            $balanceValue = abs($balanceQuantity) > 0.000001 ? ($valueIn - $valueOut) : 0.0;
            $averagePrice = 0;

            if (abs($balanceQuantity) > 0.000001) {
                $averagePrice = $balanceValue / $balanceQuantity;
            }

            return [
                'warehouse' => $warehousesById->get($row->warehouse_id),
                'project' => $projectsById->get($row->project_id),
                'item_name' => $row->item_name,
                'category' => $row->category,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'balance_quantity' => $balanceQuantity,
                'balance_value' => $balanceValue,
                'average_price' => $averagePrice,
            ];
        })
            ->filter(fn ($row) => ! $request->boolean('only_available') || $row['balance_quantity'] > 0)
            ->values();

        $totalMatches = $rows->count();
        $isLimited = $totalMatches > 1000;
        $rows = $rows->take(1000)->values();

        $summary = [
            'items_count' => $rows->count(),
            'balance_quantity' => $rows->sum('balance_quantity'),
            'balance_value' => $rows->sum('balance_value'),
        ];

        return $baseData + compact(
            'rows',
            'summary',
            'isLimited',
            'totalMatches'
        );
    }

    private function filteredInventoryDocumentLines(Request $request, bool $selectColumns = true)
    {
        $query = DB::table('inventory_document_lines as l')
            ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->leftJoin('projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.status', 'confirmed')
            ->whereIn('d.type', ['receipt', 'issue', 'consumption']);

        if ($selectColumns) {
            $query->select([
                'l.id as line_id',
                'l.quantity',
                'l.unit_price',
                'l.line_total',
                'l.description as line_description',
                'd.id as document_id',
                'd.number',
                'd.type',
                'd.document_date',
                'd.document_time',
                'd.warehouse_id',
                'd.project_id',
                'd.entry_mode',
                'd.source_type',
                'd.source_id',
                'd.description as document_description',
                'i.name as item_name',
                'i.category',
                'w.name as warehouse_name',
                'p.name as project_name',
            ]);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('d.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('type')) {
            if ($request->type === 'in') {
                $query->where('d.type', 'receipt');
            } elseif ($request->type === 'out') {
                $query->whereIn('d.type', ['issue', 'consumption']);
            } else {
                $query->where('d.type', $request->type);
            }
        }

        if ($request->filled('project_id')) {
            $query->where('d.project_id', $request->project_id);
        }

        if ($request->filled('item_id')) {
            $query->where('i.id', $request->integer('item_id'));
        } elseif ($request->filled('item_name')) {
            $query->where('i.name', 'like', '%' . $request->item_name . '%');
        }

        if ($request->filled('category')) {
            $query->where('i.category', 'like', '%' . $request->category . '%');
        }

        if ($request->filled('reference_number')) {
            $query->where('d.number', 'like', '%' . $request->reference_number . '%');
        }

        if ($request->filled('description')) {
            $query->where(function ($descriptionQuery) use ($request) {
                $descriptionQuery
                    ->where('d.description', 'like', '%' . $request->description . '%')
                    ->orWhere('l.description', 'like', '%' . $request->description . '%');
            });
        }

        if ($request->filled('start_date')) {
            $startDate = jalaliToGregorianDate($request->start_date);
            if ($startDate) {
                $query->where('d.document_date', '>=', $startDate);
            }
        }

        if ($request->filled('end_date')) {
            $endDate = jalaliToGregorianDate($request->end_date);
            if ($endDate) {
                $query->where('d.document_date', '<=', $endDate);
            }
        }

        if (! $request->filled('start_date') && ! $request->filled('end_date')) {
            $query->where(function ($scope) {
                $scope->where('d.entry_mode', '!=', 'automatic')
                    ->orWhere('d.source_type', '!=', FiscalYear::class);
            });
        }

        return $query;
    }
}
