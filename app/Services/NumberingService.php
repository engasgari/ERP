<?php

namespace App\Services;

use App\Models\NumberingSetting;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    public function next(string $key, ?string $fallbackPrefix = null): string
    {
        return DB::transaction(function () use ($key, $fallbackPrefix) {
            $setting = NumberingSetting::firstOrCreate(
                ['document_key' => $key],
                ['prefix' => $fallbackPrefix ?? strtoupper(substr($key, 0, 3)) . '-', 'next_number' => 1, 'padding' => 5]
            );

            $setting->lockForUpdate();

            $number = ($setting->prefix ?? '') . str_pad((string) $setting->next_number, $setting->padding, '0', STR_PAD_LEFT);
            $setting->increment('next_number');

            return $number;
        });
    }
}
