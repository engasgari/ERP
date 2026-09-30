<?php

namespace App\Models;

use App\Services\CashboxCodingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Cashbox extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'currency', 'opening_balance', 'chart_account_id', 'detail_account_id', 'is_active'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Cashbox $cashbox): void {
            if (! Schema::hasColumn('cashboxes', 'detail_account_id')) {
                return;
            }

            if ($cashbox->wasChanged(['code', 'name', 'chart_account_id', 'is_active']) || $cashbox->wasRecentlyCreated || ! $cashbox->detail_account_id) {
                app(CashboxCodingService::class)->syncDetailAccount($cashbox);
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }

    public function detailAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'detail_account_id');
    }
}
