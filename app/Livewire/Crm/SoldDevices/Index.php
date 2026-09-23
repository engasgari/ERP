<?php

namespace App\Livewire\Crm\SoldDevices;

use App\Livewire\Core\UI\BaseListPage;
use App\Models\Crm\SoldDevice;
use App\Models\Item;
use App\Models\Party;
use App\Repositories\Crm\CrmSoldDeviceRepository;
use App\Services\Crm\CrmSoldDeviceService;
use Carbon\Carbon;

class Index extends BaseListPage
{
    public string $search = '';

    public string $warranty_status = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $party_id = '';

    public string $serial_number = '';

    public string $item_id = '';

    public string $sold_at = '';

    public string $warranty_years = '1';

    public string $notes = '';

    protected array $queryString = [
        'search' => ['except' => ''],
        'warranty_status' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->openCreate();
        }
    }

    public function updated(string $name): void
    {
        if (in_array($name, [
            'party_id',
            'serial_number',
            'item_id',
            'sold_at',
            'warranty_years',
            'notes',
        ], true)) {
            return;
        }

        if ($name !== 'selectedRows' && $name !== 'detailsId') {
            $this->resetPage();
        }
    }

    public function updatedWarrantyYears(?string $value): void
    {
        $this->warranty_years = $this->normalizeWarrantyYearsInput($value);
    }

    private function normalizeWarrantyYearsInput(?string $value): string
    {
        $ascii = normalizePersianDigits(trim((string) ($value ?? ''))) ?? '';
        $digits = preg_replace('/\D/u', '', $ascii);

        if ($digits === '') {
            return '';
        }

        return (string) min(20, max(0, (int) $digits));
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'warranty_status']);
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->sold_at = todayJalaliDate();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $device = SoldDevice::query()->with(['party', 'item'])->findOrFail($id);

        $this->editingId = $device->id;
        $this->party_id = (string) $device->party_id;
        $this->serial_number = $device->serial_number;
        $this->item_id = (string) ($device->item_id ?? '');
        $this->sold_at = gregorianToJalaliDate($device->sold_at);
        $this->warranty_years = (string) $device->warranty_years;
        $this->notes = $device->notes ?? '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(CrmSoldDeviceService $devices): void
    {
        $this->warranty_years = $this->normalizeWarrantyYearsInput($this->warranty_years);

        if ($this->warranty_years === '') {
            $this->warranty_years = '1';
        }

        $this->validate([
            'party_id' => ['required', 'exists:parties,id'],
            'item_id' => ['required', 'exists:items,id'],
            'serial_number' => ['required', 'string', 'max:128'],
            'sold_at' => ['required', 'string'],
            'warranty_years' => ['required', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'party_id' => 'مشتری',
            'item_id' => 'کالا / دستگاه',
            'serial_number' => 'شماره سریال',
            'sold_at' => 'تاریخ فروش',
            'warranty_years' => 'مدت گارانتی',
            'notes' => 'توضیحات',
        ]);

        $data = [
            'party_id' => (int) $this->party_id,
            'item_id' => (int) $this->item_id,
            'serial_number' => $this->serial_number,
            'sold_at' => $this->sold_at,
            'warranty_years' => (int) $this->warranty_years,
            'notes' => $this->notes,
        ];

        $actor = auth()->user();

        if ($this->editingId) {
            $device = SoldDevice::query()->findOrFail($this->editingId);
            $devices->update($device, $data, $actor);
            session()->flash('success', 'دستگاه به‌روزرسانی شد.');
        } else {
            $devices->create($data, $actor);
            session()->flash('success', 'دستگاه فروخته‌شده ثبت شد.');
        }

        $this->closeModal();
    }

    public function remove(int $id, CrmSoldDeviceService $devices): void
    {
        $device = SoldDevice::query()->findOrFail($id);
        $devices->remove($device, auth()->user());
        session()->flash('success', 'ثبت دستگاه حذف شد.');
    }

    public function previewWarrantyEndsAt(): string
    {
        if ($this->sold_at === '' || ! is_numeric($this->warranty_years)) {
            return '-';
        }

        try {
            $gregorian = str_contains($this->sold_at, '/')
                ? jalaliToGregorianDate($this->sold_at)
                : $this->sold_at;

            if (! $gregorian) {
                return '-';
            }

            $endsAt = app(CrmSoldDeviceService::class)
                ->computeWarrantyEndsAt(Carbon::parse($gregorian)->startOfDay(), (int) $this->warranty_years);

            return gregorianToJalaliDate($endsAt);
        } catch (\Throwable) {
            return '-';
        }
    }

    public function render(CrmSoldDeviceRepository $devices)
    {
        $filters = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'warranty_status' => $this->warranty_status !== '' ? $this->warranty_status : null,
        ]);

        $items = $devices->paginate($filters, auth()->user(), $this->perPage);
        $parties = Party::query()->customers()->orderBy('name')->limit(300)->get(['id', 'name']);
        $catalogItems = Item::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'category', 'type']);

        if ($this->item_id !== '') {
            $selectedItem = Item::query()->find((int) $this->item_id);

            if ($selectedItem) {
                $catalogItems = $catalogItems
                    ->prepend($selectedItem)
                    ->unique('id')
                    ->values();
            }
        }

        return view('livewire.crm.sold-devices.index', compact('items', 'parties', 'catalogItems'));
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->reset(['party_id', 'serial_number', 'item_id', 'sold_at', 'warranty_years', 'notes']);
        $this->warranty_years = '1';
        $this->resetValidation();
    }
}
