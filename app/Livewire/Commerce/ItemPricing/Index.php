<?php

namespace App\Livewire\Commerce\ItemPricing;

use App\Livewire\Core\UI\BaseListPage;
use App\Repositories\ItemRepository;
use App\Services\ItemSalePriceService;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Index extends BaseListPage
{
    public string $search = '';

    public string $type = '';

    public string $category = '';

    public string $is_active = '1';

    /** @var array<int|string, string> */
    public array $salePrices = [];

    protected array $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'category' => ['except' => ''],
        'is_active' => ['except' => '1'],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'category']);
        $this->is_active = '1';
        $this->resetPage();
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'salePrices.')) {
            return;
        }

        parent::updated($name);
    }

    public function saveSalePrice(int $itemId, ItemSalePriceService $pricing): void
    {
        abort_unless(auth()->user()?->hasPermission('commerce.pricing.manage'), 403);

        try {
            $item = $pricing->updateCatalogSalePrice(
                $itemId,
                $this->salePrices[$itemId] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                "salePrices.{$itemId}" => $exception->getMessage(),
            ]);
        }

        $this->salePrices[$itemId] = $this->formatEditablePrice((float) $item->sale_price);
        session()->flash('success', "قیمت فروش «{$item->name}» ذخیره شد.");
    }

    public function render(ItemRepository $items, ItemSalePriceService $pricing)
    {
        $filters = [
            'search' => $this->search !== '' ? $this->search : null,
            'type' => $this->type !== '' ? $this->type : null,
            'category' => $this->category !== '' ? $this->category : null,
            'is_active' => $this->is_active !== '' ? $this->is_active : null,
        ];

        $rows = $items->paginateForPricing($filters, $this->perPage);
        $lastSalePrices = $pricing->lastSaleUnitPricesForItems($rows->getCollection()->pluck('id'));

        foreach ($rows as $item) {
            if (! array_key_exists($item->id, $this->salePrices)) {
                $this->salePrices[$item->id] = $this->formatEditablePrice((float) ($item->sale_price ?? 0));
            }
        }

        return view('livewire.commerce.item-pricing.index', [
            'items' => $rows,
            'categories' => $items->categories(),
            'lastSalePrices' => $lastSalePrices,
            'canManage' => (bool) auth()->user()?->hasPermission('commerce.pricing.manage'),
        ]);
    }

    private function formatEditablePrice(float $amount): string
    {
        if (abs($amount) < 0.00001) {
            return '0';
        }

        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
