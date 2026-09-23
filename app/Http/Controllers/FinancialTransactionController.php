<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\FinancialTransaction;
use App\Models\Project;
use App\Repositories\FinancialTransactionRepository;
use App\Services\AccountingPostingService;
use App\Services\ProjectCostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinancialTransactionController extends Controller
{
    public function index()
    {
        return view('financial-transactions.index');
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
            $this->appendFinancialTransactionCodingValidation($validator, $request);
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

        DB::transaction(function () use ($validated, $request, $posting) {
            if (blank($validated['category'] ?? null) && filled($validated['detail_account_id'] ?? null)) {
                $validated['category'] = ChartAccount::query()->whereKey($validated['detail_account_id'])->value('title');
            }

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
            $this->appendFinancialTransactionCodingValidation($validator, $request);
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
            if (blank($validated['category'] ?? null) && filled($validated['detail_account_id'] ?? null)) {
                $validated['category'] = ChartAccount::query()->whereKey($validated['detail_account_id'])->value('title');
            }

            $posting->deleteFinancialTransactionDocument($financialTransaction, $request->user()?->id);

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

    public function destroy(FinancialTransaction $financialTransaction, AccountingPostingService $posting, Request $request)
    {
        DB::transaction(function () use ($financialTransaction, $posting, $request) {
            $posting->deleteFinancialTransactionDocument($financialTransaction, $request->user()?->id);
            $financialTransaction->delete();
        });

        return redirect()->route('financial-transactions.index')
            ->with('success', 'تراکنش مالی با موفقیت حذف شد!');
    }

    public function projectReport($projectId)
    {
        $project = Project::with(['financialTransactions', 'workLogs.employee'])->findOrFail($projectId);
        $costing = app(ProjectCostingService::class)->summary($project);
        $repository = app(FinancialTransactionRepository::class);

        $transactions = $repository->unifiedRows([
            'project_id' => (string) $project->id,
        ]);

        $incomeByCategory = $transactions
            ->where('type', 'income')
            ->groupBy(fn ($row) => $row->category ?: 'بدون دسته')
            ->map(fn ($group, $category) => (object) [
                'category' => $category,
                'total' => $group->sum('amount'),
            ])
            ->values();

        $expenseByCategory = $transactions
            ->where('type', 'expense')
            ->groupBy(fn ($row) => $row->category ?: 'بدون دسته')
            ->map(fn ($group, $category) => (object) [
                'category' => $category,
                'total' => $group->sum('amount'),
            ])
            ->values();

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
                    'quantity' => formatQuantity((float) $line->quantity),
                    'unit_price' => formatMoney((float) $line->unit_price),
                    'total_amount' => formatMoney((float) $line->line_total),
                    'description' => $line->description ?: $document->description,
                ];
            });
        });

        $wareHouseOutTotalAmount = (float) $inventoryDocuments
            ->flatMap(fn ($document) => $document->lines)
            ->sum('line_total');

        $totalIncome = (float) $costing['revenue'];
        $totalExpense = (float) ($costing['registered_expense_cost'] + $costing['ledger_expense_cost']);
        $totalLaborCost = (float) $costing['labor_cost'];

        $grossProfit = $totalIncome - $totalExpense;
        $netProfit = (float) $costing['profit_net'];
        $profitPercentage = (float) $costing['profit_margin'];
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
        return [
            'income' => $this->buildCodingGroupPayload(['41', '42']),
            'expense' => $this->buildCodingGroupPayload(['52']),
        ];
    }

    private function buildCodingGroupPayload(array $ledgerCodes): array
    {
        $ledgerIds = ChartAccount::query()
            ->whereIn('code', $ledgerCodes)
            ->pluck('id');

        $subsidiaries = ChartAccount::query()
            ->whereIn('parent_id', $ledgerIds)
            ->where('level', 'subsidiary')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('level', 'detail')->where('is_active', true)->orderBy('code')])
            ->orderBy('code')
            ->get();

        return [
            'subsidiaries' => $subsidiaries
                ->map(fn (ChartAccount $account) => $this->codingAccountPayload($account))
                ->filter()
                ->values()
                ->all(),
            'details_by_subsidiary' => $subsidiaries
                ->mapWithKeys(function (ChartAccount $subsidiary) {
                    return [
                        (string) $subsidiary->id => $subsidiary->children
                            ->map(fn (ChartAccount $detail) => $this->codingAccountPayload($detail, $subsidiary->id))
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                })
                ->all(),
        ];
    }

    private function codingAccountPayload(?ChartAccount $account, ?int $parentId = null): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'parent_id' => $parentId ?? $account->parent_id,
            'code' => $account->code,
            'title' => $account->title,
            'label' => $account->level === 'subsidiary'
                ? $account->title
                : $account->title,
        ];
    }

    private function appendFinancialTransactionCodingValidation($validator, Request $request): void
    {
        $chartAccountId = $request->input('chart_account_id');
        $detailAccountId = $request->input('detail_account_id');
        $type = $request->input('type');

        if (! filled($chartAccountId)) {
            return;
        }

        $chartAccount = ChartAccount::query()->with('parent')->find($chartAccountId);
        if (! $chartAccount) {
            $validator->errors()->add('chart_account_id', 'دسته‌بندی انتخاب شده معتبر نیست.');

            return;
        }

        if ($chartAccount->level !== 'subsidiary') {
            $validator->errors()->add('chart_account_id', 'دسته‌بندی باید یک حساب معین باشد.');
        }

        $ledgerCode = $chartAccount->parent?->code;
        if ($type === 'expense' && $ledgerCode !== '52') {
            $validator->errors()->add('chart_account_id', 'برای هزینه فقط حساب‌های معین هزینه قابل انتخاب است.');
        }

        if ($type === 'income' && ! in_array($ledgerCode, ['41', '42'], true)) {
            $validator->errors()->add('chart_account_id', 'برای درآمد فقط حساب‌های معین درآمد قابل انتخاب است.');
        }

        if (! filled($detailAccountId)) {
            $validator->errors()->add('detail_account_id', 'انتخاب تفصیل الزامی است.');

            return;
        }

        $detailAccount = ChartAccount::find($detailAccountId);
        if (! $detailAccount || (int) $detailAccount->parent_id !== (int) $chartAccountId) {
            $validator->errors()->add('detail_account_id', 'تفصیل انتخاب شده باید زیرمجموعه دسته‌بندی باشد.');
        }
    }

    private function resolveCodingSelection(FinancialTransaction $transaction): array
    {
        if ($transaction->chart_account_id || $transaction->detail_account_id) {
            $chartAccountId = $transaction->chart_account_id;
            if ($transaction->detail_account_id && ! $chartAccountId) {
                $chartAccountId = ChartAccount::query()->whereKey($transaction->detail_account_id)->value('parent_id');
            }

            return [
                'chart_account_id' => $chartAccountId,
                'detail_account_id' => $transaction->detail_account_id,
            ];
        }

        $categoryCode = $this->legacyCategoryCode($transaction->type, $transaction->category);
        if (! $categoryCode) {
            return [
                'chart_account_id' => null,
                'detail_account_id' => null,
            ];
        }

        $detailAccount = ChartAccount::query()->where('code', $categoryCode)->first();
        $mainCode = str_starts_with((string) $categoryCode, '41') || str_starts_with((string) $categoryCode, '42')
            ? substr($categoryCode, 0, 4)
            : substr($categoryCode, 0, 4);

        return [
            'chart_account_id' => $detailAccount?->parent_id ?: ChartAccount::where('code', $mainCode)->value('id'),
            'detail_account_id' => $detailAccount?->id,
        ];
    }

    private function legacyCategoryCode(?string $type, ?string $category): ?string
    {
        $category = trim((string) $category);

        return match ($type) {
            'income' => match ($category) {
                'درآمد متفرقه', 'سایر درآمدهای غیر فاکتوری' => $category === 'سایر درآمدهای غیر فاکتوری' ? '410202' : '410201',
                'برگشت هزینه' => '410203',
                'دریافت متفرقه' => '410204',
                default => '410201',
            },
            'expense' => match ($category) {
                'اجاره' => '520101',
                'ناهار پرسنل' => '520102',
                'تنخواه' => '520103',
                'پذیرایی' => '520104',
                'خرید لوازم' => '520105',
                'کمیسیون بازاریابی' => '520506',
                'تعمیرات' => '520106',
                'حمل و نقل' => '520401',
                'حقوق و دستمزد' => '520201',
                'سایر هزینه‌ها', 'سایر هزینه ها', 'سایر هزینه های روزمره' => '520108',
                default => '520101',
            },
            default => null,
        };
    }
}
