<?php

namespace App\Rules;

use App\Services\FiscalPeriodService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WithinActiveFiscalPeriod implements ValidationRule
{
    public function __construct(private readonly FiscalPeriodService $periods)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            $this->periods->ensureDateIsAllowed($value);
        } catch (\Throwable) {
            $fail('تاریخ انتخاب‌شده خارج از بازه مالی فعال است.');
        }
    }
}
