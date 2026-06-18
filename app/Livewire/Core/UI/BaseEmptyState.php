<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

class BaseEmptyState extends Component
{
    public string $message = 'داده‌ای برای نمایش وجود ندارد.';
    public string $actionLabel = '';
    public string $actionUrl = '';

    public function render()
    {
        return view('livewire.core.ui.base-empty-state');
    }
}
