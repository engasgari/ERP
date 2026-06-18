<?php

namespace App\Livewire\WorkShifts;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\WorkShift;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public string $is_active = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'is_active' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset(['search', 'is_active']);
        $this->resetPage();
    }

    public function delete(int $shiftId): void
    {
        $shift = WorkShift::findOrFail($shiftId);

        if ($shift->workGroups()->exists()) {
            session()->flash('error', 'این شیفت قابل حذف نیست.');
            session()->flash('error_details', ['این شیفت به گروه کاری وصل است. ابتدا گروه‌های کاری مرتبط را اصلاح کنید.']);
            return;
        }

        $shift->delete();
        session()->flash('success', 'شیفت کاری حذف شد.');
    }

    public function updateField(int $shiftId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'is_active'], true), 403);

        $shift = WorkShift::findOrFail($shiftId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'is_active' => ['is_active' => (string) $value === '1'],
        };

        if (($data['name'] ?? $shift->name) === '') {
            session()->flash('error', 'نام شیفت الزامی است.');
            return;
        }

        $shift->update($data);
        session()->flash('success', 'تغییرات شیفت کاری ذخیره شد.');
    }

    public function render()
    {
        $shifts = WorkShift::query()
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->when($this->is_active !== '', fn ($query) => $query->where('is_active', $this->is_active === '1'))
            ->latest()
            ->paginate(20);

        return view('livewire.work-shifts.index', compact('shifts'));
    }
}
