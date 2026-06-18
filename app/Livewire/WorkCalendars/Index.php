<?php

namespace App\Livewire\WorkCalendars;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\WorkCalendar;
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

    public function delete(int $calendarId): void
    {
        $calendar = WorkCalendar::findOrFail($calendarId);

        if ($calendar->workGroups()->exists()) {
            session()->flash('error', 'این تقویم قابل حذف نیست.');
            session()->flash('error_details', ['این تقویم به گروه کاری وصل است. ابتدا گروه‌های کاری مرتبط را اصلاح کنید.']);
            return;
        }

        $calendar->delete();
        session()->flash('success', 'تقویم کاری حذف شد.');
    }

    public function updateField(int $calendarId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'jalali_year', 'is_default', 'is_active'], true), 403);

        $calendar = WorkCalendar::findOrFail($calendarId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'jalali_year' => ['jalali_year' => $value !== '' ? (int) $value : null],
            'is_default' => ['is_default' => (string) $value === '1'],
            'is_active' => ['is_active' => (string) $value === '1'],
        };

        if (($data['name'] ?? $calendar->name) === '') {
            session()->flash('error', 'نام تقویم الزامی است.');
            return;
        }

        if (isset($data['jalali_year']) && $data['jalali_year'] !== null && ($data['jalali_year'] < 1300 || $data['jalali_year'] > 1500)) {
            session()->flash('error', 'سال تقویم باید بین 1300 تا 1500 باشد.');
            return;
        }

        $calendar->update($data);
        session()->flash('success', 'تغییرات تقویم کاری ذخیره شد.');
    }

    public function render()
    {
        $calendars = WorkCalendar::query()
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('jalali_year', 'like', "%{$search}%");
            })
            ->when($this->is_active !== '', fn ($query) => $query->where('is_active', $this->is_active === '1'))
            ->latest()
            ->paginate(20);

        return view('livewire.work-calendars.index', compact('calendars'));
    }
}
