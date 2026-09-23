<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends CrmModel
{
    protected $table = 'crm_pipelines';

    protected $fillable = ['name', 'code', 'is_default', 'is_active', 'sort_order'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_id')->orderBy('sort_order');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'pipeline_id');
    }
}
