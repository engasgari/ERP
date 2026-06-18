<?php

namespace App\Livewire\Access\Roles;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function delete(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        abort_if($role->is_system, 403);

        $role->delete();
        session()->flash('success', 'نقش حذف شد.');
    }

    public function updateField(int $roleId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['title', 'description'], true), 403);

        $role = Role::findOrFail($roleId);
        abort_if($role->is_system, 403);

        $data = match ($field) {
            'title' => ['title' => trim((string) $value)],
            'description' => ['description' => trim((string) $value) ?: null],
        };

        if (($data['title'] ?? $role->title) === '') {
            session()->flash('error', 'عنوان نقش الزامی است.');
            return;
        }

        $role->update($data);
        session()->flash('success', 'تغییرات نقش ذخیره شد.');
    }

    public function render()
    {
        $roles = Role::query()
            ->withCount('users')
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('title')
            ->paginate(20);

        return view('livewire.access.roles.index', compact('roles'));
    }
}
