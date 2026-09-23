<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocumentLine;
use App\Models\ChartAccount;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Project;
use App\Models\TreasuryTransaction;
use App\Services\Crm\CrmDuplicateGuardService;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        return view('parties.index');
    }

    public function create()
    {
        return view('parties.create', $this->formData(
            new Party(['kind' => 'person', 'is_active' => true])
        ));
    }

    public function store(Request $request, NumberingService $numbering)
    {
        $validated = $this->validated($request);
        $typeIds = $validated['types'] ?? [];
        $partnerChartAccountCode = $validated['partner_chart_account_code'] ?? null;
        unset($validated['types'], $validated['partner_chart_account_code']);

        $party = Party::create($validated + [
            'code' => $numbering->next('party', 'P-'),
            'detail_code' => $this->resolveDetailCode($typeIds, $partnerChartAccountCode, $numbering),
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
        return view('parties.create', $this->formData($party->load('types')));
    }

    public function update(Request $request, Party $party)
    {
        $validated = $this->validated($request, $party);
        $typeIds = $validated['types'] ?? [];
        $partnerChartAccountCode = $validated['partner_chart_account_code'] ?? null;
        unset($validated['types'], $validated['partner_chart_account_code']);

        $validated['detail_code'] = $this->resolveDetailCode(
            $typeIds,
            $partnerChartAccountCode,
            app(NumberingService::class),
            $party
        );

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

    private function formData(Party $party): array
    {
        PartyType::ensureDefaults();

        $shareholderType = PartyType::shareholder();
        $selectedTypeIds = old('types', $party->exists ? $party->types->pluck('id')->all() : []);
        $selectedPartnerCode = old(
            'partner_chart_account_code',
            $party->detail_code && str_starts_with((string) $party->detail_code, '32') ? $party->detail_code : ''
        );

        return [
            'party' => $party,
            'types' => PartyType::orderBy('title')->get(),
            'shareholderTypeId' => $shareholderType?->id,
            'partnerAccounts' => $this->partnerChartAccounts(),
            'selectedTypeIds' => array_map('intval', (array) $selectedTypeIds),
            'selectedPartnerCode' => $selectedPartnerCode,
        ];
    }

    private function partnerChartAccounts()
    {
        return ChartAccount::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('code', 'like', '32%')
                    ->orWhereHas('parent', fn ($parent) => $parent->where('code', '32'));
            })
            ->whereIn('level', ['subsidiary', 'detail'])
            ->orderBy('code')
            ->get();
    }

    private function resolveDetailCode(array $typeIds, ?string $partnerChartAccountCode, NumberingService $numbering, ?Party $party = null): string
    {
        if ($this->isShareholderSelected($typeIds)) {
            if (filled($partnerChartAccountCode)) {
                return $partnerChartAccountCode;
            }

            if ($party?->detail_code && str_starts_with((string) $party->detail_code, '32')) {
                return $party->detail_code;
            }
        }

        if ($party?->detail_code) {
            return $party->detail_code;
        }

        return $numbering->next('party_detail', 'D-');
    }

    private function isShareholderSelected(array $typeIds): bool
    {
        $shareholderId = PartyType::shareholder()?->id;

        return $shareholderId && in_array($shareholderId, array_map('intval', $typeIds), true);
    }

    private function validated(Request $request, ?Party $party = null): array
    {
        PartyType::ensureDefaults();

        $shareholderId = PartyType::shareholder()?->id;
        $typeIds = array_map('intval', (array) $request->input('types', []));
        $isShareholder = $shareholderId && in_array($shareholderId, $typeIds, true);

        $validated = $request->validate([
            'kind' => 'required|in:person,company',
            'name' => 'required|string|max:255',
            'economic_code' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'postal_code' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'types' => 'array',
            'types.*' => 'exists:party_types,id',
            'partner_chart_account_code' => [
                Rule::requiredIf($isShareholder),
                'nullable',
                'string',
                'max:20',
                Rule::exists('chart_accounts', 'code')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('code', 'like', '32%')),
            ],
        ], [
            'partner_chart_account_code.required' => 'برای شریک/سهامدار، انتخاب حساب جاری (گروه ۳۲) الزامی است.',
            'partner_chart_account_code.exists' => 'حساب انتخاب‌شده در کدینگ گروه ۳۲ یافت نشد.',
        ]);

        $this->assertUniqueCustomerParty($validated, $typeIds, $party?->id);

        return $validated;
    }

    private function assertUniqueCustomerParty(array $validated, array $typeIds, ?int $ignorePartyId = null): void
    {
        $customerTypeId = PartyType::where('name', 'customer')->value('id');

        if (! $customerTypeId || ! in_array((int) $customerTypeId, $typeIds, true)) {
            return;
        }

        app(CrmDuplicateGuardService::class)->assertUniqueCustomer([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'] ?? null,
            'email' => $validated['email'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
        ], $ignorePartyId);
    }
}
