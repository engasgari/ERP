<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\NumberingSetting;
use App\Models\TreasuryTransaction;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    public function next(string $key, ?string $fallbackPrefix = null): string
    {
        return DB::transaction(function () use ($key, $fallbackPrefix) {
            $setting = NumberingSetting::query()
                ->where('document_key', $key)
                ->lockForUpdate()
                ->first();

            if (!$setting) {
                $setting = NumberingSetting::create([
                    'document_key' => $key,
                    'prefix' => $fallbackPrefix ?? strtoupper(substr($key, 0, 3)) . '-',
                    'next_number' => 1,
                    'padding' => 5,
                ]);
            }

            $prefix = $setting->prefix ?: ($fallbackPrefix ?? strtoupper(substr($key, 0, 3)) . '-');
            $padding = (int) ($setting->padding ?: 5);
            $sequence = max((int) $setting->next_number, $this->currentSequence($key, $prefix) + 1);
            $number = $prefix . str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

            $setting->update([
                'prefix' => $prefix,
                'padding' => $padding,
                'next_number' => $sequence + 1,
            ]);

            return $number;
        });
    }

    private function currentSequence(string $key, string $prefix): int
    {
        $table = match ($key) {
            'accounting_document' => 'accounting_documents',
            'treasury_transaction' => 'treasury_transactions',
            'sale_invoice', 'purchase_invoice' => 'invoices',
            default => null,
        };

        if (!$table) {
            return 0;
        }

        return DB::table($table)
            ->where('number', 'like', $prefix . '%')
            ->pluck('number')
            ->map(function (string $number) use ($prefix): int {
                return (int) preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $number);
            })
            ->max() ?: 0;
    }
}
