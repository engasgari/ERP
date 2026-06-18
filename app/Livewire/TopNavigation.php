<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TopNavigation extends Component
{
    public bool $open = false;

    public function logout()
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function render()
    {
        $links = [
            ['label' => 'داشبورد', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'پروژه‌ها', 'route' => 'projects.index', 'active' => 'projects.*'],
            ['label' => 'پرسنل', 'route' => 'employees.index', 'active' => 'employees.*'],
            ['label' => 'کارکرد', 'route' => 'work-logs.index', 'active' => 'work-logs.*'],
            ['label' => 'حقوق', 'route' => 'salaries.index', 'active' => 'salaries.*'],
            ['label' => 'انبار', 'route' => 'inventory-documents.index', 'active' => 'inventory-documents.*'],
            ['label' => 'مالی', 'route' => 'financial-transactions.index', 'active' => 'financial-transactions.*'],
        ];

        return view('livewire.top-navigation', compact('links'));
    }
}
