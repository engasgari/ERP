<?php

namespace App\Http\Controllers;

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
use App\Support\PersianPdf;
use App\Support\SimpleXlsxExporter;
use App\Support\TaxElectronicBooksExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        $pdf = PersianPdf::loadView('financial-reports.print.report', $this->viewData($report, $data, $request) + [
            'company' => CompanySetting::first(),
            'forPrint' => true,
        ], 'a4', 'landscape');

        return $pdf->download($this->fileName($report, 'pdf'));
    }

    public function excel(string $report, FinancialReportRequest $request, FinancialReportService $reports)
    {
        $this->authorizeExport($report);
        $data = $reports->report($report, $request->validated());

        if ($report === 'tax-electronic-books') {
            $analysis = $data['analysis'] ?? [];

            if (! ($analysis['export_ready'] ?? false)) {
                abort(422, (string) ($analysis['export_status_message'] ?? 'خروجی دفاتر الکترونیک مالیاتی آماده نیست.'));
            }

            return TaxElectronicBooksExcelExporter::download(
                $this->fileName($report, 'xlsx'),
                $data['tax_export_rows'] ?? []
            );
        }

        if (! empty($data['export_sheets'])) {
            $sheets = collect($data['export_sheets'])->map(function (array $sheet) {
                $fields = $sheet['fields'] ?? [];
                $headings = $sheet['headings'] ?? [];
                $rows = collect($sheet['rows'] ?? [])->map(function ($row) use ($fields) {
                    if (! is_array($row)) {
                        return [$row];
                    }

                    if ($fields) {
                        return array_map(fn ($column) => $row[$column] ?? '', $fields);
                    }

                    return array_values($row);
                })->all();

                return [
                    'name' => $sheet['name'] ?? 'Sheet',
                    'headings' => $headings,
                    'rows' => $rows,
                ];
            })->all();

            return SimpleXlsxExporter::downloadMultiSheet($this->fileName($report, 'xlsx'), $sheets);
        }

        $rows = collect($data['export_rows'] ?? []);
        if ($report === 'income-statement') {
            $fields = ['section', 'code', 'title', 'amount'];
            $headings = ['بخش', 'کد', 'عنوان', 'مبلغ'];
        } else {
            $firstRow = $rows->first() ?: [];
            $fields = is_array($firstRow) ? array_keys($firstRow) : [];
            $headings = collect($data['sections'][0]['headers'] ?? [])->map(fn ($header) => (string) $header)->all();
        }

        $exportRows = $rows->map(function ($row) use ($fields) {
            if (! is_array($row)) {
                return [$row];
            }

            return array_map(fn ($column) => $row[$column] ?? '', $fields);
        })->all();

        return SimpleXlsxExporter::download(
            $this->fileName($report, 'xlsx'),
            $headings,
            $exportRows,
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
        $filters = $this->normalizedFilters($request);
        unset($filters['side']);

        return view('financial-reports.statement', [
            'summaries' => $reports->partyStatementSummaries(
                null,
                $request->integer('party_id') ?: null,
                $filters,
            ),
            'parties' => $this->statementParties(),
        ]);
    }

    public function employeeStatement(Request $request)
    {
        $query = $request->query();
        unset($query['side']);

        return redirect()->route('financial-reports.statement', $query);
    }

    public function printEmployeeStatement(Request $request, FinancialReportService $reports)
    {
        return $this->printStatement($request->merge(['side' => 'personnel']), $reports);
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
        $filters = $this->normalizedFilters($request);
        unset($filters['side']);

        $summaries = $reports->partyStatementSummaries(
            null,
            $request->integer('party_id') ?: null,
            $filters,
        );

        $reportTitle = $request->filled('party_id') ? 'صورتحساب شخص / شرکت' : 'صورتحساب اشخاص';

        return view('financial-reports.print.statement', [
            'summaries' => $summaries,
            'reportTitle' => $reportTitle,
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

    private function normalizeStatementSide(?string $side): ?string
    {
        if ($side === null || $side === '') {
            return null;
        }

        $side = strtolower(trim($side));

        return $side === 'employee' ? 'personnel' : $side;
    }

    private function statementParties()
    {
        return Party::query()->with('types')->orderBy('name')->get();
    }
}
