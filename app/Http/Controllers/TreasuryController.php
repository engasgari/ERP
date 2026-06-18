<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTreasuryTransactionRequest;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ChartAccount;
use App\Models\Party;
use App\Models\TreasuryTransaction;
use App\Repositories\TreasuryRepository;
use App\Services\TreasuryService;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(private TreasuryRepository $treasury, private TreasuryService $service)
    {
    }

    public function index(Request $request)
    {
        return view('treasury.index', [
            'transactions' => $this->treasury->paginate($this->normalizedFilters($request)),
            'banks' => BankAccount::latest()->get(),
            'cashboxes' => Cashbox::latest()->get(),
        ]);
    }

    public function create()
    {
        return view('treasury.form', $this->formData());
    }

    public function store(StoreTreasuryTransactionRequest $request)
    {
        $transaction = $this->service->createAndPost($request->validated(), $request->user()->id);

        return redirect()->route('treasury.index')->with('success', 'تراکنش خزانه ثبت شد: ' . $transaction->number);
    }

    public function edit(TreasuryTransaction $transaction)
    {
        return view('treasury.form', $this->formData($transaction));
    }

    public function update(StoreTreasuryTransactionRequest $request, TreasuryTransaction $transaction)
    {
        $transaction = $this->service->updateAndPost($transaction, $request->validated(), $request->user()->id);

        return redirect()->route('treasury.index')->with('success', 'تراکنش خزانه ویرایش شد و سند حسابداری آن دوباره صادر شد: ' . $transaction->number);
    }

    public function destroy(TreasuryTransaction $transaction)
    {
        $this->service->deleteWithAccounting($transaction);

        return redirect()->route('treasury.index')->with('success', 'تراکنش خزانه و سند حسابداری وابسته حذف شدند.');
    }

    private function formData(?TreasuryTransaction $transaction = null): array
    {
        return [
            'transaction' => $transaction,
            'isEdit' => (bool) $transaction,
            'banks' => BankAccount::where('is_active', true)->orderBy('bank_name')->get(),
            'cashboxes' => Cashbox::where('is_active', true)->orderBy('name')->get(),
            'parties' => Party::where('is_active', true)->orderBy('name')->get(),
            'accounts' => ChartAccount::where('is_active', true)->orderBy('code')->get(),
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
}
