<?php

namespace App\Livewire\Access\Users;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\User;
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

    public function delete(int $userId): void
    {
        abort_if($userId === auth()->id(), 403);

        $user = User::findOrFail($userId);
        $user->roles()->detach();
        $user->employeeAccesses()->delete();
        $user->delete();

        session()->flash('success', 'کاربر حذف شد.');
    }

    public function updateField(int $userId, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['name', 'email'], true), 403);

        $user = User::findOrFail($userId);
        $data = match ($field) {
            'name' => ['name' => trim((string) $value)],
            'email' => ['email' => trim((string) $value)],
        };

        if (($data['name'] ?? $user->name) === '') {
            session()->flash('error', 'نام کاربر الزامی است.');
            return;
        }

        if (($data['email'] ?? $user->email) === '' || ! filter_var($data['email'] ?? $user->email, FILTER_VALIDATE_EMAIL)) {
            session()->flash('error', 'ایمیل کاربر معتبر نیست.');
            return;
        }

        $email = $data['email'] ?? $user->email;
        if ($email !== $user->email && User::where('email', $email)->whereKeyNot($user->id)->exists()) {
            session()->flash('error', 'این ایمیل قبلا ثبت شده است.');
            return;
        }

        $user->update($data);
        session()->flash('success', 'تغییرات کاربر ذخیره شد.');
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn ($role) => $role->where('title', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.access.users.index', compact('users'));
    }
}
