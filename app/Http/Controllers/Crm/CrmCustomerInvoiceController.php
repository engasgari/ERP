<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Services\Crm\CrmCustomerInvoiceAccessService;
use App\Services\InventoryPostingService;
use App\Services\InvoiceExcelPdfService;
use App\Services\InvoiceExcelTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmCustomerInvoiceController extends Controller
{
    public function __construct(
        private readonly CrmCustomerInvoiceAccessService $access,
    ) {}

    public function show(Request $request, Invoice $invoice)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party.types', 'project', 'warehouse', 'lines.item.unit', 'accountingDocument.lines', 'inventoryDocuments.lines', 'settledBy']);

        return view('crm.invoices.show-embedded', [
            'invoice' => $invoice,
            'company' => CompanySetting::first(),
        ]);
    }

    public function print(Request $request, Invoice $invoice, InvoiceExcelTemplateService $excel)
    {
        // Final print always serves the official Excel template (exact layout, no converter hang).
        return $this->downloadExcel($request, $invoice, $excel);
    }

    public function downloadPdf(Request $request, Invoice $invoice, InvoiceExcelPdfService $excelPdf)
    {
        $this->access->authorize($request->user(), $invoice);

        $artifact = $excelPdf->buildPrintArtifact($invoice, CompanySetting::first());

        return response()->download($artifact['path'], $artifact['filename'], [
            'Content-Type' => $artifact['mime'],
        ])->deleteFileAfterSend(true);
    }

    public function downloadExcel(Request $request, Invoice $invoice, InvoiceExcelTemplateService $excel)
    {
        $this->access->authorize($request->user(), $invoice);

        $invoice->load(['party', 'project', 'lines.item.unit']);
        $path = $excel->build($invoice, CompanySetting::first());
        $filename = 'invoice-'.preg_replace('/[^\w\-]+/u', '_', (string) $invoice->number).'.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function edit(Request $request, Invoice $invoice, InvoiceController $invoices): View
    {
        $this->access->authorize($request->user(), $invoice);

        abort_unless(
            $invoice->document_type === 'proforma' && $invoice->status === 'draft',
            403,
            'فقط پیش‌فاکتورهای پیش‌نویس قابل ویرایش هستند.'
        );

        return view('crm.proforma.edit-embedded', array_merge(
            $invoices->embeddedEditFormData($invoice),
            ['formAction' => route('crm.invoices.update', $invoice)],
        ));
    }

    public function update(
        Request $request,
        Invoice $invoice,
        InvoiceController $invoices,
        InventoryPostingService $inventory,
    ): JsonResponse|RedirectResponse {
        $this->access->authorize($request->user(), $invoice);

        abort_unless(
            $invoice->document_type === 'proforma' && $invoice->status === 'draft',
            403,
            'فقط پیش‌فاکتورهای پیش‌نویس قابل ویرایش هستند.'
        );

        return $invoices->update($request, $invoice, $inventory);
    }
}
