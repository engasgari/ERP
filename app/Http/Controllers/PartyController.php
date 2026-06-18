<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocumentLine;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Project;
use App\Models\TreasuryTransaction;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Throwable;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        return view('parties.index');
    }

    public function create()
    {
        return view('parties.create', [
            'party' => new Party(['kind' => 'person', 'is_active' => true]),
            'types' => PartyType::orderBy('title')->get(),
        ]);
    }

    public function store(Request $request, NumberingService $numbering)
    {
        $validated = $this->validated($request);
        $typeIds = $validated['types'] ?? [];
        unset($validated['types']);

        $party = Party::create($validated + [
            'code' => $numbering->next('party', 'P-'),
            'detail_code' => $numbering->next('party_detail', 'D-'),
            'is_active' => true,
        ]);

        $party->types()->sync($typeIds);

        if ($request->expectsJson()) {
            return response()->json([
                'party' => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'code' => $party->code,
                    'detail_code' => $party->detail_code,
                ],
                'message' => 'شخص/شرکت ثبت شد.',
            ], 201);
        }

        return redirect()->route('parties.index')->with('success', 'شخص/شرکت ثبت شد.');
    }

    public function edit(Party $party)
    {
        return view('parties.create', [
            'party' => $party->load('types'),
            'types' => PartyType::orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, Party $party)
    {
        $validated = $this->validated($request);
        $typeIds = $validated['types'] ?? [];
        unset($validated['types']);

        $party->update($validated);
        $party->types()->sync($typeIds);

        return redirect()->route('parties.index')->with('success', 'شخص/شرکت ویرایش شد.');
    }

    public function destroy(Party $party)
    {
        $related = [
            'فاکتور فروش/خرید' => Invoice::where('party_id', $party->id)->count(),
            'ردیف سند حسابداری' => AccountingDocumentLine::where('party_id', $party->id)->count(),
            'پروژه' => Project::where('party_id', $party->id)->count(),
            'تراکنش خزانه' => TreasuryTransaction::withTrashed()->where('party_id', $party->id)->count(),
        ];

        $details = collect($related)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $title) => "{$title}: {$count}")
            ->values()
            ->all();

        $invoiceNumbers = Invoice::where('party_id', $party->id)->limit(5)->pluck('number')->filter()->implode('، ');
        $projectNames = Project::where('party_id', $party->id)->limit(5)->pluck('name')->filter()->implode('، ');

        if ($invoiceNumbers) {
            $details[] = 'نمونه فاکتورها: ' . $invoiceNumbers;
        }

        if ($projectNames) {
            $details[] = 'نمونه پروژه‌ها: ' . $projectNames;
        }

        if (! empty($details)) {
            return redirect()->route('parties.index')
                ->with('error', 'به دلیل وجود گردش یا سند مرتبط، امکان حذف این شخص/شرکت وجود ندارد.')
                ->with('error_details', $details);
        }

        try {
            $party->types()->detach();
            $party->delete();
        } catch (Throwable) {
            return redirect()->route('parties.index')
                ->with('error', 'به دلیل وجود گردش یا سند مرتبط، امکان حذف این شخص/شرکت وجود ندارد.')
                ->with('error_details', ['یک یا چند رکورد وابسته در دیتابیس وجود دارد.']);
        }

        return redirect()->route('parties.index')->with('success', 'شخص/شرکت حذف شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => 'required|in:person,company',
            'name' => 'required|string|max:255',
            'economic_code' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'postal_code' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'types' => 'array',
            'types.*' => 'exists:party_types,id',
        ]);
    }
}
