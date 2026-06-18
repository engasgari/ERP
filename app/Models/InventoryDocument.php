<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryDocument extends Model
{
    protected $fillable = [
        'fiscal_year_id',
        'number',
        'type',
        'document_date',
        'document_time',
        'warehouse_id',
        'target_warehouse_id',
        'project_id',
        'source_type',
        'source_id',
        'entry_mode',
        'status',
        'description',
        'accounting_document_id',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'document_date' => 'date',
        'document_time' => 'datetime:H:i',
        'confirmed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryDocumentLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getIsAutomaticAttribute(): bool
    {
        return $this->entry_mode === 'automatic' || $this->source_type !== null;
    }
}
