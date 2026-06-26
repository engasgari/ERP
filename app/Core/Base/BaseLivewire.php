<?php

namespace App\Core\Base;

use Livewire\Component;

abstract class BaseLivewire extends Component
{
    protected function notifySuccess(string $message): void
    {
        $this->dispatch('notify', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->dispatch('notify', type: 'error', message: $message);
    }
}