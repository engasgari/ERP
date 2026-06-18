<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'type',
        'transaction_date',
        'amount',
        'currency',
        'from_treasury_type',
        'from_treasury_id',
        'to_treasury_type',
        'to_treasury_id',
        'party_id',
        'project_id',
        'expense_account_id',
        'income_account_id',
        'status',
        'source_type',
        'source_id',
        'accounting_document_id',
        'description',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function fromTreasury(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'from_treasury_type', 'from_treasury_id');
    }

    public function toTreasury(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'to_treasury_type', 'to_treasury_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }
}
