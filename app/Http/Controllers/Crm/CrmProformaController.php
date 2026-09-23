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
            ]
        ));
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
        ]);
    }

    public function downloadPdf(Request $request, Invoice $invoice)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'inventoryDocuments.lines', 'settledBy']);

        $pdf = PersianPdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
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
