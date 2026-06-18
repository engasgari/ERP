<?php

namespace App\Livewire\Core\UI;

use Livewire\Component;

abstract class BaseFormPage extends Component
{
    public bool $isSaving = false;

    abstract public function save(): void;

    protected function flashSaved(string $message = 'اطلاعات با موفقیت ذخیره شد.'): void
    {
        session()->flash('success', $message);
    }
}
