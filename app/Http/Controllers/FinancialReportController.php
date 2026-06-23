<?php

namespace App\Http\Controllers;

use App\Exports\FinancialReportExport;
use App\Http\Requests\FinancialReportRequest;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\CostCenter;
use App\Models\CompanySetting;
use App\Models\FiscalYear;
use App\Models\Party;
use App\Models\Project;
use App\Services\FinancialReportService;
use App\Support\FinancialReportContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class FinancialReportController extends Controller
{
    public function index(FinancialReportService $reports)
    {
        return redirect()->route('management-reports.index', ['tab' => 'financial']);
    }

    public function show(string $report, FinancialReportRequest $request, FinancialReportService $reports)
    {
        $this->authorizeReport($report);
        $data = $reports->report($report, $request->validated());

        return view('financial-reports.report', $this->viewData($report, $data, $request));
    }

    public function print(string $report, FinancialReportRequest $request, FinancialReportService $reports)
    {
        $this->authorizeExport($report);
        $data = $reports->report($report, $request->validated());

        return view('financial-reports.print.report', $this->viewData($report, $data, $request) + [
            'company' => CompanySetting::first(),
            'forPrint' => true,
        ]);
    }

    public function pdf(string $report, FinancialReportRequest $request, FinancialReportService $reports)
    {
        $this->authorizeExport($report);
        $data = $reports->report($report, $request->validated());

        $pdf = Pdf::loadView('financial-reports.print.report', $this->viewData($report, $data, $request) + [
            'company' => CompanySetting::first(),
            'forPrint' => true,
            'forPdf' => true,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->fileName($report, 'pdf'));
    }

    public function excel(string $report, FinancialReportRequest $request, FinancialReportService $reports)
    {
        $this->authorizeExport($report);
        $data = $reports->report($report, $request->validated());

        $rows = collect($data['export_rows'] ?? []);
        if ($report === 'income-statement') {
            $fields = ['section', 'code', 'title', 'amount'];
            $headings = ['بخش', 'کد', 'عنوان', 'مبلغ'];
        } else {
            $firstRow = $rows->first() ?: [];
            $fields = is_array($firstRow) ? array_keys($firstRow) : [];
            $headings = collect($data['sections'][0]['headers'] ?? [])->map(fn ($header) => (string) $header)->all();
        }

        return Excel::download(
            new FinancialReportExport($fields, $headings, $rows, $data['title'] ?? $report),
            $this->fileName($report, 'xlsx')
        );
    }

    public function generalLedger(Request $request, FinancialReportService $reports)
    {
        return $this->legacyRender('general-ledger', $request, $reports);
    }

    public function trialBalance(Request $request, FinancialReportService $reports)
    {
        return $this->legacyRender('trial-balance', $request, $reports);
    }

    public function statement(Request $request, FinancialReportService $reports)
    {
        return view('financial-reports.statement', [
            'summaries' => $reports->partyStatementSummaries($request->integer('account_id') ?: null, $request->integer('party_id') ?: null, $this->normalizedFilters($request)),
            'accounts' => ChartAccount::orderBy('code')->get(),
            'parties' => Party::orderBy('name')->get(),
        ]);
    }

    public function accountStatement(Request $request, ChartAccount $account, FinancialReportService $reports)
    {
        return view('financial-reports.account-statement', [
            'summary' => $reports->accountStatementSummary($account, $this->normalizedFilters($request)),
            'account' => $account,
        ]);
    }

    public function printAccountStatement(Request $request, ChartAccount $account, FinancialReportService $reports)
    {
        return view('financial-reports.print.account-statement', [
            'summary' => $reports->accountStatementSummary($account, $this->normalizedFilters($request)),
            'account' => $account,
            'reportTitle' => 'دفتر حساب ' . $account->title,
            'backRoute' => route('financial-reports.account-statement', array_merge(['account' => $account->id], $request->query())),
        ]);
    }

    public function printStatement(Request $request, FinancialReportService $reports)
    {
        $summaries = $reports->partyStatementSummaries(
            $request->integer('account_id') ?: null,
            $request->integer('party_id') ?: null,
            $this->normalizedFilters($request)
        );

        return view('financial-reports.print.statement', [
            'summaries' => $summaries,
            'reportTitle' => $request->filled('party_id') ? 'صورتحساب شخص / شرکت' : 'صورتحساب اشخاص و شرکت‌ها',
            'backRoute' => route('financial-reports.statement', $request->query()),
        ]);
    }

    public function balanceSheet(Request $request, FinancialReportService $reports)
    {
        return $this->legacyRender('balance-sheet', $request, $reports);
    }

    public function profitAndLoss(Request $request, FinancialReportService $reports)
    {
        return $this->legacyRender('income-statement', $request, $reports);
    }

    public function aging(Request $request, FinancialReportService $reports)
    {
        return $this->legacyRender($request->get('side', 'customer') === 'supplier' ? 'accounts-payable-aging' : 'accounts-receivable-aging', $request, $reports);
    }

    private function legacyRender(string $report, Request $request, FinancialReportService $reports)
    {
        $this->authorizeReport($report);
        $data = $reports->report($report, $this->normalizedFilters($request));

        return view('financial-reports.report', $this->viewData($report, $data, $request));
    }

    private function viewData(string $report, array $data, Request $request): array
    {
        return [
            'reportKey' => $report,
            'reportTitle' => $data['title'] ?? '',
            'reportSubtitle' => $data['subtitle'] ?? '',
            'summary' => $data['summary'] ?? [],
            'sections' => $data['sections'] ?? [],
            'comparison' => $data['comparison'] ?? [],
            'charts' => $data['charts'] ?? [],
            'period' => $data['period'] ?? [],
            'filters' => $request->query(),
            'accounts' => ChartAccount::orderBy('code')->get(),
            'parties' => Party::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'fiscalYears' => FiscalYear::orderByDesc('jalali_year')->get(),
            'costCenters' => CostCenter::orderBy('name')->get(),
            'banks' => BankAccount::orderBy('bank_name')->get(),
            'cashboxes' => Cashbox::orderBy('name')->get(),
            'printUrl' => route('financial-reports.report.print', array_merge(['report' => $report], $request->query())),
            'excelUrl' => route('financial-reports.report.excel', array_merge(['report' => $report], $request->query())),
            'pdfUrl' => route('financial-reports.report.pdf', array_merge(['report' => $report], $request->query())),
        ];
    }

    private function normalizedFilters(Request $request): array
    {
        $filters = $request->query();

        foreach (['date_from', 'date_to'] as $key) {
            if (! empty($filters[$key])) {
                $filters[$key] = jalaliToGregorianDate($filters[$key]) ?: $filters[$key];
            }
        }

        return $filters;
    }

    private function authorizeReport(string $report): void
    {
        $context = new FinancialReportContext($report);
        Gate::authorize('view', $context);
    }

    private function authorizeExport(string $report): void
    {
        $context = new FinancialReportContext($report);
        Gate::authorize('export', $context);
    }

    private function fileName(string $title, string $extension): string
    {
        return 'financial-report-' . str()->slug($title, '-') . '.' . $extension;
    }
}
