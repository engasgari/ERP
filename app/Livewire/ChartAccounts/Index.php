<?php

namespace App\Livewire\ChartAccounts;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\ChartAccount;
use Illuminate\Support\Facades\DB;
use Throwable;

class Index extends BaseListPage
{

    public string $search = '';
    public string $level = '';
    public string $nature = '';
    public string $is_system = '';
    public ?int $showingId = null;

    public array $levelLabels = [
        'group' => 'گروه',
        'ledger' => 'کل',
        'subsidiary' => 'معین',
        'detail' => 'تفصیل',
    ];

    public array $natureLabels = [
        'debit' => 'بدهکار',
        'credit' => 'بستانکار',
        'neutral' => 'خنثی',
    ];

    protected array $queryString = [
        'search' => ['except' => ''],
        'level' => ['except' => ''],
        'nature' => ['except' => ''],
        'is_system' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'level', 'nature', 'is_system']);
        $this->resetPage();
    }

    public function show(int $accountId): void
    {
        $this->showingId = $accountId;
    }

    public function closeModal(): void
    {
        $this->showingId = null;
    }

    public function delete(int $accountId): void
    {
        $account = ChartAccount::findOrFail($accountId);

        if ($account->is_system || $account->children()->exists() || $account->lines()->exists()) {
            $children = $account->children()->limit(5)->pluck('code')->implode('، ');
            $details = collect([
                $account->is_system ? 'این حساب سیستمی است.' : null,
                $account->children()->exists() ? 'زیرمجموعه‌ها: ' . $account->children()->count() . ($children ? ' - نمونه کدها: ' . $children : '') : null,
                $account->lines()->exists() ? 'ردیف‌های سند حسابداری: ' . $account->lines()->count() : null,
            ])->filter()->values()->all();

            session()->flash('error', 'این سرفصل قابل حذف نیست چون حساب سیستمی است یا گردش/زیرمجموعه دارد.');
            session()->flash('error_details', $details);

            return;
        }

        try {
            $account->delete();
            session()->flash('success', 'سرفصل مالی حذف شد.');
        } catch (Throwable) {
            session()->flash('error', 'امکان حذف این سرفصل مالی وجود ندارد.');
            session()->flash('error_details', ['ممکن است سند حسابداری یا زیرمجموعه‌ای به این حساب وصل باشد.']);
        }
    }

    public function updateField(int $accountId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['title', 'level', 'nature'], true), 403);

        $account = ChartAccount::findOrFail($accountId);
        $data = match ($field) {
            'title' => ['title' => trim((string) $value)],
            'level' => ['level' => array_key_exists((string) $value, $this->levelLabels) ? (string) $value : $account->level],
            'nature' => ['nature' => array_key_exists((string) $value, $this->natureLabels) ? (string) $value : $account->nature],
        };

        if (array_key_exists('title', $data) && $data['title'] === '') {
            session()->flash('error', 'عنوان سرفصل مالی الزامی است.');

            return;
        }

        $account->update($data);
        session()->flash('success', 'تغییرات سرفصل مالی ذخیره شد.');
    }

    public function render()
    {
        $accounts = $this->baseQuery()
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($query) use ($search) {
                    $query->where('chart_accounts.code', 'like', "%{$search}%")
                        ->orWhere('chart_accounts.title', 'like', "%{$search}%")
                        ->orWhere('parent_accounts.title', 'like', "%{$search}%");
                });
            })
            ->when($this->level !== '', fn ($query) => $query->where('chart_accounts.level', $this->level))
            ->when($this->nature !== '', fn ($query) => $query->where('chart_accounts.nature', $this->nature))
            ->when($this->is_system !== '', fn ($query) => $query->where('chart_accounts.is_system', (bool) $this->is_system))
            ->orderBy('chart_accounts.code')
            ->paginate(50);

        $showingAccount = $this->showingId
            ? $this->baseQuery()->where('chart_accounts.id', $this->showingId)->first()
            : null;

        return view('livewire.chart-accounts.index', compact('accounts', 'showingAccount'));
    }

    private function baseQuery()
    {
        return ChartAccount::query()
            ->leftJoin('chart_accounts as parent_accounts', 'parent_accounts.id', '=', 'chart_accounts.parent_id')
            ->select('chart_accounts.*', DB::raw('parent_accounts.title as parent_title'));
    }
}
