<?php

namespace App\Livewire\PartnerCurrentAccounts;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\Party;
use App\Repositories\PartnerCurrentAccountRepository;

class Index extends BaseListPage
{
    public string $search = '';

    public string $party_id = '';

    public string $direction = '';

    public string $date_from = '';

    public string $date_to = '';

    public array $directionLabels = [
        'deposit' => 'برداشت شریک',
        'withdraw' => 'واریز شریک',
    ];

    protected array $queryString = [
        'search' => ['except' => ''],
        'party_id' => ['except' => ''],
        'direction' => ['except' => ''],
        'date_from' => ['except' => ''],
        'date_to' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'party_id', 'direction', 'date_from', 'date_to']);
        $this->resetPage();
    }

    public function render(PartnerCurrentAccountRepository $repository)
    {
        $dateErrors = [
            'date_from' => $this->date_from !== '' && ! jalaliToGregorianDate($this->date_from) ? 'تاریخ شروع معتبر نیست.' : null,
            'date_to' => $this->date_to !== '' && ! jalaliToGregorianDate($this->date_to) ? 'تاریخ پایان معتبر نیست.' : null,
        ];

        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'party_id' => $this->party_id !== '' ? $this->party_id : null,
            'direction' => $this->direction !== '' ? $this->direction : null,
            'date_from' => $this->date_from !== '' ? jalaliToGregorianDate($this->date_from) : null,
            'date_to' => $this->date_to !== '' ? jalaliToGregorianDate($this->date_to) : null,
        ]);

        $transfers = $repository->paginate($filters, $this->perPage);
        $partners = $repository->shareholderParties();

        return view('livewire.partner-current-accounts.index', compact('transfers', 'partners', 'dateErrors'));
    }

    public static function transferDirection($document): ?string
    {
        if (str_starts_with((string) $document->description, 'برداشت از حساب جاری')) {
            return 'deposit';
        }

        if (str_starts_with((string) $document->description, 'واریز به حساب جاری')) {
            return 'withdraw';
        }

        $partnerLine = $document->lines->first(fn ($line) => str_starts_with((string) $line->account?->code, '32'));

        if (! $partnerLine) {
            return null;
        }

        return (float) $partnerLine->debit > 0 ? 'deposit' : 'withdraw';
    }

    public static function transferBankLabel($document): string
    {
        $bankLine = $document->lines->first(fn ($line) => $line->bank_account_id);

        if (! $bankLine?->bankAccount) {
            return '-';
        }

        return $bankLine->bankAccount->code . ' - ' . $bankLine->bankAccount->bank_name;
    }

    public static function transferAmount($document): float
    {
        return (float) $document->lines->sum('debit');
    }

    public static function transferPartnerName($document): string
    {
        if ($document->source instanceof Party) {
            return $document->source->name;
        }

        return '-';
    }
}
