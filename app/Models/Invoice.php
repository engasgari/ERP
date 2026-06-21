<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;

class Invoice extends Model
{
    protected $fillable = [
        'fiscal_year_id',
        'direction',
        'document_type',
        'number',
        'invoice_date',
        'party_id',
        'project_id',
        'warehouse_id',
        'converted_from_id',
        'status',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'description',
        'accounting_document_id',
        'created_by',
        'confirmed_at',
        'settled_at',
        'settled_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function inventoryDocuments(): MorphMany
    {
        return $this->morphMany(InventoryDocument::class, 'source');
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn ($builder) => $builder->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($builder) => $builder->whereDate('invoice_date', '<=', $to));
    }

    public function scopeForFiscalYear($query, ?int $fiscalYearId)
    {
        return $fiscalYearId ? $query->where('fiscal_year_id', $fiscalYearId) : $query;
    }

    public function scopeForParty($query, ?int $partyId)
    {
        return $partyId ? $query->where('party_id', $partyId) : $query;
    }

    public function scopeForProject($query, ?int $projectId)
    {
        return $projectId ? $query->where('project_id', $projectId) : $query;
    }

    public function getSettlementStatusLabelAttribute(): string
    {
        return $this->settled_at ? 'تسویه شده' : ($this->status === 'confirmed' ? 'باز' : ($this->status === 'draft' ? 'موقت' : $this->status));
    }

    public function getIsSettledAttribute(): bool
    {
        return (bool) $this->settled_at;
    }
}
