<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends CrmModel
{
    protected $table = 'crm_customer_profiles';

    protected $fillable = [
        'party_id', 'assigned_user_id', 'status', 'industry', 'city', 'website',
        'source_id', 'customer_score', 'customer_segment', 'crm_notes',
        'first_contact_at', 'last_activity_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'first_contact_at' => 'date',
        'last_activity_at' => 'datetime',
        'customer_score' => 'integer',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::CUSTOMER_STATUSES[$this->status] ?? $this->status;
    }
}
