<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController;
use App\Models\CompanySetting;
use App\Models\Crm\Opportunity;
use App\Models\Invoice;
use App\Services\Crm\CrmProformaAccessService;
use App\Services\Crm\CrmProformaInvoiceService;
use App\Services\InventoryPostingService;
use App\Services\InvoiceExcelTemplateService;
use App\Services\NumberingService;
use App\Support\PersianPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmProformaController extends Controller
{
    public function __construct(
        private readonly CrmProformaAccessService $access,
        private readonly CrmProformaInvoiceService $proformas,
    ) {}

    public function compose(Request $request, Opportunity $opportunity, InvoiceController $invoices): View|RedirectResponse
    {
        $this->access->authorizeOpportunity($request->user(), $opportunity);

        if ($opportunity->invoice_id) {
            return redirect()->route('crm.proforma.edit', $opportunity->invoice_id);
        }

        return view('crm.proforma.compose-embedded', array_merge(
            $invoices->newEmbeddedFormData('sale', 'proforma'),
            [
                'formAction' => route('crm.proforma.store', $opportunity),
                'draftDefaults' => [
                    'party_id' => $opportunity->party_id,
                    'project_id' => $opportunity->project_id,
                    'invoice_date' => now()->toDateString(),
                ],
                'opportunity' => $opportunity,
                'linkExistingUrl' => route('crm.proforma.link-picker', $opportunity),
            ]
        ));
    }

    public function linkPicker(Request $request, Opportunity $opportunity): View|RedirectResponse
    {
        $this->access->authorizeOpportunity($request->user(), $opportunity);

        if ($opportunity->invoice_id) {
            return redirect()->route('crm.proforma.show', $opportunity->invoice_id);
        }

        $search = trim((string) $request->query('q', ''));

        return view('crm.proforma.link-embedded', [
            'opportunity' => $opportunity->loadMissing('party'),
            'proformas' => $this->proformas->linkableProformasForOpportunity($opportunity, $search),
            'search' => $search,
            'composeUrl' => route('crm.proforma.compose', $opportunity),
            'linkAction' => route('crm.proforma.link', $opportunity),
        ]);
    }

    public function link(Request $request, Opportunity $opportunity): JsonResponse|RedirectResponse
    {
        $this->access->authorizeOpportunity($request->user(), $opportunity);

        abort_if($opportunity->invoice_id, 422, 'برای این فرصت قبلاً پیش‌فاکتور صادر شده است.');

        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        $invoice = Invoice::query()->findOrFail($validated['invoice_id']);

        try {
            $opportunity = $this->proformas->attachExistingProforma($opportunity, $invoice, $request->user());
        } catch (\InvalidArgumentException $exception) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['invoice_id' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['invoice_id' => $exception->getMessage()]);
        }

        $invoice = $opportunity->invoice()->with('party')->first() ?? $invoice->loadMissing('party');

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'پیش‌فاکتور به فرصت متصل شد.',
                'invoice' => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'type_label' => 'فروش / پیش‌فاکتور',
                    'invoice_date' => gregorianToJalaliDate($invoice->invoice_date),
                    'party_name' => $invoice->party?->name ?: ($opportunity->party?->name ?: '-'),
                    'project_number' => '-',
                    'total_amount_formatted' => formatMoney((float) $invoice->total_amount),
                    'status' => $invoice->status,
                    'settled_at' => $invoice->settled_at,
                ],
            ]);
        }

        return redirect()
            ->route('crm.proforma.show', $invoice)
            ->with('success', 'پیش‌فاکتور به فرصت متصل شد.');
    }

    public function store(
        Request $request,
        Opportunity $opportunity,
        InvoiceController $invoices,
        NumberingService $numbering,
        InventoryPostingService $inventory,
    ): JsonResponse|RedirectResponse {
        $this->access->authorizeOpportunity($request->user(), $opportunity);

        abort_if($opportunity->invoice_id, 422, 'برای این فرصت قبلاً پیش‌فاکتور صادر شده است.');

        $response = $invoices->store($request, $numbering, $inventory);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            $invoice = Invoice::query()->findOrFail($payload['invoice']['id']);
            $this->proformas->linkToOpportunity($opportunity, $invoice, $request->user());

            return response()->json([
                ...$payload,
                'message' => 'پیش‌فاکتور ثبت شد.',
            ]);
        }

        return $response;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->wantsJson()
            || str_contains((string) $request->header('Accept', ''), 'application/json');
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'accountingDocument.lines', 'inventoryDocuments.lines', 'settledBy']);

        return view('crm.proforma.show-embedded', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
        ]);
    }

    public function edit(Request $request, Invoice $invoice, InvoiceController $invoices)
    {
        $this->access->authorize($request->user(), $invoice);

        abort_unless($invoice->status === 'draft', 403, 'فقط پیش‌فاکتورهای پیش‌نویس قابل ویرایش هستند.');

        return view('crm.proforma.edit-embedded', array_merge(
            $invoices->embeddedEditFormData($invoice),
            ['formAction' => route('crm.proforma.update', $invoice)],
        ));
    }

    public function update(Request $request, Invoice $invoice, InvoiceController $invoices, InventoryPostingService $inventory)
    {
        $this->access->authorize($request->user(), $invoice);

        abort_unless($invoice->status === 'draft', 403, 'فقط پیش‌فاکتورهای پیش‌نویس قابل ویرایش هستند.');

        return $invoices->update($request, $invoice, $inventory);
    }

    public function print(Request $request, Invoice $invoice)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines', 'settledBy']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
            'routes' => [
                'show' => route('crm.proforma.show', $invoice),
                'pdf' => route('crm.proforma.pdf', $invoice),
                'excel' => route('crm.proforma.excel', $invoice),
            ],
        ]);
    }

    public function downloadPdf(Request $request, Invoice $invoice)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines', 'settledBy']);

        $pdf = PersianPdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
            'routes' => [
                'show' => route('crm.proforma.show', $invoice),
                'pdf' => route('crm.proforma.pdf', $invoice),
                'excel' => route('crm.proforma.excel', $invoice),
            ],
        ], 'a4', 'landscape');

        return $pdf->download('proforma-'.$invoice->number.'.pdf');
    }

    public function downloadExcel(Request $request, Invoice $invoice, InvoiceExcelTemplateService $excel)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party', 'project', 'lines.item.unit']);
        $path = $excel->build($invoice, CompanySetting::first());
        $filename = 'proforma-'.preg_replace('/[^\w\-]+/u', '_', (string) $invoice->number).'.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
