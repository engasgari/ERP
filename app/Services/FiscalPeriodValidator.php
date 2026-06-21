<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class FiscalPeriodValidator
{
    public function __construct(private readonly FiscalPeriodService $periods)
    {
    }

    public function ensureDateIsAllowed(CarbonInterface|string|null $date): void
    {
        if ($date === null || $date === '') {
            return;
        }

        $this->periods->ensureDateIsAllowed($date);
    }

    public function ensurePeriodIsOpen(): void
    {
        $this->periods->ensurePeriodIsOpen();
    }

    public function failDateOutsideActivePeriod(): never
    {
        throw ValidationException::withMessages([
            'date' => 'تاریخ انتخاب‌شده خارج از بازه مالی فعال است.',
        ]);
    }
}
