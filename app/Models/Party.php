<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'detail_code',
        'kind',
        'name',
        'economic_code',
        'national_id',
        'phone',
        'mobile',
        'email',
        'postal_code',
        'address',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(PartyType::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function accountingLines(): HasMany
    {
        return $this->hasMany(AccountingDocumentLine::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getLedgerBalanceAttribute(): float
    {
        return (float) $this->accountingLines()->sum('debit') - (float) $this->accountingLines()->sum('credit');
    }
}
