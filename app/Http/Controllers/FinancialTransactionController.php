<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\FinancialTransaction;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\AccountingPostingService;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = FinancialTransaction::with(['project', 'bankAccount', 'cashbox', 'chartAccount', 'detailAccount', 'accountingDocument']);

        if ($request->filled('project_id')) {
            if ($request->input('project_id') === 'null') {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', $request->project_id);
            }
        }

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
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
        $bankAccounts = BankAccount::orderBy('bank_name')->orderBy('code')->get();
        $cashboxes = Cashbox::orderBy('name')->orderBy('code')->get();
        $categories = FinancialTransaction::query()
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $dateErrors = [
            'start_date' => $request->filled('start_date') && ! jalaliToGregorianDate($request->start_date) ? 'تاریخ شروع معتبر نیست.' : null,
            'end_date' => $request->filled('end_date') && ! jalaliToGregorianDate($request->end_date) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        return view('financial-transactions.index', compact(
            'transactions',
            'summary',
            'projects',
            'bankAccounts',
            'cashboxes',
            'categories',
            'dateErrors'
        ));
    }

    public function summary(Request $request)
    {
        $query = FinancialTransaction::with(['project', 'bankAccount', 'cashbox', 'chartAccount', 'detailAccount', 'accountingDocument']);

        if ($request->filled('project_id')) {
            if ($request->input('project_id') === 'null') {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', $request->project_id);
            }
        }

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
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

        $transactions = $query->latest()->get();
        $summary = [
            'total_income' => (float) $transactions->where('type', 'income')->sum('amount'),
            'total_expense' => (float) $transactions->where('type', 'expense')->sum('amount'),
        ];
        $summary['profit_loss'] = $summary['total_income'] - $summary['total_expense'];

        $byType = $transactions->groupBy('type')->map(fn ($rows) => (float) $rows->sum('amount'));
        $bySource = $transactions->groupBy(function ($transaction) {
            if ($transaction->bankAccount) {
                return 'bank:' . $transaction->bankAccount->bank_name . ' - ' . $transaction->bankAccount->code;
            }

            if ($transaction->cashbox) {
                return 'cashbox:' . $transaction->cashbox->name . ' - ' . $transaction->cashbox->code;
            }

            return 'unassigned';
        })->map(function ($rows, $label) {
            return [
                'label' => $label,
                'count' => $rows->count(),
                'income' => (float) $rows->where('type', 'income')->sum('amount'),
                'expense' => (float) $rows->where('type', 'expense')->sum('amount'),
                'net' => (float) $rows->where('type', 'income')->sum('amount') - (float) $rows->where('type', 'expense')->sum('amount'),
            ];
        })->values();

        $byCategory = $transactions->groupBy('category')->map(function ($rows, $category) {
            return [
                'category' => $category,
                'count' => $rows->count(),
                'income' => (float) $rows->where('type', 'income')->sum('amount'),
                'expense' => (float) $rows->where('type', 'expense')->sum('amount'),
                'net' => (float) $rows->where('type', 'income')->sum('amount') - (float) $rows->where('type', 'expense')->sum('amount'),
            ];
        })->sortByDesc('net')->values();

        return view('financial-transactions.summary', compact('transactions', 'summary', 'byType', 'bySource', 'byCategory'));
    }

    public function create()
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $bankAccounts = BankAccount::orderBy('bank_name')->orderBy('code')->get();
        $cashboxes = Cashbox::orderBy('name')->orderBy('code')->get();
        $codingGroups = $this->codingGroups();

        return view('financial-transactions.create', compact('projects', 'bankAccounts', 'cashboxes', 'codingGroups'));
    }

    public function store(Request $request, AccountingPostingService $posting)
    {
        $request->merge([
            'transaction_date' => jalaliToGregorianDate($request->input('transaction_date')),
        ]);

        $validated = Validator::make($request->all(), [
            'project_id' => 'nullable|exists:projects,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'cashbox_id' => 'nullable|exists:cashboxes,id',
            'type' => 'required|in:income,expense',
            'chart_account_id' => 'required|exists:chart_accounts,id',
            'detail_account_id' => 'nullable|exists:chart_accounts,id',
            'category' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ], [
            'bank_account_id.exists' => 'بانک انتخاب شده معتبر نیست.',
            'cashbox_id.exists' => 'صندوق انتخاب شده معتبر نیست.',
        ])->after(function ($validator) use ($request) {
            $bankAccountId = $request->input('bank_account_id');
            $cashboxId = $request->input('cashbox_id');

            if (blank($bankAccountId) && blank($cashboxId)) {
                $validator->errors()->add('bank_account_id', 'برای ثبت این سند، انتخاب بانک یا صندوق الزامی است.');
                $validator->errors()->add('cashbox_id', 'برای ثبت این سند، انتخاب بانک یا صندوق الزامی است.');
            }

            if (filled($bankAccountId) && filled($cashboxId)) {
                $validator->errors()->add('bank_account_id', 'برای این سند فقط یکی از بانک یا صندوق را انتخاب کنید.');
                $validator->errors()->add('cashbox_id', 'برای این سند فقط یکی از بانک یا صندوق را انتخاب کنید.');
            }
            $chartAccountId = $request->input('chart_account_id');
            if (filled($chartAccountId)) {
                $chartAccount = ChartAccount::find($chartAccountId);
                if (! $chartAccount) {
                    $validator->errors()->add('chart_account_id', 'کدینگ انتخاب شده معتبر نیست.');
                } elseif ($request->input('type') === 'income' && $chartAccount->code !== '4102') {
                    $validator->errors()->add('chart_account_id', 'برای درآمد فقط کدینگ درآمد متفرقه قابل انتخاب است.');
                } elseif ($request->input('type') === 'expense' && $chartAccount->code !== '5201') {
                    $validator->errors()->add('chart_account_id', 'برای هزینه فقط کدینگ هزینه عمومی قابل انتخاب است.');
                }

                $detailAccountId = $request->input('detail_account_id');
                if (filled($detailAccountId)) {
                    $detailAccount = ChartAccount::find($detailAccountId);
                    if (! $detailAccount || (int) $detailAccount->parent_id !== (int) $chartAccountId) {
                        $validator->errors()->add('detail_account_id', 'تفصیل انتخاب شده باید زیرمجموعه کدینگ اصلی باشد.');
                    }
                }
            }
            $chartAccountId = $request->input('chart_account_id');
            if (filled($chartAccountId)) {
                $chartAccount = ChartAccount::find($chartAccountId);
                if (! $chartAccount) {
                    $validator->errors()->add('chart_account_id', 'کدینگ انتخاب شده معتبر نیست.');
                } elseif ($request->input('type') === 'income' && $chartAccount->code !== '4102') {
                    $validator->errors()->add('chart_account_id', 'برای درآمد فقط کدینگ درآمد متفرقه قابل انتخاب است.');
                } elseif ($request->input('type') === 'expense' && $chartAccount->code !== '5201') {
                    $validator->errors()->add('chart_account_id', 'برای هزینه فقط کدینگ هزینه عمومی قابل انتخاب است.');
                }

                $detailAccountId = $request->input('detail_account_id');
                if (filled($detailAccountId)) {
                    $detailAccount = ChartAccount::find($detailAccountId);
                    if (! $detailAccount || (int) $detailAccount->parent_id !== (int) $chartAccountId) {
                        $validator->errors()->add('detail_account_id', 'تفصیل انتخاب شده باید زیرمجموعه کدینگ اصلی باشد.');
                    }
                }
            }
        })->validate();

        DB::transaction(function () use ($validated, $request, $posting) {
            $transaction = FinancialTransaction::create($validated);
            $document = $posting->fromFinancialTransaction($transaction, $request->user()?->id);

            $transaction->update([
                'accounting_document_id' => $document->id,
            ]);
        });

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت ثبت شد!');
    }

    public function show(FinancialTransaction $financialTransaction)
    {
        $financialTransaction->load(['project', 'bankAccount', 'cashbox', 'chartAccount', 'detailAccount', 'accountingDocument']);

        return view('financial-transactions.show', compact('financialTransaction'));
    }

    public function edit(FinancialTransaction $financialTransaction)
    {
        $projects = Project::orderBy('name')->get();
        $bankAccounts = BankAccount::orderBy('bank_name')->orderBy('code')->get();
        $cashboxes = Cashbox::orderBy('name')->orderBy('code')->get();
        $codingGroups = $this->codingGroups();
        $codingSelection = $this->resolveCodingSelection($financialTransaction);

        return view('financial-transactions.edit', compact('financialTransaction', 'projects', 'bankAccounts', 'cashboxes', 'codingGroups', 'codingSelection'));
    }

    public function update(Request $request, FinancialTransaction $financialTransaction, AccountingPostingService $posting)
    {
        $request->merge([
            'transaction_date' => jalaliToGregorianDate($request->input('transaction_date')),
        ]);

        $validated = Validator::make($request->all(), [
            'project_id' => 'nullable|exists:projects,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'cashbox_id' => 'nullable|exists:cashboxes,id',
            'type' => 'required|in:income,expense',
            'chart_account_id' => 'required|exists:chart_accounts,id',
            'detail_account_id' => 'nullable|exists:chart_accounts,id',
            'category' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ], [
            'bank_account_id.exists' => 'بانک انتخاب شده معتبر نیست.',
            'cashbox_id.exists' => 'صندوق انتخاب شده معتبر نیست.',
        ])->after(function ($validator) use ($request) {
            $bankAccountId = $request->input('bank_account_id');
            $cashboxId = $request->input('cashbox_id');

            if (blank($bankAccountId) && blank($cashboxId)) {
                $validator->errors()->add('bank_account_id', 'برای ثبت این سند، انتخاب بانک یا صندوق الزامی است.');
                $validator->errors()->add('cashbox_id', 'برای ثبت این سند، انتخاب بانک یا صندوق الزامی است.');
            }

            if (filled($bankAccountId) && filled($cashboxId)) {
                $validator->errors()->add('bank_account_id', 'برای این سند فقط یکی از بانک یا صندوق را انتخاب کنید.');
                $validator->errors()->add('cashbox_id', 'برای این سند فقط یکی از بانک یا صندوق را انتخاب کنید.');
            }
        })->validate();

        DB::transaction(function () use ($financialTransaction, $validated, $request, $posting) {
            $posting->deleteFinancialTransactionDocument($financialTransaction);

            $financialTransaction->update($validated + [
                'accounting_document_id' => null,
            ]);

            $document = $posting->fromFinancialTransaction($financialTransaction->refresh(), $request->user()?->id);
            $financialTransaction->update([
                'accounting_document_id' => $document->id,
            ]);
        });

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت ویرایش شد!');
    }

    public function destroy(FinancialTransaction $financialTransaction, AccountingPostingService $posting)
    {
        DB::transaction(function () use ($financialTransaction, $posting) {
            $posting->deleteFinancialTransactionDocument($financialTransaction);
            $financialTransaction->delete();
        });

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت حذف شد!');
    }

    public function projectReport($projectId)
    {
        $project = Project::with(['financialTransactions', 'workLogs.employee'])->findOrFail($projectId);

        $transactions = $project->financialTransactions()
            ->with(['bankAccount', 'cashbox'])
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

        $wareHouseOutTotalAmount = (float) $inventoryDocuments
            ->flatMap(fn ($document) => $document->lines)
            ->sum('line_total');

        $totalIncome = (float) $project->financialTransactions()->where('type', 'income')->sum('amount');
        $totalExpense = (float) $project->financialTransactions()->where('type', 'expense')->sum('amount');
        $totalLaborCost = $project->total_labor_cost;

        $grossProfit = $totalIncome - $totalExpense;
        $netProfit = $grossProfit - $totalLaborCost - $wareHouseOutTotalAmount;
        $profitPercentage = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;
        $WareHouseOutTotalAmount = $wareHouseOutTotalAmount;

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
            'wareHouseOutTotalAmount',
            'transactionsWarehouse'
        ));
    }

    private function codingGroups(): array
    {
        $accounts = ChartAccount::query()
            ->whereIn('code', ['4102', '5201'])
            ->with(['children' => fn ($query) => $query->orderBy('code')])
            ->orderBy('code')
            ->get()
            ->keyBy('code');
        $categories = $this->getTransactionCategories();

        return [
            'income' => array_merge(
                $this->codingGroupPayload($accounts->get('4102')),
                [
                    'categories' => $categories['income'],
                    'detail_by_category' => $this->detailCodeMap('income', $categories['income']),
                ]
            ),
            'expense' => array_merge(
                $this->codingGroupPayload($accounts->get('5201')),
                [
                    'categories' => $categories['expense'],
                    'detail_by_category' => $this->detailCodeMap('expense', $categories['expense']),
                ]
            ),
        ];
    }

    private function codingGroupPayload(?ChartAccount $mainAccount): array
    {
        return [
            'main' => $this->codingAccountPayload($mainAccount),
            'details' => $mainAccount
                ? $mainAccount->children
                    ->sortBy('code')
                    ->values()
                    ->map(fn (ChartAccount $account) => $this->codingAccountPayload($account))
                    ->all()
                : [],
        ];
    }

    private function codingAccountPayload(?ChartAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'parent_id' => $account->parent_id,
            'code' => $account->code,
            'title' => $account->title,
            'label' => $account->code . ' - ' . $account->title,
        ];
    }

    private function detailCodeMap(string $type, array $categories): array
    {
        $map = [];

        foreach ($categories as $category) {
            $code = $this->legacyCategoryCode($type, $category);
            if ($code) {
                $map[$category] = ChartAccount::where('code', $code)->value('id');
            }
        }

        return $map;
    }

    private function resolveCodingSelection(FinancialTransaction $transaction): array
    {
        if ($transaction->chart_account_id || $transaction->detail_account_id) {
            return [
                'chart_account_id' => $transaction->chart_account_id,
                'detail_account_id' => $transaction->detail_account_id,
            ];
        }

        $categoryCode = $this->legacyCategoryCode($transaction->type, $transaction->category);
        $mainCode = str_starts_with((string) $categoryCode, '41') ? '4102' : '5201';

        return [
            'chart_account_id' => ChartAccount::where('code', $mainCode)->value('id'),
            'detail_account_id' => ChartAccount::where('code', $categoryCode)->value('id'),
        ];
    }

    private function legacyCategoryCode(?string $type, ?string $category): ?string
    {
        $category = trim((string) $category);

        return match ($type) {
            'income' => match ($category) {
                'درآمد متفرقه', 'سایر درآمدهای غیر فاکتوری' => $category === 'سایر درآمدهای غیر فاکتوری' ? '410202' : '410201',
                default => '410201',
            },
            'expense' => match ($category) {
                'اجاره' => '520101',
                'ناهار پرسنل' => '520102',
                'تنخواه' => '520103',
                'پذیرایی' => '520104',
                'خرید لوازم' => '520105',
                'تعمیرات' => '520106',
                'حمل و نقل' => '520107',
                'سایر هزینه‌ها', 'سایر هزینه ها', 'سایر هزینه های روزمره' => '520108',
                default => '520101',
            },
            default => null,
        };
    }

    private function getTransactionCategories(): array
    {
        return [
            'income' => [
                'فروش محصول',
                'فروش خدمات',
                'دریافت متفرقه',
                'برگشت هزینه',
                'سایر درآمدها',
            ],
            'expense' => [
                'اجاره',
                'ناهار پرسنل',
                'تنخواه',
                'پذیرایی',
                'حمل و نقل',
                'خرید لوازم',
                'تعمیرات',
                'حقوق و دستمزد',
                'سایر هزینه‌ها',
            ],
        ];
    }
}
