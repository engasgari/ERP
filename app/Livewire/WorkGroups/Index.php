<?php

namespace App\Livewire\WorkGroups;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\WorkGroup;
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

    public function delete(int $groupId): void
    {
        $group = WorkGroup::findOrFail($groupId);

        if ($group->employees()->exists()) {
            session()->flash('error', 'این گروه کاری قابل حذف نیست.');
            session()->flash('error_details', ['پرسنل به این گروه کاری وصل هستند. ابتدا عضویت پرسنل را اصلاح کنید.']);
            return;
        }

        $group->delete();
        session()->flash('success', 'گروه کاری حذف شد.');
    }

    public function updateField(int $groupId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'is_active'], true), 403);

        $group = WorkGroup::findOrFail($groupId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'is_active' => ['is_active' => (string) $value === '1'],
        };

        if (($data['name'] ?? $group->name) === '') {
            session()->flash('error', 'نام گروه کاری الزامی است.');
            return;
        }

        $group->update($data);
        session()->flash('success', 'تغییرات گروه کاری ذخیره شد.');
    }

    public function render()
    {
        $groups = WorkGroup::query()
            ->with(['shift', 'calendar'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('shift', fn ($shift) => $shift->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('calendar', fn ($calendar) => $calendar->where('name', 'like', "%{$search}%"));
            })
            ->when($this->is_active !== '', fn ($query) => $query->where('is_active', $this->is_active === '1'))
            ->latest()
            ->paginate(20);

        return view('livewire.work-groups.index', compact('groups'));
    }
}
