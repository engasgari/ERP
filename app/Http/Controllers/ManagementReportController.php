<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmploymentOrder;
use App\Models\Payment;
use App\Models\MonthlyAttendance;
use App\Models\PayrollCalculation;
use App\Models\PayrollCalculationLine;
use App\Models\InsuranceRecord;
use App\Models\TaxRecord;
use App\Models\Payslip;
use App\Models\Project;
use App\Models\Salary;
use App\Models\Warehouse;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;

class ManagementReportController extends Controller
{
    public function index(): View
    {
        $groups = [
            [
                'title' => 'گزارش‌های مالی',
                'description' => 'ترازها، سود و زیان، جریان نقد و تحلیل درآمد و هزینه',
                'reports' => [
                    [
                        'title' => 'تراز آزمایشی مالی',
                        'description' => 'نمای بدهکار و بستانکار کل پروژه‌ها، حقوق، هزینه‌ها و درآمدها',
                        'route' => route('management-reports.trial-balance'),
                        'status' => 'آماده',
                    ],
                ],
            ],
            [
                'title' => 'گزارش‌های انبار',
                'description' => 'موجودی، گردش کالا، مصرف پروژه و ارزش ریالی انبار',
                'reports' => [
                    [
                        'title' => 'کاردکس انبار',
                        'description' => 'گردش ورود و خروج کالا با مانده تعدادی و ریالی',
                        'route' => route('management-reports.warehouse-cardex'),
                        'status' => 'آماده',
                    ],
                    [
                        'title' => 'موجودی انبار',
                        'description' => 'مانده موجودی کالاها بر اساس انبار، دسته و پروژه',
                        'route' => route('management-reports.warehouse-inventory'),
                        'status' => 'آماده',
                    ],
                ],
            ],
            [
                'title' => 'گزارش‌های فروش',
                'description' => 'درآمد پروژه‌ها، فروش خدمات/محصولات و روندهای فروش',
                'reports' => [],
            ],
            [
                'title' => 'گزارش‌های مدیریتی',
                'description' => 'داشبوردهای خلاصه، سودآوری پروژه‌ها و شاخص‌های کلیدی',
                'reports' => [],
            ],
        ];

        $groups[] = [
            'title' => 'گزارش‌های منابع انسانی',
            'description' => 'پرسنل، سوابق پرسنلی و احکام کارگزینی',
            'reports' => [
                ['title' => 'لیست پرسنل', 'description' => 'گزارش پرسنل فعال و غیرفعال', 'route' => route('management-reports.hr-employees'), 'status' => 'آماده'],
                ['title' => 'سوابق پرسنلی', 'description' => 'تاریخچه احکام و تغییرات پرسنل', 'route' => route('management-reports.hr-history'), 'status' => 'آماده'],
                ['title' => 'احکام کارگزینی', 'description' => 'فهرست احکام و وضعیت تایید', 'route' => route('management-reports.hr-employment-orders'), 'status' => 'آماده'],
            ],
        ];

        $groups = array_merge($groups, $this->newPayrollReportGroups());

        return view('management-reports.index', compact('groups'));
    }

    public function trialBalance(FinancialReportService $reports): View
    {
        return view('management-reports.trial-balance', $this->trialBalanceData($reports));
    }

    public function warehouseCardex(Request $request): View
    {
        return view('management-reports.warehouse-cardex', $this->warehouseCardexData($request));
    }

    public function warehouseInventory(Request $request): View
    {
        return view('management-reports.warehouse-inventory', $this->warehouseInventoryData($request));
    }

    public function hrEmployees(Request $request): View
    {
        return view('management-reports.hr-employees', $this->hrEmployeesData($request));
    }

    public function hrHistory(Request $request): View
    {
        return view('management-reports.hr-history', $this->hrHistoryData($request));
    }

    public function hrEmploymentOrders(Request $request): View
    {
        return view('management-reports.hr-employment-orders', $this->hrEmploymentOrdersData($request));
    }

    public function attendanceMonthly(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'گزارش کارکرد ماهانه',
            'headers' => ['پرسنل', 'دوره', 'کار عادی', 'اضافه‌کاری', 'تاخیر', 'تعجیل', 'غیبت', 'مرخصی', 'ماموریت'],
            'rows' => MonthlyAttendance::with(['employee.party', 'period'])->latest()->paginate(100)->withQueryString(),
            'mapper' => fn ($row) => [$row->employee->full_name, $row->period->persian_title, $row->normal_hours, $row->overtime_hours, $row->delay_hours, $row->early_leave_hours, $row->absence_hours, $row->leave_hours, $row->mission_hours],
        ]);
    }

    public function attendanceExceptions(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'گزارش اضافه‌کاری، تاخیر و غیبت',
            'headers' => ['پرسنل', 'دوره', 'اضافه‌کاری', 'تاخیر', 'تعجیل', 'غیبت', 'شب‌کاری', 'تعطیل‌کاری'],
            'rows' => MonthlyAttendance::with(['employee.party', 'period'])
                ->where(fn ($query) => $query->where('overtime_hours', '>', 0)->orWhere('delay_hours', '>', 0)->orWhere('absence_hours', '>', 0))
                ->latest()->paginate(100)->withQueryString(),
            'mapper' => fn ($row) => [$row->employee->full_name, $row->period->persian_title, $row->overtime_hours, $row->delay_hours, $row->early_leave_hours, $row->absence_hours, $row->night_hours, $row->holiday_hours],
        ]);
    }

    public function payrollSummary(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'گزارش خلاصه حقوق',
            'headers' => ['پرسنل', 'دوره', 'ناخالص', 'مزایا', 'بیمه', 'مالیات', 'کسورات', 'خالص پرداختی'],
            'rows' => PayrollCalculation::with(['employee.party', 'period'])->latest()->paginate(100)->withQueryString(),
            'mapper' => fn ($row) => [$row->employee->full_name, $row->period->persian_title, number_format((float) $row->gross_salary), number_format((float) $row->total_benefits), number_format((float) $row->insurance_employee), number_format((float) $row->tax_amount), number_format((float) $row->total_deductions), number_format((float) $row->net_payable)],
        ]);
    }

    public function payrollRegister(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'گزارش ریز حقوق',
            'headers' => ['پرسنل', 'دوره', 'شرح', 'نوع', 'ساعت', 'نرخ', 'مبلغ'],
            'rows' => PayrollCalculationLine::with(['calculation.employee.party', 'calculation.period'])->latest()->paginate(150)->withQueryString(),
            'mapper' => fn ($row) => [$row->calculation->employee->full_name, $row->calculation->period->persian_title, $row->title, $row->type === 'earning' ? 'مزایا' : 'کسورات', $row->hours, number_format((float) $row->rate), number_format((float) $row->amount)],
        ]);
    }

    public function payslipArchive(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'آرشیو فیش حقوقی',
            'headers' => ['شماره فیش', 'پرسنل', 'دوره', 'تاریخ صدور', 'وضعیت', 'لینک'],
            'rows' => Payslip::with(['employee.party', 'period'])->latest()->paginate(100)->withQueryString(),
            'mapper' => fn ($row) => [$row->number, $row->employee->full_name, $row->period->persian_title, formatJalaliDateSafe($row->issued_at), $row->status, '<a class="text-blue-600" href="' . route('payslips.print', $row) . '">چاپ</a>'],
            'raw' => true,
        ]);
    }

    public function insuranceSummary(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'گزارش بیمه',
            'headers' => ['پرسنل', 'دوره', 'روز بیمه', 'مزد مشمول', 'سهم کارمند', 'سهم کارفرما', 'بیمه بیکاری'],
            'rows' => InsuranceRecord::with(['employee.party', 'period'])->latest()->paginate(100)->withQueryString(),
            'mapper' => fn ($row) => [$row->employee->full_name, $row->period->persian_title, $row->insurance_days, number_format((float) $row->insurance_wage), number_format((float) $row->employee_share), number_format((float) $row->employer_share), number_format((float) $row->unemployment_share)],
        ]);
    }

    public function taxSummary(Request $request): View
    {
        return view('management-reports.generic', [
            'title' => 'گزارش مالیات',
            'headers' => ['پرسنل', 'دوره', 'درآمد مشمول', 'معافیت', 'مالیات'],
            'rows' => TaxRecord::with(['employee.party', 'period'])->latest()->paginate(100)->withQueryString(),
            'mapper' => fn ($row) => [$row->employee->full_name, $row->period->persian_title, number_format((float) $row->taxable_income), number_format((float) $row->exemption_amount), number_format((float) $row->tax_amount)],
        ]);
    }

    private function newPayrollReportGroups(): array
    {
        return [
            [
                'title' => 'حضور و غیاب',
                'description' => 'کارکرد ماهانه، اضافه‌کاری، تاخیر، غیبت، مرخصی و ماموریت',
                'reports' => [
                    ['title' => 'کارکرد ماهانه', 'description' => 'خلاصه پردازش کارکرد ماهانه موتور جدید', 'route' => route('management-reports.attendance-monthly'), 'status' => 'آماده'],
                    ['title' => 'اضافه‌کاری و تاخیر', 'description' => 'تحلیل اضافه‌کاری، تاخیر، تعجیل و غیبت', 'route' => route('management-reports.attendance-exceptions'), 'status' => 'آماده'],
                ],
            ],
            [
                'title' => 'حقوق و دستمزد',
                'description' => 'خلاصه حقوق، ریز حقوق و آرشیو فیش',
                'reports' => [
                    ['title' => 'خلاصه حقوق', 'description' => 'جمع حقوق ناخالص، کسورات و خالص پرداختی', 'route' => route('management-reports.payroll-summary'), 'status' => 'آماده'],
                    ['title' => 'ریز حقوق', 'description' => 'لیست تفصیلی محاسبات حقوق پرسنل', 'route' => route('management-reports.payroll-register'), 'status' => 'آماده'],
                    ['title' => 'آرشیو فیش حقوقی', 'description' => 'فیش‌های صادرشده از موتور جدید', 'route' => route('management-reports.payslip-archive'), 'status' => 'آماده'],
                ],
            ],
            [
                'title' => 'بیمه',
                'description' => 'مزد مشمول بیمه و سهم کارمند/کارفرما',
                'reports' => [
                    ['title' => 'خلاصه بیمه', 'description' => 'سهم بیمه کارمند، کارفرما و بیکاری', 'route' => route('management-reports.insurance-summary'), 'status' => 'آماده'],
                ],
            ],
            [
                'title' => 'مالیات',
                'description' => 'خلاصه و جزئیات مالیات حقوق',
                'reports' => [
                    ['title' => 'خلاصه مالیات', 'description' => 'درآمد مشمول و مالیات محاسبه‌شده', 'route' => route('management-reports.tax-summary'), 'status' => 'آماده'],
                ],
            ],
        ];
    }

    private function trialBalanceData(FinancialReportService $reports): array
    {
        $report = $reports->report('trial-balance', []);
        $rows = collect($report['sections'][0]['rows'] ?? []);
        $summary = $report['summary'] ?? [];

        $totals = [
            'opening_debit' => (float) ($summary['opening_debit'] ?? 0),
            'opening_credit' => (float) ($summary['opening_credit'] ?? 0),
            'period_debit' => (float) ($summary['period_debit'] ?? 0),
            'period_credit' => (float) ($summary['period_credit'] ?? 0),
            'closing_debit' => (float) ($summary['closing_debit'] ?? 0),
            'closing_credit' => (float) ($summary['closing_credit'] ?? 0),
        ];

        $summary = [
            'opening' => $totals['opening_debit'] - $totals['opening_credit'],
            'period' => $totals['period_debit'] - $totals['period_credit'],
            'closing' => $totals['closing_debit'] - $totals['closing_credit'],
            'is_balanced' => abs(($totals['opening_debit'] + $totals['period_debit']) - ($totals['opening_credit'] + $totals['period_credit'])) < 0.01,
        ];

        return compact('rows', 'totals', 'summary');
    }

    private function warehouseCardexData(Request $request): array
    {
        $query = $this->filteredInventoryDocumentLines($request)
            ->orderBy('d.document_date')
            ->orderBy('d.id')
            ->orderBy('l.id');

        $totalMatches = (clone $query)->count();
        $isLimited = $totalMatches > 500;
        $documentLines = $query->limit(500)->get();
        $balances = [];

        $rows = $documentLines->map(function ($line) use (&$balances) {
            $balanceKey = implode('|', [
                $line->warehouse_id,
                $line->item_name,
                $line->category,
                $line->project_id ?: 'none',
            ]);

            $balances[$balanceKey] ??= ['quantity' => 0, 'value' => 0];

            $quantityIn = $line->type === 'receipt' ? (float) $line->quantity : 0;
            $quantityOut = in_array($line->type, ['issue', 'consumption'], true) ? (float) $line->quantity : 0;
            $valueIn = $line->type === 'receipt' ? (float) $line->line_total : 0;
            $valueOut = in_array($line->type, ['issue', 'consumption'], true) ? (float) $line->line_total : 0;

            $balances[$balanceKey]['quantity'] += $quantityIn - $quantityOut;
            $balances[$balanceKey]['value'] += $valueIn - $valueOut;

            return [
                'transaction' => $this->inventoryLineAsTransaction($line),
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'balance_quantity' => $balances[$balanceKey]['quantity'],
                'balance_value' => $balances[$balanceKey]['value'],
            ];
        });

        $summary = [
            'quantity_in' => $rows->sum('quantity_in'),
            'quantity_out' => $rows->sum('quantity_out'),
            'balance_quantity' => collect($balances)->sum('quantity'),
            'balance_value' => collect($balances)->sum('value'),
        ];

        return $this->warehouseReportData($request) + compact(
            'rows',
            'summary',
            'isLimited',
            'totalMatches'
        );
    }

    private function hrEmployeesData(Request $request): array
    {
        $query = Employee::with(['party']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($employee) => $employee
                ->whereHas('party', fn ($party) => $party->where('name', 'like', "%{$search}%")->orWhere('national_id', 'like', "%{$search}%"))
                ->orWhere('personnel_code', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return [
            'rows' => $query->orderBy('id')->paginate(50)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ];
    }

    private function hrHistoryData(Request $request): array
    {
        $query = EmployeeHistory::with('employee.party')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($history) => $history->where('title', 'like', "%{$search}%")
                ->orWhereHas('employee.party', fn ($party) => $party->where('name', 'like', "%{$search}%")));
        }

        return [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['search']),
        ];
    }

    private function hrEmploymentOrdersData(Request $request): array
    {
        $query = EmploymentOrder::with(['employee.party'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($order) => $order->where('number', 'like', "%{$search}%")
                ->orWhereHas('employee.party', fn ($party) => $party->where('name', 'like', "%{$search}%")));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ];
    }

    private function warehouseDocumentTotals(): array
    {
        return [
            'in' => (float) DB::table('inventory_document_lines as l')
                ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
                ->where('d.status', 'confirmed')
                ->where('d.type', 'receipt')
                ->sum('l.line_total'),
            'out' => (float) DB::table('inventory_document_lines as l')
                ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
                ->where('d.status', 'confirmed')
                ->whereIn('d.type', ['issue', 'consumption'])
                ->sum('l.line_total'),
        ];
    }

    private function warehouseInventoryData(Request $request): array
    {
        $aggregates = $this->filteredInventoryDocumentLines($request, false)
            ->selectRaw("
                d.warehouse_id,
                d.project_id,
                i.name as item_name,
                i.category,
                SUM(CASE WHEN d.type = 'receipt' THEN l.quantity ELSE 0 END) as quantity_in,
                SUM(CASE WHEN d.type IN ('issue', 'consumption') THEN l.quantity ELSE 0 END) as quantity_out,
                SUM(CASE WHEN d.type = 'receipt' THEN l.line_total ELSE 0 END) as value_in,
                SUM(CASE WHEN d.type IN ('issue', 'consumption') THEN l.line_total ELSE 0 END) as value_out
            ")
            ->groupBy('d.warehouse_id', 'd.project_id', 'i.name', 'i.category')
            ->orderBy('d.warehouse_id')
            ->orderBy('i.name')
            ->get();

        $warehousesById = Warehouse::whereIn('id', $aggregates->pluck('warehouse_id')->filter()->unique())->get()->keyBy('id');
        $projectsById = Project::whereIn('id', $aggregates->pluck('project_id')->filter()->unique())->get()->keyBy('id');

        $rows = $aggregates->map(function ($row) use ($warehousesById, $projectsById) {
            $quantityIn = (float) $row->quantity_in;
            $quantityOut = (float) $row->quantity_out;
            $valueIn = (float) $row->value_in;
            $valueOut = (float) $row->value_out;
            $balanceQuantity = $quantityIn - $quantityOut;
            $balanceValue = $valueIn - $valueOut;
            $averagePrice = 0;

            if (abs($balanceQuantity) > 0.000001) {
                $averagePrice = $balanceValue / $balanceQuantity;
            }

            return [
                'warehouse' => $warehousesById->get($row->warehouse_id),
                'project' => $projectsById->get($row->project_id),
                'item_name' => $row->item_name,
                'category' => $row->category,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'balance_quantity' => $balanceQuantity,
                'balance_value' => $balanceValue,
                'average_price' => $averagePrice,
            ];
        })
            ->filter(fn ($row) => !$request->boolean('only_available') || $row['balance_quantity'] > 0)
            ->values();

        $totalMatches = $rows->count();
        $isLimited = $totalMatches > 1000;
        $rows = $rows->take(1000)->values();

        $summary = [
            'items_count' => $rows->count(),
            'balance_quantity' => $rows->sum('balance_quantity'),
            'balance_value' => $rows->sum('balance_value'),
        ];

        return $this->warehouseReportData($request) + compact(
            'rows',
            'summary',
            'isLimited',
            'totalMatches'
        );
    }

    private function filteredInventoryDocumentLines(Request $request, bool $selectColumns = true)
    {
        $query = DB::table('inventory_document_lines as l')
            ->join('inventory_documents as d', 'd.id', '=', 'l.inventory_document_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->leftJoin('projects as p', 'p.id', '=', 'd.project_id')
            ->where('d.status', 'confirmed')
            ->whereIn('d.type', ['receipt', 'issue', 'consumption']);

        if ($selectColumns) {
            $query->select([
                'l.id as line_id',
                'l.quantity',
                'l.unit_price',
                'l.line_total',
                'l.description as line_description',
                'd.id as document_id',
                'd.number',
                'd.type',
                'd.document_date',
                'd.document_time',
                'd.warehouse_id',
                'd.project_id',
                'd.entry_mode',
                'd.source_type',
                'd.source_id',
                'd.description as document_description',
                'i.name as item_name',
                'i.category',
                'w.name as warehouse_name',
                'p.name as project_name',
            ]);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('d.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('type')) {
            if ($request->type === 'in') {
                $query->where('d.type', 'receipt');
            } elseif ($request->type === 'out') {
                $query->whereIn('d.type', ['issue', 'consumption']);
            } else {
                $query->where('d.type', $request->type);
            }
        }

        if ($request->filled('project_id')) {
            $query->where('d.project_id', $request->project_id);
        }

        if ($request->filled('item_name')) {
            $query->where('i.name', 'like', '%' . $request->item_name . '%');
        }

        if ($request->filled('category')) {
            $query->where('i.category', 'like', '%' . $request->category . '%');
        }

        if ($request->filled('reference_number')) {
            $query->where('d.number', 'like', '%' . $request->reference_number . '%');
        }

        if ($request->filled('description')) {
            $query->where(function ($descriptionQuery) use ($request) {
                $descriptionQuery
                    ->where('d.description', 'like', '%' . $request->description . '%')
                    ->orWhere('l.description', 'like', '%' . $request->description . '%');
            });
        }

        if ($request->filled('start_date')) {
            $startDate = jalaliToGregorianDate($request->start_date);
            if ($startDate) {
                $query->where('d.document_date', '>=', $startDate);
            }
        }

        if ($request->filled('end_date')) {
            $endDate = jalaliToGregorianDate($request->end_date);
            if ($endDate) {
                $query->where('d.document_date', '<=', $endDate);
            }
        }

        return $query;
    }

    private function inventoryLineAsTransaction(object $line): stdClass
    {
        $warehouse = new stdClass();
        $warehouse->id = $line->warehouse_id;
        $warehouse->name = $line->warehouse_name;

        $transaction = new stdClass();
        $transaction->transaction_date = $line->document_date;
        $transaction->warehouse = $warehouse;
        $transaction->warehouse_id = $line->warehouse_id;
        $transaction->project = null;
        if ($line->project_id) {
            $project = new stdClass();
            $project->id = $line->project_id;
            $project->name = $line->project_name;
            $transaction->project = $project;
        }
        $transaction->item_name = $line->item_name;
        $transaction->category = $line->category;
        $transaction->reference_number = $line->number;
        $transaction->type = $line->type;
        $transaction->document_type_label = [
            'receipt' => 'رسید انبار',
            'issue' => 'حواله خروج',
            'consumption' => 'حواله مصرف',
            'transfer' => 'انتقال بین انبار',
        ][$line->type] ?? $line->type;
        $transaction->reference_modal_id = 'cardex-reference-' . $line->line_id;
        $transaction->reference_details = [
            'شماره سند' => $line->number,
            'نوع سند' => $transaction->document_type_label,
            'تاریخ' => gregorianToJalaliDate($line->document_date),
            'انبار' => $line->warehouse_name ?: '-',
            'پروژه' => $line->project_name ?: '-',
            'منبع' => $line->source_type ? class_basename($line->source_type) . ' #' . $line->source_id : 'ثبت دستی',
            'کالا' => $line->item_name,
            'تعداد' => number_format((float) $line->quantity, 3),
            'فی' => number_format((float) $line->unit_price),
            'مبلغ' => number_format((float) $line->line_total),
            'شرح سند' => $line->document_description ?: '-',
            'شرح ردیف' => $line->line_description ?: '-',
        ];

        return $transaction;
    }

    private function warehouseReportData(Request $request): array
    {
        return [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'categories' => DB::table('items')
                ->select('category')
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'dateErrors' => [
                'start_date' => $request->filled('start_date') && !jalaliToGregorianDate($request->start_date) ? 'تاریخ شروع معتبر نیست.' : null,
                'end_date' => $request->filled('end_date') && !jalaliToGregorianDate($request->end_date) ? 'تاریخ پایان معتبر نیست.' : null,
            ],
        ];
    }
}
