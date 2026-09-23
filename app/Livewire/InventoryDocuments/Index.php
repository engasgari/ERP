<?php

namespace App\Livewire\InventoryDocuments;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\RelatedDocumentDeletionService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $type = '';
    public string $entry_mode = '';
    public string $status = '';
    public string $warehouse_id = '';
    public ?int $showingId = null;
    public string $showMode = 'details';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'entry_mode' => ['except' => ''],
        'status' => ['except' => ''],
        'warehouse_id' => ['except' => ''],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'entry_mode', 'status', 'warehouse_id']);
        $this->resetPage();
    }

    public function show(int $documentId): void
    {
        $this->showingId = $documentId;
        $this->showMode = 'details';
    }

    public function showFull(int $documentId): void
    {
        $this->showingId = $documentId;
        $this->showMode = 'full';
    }

    public function closeModal(): void
    {
        $this->showingId = null;
        $this->showMode = 'details';
    }

    public function delete(int $documentId, RelatedDocumentDeletionService $deletion): void
    {
        $document = InventoryDocument::findOrFail($documentId);

        if ($document->is_automatic || $document->is_initial_stock) {
            $source = $document->source_type
                ? class_basename($document->source_type) . ' #' . $document->source_id
                : 'ثبت سیستمی';

            session()->flash('error', $document->is_initial_stock
                ? 'سند موجودی اولیه را از صفحه ویرایش کالا اصلاح کنید.'
                : 'اسناد اتوماتیک فقط از سرمنشا قابل حذف هستند.');
            session()->flash('error_details', [
                'سند انبار: ' . $document->number,
                'منبع مرتبط: ' . $source,
                'برای حذف، سند مادر را بررسی یا حذف کنید.',
            ]);

            return;
        }

        $deletion->deleteInventoryDocumentWithRelated($document);
        $this->closeModal();
        session()->flash('success', 'سند انبار حذف شد.');
    }

    public function render()
    {
        $query = InventoryDocument::with([
            'warehouse',
            'targetWarehouse',
            'project',
            'accountingDocument',
            'lines.item.unit',
            'source' => function ($morphTo) {
                $morphTo->morphWith([
                    Invoice::class => ['party'],
                    ProductionOrder::class => ['project'],
                ]);
            },
        ])->latest();

        if ($this->search !== '') {
            $search = trim($this->search);

            $query->where(fn ($q) => $q
                ->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('lines.item', fn ($lineQuery) => $lineQuery->where('name', 'like', "%{$search}%"))
            );
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->entry_mode === 'manual') {
            $query->where('entry_mode', 'manual');
        } elseif ($this->entry_mode === 'automatic') {
            $query->where('entry_mode', 'automatic');
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->warehouse_id !== '') {
            $query->where('warehouse_id', $this->warehouse_id);
        }

        $documents = $query->paginate(20);

        $showingDocument = $this->showingId
            ? InventoryDocument::with([
                'warehouse',
                'targetWarehouse',
                'project',
                'accountingDocument',
                'lines.item.unit',
                'source' => function ($morphTo) {
                    $morphTo->morphWith([
                        Invoice::class => ['party'],
                        ProductionOrder::class => ['project'],
                    ]);
                },
            ])->find($this->showingId)
            : null;

        return view('livewire.inventory-documents.index', [
            'documents' => $documents,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'showingDocument' => $showingDocument,
            'documentTypeLabels' => [
                'receipt' => 'رسید انبار',
                'issue' => 'حواله خروج',
                'consumption' => 'حواله مصرف',
                'transfer' => 'انتقال بین انبار',
            ],
        ]);
    }

    public function sourceSummary(InventoryDocument $document): string
    {
        $meta = $this->sourceMeta($document);

        if ($document->is_initial_stock) {
            $source = $document->source;
            $name = $source?->name
                ?? $document->lines->first()?->item?->name
                ?? ('کالا #' . $document->source_id);

            return 'موجودی اولیه - ' . $name;
        }

        if (! $document->is_automatic) {
            return 'دستی';
        }

        $summary = 'اتوماتیک - ' . $meta['label'];

        if ($meta['detail']) {
            $summary .= ' - ' . $meta['detail'];
        }

        return $summary;
    }

    public function sourceMeta(InventoryDocument $document): array
    {
        if (! $document->source_type) {
            return [
                'label' => $document->is_automatic ? 'ثبت سیستمی' : 'دستی',
                'detail' => null,
            ];
        }

        $source = $document->source;

        if ($document->source_type === Invoice::class) {
            return [
                'label' => 'فاکتور ' . ($source?->number ?: '#' . $document->source_id),
                'detail' => $source
                    ? (($source->direction === 'sale' ? 'فروش' : 'خرید') . ' - ' . ($source->party?->name ?: 'طرف حساب حذف شده'))
                    : null,
            ];
        }

        if ($document->source_type === ProductionOrder::class) {
            return [
                'label' => 'سفارش تولید ' . ($source?->number ?: '#' . $document->source_id),
                'detail' => $source?->project?->name,
            ];
        }

        if ($document->source_type === Item::class) {
            return [
                'label' => 'موجودی اولیه کالا ' . ($source?->name ?: '#' . $document->source_id),
                'detail' => $source?->code,
            ];
        }

        return [
            'label' => class_basename($document->source_type) . ' #' . $document->source_id,
            'detail' => null,
        ];
    }
}
