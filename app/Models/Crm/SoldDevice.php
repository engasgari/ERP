<?php

namespace App\Models\Crm;

use App\Models\Item;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoldDevice extends CrmModel
{
    use SoftDeletes;

    protected $table = 'crm_sold_devices';

    protected $fillable = [
        'party_id',
        'item_id',
        'serial_number',
        'device_name',
        'sold_at',
        'warranty_years',
        'warranty_ends_at',
        'notes',
        'assigned_user_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sold_at' => 'date',
        'warranty_ends_at' => 'date',
        'warranty_years' => 'integer',
        'is_active' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_ends_at !== null
            && $this->warranty_ends_at->gte(now()->startOfDay());
    }

    public function getWarrantyStatusLabelAttribute(): string
    {
        return $this->isUnderWarranty() ? 'تحت گارانتی' : 'پایان گارانتی';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->item?->name ?? $this->device_name ?? '-';
    }
}
