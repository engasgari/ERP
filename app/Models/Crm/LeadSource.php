<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends CrmModel
{
    protected $table = 'crm_lead_sources';

    protected $fillable = ['code', 'title', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'source_id');
    }
}
