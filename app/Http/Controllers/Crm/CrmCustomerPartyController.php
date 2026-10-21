<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PartyController;
use App\Models\Party;
use App\Models\PartyType;
use App\Services\Crm\CrmCustomerService;
use App\Services\NumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM entry-point for the shared ERP party create/edit form.
 * Keeps the user inside the CRM app context (active.crm).
 */
class CrmCustomerPartyController extends Controller
{
    public function create(Request $request, PartyController $parties): View
    {
        PartyType::ensureDefaults();
        $customerTypeId = (int) PartyType::where('name', 'customer')->value('id');

        $selectedTypeIds = array_map('intval', (array) $request->old('types', []));
        if ($customerTypeId > 0 && ! in_array($customerTypeId, $selectedTypeIds, true)) {
            $selectedTypeIds[] = $customerTypeId;
        }

        return $parties->createFormView(
            new Party(['kind' => 'person', 'is_active' => true]),
            selectedTypeIds: $selectedTypeIds,
            formAction: route('crm.customers.store'),
            cancelUrl: route('crm.customers.index'),
            pageTitle: 'تعریف مشتری جدید',
            lockCustomerType: true,
            embedded: $request->boolean('embedded') || $request->headers->get('Sec-Fetch-Dest') === 'iframe',
        );
    }

    public function store(Request $request, NumberingService $numbering, CrmCustomerService $customers, PartyController $parties): RedirectResponse|JsonResponse
    {
        PartyType::ensureDefaults();
        $customerTypeId = (int) PartyType::where('name', 'customer')->value('id');

        // Force customer type for CRM-created parties.
        if ($customerTypeId > 0) {
            $types = array_map('intval', (array) $request->input('types', []));
            if (! in_array($customerTypeId, $types, true)) {
                $types[] = $customerTypeId;
            }
            $request->merge(['types' => $types]);
        }

        $party = $parties->storeParty($request, $numbering);

        $customers->ensureProfile($party, $request->user());

        if ($this->wantsJson($request)) {
            return $parties->partyJsonPayload($party->fresh(['types', 'crmProfile.assignedUser']), 'مشتری ثبت شد.');
        }

        return redirect()
            ->route('crm.customers.show', ['party' => $party->id, 'tab' => 'contacts'])
            ->with('success', 'مشتری ثبت شد. می‌توانید مخاطب تعریف کنید.');
    }

    public function edit(Request $request, Party $party, PartyController $parties): View
    {
        $this->ensureCustomerParty($party);

        return $parties->createFormView(
            $party->load(['types', 'crmProfile']),
            formAction: route('crm.customers.update', $party),
            cancelUrl: route('crm.customers.show', $party),
            pageTitle: 'ویرایش مشتری',
            lockCustomerType: true,
            embedded: $request->boolean('embedded') || $request->headers->get('Sec-Fetch-Dest') === 'iframe',
        );
    }

    public function update(
        Request $request,
        Party $party,
        CrmCustomerService $customers,
        PartyController $parties,
    ): RedirectResponse|JsonResponse {
        $this->ensureCustomerParty($party);

        PartyType::ensureDefaults();
        $customerTypeId = (int) PartyType::where('name', 'customer')->value('id');

        if ($customerTypeId > 0) {
            $types = array_map('intval', (array) $request->input('types', []));
            if (! in_array($customerTypeId, $types, true)) {
                $types[] = $customerTypeId;
            }
            $request->merge(['types' => $types]);
        }

        $party = $parties->updateParty($request, $party);
        $customers->ensureProfile($party, $request->user());

        if ($this->wantsJson($request)) {
            return $parties->partyJsonPayload($party, 'مشتری ویرایش شد.');
        }

        return redirect()
            ->route('crm.customers.show', $party)
            ->with('success', 'مشتری ویرایش شد.');
    }

    private function ensureCustomerParty(Party $party): void
    {
        PartyType::ensureDefaults();
        $customerTypeId = (int) PartyType::where('name', 'customer')->value('id');

        abort_unless(
            $customerTypeId > 0 && $party->types()->where('party_types.id', $customerTypeId)->exists(),
            404
        );
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->wantsJson()
            || str_contains((string) $request->header('Accept', ''), 'application/json');
    }
}
