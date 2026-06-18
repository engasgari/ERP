<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    protected $fillable = [
        'number',
        'project_id',
        'item_id',
        'bom_version_id',
        'quantity',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bomVersion(): BelongsTo
    {
        return $this->belongsTo(BomVersion::class);
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(ProductionMaterialConsumption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'تایید شده',
            'in_progress' => 'در حال تولید',
            'testing' => 'کنترل کیفیت',
            'completed' => 'تکمیل شده',
            'closed' => 'بسته شده',
            default => 'پیش‌نویس',
        };
    }
}
