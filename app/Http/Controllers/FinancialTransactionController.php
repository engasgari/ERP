<?php
namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Models\Project;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = FinancialTransaction::with('project');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', 'like', '%' . $request->category . '%');
        }

        if ($request->filled('reference_number')) {
            $query->where('reference_number', 'like', '%' . $request->reference_number . '%');
        }

        if ($request->filled('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }

        if ($request->filled('start_date')) {
            $startDate = jalaliToGregorianDate($request->start_date);
            if ($startDate) {
                $query->whereDate('transaction_date', '>=', $startDate);
            }
        }

        if ($request->filled('end_date')) {
            $endDate = jalaliToGregorianDate($request->end_date);
            if ($endDate) {
                $query->whereDate('transaction_date', '<=', $endDate);
            }
        }

        $amountMin = normalizePersianDigits($request->input('amount_min'));
        if ($amountMin !== null && $amountMin !== '' && is_numeric($amountMin)) {
            $query->where('amount', '>=', $amountMin);
        }

        $amountMax = normalizePersianDigits($request->input('amount_max'));
        if ($amountMax !== null && $amountMax !== '' && is_numeric($amountMax)) {
            $query->where('amount', '<=', $amountMax);
        }

        $summaryQuery = clone $query;
        $totalIncome = (clone $summaryQuery)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $summaryQuery)->where('type', 'expense')->sum('amount');

        $transactions = $query->latest()->paginate(15)->withQueryString();

        $summary = [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'profit_loss' => $totalIncome - $totalExpense,
        ];

        $projects = Project::orderBy('name')->get();
        $categories = FinancialTransaction::query()
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $dateErrors = [
            'start_date' => $request->filled('start_date') && !jalaliToGregorianDate($request->start_date) ? 'تاریخ شروع معتبر نیست.' : null,
            'end_date' => $request->filled('end_date') && !jalaliToGregorianDate($request->end_date) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        return view('financial-transactions.index', compact('transactions', 'summary', 'projects', 'categories', 'dateErrors'));
    }

    public function create()
    {
        $projects = Project::where('status', 'active')->get();
        $categories = $this->getTransactionCategories();
        return view('financial-transactions.create', compact('projects', 'categories'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'transaction_date' => jalaliToGregorianDate($request->input('transaction_date')),
        ]);

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:income,expense',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ]);

        FinancialTransaction::create($request->safe()->only([
            'project_id', 'type', 'category', 'amount', 'transaction_date', 'description', 'reference_number',
        ]));

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت ثبت شد!');
    }

    public function show(FinancialTransaction $financialTransaction)
    {
        $financialTransaction->load('project');

        return view('financial-transactions.show', compact('financialTransaction'));
    }

    public function edit(FinancialTransaction $financialTransaction)
    {
        $projects = Project::all();
        $categories = $this->getTransactionCategories();
        return view('financial-transactions.edit', compact('financialTransaction', 'projects', 'categories'));
    }

    public function update(Request $request, FinancialTransaction $financialTransaction)
    {
        $request->merge([
            'transaction_date' => jalaliToGregorianDate($request->input('transaction_date')),
        ]);

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:income,expense',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ]);

        $financialTransaction->update($request->safe()->only([
            'project_id', 'type', 'category', 'amount', 'transaction_date', 'description', 'reference_number',
        ]));

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت ویرایش شد!');
    }

    public function destroy(FinancialTransaction $financialTransaction)
    {
        $financialTransaction->delete();

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت حذف شد!');
    }

    // گزارش مالی پروژه
    public function projectReport($projectId)
    {
        $project = Project::with(['financialTransactions', 'workLogs.employee'])->findOrFail($projectId);

        $transactions = $project->financialTransactions()
            ->latest()
            ->get();

        $incomeByCategory = $project->financialTransactions()
            ->where('type', 'income')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        $expenseByCategory = $project->financialTransactions()
            ->where('type', 'expense')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        // محاسبه هزینه انبار بابت هر پروژه
        $inventoryDocuments = $project->inventoryDocuments()
            ->with(['lines.item'])
            ->whereIn('type', ['issue', 'consumption'])
            ->where('status', 'confirmed')
            ->latest('document_date')
            ->get();

        $transactionsWarehouse = $inventoryDocuments->flatMap(function ($document) {
            return $document->lines->map(function ($line) use ($document) {
                return (object) [
                    'transaction_date' => $document->document_date,
                    'type_color' => 'text-slate-700',
                    'type_icon' => $document->type === 'consumption' ? 'مصرف' : 'خروج',
                    'type_label' => $document->type === 'consumption' ? 'حواله مصرف' : 'حواله خروج',
                    'item_name' => $line->item?->name ?: '-',
                    'quantity' => number_format((float) $line->quantity, 3),
                    'unit_price' => number_format((float) $line->unit_price),
                    'total_amount' => number_format((float) $line->line_total),
                    'description' => $line->description ?: $document->description,
                ];
            });
        });

        $WareHouseOutTotalAmount = (float) $inventoryDocuments
            ->flatMap(fn ($document) => $document->lines)
            ->sum('line_total');

        // محاسبات مالی
        $totalIncome = (float) $project->financialTransactions()->where('type', 'income')->sum('amount');
        $totalExpense = (float) $project->financialTransactions()->where('type', 'expense')->sum('amount');
        $totalLaborCost = $project->total_labor_cost;

        // محاسبه سود ناخالص و خالص
        $grossProfit = $totalIncome - $totalExpense ; // سود قبل از کسر حقوق
        $netProfit = $grossProfit - $totalLaborCost - $WareHouseOutTotalAmount; // سود پس از کسر حقوق

        $profitPercentage = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;

        return view('financial-transactions.project-report', compact(
            'project',
            'transactions',
            'incomeByCategory',
            'expenseByCategory',
            'totalIncome',
            'totalExpense',
            'totalLaborCost',
            'grossProfit',
            'netProfit',
            'profitPercentage',
            'WareHouseOutTotalAmount',
            'transactionsWarehouse'
        ));
    }

    // دسته‌بندی‌های تراکنش
    private function getTransactionCategories()
    {
        return [
            'income' => [
                'فروش محصول',
                'فروش خدمات',
                'قرارداد',
                'پشتیبانی',
                'سایر درآمدها'
            ],
            'expense' => [
                'حقوق و دستمزد',
                'تجهیزات',
                'نرم‌افزار',
                'مصالح و مواد',
                'حمل و نقل',
                'اجاره',
                'بازاریابی',
                'سایر هزینه‌ها'
            ]
        ];
    }
}
