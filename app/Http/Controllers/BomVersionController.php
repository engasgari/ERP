<?php

namespace App\Http\Controllers;

use App\Models\BomVersion;
use App\Models\Item;
use App\Models\MeasurementUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BomVersionController extends Controller
{
    public function index(Request $request)
    {
        $boms = BomVersion::query()
            ->with('item', 'lines.component')
            ->withCount('productionOrders')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);
                $query->where(function ($query) use ($search) {
                    $query->where('version_number', 'like', "%{$search}%")
                        ->orWhere('revision', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($item) => $item->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('project-boms.index', ['boms' => $boms]);
    }

    public function create()
    {
        return view('project-boms.form', $this->formData(new BomVersion()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $bom = BomVersion::create($data['bom'] + ['created_by' => $request->user()?->id]);
            $this->replaceLines($bom, $data['lines']);
        });

        return redirect()->route('project-boms.index')->with('success', 'فرمول ساخت ثبت شد.');
    }

    public function show(BomVersion $projectBom)
    {
        return view('project-boms.show', [
            'bom' => $projectBom->load('item', 'lines.component.unit', 'creator')->loadCount('productionOrders'),
        ]);
    }

    public function edit(BomVersion $projectBom)
    {
        return view('project-boms.form', $this->formData($projectBom->load('lines')));
    }

    public function update(Request $request, BomVersion $projectBom)
    {
        $data = $this->validated($request, $projectBom->id);

        DB::transaction(function () use ($projectBom, $data) {
            $projectBom->update($data['bom']);
            $this->replaceLines($projectBom, $data['lines']);
        });

        return redirect()->route('project-boms.show', $projectBom)->with('success', 'فرمول ساخت ویرایش شد.');
    }

    public function destroy(BomVersion $projectBom)
    {
        if ($projectBom->productionOrders()->exists()) {
            $orders = $projectBom->productionOrders()
                ->latest()
                ->limit(5)
                ->pluck('number')
                ->filter()
                ->implode('، ');

            return redirect()
                ->route('project-boms.index')
                ->with('error', 'این فرمول ساخت در سفارش تولید استفاده شده و قابل حذف نیست.')
                ->with('error_details', [
                    'سفارش‌های تولید مرتبط: ' . $projectBom->productionOrders()->count() . ($orders ? ' - نمونه: ' . $orders : ''),
                ]);
        }

        $projectBom->delete();

        return redirect()->route('project-boms.index')->with('success', 'فرمول ساخت حذف شد.');
    }

    private function formData(BomVersion $bom): array
    {
        return [
            'bom' => $bom,
            'products' => Item::where('type', 'product')->where('is_active', true)->orderBy('name')->get(),
            'components' => Item::where('is_active', true)->orderBy('name')->get(),
            'units' => MeasurementUnit::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'effective_date' => jalaliToGregorianDate($request->input('effective_date')),
        ]);

        $rules = [
            'item_id' => 'required|exists:items,id',
            'version_number' => 'required|string|max:50',
            'revision' => 'nullable|string|max:50',
            'status' => 'required|in:draft,active,archived',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array',
            'lines.*.component_item_id' => 'nullable|exists:items,id',
            'lines.*.quantity' => 'nullable|required_with:lines.*.component_item_id|numeric|min:0.001',
            'lines.*.measurement_unit_id' => 'nullable|exists:measurement_units,id',
            'lines.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            'lines.*.notes' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        $data = [
            'bom' => collect($validated)->only(['item_id', 'version_number', 'revision', 'status', 'effective_date', 'notes'])->all(),
            'lines' => collect($validated['lines'])
                ->filter(fn ($line) => !empty($line['component_item_id']))
                ->values()
                ->all(),
        ];

        if (empty($data['lines'])) {
            throw ValidationException::withMessages([
                'lines' => 'حداقل یک ردیف ماده یا قطعه برای فرمول ساخت وارد کنید.',
            ]);
        }

        return $data;
    }

    private function replaceLines(BomVersion $bom, array $lines): void
    {
        $bom->lines()->delete();

        foreach ($lines as $line) {
            $bom->lines()->create([
                'component_item_id' => $line['component_item_id'],
                'quantity' => $line['quantity'],
                'measurement_unit_id' => $line['measurement_unit_id'] ?? null,
                'waste_percentage' => $line['waste_percentage'] ?? 0,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }
}
