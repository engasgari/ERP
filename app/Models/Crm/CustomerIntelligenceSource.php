<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIntelligenceSource extends Model
{
    protected $table = 'crm_customer_intelligence_sources';

    protected $fillable = [
        'customer_intelligence_id', 'url', 'domain', 'title', 'source_type', 'fetch_status',
        'http_status', 'is_verified', 'claims', 'fetched_at', 'last_checked_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'claims' => 'array',
        'http_status' => 'integer',
        'fetched_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function intelligence(): BelongsTo
    {
        return $this->belongsTo(CustomerIntelligence::class, 'customer_intelligence_id');
    }
}
