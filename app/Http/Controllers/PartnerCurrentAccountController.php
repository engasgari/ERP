<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerCurrentAccountTransferRequest;
use App\Models\BankAccount;
use App\Models\Party;
use App\Repositories\PartnerCurrentAccountRepository;
use App\Services\PartnerCurrentAccountService;
use RuntimeException;

class PartnerCurrentAccountController extends Controller
{
    public function __construct(
        private PartnerCurrentAccountRepository $repository,
        private PartnerCurrentAccountService $service
    ) {
    }

    public function index()
    {
        return view('partner-current-accounts.index');
    }

    public function create()
    {
        return view('partner-current-accounts.create', $this->formData());
    }

    public function store(StorePartnerCurrentAccountTransferRequest $request)
    {
        $validated = $request->validated();
        $party = Party::findOrFail($validated['party_id']);
        $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);

        try {
            $document = $validated['direction'] === 'deposit'
                ? $this->service->depositToPartnerAccount(
                    party: $party,
                    bankAccount: $bankAccount,
                    amount: (float) $validated['amount'],
                    transactionDate: $validated['transaction_date'],
                    description: $validated['description'] ?? null,
                    userId: $request->user()->id
                )
                : $this->service->withdrawFromPartnerAccount(
                    party: $party,
                    bankAccount: $bankAccount,
                    amount: (float) $validated['amount'],
                    transactionDate: $validated['transaction_date'],
                    description: $validated['description'] ?? null,
                    userId: $request->user()->id
                );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()
            ->route('partner-current-accounts.index')
            ->with('success', 'انتقال حساب جاری شریک ثبت شد. سند حسابداری: ' . $document->number);
    }

    private function formData(): array
    {
        return [
            'partners' => $this->repository->shareholderParties(),
            'banks' => BankAccount::where('is_active', true)->orderBy('bank_name')->get(),
        ];
    }
}
