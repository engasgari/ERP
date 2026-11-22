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

    public function print(Request $request, Invoice $invoice, InvoiceExcelPdfService $excelPdf, InvoiceExcelTemplateService $excel)
    {
        $this->access->authorize($request->user(), $invoice);
        $company = CompanySetting::first();

        try {
            $pdfPath = $excelPdf->build($invoice, $company);
            $pdfBase64 = base64_encode((string) file_get_contents($pdfPath));
            @unlink($pdfPath);

            return view('invoices.print-preview', [
                'invoice' => $invoice,
                'pdfBase64' => $pdfBase64,
                'backUrl' => route('crm.invoices.show', $invoice),
            ]);
        } catch (\Throwable) {
            return view('invoices.print', [
                'invoice' => $invoice,
                'company' => $company,
                'print' => $excel->printData($invoice, $company),
                'routes' => [
                    'show' => route('crm.invoices.show', $invoice),
                    'pdf' => route('crm.invoices.pdf', $invoice),
                    'excel' => route('crm.invoices.excel', $invoice),
                ],
            ]);
        }
    }

    public function downloadPdf(Request $request, Invoice $invoice, InvoiceExcelPdfService $excelPdf)
    {
        $this->access->authorize($request->user(), $invoice);

        $path = $excelPdf->build($invoice, CompanySetting::first());
        $filename = 'invoice-'.preg_replace('/[^\w\-]+/u', '_', (string) $invoice->number).'.pdf';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
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
