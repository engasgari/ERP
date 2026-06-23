<?php

namespace App\Models;

use App\Services\BankAccountCodingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected static bool $syncingCoding = false;

    protected $fillable = [
        'code',
        'bank_name',
        'branch',
        'account_number',
        'iban',
        'card_number',
        'currency',
        'opening_balance',
        'chart_account_id',
        'detail_account_id',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }

    public function detailAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'detail_account_id');
    }

    protected static function booted(): void
    {
        static::saved(function (BankAccount $bankAccount): void {
            if (static::$syncingCoding) {
                return;
            }

            if ($bankAccount->wasChanged('detail_account_id') && ! $bankAccount->wasRecentlyCreated) {
                return;
            }

            static::$syncingCoding = true;

            try {
                app(BankAccountCodingService::class)->syncDetailAccount($bankAccount);
            } finally {
                static::$syncingCoding = false;
            }
        });
    }
}
