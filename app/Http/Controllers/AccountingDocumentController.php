<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingDocumentRequest;
use App\Models\AccountingDocument;
use App\Models\ChartAccount;
use App\Models\Party;
use App\Models\Project;
use App\Repositories\AccountingDocumentRepository;
use App\Services\AccountingPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AccountingDocumentController extends Controller
{
    public function __construct(
        private AccountingDocumentRepository $documents,
        private AccountingPostingService $posting
    ) {
    }

    public function index(Request $request)
    {
        return view('accounting-documents.index', [
            'documents' => $this->documents->paginate($this->normalizedFilters($request)),
        ]);
    }

    public function create()
    {
        return view('accounting-documents.form', $this->formData(new AccountingDocument()));
    }

    public function store(StoreAccountingDocumentRequest $request)
    {
        $document = $this->posting->createManual(
            $request->safe()->except('lines'),
            $request->validated('lines'),
            $request->user()->id
        );

        return redirect()->route('accounting-documents.show', $document)->with('success', 'سند حسابداری ذخیره شد.');
    }

    public function show(AccountingDocument $accountingDocument)
    {
        return view('accounting-documents.show', [
            'document' => $accountingDocument->load(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount', 'audits']),
        ]);
    }

    public function print(AccountingDocument $accountingDocument)
    {
        return view('accounting-documents.print', [
            'document' => $accountingDocument->load(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount']),
        ]);
    }

    public function pdf(AccountingDocument $accountingDocument)
    {
        $pdf = Pdf::loadView('accounting-documents.print', [
            'document' => $accountingDocument->load(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount']),
            'forPdf' => true,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('accounting-document-' . $accountingDocument->number . '.pdf');
    }

    public function edit(AccountingDocument $accountingDocument)
    {
        if ($redirect = $this->redirectIfPosted($accountingDocument)) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfAutomatic($accountingDocument)) {
            return $redirect;
        }

        return view('accounting-documents.form', $this->formData($accountingDocument->load(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project'])));
    }

    public function update(StoreAccountingDocumentRequest $request, AccountingDocument $accountingDocument)
    {
        if ($redirect = $this->redirectIfPosted($accountingDocument)) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfAutomatic($accountingDocument)) {
            return $redirect;
        }

        $this->posting->updateManual(
            $accountingDocument,
            $request->safe()->except('lines'),
            $request->validated('lines'),
            $request->user()->id
        );

        return redirect()->route('accounting-documents.show', $accountingDocument)->with('success', 'سند حسابداری ویرایش شد.');
    }

    public function destroy(Request $request, AccountingDocument $accountingDocument)
    {
        if ($redirect = $this->redirectIfPosted($accountingDocument)) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfAutomatic($accountingDocument)) {
            return $redirect;
        }

        $accountingDocument->delete();

        return redirect()->route('accounting-documents.index')->with('success', 'سند حسابداری حذف شد.');
    }

    public function post(Request $request, AccountingDocument $accountingDocument)
    {
        $this->posting->post($accountingDocument, $request->user()->id);

        return back()->with('success', 'سند حسابداری ثبت قطعی شد.');
    }

    public function unpost(Request $request, AccountingDocument $accountingDocument)
    {
        if ($redirect = $this->redirectIfPosted($accountingDocument)) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfAutomatic($accountingDocument)) {
            return $redirect;
        }

        $this->posting->unpost($accountingDocument, $request->user()->id);

        return back()->with('success', 'سند حسابداری به حالت پیش‌نویس برگشت.');
    }

    private function formData(AccountingDocument $document): array
    {
        return [
            'document' => $document,
            'accounts' => ChartAccount::where('is_active', true)->orderBy('code')->get(),
            'parties' => Party::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ];
    }

    private function normalizedFilters(Request $request): array
    {
        $filters = $request->query();

        foreach (['date_from', 'date_to'] as $key) {
            if (!empty($filters[$key])) {
                $filters[$key] = jalaliToGregorianDate($filters[$key]) ?: $filters[$key];
            }
        }

        return $filters;
    }

    private function redirectIfAutomatic(AccountingDocument $document)
    {
        if (! $document->is_automatic) {
            return null;
        }

        return redirect()
            ->route('accounting-documents.show', $document)
            ->with('error', 'این سند حسابداری به صورت خودکار توسط سیستم ثبت شده و از این صفحه قابل ویرایش یا حذف نیست.')
            ->with('error_details', [
                'سند حسابداری: ' . $document->number,
                'منبع مرتبط: ' . ($document->source_type ? class_basename($document->source_type) . ' #' . $document->source_id : 'ثبت سیستمی'),
                'برای تغییر یا حذف، سند مادر را بررسی کنید.',
            ]);
    }

    private function redirectIfPosted(AccountingDocument $document)
    {
        if ($document->status !== 'posted') {
            return null;
        }

        return redirect()
            ->route('accounting-documents.show', $document)
            ->with('error', 'این سند حسابداری ثبت قطعی شده و ویرایش، حذف یا بازگشت به پیش‌نویس برای آن مجاز نیست.');
    }
}
