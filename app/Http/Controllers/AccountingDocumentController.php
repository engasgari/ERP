<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingDocumentRequest;
use App\Models\AccountingDocument;
use App\Models\ChartAccount;
use App\Models\Party;
use App\Models\Project;
use App\Repositories\AccountingDocumentRepository;
use App\Services\AccountingPostingService;
use App\Services\NumberingService;
use App\Support\PersianPdf;
use Illuminate\Http\Request;
use RuntimeException;

class AccountingDocumentController extends Controller
{
    public function __construct(
        private AccountingDocumentRepository $documents,
        private AccountingPostingService $posting
    ) {
    }

    public function index()
    {
        return view('accounting-documents.index');
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

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $document->getKey()])
            ->with('success', 'سند حسابداری ذخیره شد.');
    }

    public function show(Request $request, AccountingDocument $accountingDocument)
    {
        $documentId = $this->resolveDocumentId($request, $accountingDocument);
        $document = AccountingDocument::query()
            ->with(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount', 'audits'])
            ->findOrFail($documentId);

        return view('accounting-documents.show', [
            'document' => $document,
            'documentId' => $documentId,
        ]);
    }

    public function print(Request $request, AccountingDocument $accountingDocument)
    {
        $documentId = $this->resolveDocumentId($request, $accountingDocument);
        $document = AccountingDocument::query()
            ->with(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount'])
            ->findOrFail($documentId);

        return view('accounting-documents.print', [
            'document' => $document,
            'documentId' => $documentId,
        ]);
    }

    public function pdf(Request $request, AccountingDocument $accountingDocument)
    {
        $documentId = $this->resolveDocumentId($request, $accountingDocument);
        $document = AccountingDocument::query()
            ->with(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project', 'lines.bankAccount'])
            ->findOrFail($documentId);

        $pdf = PersianPdf::loadView('accounting-documents.print', [
            'document' => $document,
            'documentId' => $documentId,
        ], 'a4', 'landscape');

        return $pdf->download('accounting-document-' . $document->number . '.pdf');
    }

    public function edit(AccountingDocument $accountingDocument)
    {
        if ($redirect = $this->redirectIfPosted($accountingDocument)) {
            return $redirect;
        }

        return view('accounting-documents.form', $this->formData($accountingDocument->load(['lines.account', 'lines.detailAccount', 'lines.party', 'lines.project'])));
    }

    public function update(StoreAccountingDocumentRequest $request, AccountingDocument $accountingDocument)
    {
        if ($redirect = $this->redirectIfPosted($accountingDocument)) {
            return $redirect;
        }

        if ($accountingDocument->is_automatic) {
            $this->posting->updateAutomatic(
                $accountingDocument,
                $request->safe()->except('lines'),
                $request->validated('lines'),
                $request->user()->id
            );

            return redirect()
                ->route('accounting-documents.show', ['accountingDocument' => $accountingDocument->getKey()])
                ->with('success', 'سند سیستمی ویرایش شد. برای اعمال در گزارش‌ها آن را ثبت قطعی کنید.');
        }

        $this->posting->updateManual(
            $accountingDocument,
            $request->safe()->except('lines'),
            $request->validated('lines'),
            $request->user()->id
        );

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $accountingDocument->getKey()])
            ->with('success', 'سند حسابداری ویرایش شد.');
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
        $documentId = $this->resolveDocumentId($request, $accountingDocument);
        $document = AccountingDocument::query()->findOrFail($documentId);
        $this->posting->post($document, $request->user()->id);

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $documentId])
            ->with('success', 'سند حسابداری ثبت قطعی شد.');
    }

    public function unpost(Request $request, AccountingDocument $accountingDocument)
    {
        $documentId = $this->resolveDocumentId($request, $accountingDocument);
        $document = AccountingDocument::query()->findOrFail($documentId);

        if ($redirect = $this->redirectIfNotPosted($document, 'فقط اسناد ثبت قطعی را می‌توان به پیش‌نویس برگرداند.')) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfVoided($document)) {
            return $redirect;
        }

        $this->posting->unpost($document, $request->user()->id);

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $documentId])
            ->with('success', 'سند حسابداری به حالت پیش‌نویس برگشت و اکنون قابل ویرایش است.');
    }

    /**
     * Capture the path id up front — never rebuild URLs via route($model)
     * when the route-key may be unavailable (UrlGenerationException).
     */
    private function resolveDocumentId(Request $request, AccountingDocument $accountingDocument): int
    {
        $routeParam = $request->route('accountingDocument');
        $documentId = (int) ($accountingDocument->getKey()
            ?: (is_numeric($routeParam) ? $routeParam : null)
            ?: $request->segment(2));

        if ($documentId <= 0) {
            abort(404);
        }

        return $documentId;
    }

    private function formData(AccountingDocument $document): array
    {
        $usedAccountIds = $document->relationLoaded('lines')
            ? $document->lines->pluck('chart_account_id')->merge($document->lines->pluck('detail_account_id'))->filter()->unique()->all()
            : [];

        $suggestedNumber = null;

        if (! $document->exists) {
            try {
                $suggestedNumber = app(NumberingService::class)->peek('accounting_document', 'ACC-');
            } catch (RuntimeException) {
                $suggestedNumber = null;
            }
        }

        return [
            'document' => $document,
            'accounts' => ChartAccount::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $usedAccountIds))
                ->orderBy('code')
                ->get(),
            'parties' => Party::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'suggestedNumber' => $suggestedNumber,
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
            ->route('accounting-documents.show', ['accountingDocument' => $document->getKey()])
            ->with('error', 'این سند حسابداری به صورت خودکار توسط سیستم ثبت شده و از این صفحه قابل حذف نیست.')
            ->with('error_details', [
                'سند حسابداری: ' . $document->number,
                'منبع مرتبط: ' . ($document->source_type ? class_basename($document->source_type) . ' #' . $document->source_id : 'ثبت سیستمی'),
                'برای حذف، سند مادر را حذف کنید.',
            ]);
    }

    private function redirectIfPosted(AccountingDocument $document)
    {
        if ($document->status !== 'posted') {
            return null;
        }

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $document->getKey()])
            ->with('error', 'این سند حسابداری ثبت قطعی شده است. برای ویرایش یا حذف، ابتدا آن را به پیش‌نویس برگردانید.');
    }

    private function redirectIfNotPosted(AccountingDocument $document, string $message)
    {
        if ($document->status === 'posted') {
            return null;
        }

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $document->getKey()])
            ->with('error', $message);
    }

    private function redirectIfVoided(AccountingDocument $document)
    {
        if ($document->voided_at === null) {
            return null;
        }

        return redirect()
            ->route('accounting-documents.show', ['accountingDocument' => $document->getKey()])
            ->with('error', 'سند باطل‌شده قابل برگشت به پیش‌نویس نیست.');
    }
}
