<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesReportRequest;
use App\Models\CompanySetting;
use App\Services\SalesReportService;
use App\Support\PersianPdf;
use App\Support\SimpleXlsxExporter;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    private const REPORTS = [
        'dashboard' => 'داشبورد فروش',
        'sales' => 'گزارش اصلی فروش',
        'by-customer' => 'فروش به تفکیک مشتری',
        'by-product' => 'فروش به تفکیک محصول',
        'receivables' => 'مطالبات',
        'salesperson' => 'عملکرد فروشندگان',
        'profitability' => 'سود و حاشیه سود',
        'returns-discounts' => 'برگشت و تخفیفات',
    ];

    public function index()
    {
        return redirect()->route('sales.intelligence');
    }

    public function show(string $report, SalesReportRequest $request, SalesReportService $reports)
    {
        if ($report === 'dashboard') {
            return redirect()->route('sales.intelligence');
        }

        $this->ensureReportExists($report, $reports);

        $data = $reports->report($report, $request->validated());

        return view('sales-reports.report', $this->viewData($report, $data, $request, $reports));
    }

    public function print(string $report, SalesReportRequest $request, SalesReportService $reports)
    {
        $this->authorizeExport($request);
        $this->ensureReportExists($report, $reports);

        $data = $reports->report($report, $request->validated());

        return view('sales-reports.print.report', $this->viewData($report, $data, $request, $reports) + [
            'company' => CompanySetting::first(),
            'forPrint' => true,
        ]);
    }

    public function pdf(string $report, SalesReportRequest $request, SalesReportService $reports)
    {
        $this->authorizeExport($request);
        $this->ensureReportExists($report, $reports);

        $data = $reports->report($report, $request->validated());

        $pdf = PersianPdf::loadView('sales-reports.print.report', $this->viewData($report, $data, $request, $reports) + [
            'company' => CompanySetting::first(),
            'forPrint' => true,
        ], 'a4', 'landscape');

        return $pdf->download($this->fileName($report, 'pdf'));
    }

    public function excel(string $report, SalesReportRequest $request, SalesReportService $reports)
    {
        $this->authorizeExport($request);
        $this->ensureReportExists($report, $reports);

        $data = $reports->report($report, $request->validated());
        $rows = collect($data['export_rows'] ?? []);
        $section = $data['sections'][0] ?? [];
        $fields = $section['columns'] ?? [];
        $headings = $section['headers'] ?? [];

        if ($fields === [] && $rows->isNotEmpty()) {
            $fields = array_keys((array) $rows->first());
            $headings = $fields;
        }

        $exportRows = $rows->map(function ($row) use ($fields) {
            if (! is_array($row)) {
                return [$row];
            }

            return array_map(function ($column) use ($row) {
                $value = $row[$column] ?? '';
                if (is_numeric($value) && str_contains($column, 'amount') || in_array($column, ['profit', 'cogs_amount', 'net_amount', 'gross_amount', 'paid_amount', 'outstanding_amount', 'total_amount', 'tax_amount', 'discount_amount', 'quantity_sold', 'quantity', 'value'], true)) {
                    return (float) $value;
                }

                return $value;
            }, $fields);
        })->all();

        return SimpleXlsxExporter::download(
            $this->fileName($report, 'xlsx'),
            $headings,
            $exportRows,
        );
    }

    private function viewData(string $report, array $data, Request $request, SalesReportService $reports): array
    {
        $options = $reports->filterOptions();

        return [
            'reportKey' => $report,
            'reportTitle' => $data['title'] ?? (self::REPORTS[$report] ?? $report),
            'reportSubtitle' => $data['subtitle'] ?? '',
            'summary' => $data['summary'] ?? [],
            'summary_labels' => $data['summary_labels'] ?? [],
            'fiscal_year' => $data['fiscal_year'] ?? null,
            'totals' => $data['totals'] ?? [],
            'sections' => $data['sections'] ?? [],
            'comparison' => $data['comparison'] ?? [],
            'charts' => $data['charts'] ?? [],
            'tab' => $data['tab'] ?? $request->query('tab', 'discounts'),
            'groupBy' => $data['group_by'] ?? $request->query('group_by', 'invoice'),
            'hasValidCogs' => $reports->hasValidCogs(),
            'reportsNav' => $this->navigationReports($reports),
            'filters' => $request->query(),
            'parties' => $options['parties'],
            'projects' => $options['projects'],
            'items' => $options['items'],
            'categories' => $options['categories'],
            'salespeople' => $options['salespeople'],
            'fiscalYears' => $options['fiscal_years'],
            'printUrl' => route('sales-reports.report.print', array_merge(['report' => $report], $request->query())),
            'excelUrl' => route('sales-reports.report.excel', array_merge(['report' => $report], $request->query())),
            'pdfUrl' => route('sales-reports.report.pdf', array_merge(['report' => $report], $request->query())),
        ];
    }

    private function navigationReports(SalesReportService $reports): array
    {
        $nav = [];
        foreach (self::REPORTS as $key => $label) {
            if ($key === 'profitability' && ! $reports->hasValidCogs()) {
                continue;
            }
            $nav[] = ['key' => $key, 'label' => $label];
        }

        return $nav;
    }

    private function ensureReportExists(string $report, SalesReportService $reports): void
    {
        if (! array_key_exists($report, self::REPORTS)) {
            abort(404);
        }

        if ($report === 'profitability' && ! $reports->hasValidCogs()) {
            abort(404);
        }
    }

    private function authorizeExport(SalesReportRequest $request): void
    {
        if (! $request->user()?->hasPermission('reports.sales.export')
            && ! $request->user()?->hasPermission('reports.view')
            && ! $request->user()?->hasPermission('commerce.manage')) {
            abort(403);
        }
    }

    private function fileName(string $report, string $extension): string
    {
        return 'sales-report-' . str()->slug($report, '-') . '.' . $extension;
    }
}
