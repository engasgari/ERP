<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends CrmModel
{
    protected $table = 'crm_pipeline_stages';

    protected $fillable = [
        'pipeline_id', 'name', 'sort_order', 'probability',
        'is_won', 'is_lost', 'is_active',
    ];

    protected $casts = [
        'probability' => 'decimal:2',
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'stage_id');
    }
}
