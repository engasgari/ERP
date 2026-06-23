<?php

namespace App\Livewire\BankAccounts;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\AccountingDocumentLine;
use App\Models\BankAccount;
use App\Models\ChartAccount;
use App\Models\TreasuryTransaction;
use App\Services\BankAccountCodingService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $is_active = '';
    public ?int $editingId = null;

    public array $form = [
        'code' => '',
        'bank_name' => '',
        'branch' => '',
        'account_number' => '',
        'iban' => '',
        'card_number' => '',
        'currency' => 'IRR',
        'opening_balance' => 0,
        'chart_account_id' => '',
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'is_active' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function rules(): array
    {
        return [
            'form.code' => 'required|string|max:50|unique:bank_accounts,code,' . $this->editingId,
            'form.bank_name' => 'required|string|max:255',
            'form.branch' => 'nullable|string|max:255',
            'form.account_number' => 'nullable|string|max:100',
            'form.iban' => 'nullable|string|max:100',
            'form.card_number' => 'nullable|string|max:100',
            'form.currency' => 'required|string|max:10',
            'form.opening_balance' => 'nullable|numeric',
            'form.chart_account_id' => 'nullable|exists:chart_accounts,id',
            'form.is_active' => 'boolean',
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'is_active']);
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $bank = BankAccount::with(['account', 'detailAccount'])->findOrFail($id);
        $this->editingId = $bank->id;
        $this->form = $bank->only(['code', 'bank_name', 'branch', 'account_number', 'iban', 'card_number', 'currency', 'opening_balance', 'chart_account_id', 'is_active']);
        $this->form['chart_account_id'] = $this->form['chart_account_id'] ?: '';
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'bank_name' => '',
            'branch' => '',
            'account_number' => '',
            'iban' => '',
            'card_number' => '',
            'currency' => 'IRR',
            'opening_balance' => 0,
            'chart_account_id' => '',
            'is_active' => true,
        ];
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        $data['chart_account_id'] = $data['chart_account_id'] ?: null;
        $data['opening_balance'] = (float) $data['opening_balance'];
        $data['is_active'] = (string) $data['is_active'] === '1' || $data['is_active'] === true;

        $bank = BankAccount::updateOrCreate(['id' => $this->editingId], $data);
        app(BankAccountCodingService::class)->syncDetailAccount($bank);

        session()->flash('success', 'حساب بانکی ذخیره شد.');
        $this->cancel();
    }

    public function delete(int $id): void
    {
        $bank = BankAccount::findOrFail($id);

        $hasLines = AccountingDocumentLine::where('bank_account_id', $bank->id)->exists();
        $hasTreasuryTransactions = TreasuryTransaction::query()
            ->where(function (Builder $query) use ($bank): void {
                $query->where('from_treasury_type', BankAccount::class)
                    ->where('from_treasury_id', $bank->id);
            })
            ->orWhere(function (Builder $query) use ($bank): void {
                $query->where('to_treasury_type', BankAccount::class)
                    ->where('to_treasury_id', $bank->id);
            })
            ->exists();

        if ($hasLines || $hasTreasuryTransactions) {
            session()->flash('error', 'این حساب بانکی به اسناد مالی یا تراکنش خزانه وصل است و قابل حذف نیست.');
            session()->flash('error_details', ['ابتدا ارتباط‌های مالی این حساب را بررسی و حذف یا منتقل کنید.']);
            return;
        }

        $bank->delete();
        session()->flash('success', 'حساب بانکی حذف شد.');
    }

    public function render()
    {
        $banks = BankAccount::query()
            ->with(['account', 'detailAccount'])
            ->when($this->search !== '', function (Builder $query) {
                $search = trim($this->search);
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('bank_name', 'like', "%{$search}%")
                        ->orWhere('branch', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('iban', 'like', "%{$search}%");
                });
            })
            ->when($this->is_active !== '', fn (Builder $query) => $query->where('is_active', $this->is_active === '1'))
            ->orderBy('bank_name')
            ->paginate(15);

        $accounts = ChartAccount::orderBy('code')->get();

        return view('livewire.bank-accounts.index', compact('banks', 'accounts'));
    }
}
